<?php

namespace App\Support\Billing;

use App\Enums\Feature;
use App\Enums\SubscriptionStatus;
use Illuminate\Support\Carbon;

/**
 * Resolved entitlement context for one organization.
 *
 * This object may be cached. It deliberately excludes live metered usage
 * counters, which are always read from the database to keep limit enforcement
 * exact.
 */
final class OrganizationEntitlements
{
    /**
     * @param  array<string, int|null>  $features  feature value => limit
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly ?int $subscriptionId = null,
        public readonly ?SubscriptionStatus $status = null,
        public readonly ?Carbon $periodStart = null,
        public readonly ?Carbon $periodEnd = null,
        public readonly ?Carbon $graceEndsAt = null,
        public readonly ?Carbon $trialEndsAt = null,
        public readonly array $features = [],
    ) {
    }

    public function hasFeature(Feature $feature): bool
    {
        return array_key_exists($feature->value, $this->features);
    }

    public function limitFor(Feature $feature): ?int
    {
        return $this->features[$feature->value] ?? null;
    }

    /**
     * Whether the subscription grants access at the given moment, ignoring
     * feature inclusion.
     */
    public function isEntitledAt(Carbon $at): bool
    {
        if ($this->subscriptionId === null || $this->status === null) {
            return false;
        }

        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->trialEndsAt !== null && $at->lessThan($this->trialEndsAt),
            SubscriptionStatus::Active => $this->periodEnd !== null && $at->lessThan($this->periodEnd),
            SubscriptionStatus::PastDue => $this->graceEndsAt !== null && $at->lessThanOrEqualTo($this->graceEndsAt),
            SubscriptionStatus::Cancelled => $this->periodEnd !== null && $at->lessThan($this->periodEnd),
            SubscriptionStatus::Suspended, SubscriptionStatus::Expired => false,
        };
    }

    /**
     * The moment entitlement ends for the current term, if known.
     */
    public function entitledUntil(): ?Carbon
    {
        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->trialEndsAt,
            SubscriptionStatus::PastDue => $this->graceEndsAt,
            SubscriptionStatus::Active, SubscriptionStatus::Cancelled => $this->periodEnd,
            SubscriptionStatus::Suspended, SubscriptionStatus::Expired, null => null,
        };
    }

    /**
     * @return array<string, int|null>
     */
    public function featureMap(): array
    {
        return $this->features;
    }
}
