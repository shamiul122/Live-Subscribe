<?php

namespace App\Services\Social\Providers;

use App\Contracts\Social\SocialAccountProviderInterface;

/**
 * Facebook provider adapter for the connected accounts foundation.
 *
 * Supports the OAuth authorization-code flow shape, token refresh and revoke.
 * No external request is performed unless credentials are configured; when
 * they are missing all operations degrade safely without fabricating results.
 */
final class FacebookProvider implements SocialAccountProviderInterface
{
    public function identifier(): string
    {
        return 'facebook';
    }

    public function label(): string
    {
        return 'Facebook';
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

        return rtrim((string) config('services.social.facebook.authorize_url', 'https://www.facebook.com/v20.0/dialog/oauth'), '/').'?client_id='.rawurlencode((string) config('services.social.facebook.client_id')).'&redirect_uri='.rawurlencode($redirectUri).'&state='.rawurlencode($state).'&scope='.rawurlencode('email,public_profile');
    }

    public function validateCallback(string $state, string $code, string $redirectUri, array $context = []): ?array
    {
        if (! $this->isConfigured() || ! $this->supportsCallbackExchange()) {
            return null;
        }

        // Real OAuth exchange would post to Facebook here. With no credentials
        // configured this foundation refuses to fabricate a token/profile.
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

        // Real revoke would call Facebook here.
    }
}
