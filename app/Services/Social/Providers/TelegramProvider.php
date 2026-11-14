<?php

namespace App\Services\Social\Providers;

use App\Contracts\Social\SocialAccountProviderInterface;

/**
 * Telegram provider adapter for the connected accounts foundation.
 *
 * Telegram bot token based connections do not use the OAuth authorization code
 * flow. Capability declarations follow the provider-neutral contract; no
 * external request is made without configuration and successful outcomes are
 * never fabricated.
 */
final class TelegramProvider implements SocialAccountProviderInterface
{
    public function identifier(): string
    {
        return 'telegram';
    }

    public function label(): string
    {
        return 'Telegram';
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.social.telegram.bot_token');
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
        return false;
    }

    public function supportsRevoke(): bool
    {
        return false;
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
        return null;
    }

    public function revokeAuthorization(?string $refreshToken = null, array $context = []): void
    {
    }
}
