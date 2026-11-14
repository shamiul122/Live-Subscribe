<?php

namespace App\Contracts\Social;

/**
 * Provider-agnostic connected social account adapter contract.
 *
 * Each implementation owns one social provider (Facebook, Instagram,
 * WhatsApp, Telegram or Email) and declares which operations it supports.
 * Unsupported operations must report false via the capability methods rather
 * than pretending to implement them. External calls must only happen when the
 * provider credentials are configured; otherwise every capability method
 * degrades safely (no fabricated outcomes).
 */
interface SocialAccountProviderInterface
{
    /**
     * Stable machine identifier of the provider (e.g. "facebook").
     */
    public function identifier(): string;

    /**
     * Human-facing provider label.
     */
    public function label(): string;

    /**
     * Whether the provider has the required OAuth credentials configured.
     */
    public function isConfigured(): bool;

    /**
     * Whether the provider can generate an authorization URL.
     */
    public function supportsAuthorizationUrl(): bool;

    /**
     * Whether the provider can exchange an authorization code for tokens.
     */
    public function supportsCallbackExchange(): bool;

    /**
     * Whether the provider can refresh an access token.
     */
    public function supportsTokenRefresh(): bool;

    /**
     * Whether the provider can revoke an authorization.
     */
    public function supportsRevoke(): bool;

    /**
     * Generate the provider authorization URL for the current context.
     *
     * @param  array<string, mixed>  $context
     */
    public function generateAuthorizationUrl(string $state, string $redirectUri, array $context = []): ?string;

    /**
     * Validate an OAuth callback and, when possible, exchange the code and
     * resolve the external account profile.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null A token/profile payload, or null when invalid.
     */
    public function validateCallback(string $state, string $code, string $redirectUri, array $context = []): ?array;

    /**
     * Refresh a provider access token.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null A token payload, or null when unsupported/failed.
     */
    public function refreshToken(string $refreshToken, array $context = []): ?array;

    /**
     * Revoke the provider authorization when supported.
     *
     * @param  array<string, mixed>  $context
     */
    public function revokeAuthorization(?string $refreshToken = null, array $context = []): void;
}
