<?php

namespace Tests\Feature\Communication;

use App\Enums\CommunicationErrorCode;
use App\Enums\DeliveryAttemptStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\SocialProvider;
use App\Models\DeliveryAttempt;
use App\Models\Message;
use App\Services\Communication\DTOs\OutboundMessageRequest;
use App\Services\Communication\OutboundMessageService;
use App\Services\Inbox\ConversationEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesCommunicationContext;
use Tests\TestCase;

class OutboundMessageTest extends TestCase
{
    use CreatesCommunicationContext, RefreshDatabase;

    public function test_outbound_message_is_persisted_queued_and_sent_by_the_job(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.out.1'], 200),
        ]);

        $result = app(OutboundMessageService::class)->queue(new OutboundMessageRequest(
            organization: $organization,
            conversation: $conversation,
            connectedAccount: $account,
            actor: $owner,
            body: 'Hello from the inbox',
        ));

        $message = $result['message']->fresh();

        $this->assertTrue($result['job_dispatched']);
        $this->assertSame(MessageDeliveryStatus::Sent, $message->delivery_status);
        $this->assertSame('mid.out.1', $message->provider_message_id);
        $this->assertNotNull($message->sent_at);
        $this->assertNull($message->delivered_at, 'Provider acceptance must not be reported as delivered.');

        $attempt = DeliveryAttempt::query()->where('message_id', $message->id)->sole();
        $this->assertSame(DeliveryAttemptStatus::Sent, $attempt->status);
        $this->assertSame('mid.out.1', $attempt->provider_message_id);

        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $conversation->id,
            'event_type' => ConversationEventService::MESSAGE_QUEUED,
        ]);
        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $conversation->id,
            'event_type' => ConversationEventService::MESSAGE_SENT,
        ]);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v20.0/page-1/messages'
            && $request['recipient']['id'] === 'psid-1'
            && $request['message']['text'] === 'Hello from the inbox');
    }

    public function test_replaying_the_same_idempotency_key_does_not_send_twice(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.once'], 200),
        ]);

        $service = app(OutboundMessageService::class);
        $request = new OutboundMessageRequest(
            organization: $organization,
            conversation: $conversation,
            connectedAccount: $account,
            actor: $owner,
            body: 'Only once',
            idempotencyKey: 'composer-key-123',
        );

        $first = $service->queue($request);
        $second = $service->queue($request);

        $this->assertTrue($first['job_dispatched']);
        $this->assertFalse($second['job_dispatched']);
        $this->assertSame($first['message']->id, $second['message']->id);
        $this->assertSame(1, Message::query()->where('conversation_id', $conversation->id)->count());
        Http::assertSentCount(1);
    }

    public function test_unconfigured_provider_rejects_before_persisting_or_calling_out(): void
    {
        config([
            'social.facebook.client_id' => null,
            'social.facebook.client_secret' => null,
            'social.facebook.app_secret' => null,
        ]);

        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        Http::fake();

        try {
            app(OutboundMessageService::class)->queue(new OutboundMessageRequest(
                organization: $organization,
                conversation: $conversation,
                connectedAccount: $account,
                actor: $owner,
                body: 'Should never be stored',
            ));

            $this->fail('An unconfigured provider must reject the message.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('message', $exception->errors());
        }

        $this->assertSame(0, Message::query()->count());
        Http::assertNothingSent();
    }

    public function test_provider_rejection_marks_the_message_failed_and_explicit_retry_succeeds(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['error' => ['message' => 'Invalid OAuth access token.', 'type' => 'OAuthException', 'code' => 190]], 400)
            ->push(['message_id' => 'mid.retry.1'], 200);

        $service = app(OutboundMessageService::class);

        $result = $service->queue(new OutboundMessageRequest(
            organization: $organization,
            conversation: $conversation,
            connectedAccount: $account,
            actor: $owner,
            body: 'Please retry me',
        ));

        $message = $result['message']->fresh();

        $this->assertSame(MessageDeliveryStatus::Failed, $message->delivery_status);
        $this->assertNotNull($message->failed_at);
        $this->assertStringContainsString('Invalid OAuth access token', (string) $message->failure_reason);
        $this->assertSame(CommunicationErrorCode::InvalidAccountCredentials->value, $message->metadata['provider_error_code'] ?? null);

        $failedAttempt = DeliveryAttempt::query()->where('message_id', $message->id)->sole();
        $this->assertSame(DeliveryAttemptStatus::Failed, $failedAttempt->status);

        $retry = $service->retry($owner, $organization, $message);

        $this->assertTrue($retry['job_dispatched']);

        $message->refresh();

        $this->assertSame(MessageDeliveryStatus::Sent, $message->delivery_status);
        $this->assertNull($message->failure_reason);
        $this->assertSame('mid.retry.1', $message->provider_message_id);

        $attempts = DeliveryAttempt::query()
            ->where('message_id', $message->id)
            ->orderBy('attempt_number')
            ->get();

        $this->assertCount(2, $attempts);
        $this->assertSame(DeliveryAttemptStatus::Failed, $attempts[0]->status);
        $this->assertSame(DeliveryAttemptStatus::Sent, $attempts[1]->status);
        Http::assertSentCount(2);
    }

    public function test_network_failure_is_reported_honestly_and_not_auto_retried(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        $calls = 0;

        Http::fake([
            'graph.facebook.com/*' => function () use (&$calls): never {
                $calls++;

                throw new ConnectionException('connection refused');
            },
        ]);

        $result = app(OutboundMessageService::class)->queue(new OutboundMessageRequest(
            organization: $organization,
            conversation: $conversation,
            connectedAccount: $account,
            actor: $owner,
            body: 'Network uncertain',
        ));

        $message = $result['message']->fresh();

        $this->assertSame(MessageDeliveryStatus::Failed, $message->delivery_status);
        $this->assertStringContainsString('retry manually', (string) $message->failure_reason);
        $this->assertSame(1, DeliveryAttempt::query()->where('message_id', $message->id)->count());
        $this->assertSame(1, $calls, 'A single attempt must be made and must not be automatically retried.');
    }
}
