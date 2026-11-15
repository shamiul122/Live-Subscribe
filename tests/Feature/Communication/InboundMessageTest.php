<?php

namespace Tests\Feature\Communication;

use App\Enums\MessageType;
use App\Enums\SocialProvider;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesCommunicationContext;
use Tests\TestCase;

class InboundMessageTest extends TestCase
{
    use CreatesCommunicationContext, RefreshDatabase;

    public function test_whatsapp_inbound_media_is_recorded_as_metadata_without_fabricating_a_file(): void
    {
        $this->configureCommunicationProvider(SocialProvider::WhatsApp);
        [, $organization, $account, $contact] = $this->communicationContext(SocialProvider::WhatsApp);

        $this->postSignedWhatsApp($account->provider_account_id, [
            'messages' => [[
                'from' => '15551234567',
                'id' => 'wamid.image.1',
                'timestamp' => (string) now()->getTimestamp(),
                'type' => 'image',
                'image' => [
                    'id' => 'media-image-1',
                    'mime_type' => 'image/jpeg',
                ],
            ]],
        ])->assertOk()->assertJson(['status' => 'accepted']);

        $message = Message::query()->where('provider_message_id', 'wamid.image.1')->sole();

        $this->assertSame(MessageType::Image, $message->message_type);
        $this->assertNull($message->body);
        $this->assertSame($contact->id, $message->sender_id);
        $this->assertSame('media-image-1', $message->metadata['inbound_attachments'][0]['provider_media_id'] ?? null);
        $this->assertSame('image/jpeg', $message->metadata['inbound_attachments'][0]['mime_type'] ?? null);

        // Inbound media is a provider reference, not a copied file.
        $this->assertSame(0, Attachment::query()->count());

        $conversation = Conversation::query()->where('organization_id', $organization->id)->sole();
        $this->assertSame(1, $conversation->unread_count);
    }

    public function test_two_whatsapp_events_from_the_same_business_account_are_both_processed(): void
    {
        $this->configureCommunicationProvider(SocialProvider::WhatsApp);
        [, , $account] = $this->communicationContext(SocialProvider::WhatsApp);

        $this->postSignedWhatsApp($account->provider_account_id, [
            'messages' => [[
                'from' => '15551234567',
                'id' => 'wamid.first',
                'timestamp' => (string) now()->getTimestamp(),
                'type' => 'text',
                'text' => ['body' => 'First'],
            ]],
        ])->assertOk();

        $this->postSignedWhatsApp($account->provider_account_id, [
            'messages' => [[
                'from' => '15551234567',
                'id' => 'wamid.second',
                'timestamp' => (string) now()->getTimestamp(),
                'type' => 'text',
                'text' => ['body' => 'Second'],
            ]],
        ])->assertOk();

        $this->assertSame(2, WebhookEvent::query()->count());
        $this->assertSame(2, Message::query()->count());
    }

    public function test_duplicate_telegram_message_with_a_new_update_id_is_not_stored_twice(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Telegram);
        [, $organization, $account] = $this->communicationContext(SocialProvider::Telegram);

        $this->postTelegram($this->telegramPayload(1, 500), $account->id)
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        // Telegram may redeliver the same message under a new update id.
        $this->postTelegram($this->telegramPayload(2, 500), $account->id)
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $this->assertSame(2, WebhookEvent::query()->count());
        $this->assertSame(1, Message::query()->where('provider_message_id', '500')->count());

        $conversation = Conversation::query()->where('organization_id', $organization->id)->sole();
        $this->assertSame(1, $conversation->unread_count);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function postSignedWhatsApp(string $phoneNumberId, array $value): TestResponse
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => array_merge([
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => $phoneNumberId],
                    ], $value),
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

    /**
     * @return array<string, mixed>
     */
    private function telegramPayload(int $updateId, int $messageId): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $messageId,
                'from' => ['id' => 12345, 'first_name' => 'Tara'],
                'chat' => ['id' => 12345, 'type' => 'private'],
                'date' => now()->getTimestamp(),
                'text' => 'Telegram hello',
            ],
        ];
    }

    private function postTelegram(array $payload, int $accountId): TestResponse
    {
        return $this->postJson('/webhooks/telegram?account='.$accountId, $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-webhook-secret',
        ]);
    }
}
