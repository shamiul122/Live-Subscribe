<?php

namespace Tests\Concerns;

use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;

trait CreatesCommunicationContext
{
    use CreatesOrganizations;

    /**
     * @param  array<string, mixed>  $accountAttributes
     * @return array{0: User, 1: Organization, 2: ConnectedAccount, 3: Contact, 4: Conversation}
     */
    protected function communicationContext(SocialProvider $provider, array $accountAttributes = []): array
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        [$providerAccountId, $accountMetadata, $contactMetadata, $contactPhone, $contactEmail, $providerConversationId] = match ($provider) {
            SocialProvider::Facebook => ['page-1', [], ['facebook_id' => 'psid-1'], null, null, 'psid-1'],
            SocialProvider::Instagram => ['ig-1', [], ['instagram_id' => 'igsid-1'], null, null, 'igsid-1'],
            SocialProvider::WhatsApp => ['phone-1', ['phone_number_id' => 'phone-1'], ['whatsapp_number' => '15551234567'], '15551234567', null, '15551234567'],
            SocialProvider::Telegram => ['bot-1', [], ['telegram_chat_id' => '12345'], null, null, '12345'],
            SocialProvider::Email => ['inbox@example.com', [], ['email' => 'customer@example.com'], null, 'customer@example.com', null],
        };

        $account = ConnectedAccount::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'provider' => $provider,
            'provider_account_id' => $providerAccountId,
            'account_name' => $provider->label().' account',
            'access_token' => 'test-access-token',
            'token_expires_at' => now()->addDay(),
            'metadata' => $accountMetadata,
        ], $accountAttributes));

        $contact = Contact::factory()->create([
            'organization_id' => $organization->id,
            'email' => $contactEmail,
            'phone' => $contactPhone,
            'metadata' => $contactMetadata,
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'connected_account_id' => $account->id,
            'contact_id' => $contact->id,
            'provider' => $provider,
            'provider_conversation_id' => $providerConversationId,
        ]);

        return [$owner, $organization, $account, $contact, $conversation];
    }

    protected function configureCommunicationProvider(SocialProvider $provider): void
    {
        match ($provider) {
            SocialProvider::Facebook => config([
                'social.facebook.client_id' => 'facebook-app-id',
                'social.facebook.client_secret' => 'facebook-app-secret',
                'social.facebook.app_secret' => 'facebook-app-secret',
                'social.facebook.webhook_verify_token' => 'facebook-verify-token',
                'social.facebook.api_base_url' => 'https://graph.facebook.com/v20.0',
            ]),
            SocialProvider::Instagram => config([
                'social.instagram.client_id' => 'instagram-app-id',
                'social.instagram.client_secret' => 'instagram-app-secret',
                'social.instagram.app_secret' => 'instagram-app-secret',
                'social.instagram.webhook_verify_token' => 'instagram-verify-token',
                'social.instagram.api_base_url' => 'https://graph.facebook.com/v20.0',
            ]),
            SocialProvider::WhatsApp => config([
                'social.whatsapp.access_token' => 'whatsapp-access-token',
                'social.whatsapp.phone_number_id' => 'phone-1',
                'social.whatsapp.app_secret' => 'whatsapp-app-secret',
                'social.whatsapp.webhook_verify_token' => 'whatsapp-verify-token',
                'social.whatsapp.api_base_url' => 'https://graph.facebook.com/v20.0',
            ]),
            SocialProvider::Telegram => config([
                'social.telegram.bot_token' => 'telegram-bot-token',
                'social.telegram.webhook_secret_token' => 'telegram-webhook-secret',
                'social.telegram.api_base_url' => 'https://api.telegram.org',
            ]),
            SocialProvider::Email => config([
                'mail.default' => 'array',
                'mail.from.address' => 'inbox@example.com',
            ]),
        };
    }
}
