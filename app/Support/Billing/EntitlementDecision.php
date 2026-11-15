<?php

namespace App\Support\Billing;

use App\Enums\EntitlementReason;
use App\Enums\Feature;

/**
 * Immutable result of a single entitlement evaluation.
 */
final class EntitlementDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly EntitlementReason $reason,
        public readonly ?Feature $feature = null,
        public readonly ?int $limit = null,
        public readonly int $used = 0,
        public readonly ?int $remaining = null,
        public readonly ?int $subscriptionId = null,
        public readonly ?string $subscriptionStatus = null,
    ) {
    }

    public static function allow(
        Feature $feature,
        ?int $limit,
        int $used,
        ?int $subscriptionId = null,
        ?string $subscriptionStatus = null,
    ): self {
        return new self(
            allowed: true,
            reason: EntitlementReason::Allowed,
            feature: $feature,
            limit: $limit,
            used: $used,
            remaining: $limit === null ? null : max(0, $limit - $used),
            subscriptionId: $subscriptionId,
            subscriptionStatus: $subscriptionStatus,
        );
    }

    public static function deny(
        EntitlementReason $reason,
        ?Feature $feature = null,
        ?int $subscriptionId = null,
        ?string $subscriptionStatus = null,
        ?int $limit = null,
        int $used = 0,
    ): self {
        return new self(
            allowed: false,
            reason: $reason,
            feature: $feature,
            limit: $limit,
            used: $used,
            remaining: $limit === null ? null : max(0, $limit - $used),
            subscriptionId: $subscriptionId,
            subscriptionStatus: $subscriptionStatus,
        );
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'feature' => $this->feature?->value,
            'limit' => $this->limit,
            'used' => $this->used,
            'remaining' => $this->remaining,
            'subscription_id' => $this->subscriptionId,
            'subscription_status' => $this->subscriptionStatus,
        ];
    }
}
