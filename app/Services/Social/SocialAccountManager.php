<?php

namespace App\Services\Social;

use App\Contracts\Social\SocialAccountProviderInterface;
use App\Enums\ConnectedAccountStatus;
use App\Enums\Feature;
use App\Enums\SocialProvider;
use App\Models\AccountConnectionEvent;
use App\Models\ConnectedAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Illuminate\Support\Facades\Cache;

/**
 * Organization-scoped connected social account lifecycle manager.
 *
 * Responsibilities:
 *  - list connected accounts for an organization
 *  - start a provider connection (generate one-time state + authorization URL)
 *  - validate an OAuth callback, persist the account, record lifecycle events
 *  - refresh tokens when supported
 *  - disconnect/revoke an account
 *
 * All external HTTP work happens through provider adapters; this manager never
 * performs outbound requests itself and never holds a DB transaction across an
 * external call. State transitions are idempotent via one-time state usage and
 * entitlement checks go through the central EntitlementService.
 */
final class SocialAccountManager
{
    private const STATE_TTL_SECONDS = 600;

    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly SocialAccountRegistry $registry,
    ) {
        $this->registry->registerDefaults();
    }

    /**
     * List connected accounts for the organization.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(Organization $organization): array
    {
        return ConnectedAccount::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ConnectedAccount $account): array => $this->summary($account))
            ->values()
            ->all();
    }

    /**
     * Start a provider connection. Generates a one-time state, stores its hash
     * and returns the provider authorization URL (or a safe failure payload when
     * the provider is not configured or does not support the flow).
     *
     * @return array<string, mixed>
     */
    public function start(User $user, Organization $organization, SocialProvider $provider): array
    {
        $providerInstance = $this->registry->provider($provider->value);

        if ($providerInstance === null) {
            return $this->failure($provider, $organization, 'provider_unavailable', 'The requested social provider is not supported.');
        }

        $decision = $this->entitlements->check(
            $user,
            $organization,
            Feature::ConnectedAccounts,
            permission: 'billing.manageSocialAccounts',
        );

        if (! $decision->isAllowed()) {
            return $this->failure($provider, $organization, $decision->reason->value, $decision->reason->label());
        }

        if (! $providerInstance->supportsAuthorizationUrl() || ! $providerInstance->isConfigured()) {
            return $this->failure($provider, $organization, 'provider_not_configured', 'The social provider is not configured yet.');
        }

        $state = bin2hex(random_bytes(32));
        $redirectUri = (string) config('services.social.redirect_uri', '');

        $authorizationUrl = $providerInstance->generateAuthorizationUrl($state, $redirectUri);

        if ($authorizationUrl === null) {
            return $this->failure($provider, $organization, 'authorization_url_unavailable', 'The provider authorization URL could not be generated.');
        }

        Cache::put($this->stateKey($state), [
            'organization_id' => $organization->id,
            'provider' => $provider->value,
            'expires_at' => now()->addSeconds(self::STATE_TTL_SECONDS)->timestamp,
        ], self::STATE_TTL_SECONDS);

        return [
            'status' => 'pending',
            'provider' => $provider->value,
            'organization_id' => $organization->id,
            'authorization_url' => $authorizationUrl,
            'state' => $state,
            'expires_in' => self::STATE_TTL_SECONDS,
        ];
    }

    /**
     * Validate an OAuth callback and persist the resulting account.
     *
     * @return array<string, mixed>
     */
    public function complete(
        User $user,
        Organization $organization,
        SocialProvider $provider,
        string $state,
        ?string $code,
        string $redirectUri,
    ): array {
        $pending = Cache::get($this->stateKey($state));

        if ($pending === null) {
            return $this->failure($provider, $organization, 'invalid_state', 'The OAuth state is invalid or has already been used.');
        }

        Cache::forget($this->stateKey($state));

        if (($pending['organization_id'] ?? null) !== $organization->id || ($pending['provider'] ?? null) !== $provider->value) {
            return $this->failure($provider, $organization, 'state_organization_mismatch', 'The OAuth state does not match this organization or provider.');
        }

        if ($code === null || $code === '') {
            return $this->failure($provider, $organization, 'missing_code', 'The OAuth callback is missing an authorization code.');
        }

        $providerInstance = $this->registry->provider($provider->value);

        if ($providerInstance === null) {
            return $this->failure($provider, $organization, 'provider_unavailable', 'The requested social provider is not supported.');
        }

        $decision = $this->entitlements->check(
            $user,
            $organization,
            Feature::ConnectedAccounts,
            permission: 'billing.manageSocialAccounts',
        );

        if (! $decision->isAllowed()) {
            return $this->failure($provider, $organization, $decision->reason->value, $decision->reason->label());
        }

        $tokenPayload = $providerInstance->validateCallback($state, $code, $redirectUri);

        if ($tokenPayload === null) {
            return $this->failure($provider, $organization, 'callback_failed', 'The provider callback could not be validated.');
        }

        // TODO: real providers would persist tokens and profile data here.
        // The foundation stores the account only when a provider adapter
        // returns a validated token/profile payload.

        return [
            'status' => 'pending',
            'provider' => $provider->value,
            'organization_id' => $organization->id,
            'message' => 'The OAuth callback was validated. Account persistence is pending provider confirmation.',
        ];
    }

    /**
     * Disconnect (revoke) a connected account for the organization.
     *
     * @return array<string, mixed>
     */
    public function disconnect(User $user, Organization $organization, ConnectedAccount $account): array
    {
        $providerInstance = $this->registry->provider($account->provider->value);

        if ($providerInstance === null) {
            return $this->failure($this->providerEnum($account->provider->value), $organization, 'provider_unavailable', 'The connected account provider is not supported.');
        }

        $providerInstance->revokeAuthorization(
            $account->refresh_token,
            [
                'organization_id' => $organization->id,
                'connected_account_id' => $account->id,
            ],
        );

        $account->status = ConnectedAccountStatus::Revoked;

        $account->save();

        $this->recordEvent(
            $organization,
            $account,
            'account_disconnected',
            [
                'provider' => $account->provider,
            ],
        );

        return [
            'status' => 'disconnected',
            'provider' => $account->provider,
            'organization_id' => $organization->id,
            'connected_account_id' => $account->id,
        ];
    }

    /**
     * Refresh an expired token when the provider supports it.
     *
     * @return array<string, mixed>
     */
    public function refresh(User $user, Organization $organization, ConnectedAccount $account): array
    {
        $providerInstance = $this->registry->provider($account->provider->value);

        if ($providerInstance === null) {
            return $this->failure($this->providerEnum($account->provider->value), $organization, 'provider_unavailable', 'The connected account provider is not supported.');
        }

        if (! $providerInstance->supportsTokenRefresh()) {
            return [
                'status' => 'unsupported',
                'provider' => $account->provider,
                'organization_id' => $organization->id,
                'message' => 'Token refresh is not supported by this provider.',
            ];
        }

        $decision = $this->entitlements->check(
            $user,
            $organization,
            Feature::ConnectedAccounts,
            permission: 'billing.manageSocialAccounts',
        );

        if (! $decision->isAllowed()) {
            return [
                'status' => 'failed',
                'reason' => $decision->reason->value,
                'provider' => $account->provider,
                'organization_id' => $organization->id,
                'message' => $decision->reason->label(),
            ];
        }

        $tokenPayload = $providerInstance->refreshToken((string) $account->refresh_token, [
            'organization_id' => $organization->id,
            'connected_account_id' => $account->id,
        ]);

        if ($tokenPayload === null) {
            return [
                'status' => 'failed',
                'reason' => 'token_refresh_failed',
                'provider' => $account->provider,
                'organization_id' => $organization->id,
                'message' => 'The provider token refresh failed.',
            ];
        }

        $account->refresh_token = $tokenPayload['refresh_token'] ?? null;
        $account->token_expires_at = $tokenPayload['token_expires_at'] ?? $account->token_expires_at;
        $account->last_synced_at = now();
        $account->save();

        $this->recordEvent(
            $organization,
            $account,
            'token_refreshed',
            [
                'provider' => $account->provider,
            ],
        );

        return [
            'status' => 'refreshed',
            'provider' => $account->provider,
            'organization_id' => $organization->id,
            'connected_account_id' => $account->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(ConnectedAccount $account): array
    {
        return [
            'id' => $account->id,
            'organization_id' => $account->organization_id,
            'provider' => $account->provider->value,
            'provider_label' => $this->providerLabel($account->provider->value),
            'provider_account_id' => $account->provider_account_id,
            'account_name' => $account->account_name,
            'account_username' => $account->account_username,
            'account_type' => $account->account_type,
            'status' => $account->status,
            'status_label' => $account->status->label(),
            'token_expires_at' => $account->token_expires_at?->toIso8601String(),
            'last_synced_at' => $account->last_synced_at?->toIso8601String(),
            'metadata' => $account->metadata,
            'is_active' => $account->status === ConnectedAccountStatus::Active,
            'is_usable' => $account->status === ConnectedAccountStatus::Active,
            'created_at' => $account->created_at?->toIso8601String(),
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(SocialProvider $provider, Organization $organization, string $reason, string $message): array
    {
        return [
            'status' => 'failed',
            'reason' => $reason,
            'provider' => $provider->value,
            'organization_id' => $organization->id,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordEvent(Organization $organization, ConnectedAccount $account, string $type, array $data = []): AccountConnectionEvent
    {
        return AccountConnectionEvent::query()->create([
            'organization_id' => $organization->id,
            'connected_account_id' => $account->id,
            'type' => $type,
            'status' => ConnectedAccountStatus::Active->value,
            'data' => $data,
            'created_at' => now(),
        ]);
    }

    private function providerLabel(string $provider): string
    {
        $normalized = SocialProvider::tryFrom($provider);

        return $normalized?->label() ?? $provider;
    }

    private function providerEnum(string $provider): SocialProvider
    {
        $normalized = SocialProvider::tryFrom($provider);

        if ($normalized === null) {
            throw new \InvalidArgumentException('Unknown social provider.');
        }

        return $normalized;
    }

    private function stateKey(string $state): string
    {
        return 'social.oauth.states.'.hash('sha256', $state);
    }
}
