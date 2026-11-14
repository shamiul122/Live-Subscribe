<?php

namespace App\Services\Social\Providers;

use App\Contracts\Social\SocialAccountProviderInterface;

/**
 * Email provider adapter for the connected accounts foundation.
 *
 * Email connections use address-based verification rather than OAuth. The
 * adapter supports capability declarations for future email delivery providers
 * while never performing external requests or fabricating connections without
 * configuration.
 */
final class EmailProvider implements SocialAccountProviderInterface
{
    public function identifier(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return 'Email';
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.social.email.messaging_service_sid') || (bool) config('services.social.email.api_key');
    }

    public function supportsAuthorizationUrl(): bool
    {
        return false;
    }

    public function supportsCallbackExchange(): bool
    {
        return false;
    }

    public function supportsTokenRefresh(): bool
    {
        return true;
    }

    public function supportsRevoke(): bool
    {
        return true;
    }

    public function generateAuthorizationUrl(string $state, string $redirectUri, array $context = []): ?string
    {
        return null;
    }

    public function validateCallback(string $state, string $code, string $redirectUri, array $context = []): ?array
    {
        return null;
    }

    public function refreshToken(string $refreshToken, array $context = []): ?array
    {
        if (! $this->isConfigured() || ! $this->supportsTokenRefresh()) {
            return null;
        }

        return null;
    }

    public function revokeAuthorization(?string $refreshToken = null, array $context = []): void
    {
        if (! $this->isConfigured() || ! $this->supportsRevoke()) {
            return;
        }
    }
}
