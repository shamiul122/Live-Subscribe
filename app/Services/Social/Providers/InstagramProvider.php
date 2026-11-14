<?php

namespace App\Services\Social\Providers;

use App\Contracts\Social\SocialAccountProviderInterface;

/**
 * Instagram provider adapter for the connected accounts foundation.
 *
 * Instagram authorization is performed through the Facebook Graph OAuth
 * flow. Capabilities mirror the Facebook Graph path; without credentials the
 * adapter degrades safely without fabricating any successful connection.
 */
final class InstagramProvider implements SocialAccountProviderInterface
{
    public function identifier(): string
    {
        return 'instagram';
    }

    public function label(): string
    {
        return 'Instagram';
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.social.facebook.client_id') && (bool) config('services.social.facebook.client_secret');
    }

    public function supportsAuthorizationUrl(): bool
    {
        return true;
    }

    public function supportsCallbackExchange(): bool
    {
        return true;
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
        if (! $this->isConfigured() || ! $this->supportsAuthorizationUrl()) {
            return null;
        }

        return rtrim((string) config('services.social.facebook.authorize_url', 'https://www.facebook.com/v20.0/dialog/oauth'), '/').'?client_id='.rawurlencode((string) config('services.social.facebook.client_id')).'&redirect_uri='.rawurlencode($redirectUri).'&state='.rawurlencode($state).'&scope='.rawurlencode('instagram_basic,instagram_manage_comments,email');
    }

    public function validateCallback(string $state, string $code, string $redirectUri, array $context = []): ?array
    {
        if (! $this->isConfigured() || ! $this->supportsCallbackExchange()) {
            return null;
        }

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
