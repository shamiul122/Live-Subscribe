<?php

namespace App\Actions\Billing\Concerns;

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionEntitlement;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Shared subscription state helpers used by every billing action.
 *
 * Keeping entitlement snapshotting, event writing and cache invalidation in
 * one place guarantees that no lifecycle transition can forget an audit row or
 * leave a stale entitlement cache behind.
 */
trait ManagesSubscriptionState
{
    /**
     * Copy the plan's current feature limits onto the subscription. This is the
     * commercial snapshot for the billing period: later edits to the plan do
     * not change an existing subscription until it renews.
     */
    protected function snapshotEntitlements(Subscription $subscription, Plan $plan): void
    {
        $features = $plan->features()->get();

        foreach ($features as $feature) {
            SubscriptionEntitlement::query()->updateOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'feature' => $feature->feature->value,
                ],
                [
                    'organization_id' => $subscription->organization_id,
                    'limit' => $feature->limit,
                ],
            );
        }

        $values = $features
            ->map(fn ($feature): string => $feature->feature->value)
            ->all();

        $subscription->entitlements()
            ->whereNotIn('feature', $values)
            ->delete();
    }

    /**
     * Append a subscription lifecycle event. Events are append-only and never
     * contain secrets.
     *
     * @param  array<string, mixed>  $data
     */
    protected function recordEvent(
        Subscription $subscription,
        SubscriptionEventType $type,
        ?User $actor = null,
        ?SubscriptionStatus $from = null,
        ?SubscriptionStatus $to = null,
        array $data = [],
    ): SubscriptionEvent {
        return SubscriptionEvent::query()->create([
            'organization_id' => $subscription->organization_id,
            'subscription_id' => $subscription->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'data' => $data === [] ? null : $data,
            'created_at' => now(),
        ]);
    }

    /**
     * Drop the cached entitlement context after any billing mutation.
     */
    protected function forgetEntitlementCache(Subscription $subscription): void
    {
        $organization = $subscription->organization()->first();

        if ($organization !== null) {
            app(EntitlementService::class)->forget($organization);
        }
    }

    /**
     * A second non-terminal subscription is never allowed for an organization.
     * The database index is the final defense; this check produces a friendly
     * validation error first.
     */
    protected function ensureNoNonTerminalSubscription(Subscription $subscription): void
    {
        $this->ensureOrganizationHasNoNonTerminalSubscription(
            $subscription->organization_id,
            $subscription->id,
        );
    }

    protected function ensureOrganizationHasNoNonTerminalSubscription(
        int $organizationId,
        ?int $ignoreSubscriptionId = null,
    ): void {
        $query = Subscription::query()
            ->where('organization_id', $organizationId)
            ->where('status', '!=', SubscriptionStatus::Expired->value);

        if ($ignoreSubscriptionId !== null) {
            $query->whereKeyNot($ignoreSubscriptionId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'subscription' => 'This organization already has a subscription. Cancel or let it expire first.',
            ]);
        }
    }

    /**
     * The next billing window. Renewal starts where the previous period ended
     * unless that is already in the past, in which case it starts now so a late
     * renewal never produces a window in the past.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function nextPeriod(Subscription $subscription, Plan $plan, ?Carbon $previousEnd = null): array
    {
        $reference = $previousEnd ?? $subscription->current_period_end;

        $start = $reference->greaterThan(now()) ? $reference->copy() : now();

        $end = $plan->billing_interval->addTo($start, $plan->billing_interval_count);

        return [$start, $end];
    }
}
