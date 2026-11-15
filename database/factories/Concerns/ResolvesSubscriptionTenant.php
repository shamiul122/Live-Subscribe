<?php

namespace Database\Factories\Concerns;

use App\Models\Subscription;

trait ResolvesSubscriptionTenant
{
    /**
     * Resolve a subscription_id/organization_id pair that satisfies the
     * composite tenant foreign keys. A subscription always determines its
     * organization.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: int, 1: int}
     */
    protected function resolveSubscriptionTenant(array &$attributes): array
    {
        $subscriptionId = $attributes['subscription_id'] ?? null;
        $organizationId = $attributes['organization_id'] ?? null;

        unset($attributes['subscription_id'], $attributes['organization_id']);

        if ($subscriptionId === null) {
            $subscription = Subscription::factory()->create();
            $subscriptionId = $subscription->id;
            $organizationId ??= $subscription->organization_id;
        } elseif ($organizationId === null) {
            $organizationId = Subscription::query()
                ->whereKey($subscriptionId)
                ->value('organization_id');
        }

        return [(int) $subscriptionId, (int) $organizationId];
    }
}
