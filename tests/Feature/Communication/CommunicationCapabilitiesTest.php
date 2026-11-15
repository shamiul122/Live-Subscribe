<?php

namespace Tests\Feature\Communication;

use App\Enums\CommunicationCapability;
use App\Enums\CommunicationErrorCode;
use App\Enums\SocialProvider;
use App\Services\Communication\CommunicationProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCommunicationContext;
use Tests\TestCase;

class CommunicationCapabilitiesTest extends TestCase
{
    use CreatesCommunicationContext, RefreshDatabase;

    public function test_adapters_declare_their_real_capabilities(): void
    {
        $registry = app(CommunicationProviderRegistry::class);

        $expected = [
            'facebook' => [
                'outbound_text' => true,
                'attachments' => false,
                'delivery_status' => true,
                'inbound_webhooks' => true,
            ],
            'instagram' => [
                'outbound_text' => true,
                'attachments' => false,
                'delivery_status' => true,
                'inbound_webhooks' => true,
            ],
            'whatsapp' => [
                'outbound_text' => true,
                'attachments' => true,
                'delivery_status' => true,
                'inbound_webhooks' => true,
            ],
            'telegram' => [
                'outbound_text' => true,
                'attachments' => true,
                'delivery_status' => false,
                'inbound_webhooks' => true,
            ],
            'email' => [
                'outbound_text' => true,
                'attachments' => true,
                'delivery_status' => false,
                'inbound_webhooks' => false,
            ],
        ];

        foreach ($expected as $identifier => $capabilities) {
            $provider = $registry->provider($identifier);

            $this->assertNotNull($provider, "No adapter registered for {$identifier}.");
            $this->assertSame($capabilities, $provider->capabilities(), $identifier);
        }
    }

    public function test_unsupported_capabilities_explain_themselves(): void
    {
        $registry = app(CommunicationProviderRegistry::class);

        $this->assertNotNull($registry->provider('telegram')?->capabilityReason(CommunicationCapability::DeliveryStatus));
        $this->assertNotNull($registry->provider('email')?->capabilityReason(CommunicationCapability::InboundWebhooks));
        $this->assertStringContainsString(
            'publicly reachable',
            (string) $registry->provider('facebook')?->capabilityReason(CommunicationCapability::Attachments),
        );
    }

    public function test_email_is_reported_as_unconfigured_when_the_log_mailer_is_in_use(): void
    {
        config([
            'mail.default' => 'log',
            'mail.from.address' => 'inbox@example.com',
        ]);

        [, , $account] = $this->communicationContext(SocialProvider::Email);

        $status = app(CommunicationProviderRegistry::class)
            ->provider('email')
            ?->accountStatus($account);

        $this->assertNotNull($status);
        $this->assertFalse($status->configured);
        $this->assertFalse($status->usable);
        $this->assertSame(CommunicationErrorCode::ProviderNotConfigured, $status->errorCode);
        $this->assertStringContainsString('mail transport', (string) $status->errorMessage);
    }

    public function test_telegram_account_without_a_bot_token_is_not_usable(): void
    {
        config(['social.telegram.bot_token' => null]);

        [, , $account] = $this->communicationContext(SocialProvider::Telegram, ['access_token' => null]);

        $status = app(CommunicationProviderRegistry::class)
            ->provider('telegram')
            ?->accountStatus($account);

        $this->assertNotNull($status);
        $this->assertFalse($status->usable);
        $this->assertSame(CommunicationErrorCode::InvalidAccountCredentials, $status->errorCode);
    }

    public function test_expired_facebook_token_is_not_usable(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);

        [, , $account] = $this->communicationContext(SocialProvider::Facebook, [
            'token_expires_at' => now()->subMinute(),
        ]);

        $status = app(CommunicationProviderRegistry::class)
            ->provider('facebook')
            ?->accountStatus($account);

        $this->assertNotNull($status);
        $this->assertFalse($status->usable);
        $this->assertStringContainsString('expired', (string) $status->errorMessage);
    }
}
