<?php

namespace Tests\Feature\Communication;

use App\Enums\MessageDirection;
use App\Enums\SocialProvider;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesCommunicationContext;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use CreatesCommunicationContext, RefreshDatabase;

    public function test_facebook_webhook_with_a_valid_signature_creates_an_inbound_message(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [, $organization, $account, $contact] = $this->communicationContext(SocialProvider::Facebook);

        $payload = [
            'object' => 'page',
            'entry' => [[
                'id' => $account->provider_account_id,
                'time' => now()->getTimestampMs(),
                'messaging' => [[
                    'sender' => ['id' => 'psid-1'],
                    'recipient' => ['id' => $account->provider_account_id],
                    'timestamp' => now()->getTimestampMs(),
                    'message' => ['mid' => 'mid.fb.in.1', 'text' => 'Hi, I need help'],
                ]],
            ]],
        ];

        $response = $this->postSignedMeta('facebook', $payload, 'facebook-app-secret');

        $response->assertOk()->assertJson(['status' => 'accepted']);

        $event = WebhookEvent::query()->sole();
        $this->assertSame('processed', $event->processing_status);
        $this->assertSame($account->id, $event->connected_account_id);
        $this->assertNotNull($event->payload);
        $this->assertArrayNotHasKey('access_token', $event->payload);

        $message = Message::query()->where('provider_message_id', 'mid.fb.in.1')->sole();
        $this->assertSame(MessageDirection::Inbound, $message->direction);
        $this->assertSame('Hi, I need help', $message->body);
        $this->assertSame($contact->id, $message->sender_id);

        $conversation = Conversation::query()->where('organization_id', $organization->id)->sole();
        $this->assertSame($account->id, $conversation->connected_account_id);
    }

    public function test_facebook_webhook_without_a_signature_is_rejected(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [, , $account] = $this->communicationContext(SocialProvider::Facebook);

        $response = $this->call('POST', '/webhooks/facebook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'object' => 'page',
            'entry' => [['id' => $account->provider_account_id, 'messaging' => []]],
        ], JSON_THROW_ON_ERROR));

        $response->assertForbidden();
        $this->assertSame(0, WebhookEvent::query()->count());
        $this->assertSame(0, Message::query()->count());
    }

    public function test_facebook_webhook_with_a_wrong_signature_is_rejected(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [, , $account] = $this->communicationContext(SocialProvider::Facebook);

        $raw = json_encode([
            'object' => 'page',
            'entry' => [['id' => $account->provider_account_id, 'messaging' => []]],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call('POST', '/webhooks/facebook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', 'different-body', 'facebook-app-secret'),
        ], $raw);

        $response->assertForbidden();
        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_duplicate_facebook_webhook_payload_is_acknowledged_but_not_processed_twice(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [, , $account] = $this->communicationContext(SocialProvider::Facebook);

        $payload = [
            'object' => 'page',
            'entry' => [[
                'id' => $account->provider_account_id,
                'messaging' => [[
                    'sender' => ['id' => 'psid-1'],
                    'timestamp' => now()->getTimestampMs(),
                    'message' => ['mid' => 'mid.fb.dup', 'text' => 'Only once'],
                ]],
            ]],
        ];

        $this->postSignedMeta('facebook', $payload, 'facebook-app-secret')
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $this->postSignedMeta('facebook', $payload, 'facebook-app-secret')
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(1, Message::query()->where('provider_message_id', 'mid.fb.dup')->count());
    }

    public function test_meta_challenge_requires_the_configured_verify_token(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);

        $this->get('/webhooks/facebook?hub.mode=subscribe&hub.verify_token=facebook-verify-token&hub.challenge=challenge-123')
            ->assertOk()
            ->assertSee('challenge-123');

        $this->get('/webhooks/facebook?hub.mode=subscribe&hub.verify_token=wrong-token&hub.challenge=challenge-123')
            ->assertForbidden();
    }

    public function test_facebook_payload_for_an_unknown_page_is_ignored(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);

        $payload = [
            'object' => 'page',
            'entry' => [[
                'id' => 'unknown-page',
                'messaging' => [[
                    'sender' => ['id' => 'psid-1'],
                    'timestamp' => now()->getTimestampMs(),
                    'message' => ['mid' => 'mid.fb.unknown', 'text' => 'No account'],
                ]],
            ]],
        ];

        $this->postSignedMeta('facebook', $payload, 'facebook-app-secret')
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        $event = WebhookEvent::query()->sole();
        $this->assertSame('ignored', $event->processing_status);
        $this->assertSame('connected_account_not_found', $event->failure_reason);
        $this->assertSame(0, Message::query()->count());
    }

    public function test_instagram_changes_payload_is_normalized(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Instagram);
        [, , $account] = $this->communicationContext(SocialProvider::Instagram);

        $payload = [
            'object' => 'instagram',
            'entry' => [[
                'id' => $account->provider_account_id,
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'sender' => ['id' => 'igsid-1'],
                        'timestamp' => now()->getTimestampMs(),
                        'message' => ['mid' => 'mid.ig.in.1', 'text' => 'Instagram hello'],
                    ],
                ]],
            ]],
        ];

        $this->postSignedMeta('instagram', $payload, 'instagram-app-secret')
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $message = Message::query()->where('provider_message_id', 'mid.ig.in.1')->sole();
        $this->assertSame('Instagram hello', $message->body);
    }

    public function test_whatsapp_inbound_message_is_normalized(): void
    {
        $this->configureCommunicationProvider(SocialProvider::WhatsApp);
        [, , $account] = $this->communicationContext(SocialProvider::WhatsApp);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => $account->provider_account_id],
                        'contacts' => [['profile' => ['name' => 'Ada']]],
                        'messages' => [[
                            'from' => '15551234567',
                            'id' => 'wamid.in.1',
                            'timestamp' => (string) now()->getTimestamp(),
                            'type' => 'text',
                            'text' => ['body' => 'WhatsApp hello'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postSignedMeta('whatsapp', $payload, 'whatsapp-app-secret')
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $message = Message::query()->where('provider_message_id', 'wamid.in.1')->sole();
        $this->assertSame('WhatsApp hello', $message->body);
    }

    public function test_telegram_requires_the_account_query_parameter_and_secret_header(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Telegram);
        [, , $account] = $this->communicationContext(SocialProvider::Telegram);

        $payload = $this->telegramPayload('1001');

        $this->postTelegram($payload, $account->id, 'telegram-webhook-secret')
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $message = Message::query()->where('provider_message_id', '1001')->sole();
        $this->assertSame('Telegram hello', $message->body);

        $this->postTelegram($this->telegramPayload('1002'), $account->id, 'wrong-secret')
            ->assertForbidden();

        $this->postTelegram($this->telegramPayload('1003'), null, 'telegram-webhook-secret')
            ->assertForbidden()
            ->assertJson(['error' => 'account_required']);
    }

    public function test_email_webhooks_are_not_supported(): void
    {
        $this->postJson('/webhooks/email', ['subject' => 'hello'])
            ->assertForbidden()
            ->assertJson(['error' => 'capability_not_supported']);
    }

    public function test_unknown_provider_returns_not_found(): void
    {
        $this->postJson('/webhooks/unknown', [])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function postSignedMeta(string $provider, array $payload, string $secret): TestResponse
    {
        $raw = json_encode($payload);
        $raw = is_string($raw) ? $raw : '';

        return $this->call('POST', '/webhooks/'.$provider, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, $secret),
        ], $raw);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function postTelegram(array $payload, ?int $accountId, string $secret): TestResponse
    {
        $url = '/webhooks/telegram'.($accountId !== null ? '?account='.$accountId : '');

        return $this->postJson($url, $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $secret,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function telegramPayload(string $messageId): array
    {
        return [
            'update_id' => (int) $messageId,
            'message' => [
                'message_id' => (int) $messageId,
                'from' => ['id' => 12345, 'first_name' => 'Tara'],
                'chat' => ['id' => 12345, 'type' => 'private'],
                'date' => now()->getTimestamp(),
                'text' => 'Telegram hello',
            ],
        ];
    }
}
