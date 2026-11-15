<?php

namespace Tests\Feature\Communication;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Models\Conversation;
use App\Models\DeliveryAttempt;
use App\Models\Message;
use App\Services\Inbox\ConversationEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesCommunicationContext;
use Tests\TestCase;

class DeliveryStatusTest extends TestCase
{
    use CreatesCommunicationContext, RefreshDatabase;

    public function test_whatsapp_delivery_callbacks_are_applied_once_and_never_move_backwards(): void
    {
        $this->configureCommunicationProvider(SocialProvider::WhatsApp);
        [, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::WhatsApp);

        [$message, $attempt] = $this->sentWhatsAppMessage($organization, $conversation, $account);

        $this->postStatus($account, 'wamid.out.1', 'delivered')
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $message->refresh();
        $attempt->refresh();

        $this->assertSame(MessageDeliveryStatus::Delivered, $message->delivery_status);
        $this->assertNotNull($message->delivered_at);
        $this->assertNull($message->read_at);
        $this->assertSame(DeliveryAttemptStatus::Delivered, $attempt->status);

        $this->postStatus($account, 'wamid.out.1', 'read')
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $message->refresh();
        $attempt->refresh();

        $this->assertSame(MessageDeliveryStatus::Read, $message->delivery_status);
        $this->assertNotNull($message->read_at);
        $this->assertSame(DeliveryAttemptStatus::Read, $attempt->status);

        // A delayed `sent` callback must not move a read message backwards.
        $this->postStatus($account, 'wamid.out.1', 'sent')
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $this->assertSame(MessageDeliveryStatus::Read, $message->fresh()->delivery_status);

        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $conversation->id,
            'event_type' => ConversationEventService::MESSAGE_DELIVERED,
        ]);
    }

    public function test_whatsapp_failure_callback_marks_the_message_and_attempt_failed(): void
    {
        $this->configureCommunicationProvider(SocialProvider::WhatsApp);
        [, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::WhatsApp);

        [$message, $attempt] = $this->sentWhatsAppMessage($organization, $conversation, $account);

        $this->postStatus($account, 'wamid.out.1', 'failed', [
            'errors' => [[
                'code' => 131047,
                'title' => 'Re-engagement message',
            ]],
        ])->assertOk();

        $message->refresh();
        $attempt->refresh();

        $this->assertSame(MessageDeliveryStatus::Failed, $message->delivery_status);
        $this->assertNotNull($message->failed_at);
        $this->assertStringContainsString('Re-engagement', (string) $message->failure_reason);
        $this->assertSame('131047', $message->metadata['provider_error_code'] ?? null);

        $this->assertSame(DeliveryAttemptStatus::Failed, $attempt->status);
        $this->assertSame('131047', $attempt->error_code);

        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $conversation->id,
            'event_type' => ConversationEventService::MESSAGE_FAILED,
        ]);
    }

    public function test_status_callback_for_an_unknown_provider_message_is_ignored(): void
    {
        $this->configureCommunicationProvider(SocialProvider::WhatsApp);
        [, , $account] = $this->communicationContext(SocialProvider::WhatsApp);

        $this->postStatus($account, 'wamid.unknown', 'delivered')
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $this->assertSame(0, DeliveryAttempt::query()->count());
        $this->assertSame(0, Message::query()->count());
    }

    /**
     * @return array{0: Message, 1: DeliveryAttempt}
     */
    private function sentWhatsAppMessage(
        \App\Models\Organization $organization,
        Conversation $conversation,
        ConnectedAccount $account,
    ): array {
        $message = Message::factory()->outbound()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'provider_message_id' => 'wamid.out.1',
            'delivery_status' => MessageDeliveryStatus::Sent,
            'sent_at' => now(),
        ]);

        $attempt = DeliveryAttempt::query()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'connected_account_id' => $account->id,
            'provider' => SocialProvider::WhatsApp->value,
            'attempt_number' => 1,
            'status' => DeliveryAttemptStatus::Sent,
            'provider_message_id' => 'wamid.out.1',
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        return [$message, $attempt];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function postStatus(ConnectedAccount $account, string $messageId, string $status, array $extra = []): TestResponse
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => $account->provider_account_id],
                        'statuses' => [array_merge([
                            'id' => $messageId,
                            'status' => $status,
                            'timestamp' => (string) now()->getTimestamp(),
                            'recipient_id' => '15551234567',
                        ], $extra)],
                    ],
                ]],
            ]],
        ];

        $raw = json_encode($payload);
        $raw = is_string($raw) ? $raw : '';

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'whatsapp-app-secret'),
        ], $raw);
    }
}
