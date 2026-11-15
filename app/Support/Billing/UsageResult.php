<?php

namespace App\Support\Billing;

use App\Enums\EntitlementReason;
use App\Enums\Feature;

/**
 * Immutable result of a usage consumption attempt.
 */
final class UsageResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly EntitlementReason $reason,
        public readonly ?Feature $feature = null,
        public readonly int $used = 0,
        public readonly ?int $limit = null,
        public readonly ?int $remaining = null,
        public readonly bool $idempotentReplay = false,
    ) {
    }

    public static function allowed(
        Feature $feature,
        int $used,
        ?int $limit,
        bool $idempotentReplay = false,
    ): self {
        return new self(
            allowed: true,
            reason: EntitlementReason::Allowed,
            feature: $feature,
            used: $used,
            limit: $limit,
            remaining: $limit === null ? null : max(0, $limit - $used),
            idempotentReplay: $idempotentReplay,
        );
    }

    public static function denied(
        EntitlementReason $reason,
        Feature $feature,
        int $used = 0,
        ?int $limit = null,
    ): self {
        return new self(
            allowed: false,
            reason: $reason,
            feature: $feature,
            used: $used,
            limit: $limit,
            remaining: $limit === null ? null : max(0, $limit - $used),
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
            'feature' => $this->feature?->value,
            'used' => $this->used,
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'idempotent_replay' => $this->idempotentReplay,
        ];
    }
}
