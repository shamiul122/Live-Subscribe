<?php

namespace App\Services\Billing;

use App\Enums\EntitlementReason;
use App\Enums\Feature;
use App\Enums\FeatureType;
use App\Enums\SubscriptionStatus;
use App\Models\ConnectedAccount;
use App\Models\License;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\UsageEvent;
use App\Models\UsageRecord;
use App\Models\User;
use App\Support\Billing\EntitlementDecision;
use App\Support\Billing\OrganizationEntitlements;
use App\Support\Billing\UsageResult;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The single place that answers "may this organization use this feature?".
 *
 * Business features must call this service instead of inspecting
 * subscriptions, plans or usage tables directly. It composes:
 * organization existence, membership, an optional permission, the
 * subscription status/date window, the plan entitlement snapshot and current
 * usage or resource count.
 */
final class EntitlementService
{
    private const CONTEXT_TTL_SECONDS = 60;

    /**
     * Resolve the entitlement context for an organization. This is used by
     * overview screens and may be cached. It deliberately does not include
     * metered usage counters.
     */
    public function for(User $user, Organization $organization): OrganizationEntitlements
    {
        if (! $this->isMember($user, $organization)) {
            return new OrganizationEntitlements(organizationId: $organization->id);
        }

        return $this->context($organization);
    }

    /**
     * Evaluate access without mutating usage.
     */
    public function check(
        User $user,
        Organization $organization,
        Feature $feature,
        ?string $permission = null,
        int $units = 1,
    ): EntitlementDecision {
        $context = $this->context($organization);

        return $this->evaluate($user, $organization, $context, $feature, $permission, $units);
    }

    /**
     * Evaluate access and, for metered features, atomically consume units.
     *
     * When an idempotency key is supplied, a repeated call with the same key
     * never consumes usage twice.
     */
    public function consume(
        User $user,
        Organization $organization,
        Feature $feature,
        int $units = 1,
        ?string $idempotencyKey = null,
    ): UsageResult {
        if ($units < 1) {
            throw new \InvalidArgumentException('Usage units must be at least 1.');
        }

        $decision = $this->check($user, $organization, $feature, null, $units);

        if (! $decision->isAllowed()) {
            return UsageResult::denied(
                $decision->reason,
                $feature,
                $decision->used,
                $decision->limit,
            );
        }

        $subscriptionId = $decision->subscriptionId;

        if ($subscriptionId === null) {
            return UsageResult::denied(EntitlementReason::NoSubscription, $feature);
        }

        // Boolean features have no counter; the check above is the whole
        // answer.
        if ($feature->type() === FeatureType::Boolean) {
            return UsageResult::allowed($feature, 0, null);
        }

        // Resource capacity is measured by counting resources, not by
        // incrementing a period counter.
        if ($feature->type() === FeatureType::Resource) {
            return UsageResult::allowed(
                $feature,
                $this->resourceUsage($organization, $feature),
                $decision->limit,
            );
        }

        if ($idempotencyKey !== null && $this->hasUsageEvent($subscriptionId, $feature, $idempotencyKey)) {
            return UsageResult::allowed(
                $feature,
                $this->meteredUsage($subscriptionId, $feature),
                $decision->limit,
                idempotentReplay: true,
            );
        }

        $lock = Cache::lock("billing:usage:{$subscriptionId}:{$feature->value}", 10);

        try {
            return $lock->block(5, fn (): UsageResult => $this->consumeLocked(
                $organization,
                $feature,
                $units,
                $idempotencyKey,
            ));
        } catch (LockTimeoutException) {
            return UsageResult::denied(
                EntitlementReason::UsageLimitReached,
                $feature,
                $decision->used,
                $decision->limit,
            );
        }
    }

    /**
     * Check a resource-capacity feature. `additional` is the number of new
     * resources the caller is about to create.
     */
    public function checkResource(
        User $user,
        Organization $organization,
        Feature $feature,
        int $additional = 1,
    ): EntitlementDecision {
        if ($feature->type() !== FeatureType::Resource) {
            throw new \InvalidArgumentException("Feature [{$feature->value}] is not a resource feature.");
        }

        return $this->check($user, $organization, $feature, null, max(1, $additional));
    }

    /**
     * The current usage/count for a feature, used by overview screens.
     */
    public function usage(Organization $organization, Feature $feature): int
    {
        $context = $this->context($organization);

        if ($context->subscriptionId === null) {
            return 0;
        }

        return match ($feature->type()) {
            FeatureType::Boolean => $context->hasFeature($feature) ? 1 : 0,
            FeatureType::Resource => $this->resourceUsage($organization, $feature),
            FeatureType::Metered => $this->meteredUsage($context->subscriptionId, $feature),
        };
    }

    /**
     * Invalidate the cached entitlement context for an organization.
     */
    public function forget(Organization $organization): void
    {
        Cache::forget($this->cacheKey($organization->id));
    }

    /**
     * @param  array<string, int|null>  $features
     */
    public function contextFromValues(
        int $organizationId,
        ?int $subscriptionId,
        ?SubscriptionStatus $status,
        ?Carbon $periodStart,
        ?Carbon $periodEnd,
        ?Carbon $graceEndsAt,
        ?Carbon $trialEndsAt,
        array $features,
    ): OrganizationEntitlements {
        return new OrganizationEntitlements(
            organizationId: $organizationId,
            subscriptionId: $subscriptionId,
            status: $status,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            graceEndsAt: $graceEndsAt,
            trialEndsAt: $trialEndsAt,
            features: $features,
        );
    }

    private function evaluate(
        User $user,
        Organization $organization,
        OrganizationEntitlements $context,
        Feature $feature,
        ?string $permission,
        int $units,
    ): EntitlementDecision {
        $fresh = Organization::withTrashed()->whereKey($organization->id)->first();

        if ($fresh === null || $fresh->trashed()) {
            return EntitlementDecision::deny(EntitlementReason::OrganizationMissing, $feature);
        }

        if (! $this->isMember($user, $fresh)) {
            return EntitlementDecision::deny(EntitlementReason::NotMember, $feature);
        }

        if ($permission !== null && ! $user->can($permission, $fresh)) {
            return EntitlementDecision::deny(EntitlementReason::PermissionDenied, $feature);
        }

        if ($context->subscriptionId === null || $context->status === null) {
            return EntitlementDecision::deny(EntitlementReason::NoSubscription, $feature);
        }

        if (! $context->isEntitledAt(now())) {
            return EntitlementDecision::deny(
                EntitlementReason::SubscriptionNotEntitled,
                $feature,
                $context->subscriptionId,
                $context->status->value,
            );
        }

        if (! $context->hasFeature($feature)) {
            return EntitlementDecision::deny(
                EntitlementReason::FeatureNotIncluded,
                $feature,
                $context->subscriptionId,
                $context->status->value,
            );
        }

        $limit = $context->limitFor($feature);

        if ($feature->type() === FeatureType::Boolean) {
            return EntitlementDecision::allow(
                $feature,
                null,
                0,
                $context->subscriptionId,
                $context->status->value,
            );
        }

        if ($feature->type() === FeatureType::Resource) {
            $current = $this->resourceUsage($fresh, $feature);

            if ($limit !== null && $current + max(1, $units) > $limit) {
                return EntitlementDecision::deny(
                    EntitlementReason::ResourceLimitReached,
                    $feature,
                    $context->subscriptionId,
                    $context->status->value,
                    $limit,
                    $current,
                );
            }

            return EntitlementDecision::allow(
                $feature,
                $limit,
                $current,
                $context->subscriptionId,
                $context->status->value,
            );
        }

        $used = $this->meteredUsage($context->subscriptionId, $feature);

        if ($limit !== null && $used + $units > $limit) {
            return EntitlementDecision::deny(
                EntitlementReason::UsageLimitReached,
                $feature,
                $context->subscriptionId,
                $context->status->value,
                $limit,
                $used,
            );
        }

        return EntitlementDecision::allow(
            $feature,
            $limit,
            $used,
            $context->subscriptionId,
            $context->status->value,
        );
    }

    private function consumeLocked(
        Organization $organization,
        Feature $feature,
        int $units,
        ?string $idempotencyKey,
    ): UsageResult {
        return DB::transaction(function () use ($organization, $feature, $units, $idempotencyKey): UsageResult {
            $subscription = Subscription::query()
                ->where('organization_id', $organization->id)
                ->where('status', '!=', SubscriptionStatus::Expired->value)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($subscription === null) {
                return UsageResult::denied(EntitlementReason::NoSubscription, $feature);
            }

            $limit = $subscription->entitlementLimit($feature);

            if (! $subscription->hasFeature($feature)) {
                return UsageResult::denied(EntitlementReason::FeatureNotIncluded, $feature);
            }

            if (! $subscription->isEntitledAt(now())) {
                return UsageResult::denied(EntitlementReason::SubscriptionNotEntitled, $feature);
            }

            /** @var UsageRecord $record */
            $record = UsageRecord::query()->firstOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'feature' => $feature->value,
                    'period_start' => $subscription->current_period_start,
                ],
                [
                    'organization_id' => $subscription->organization_id,
                    'period_end' => $subscription->current_period_end,
                    'used' => 0,
                    'limit_snapshot' => $limit,
                ],
            );

            $record = UsageRecord::query()
                ->whereKey($record->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($limit !== null && $record->used + $units > $limit) {
                return UsageResult::denied(
                    EntitlementReason::UsageLimitReached,
                    $feature,
                    $record->used,
                    $limit,
                );
            }

            if ($idempotencyKey !== null) {
                $inserted = UsageEvent::query()->insertOrIgnore([
                    'organization_id' => $subscription->organization_id,
                    'subscription_id' => $subscription->id,
                    'feature' => $feature->value,
                    'period_start' => $subscription->current_period_start,
                    'idempotency_key' => $idempotencyKey,
                    'units' => $units,
                    'created_at' => now(),
                ]);

                if ($inserted === 0) {
                    return UsageResult::allowed($feature, $record->used, $limit, idempotentReplay: true);
                }
            }

            $record->increment('used', $units);
            $record->refresh();

            return UsageResult::allowed($feature, $record->used, $limit);
        });
    }

    private function hasUsageEvent(int $subscriptionId, Feature $feature, string $idempotencyKey): bool
    {
        return UsageEvent::query()
            ->where('subscription_id', $subscriptionId)
            ->where('feature', $feature->value)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }

    private function meteredUsage(int $subscriptionId, Feature $feature): int
    {
        $subscription = Subscription::query()->find($subscriptionId);

        if ($subscription === null) {
            return 0;
        }

        $used = UsageRecord::query()
            ->where('subscription_id', $subscriptionId)
            ->where('feature', $feature->value)
            ->where('period_start', $subscription->current_period_start)
            ->value('used');

        return (int) $used;
    }

    /**
     * Count live resources for resource-capacity features.
     *
     * Licenses are a concrete resource (active licenses). Connected social
     * accounts are a concrete resource (active connected accounts). Projects
     * are part of the catalog but have no owning module yet, so they count as
     * zero.
     */
    private function resourceUsage(Organization $organization, Feature $feature): int
    {
        return match ($feature) {
            Feature::Licenses => License::query()
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->count(),
            Feature::ConnectedAccounts => ConnectedAccount::query()
                ->where('organization_id', $organization->id)
                ->where('status', \App\Enums\ConnectedAccountStatus::Active->value)
                ->count(),
            default => 0,
        };
    }

    private function isMember(User $user, Organization $organization): bool
    {
        return $organization->isOwnedBy($user) || $organization->hasMember($user);
    }

    private function context(Organization $organization): OrganizationEntitlements
    {
        return Cache::remember(
            $this->cacheKey($organization->id),
            self::CONTEXT_TTL_SECONDS,
            fn (): OrganizationEntitlements => $this->resolveContext($organization),
        );
    }

    private function resolveContext(Organization $organization): OrganizationEntitlements
    {
        $fresh = Organization::withTrashed()->whereKey($organization->id)->first();

        if ($fresh === null || $fresh->trashed()) {
            return new OrganizationEntitlements(organizationId: $organization->id);
        }

        $subscription = Subscription::query()
            ->where('organization_id', $organization->id)
            ->where('status', '!=', SubscriptionStatus::Expired->value)
            ->latest('id')
            ->first();

        if ($subscription === null) {
            return new OrganizationEntitlements(organizationId: $organization->id);
        }

        /** @var array<string, int|null> $features */
        $features = [];

        foreach ($subscription->entitlements()->get() as $entitlement) {
            $features[$entitlement->feature->value] = $entitlement->limit;
        }

        return new OrganizationEntitlements(
            organizationId: $organization->id,
            subscriptionId: $subscription->id,
            status: $subscription->status,
            periodStart: $subscription->current_period_start,
            periodEnd: $subscription->current_period_end,
            graceEndsAt: $subscription->grace_ends_at,
            trialEndsAt: $subscription->trial_ends_at,
            features: $features,
        );
    }

    private function cacheKey(int $organizationId): string
    {
        return "billing:org:{$organizationId}:entitlements";
    }
}
