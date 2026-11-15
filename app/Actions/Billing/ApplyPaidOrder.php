<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Apply a paid order to the subscription domain.
 *
 * This runs inside the caller's transaction and assumes the order row is
 * already locked. It never talks to a payment gateway.
 */
class ApplyPaidOrder
{
    use ManagesSubscriptionState;

    public function handle(Order $order, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($order, $actor): Subscription {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $plan = $locked->plan()->firstOrFail();

            $subscription = match ($locked->type) {
                PlaceOrder::TYPE_NEW => $this->activateNew($locked, $plan, $actor),
                PlaceOrder::TYPE_RENEWAL => $this->renew($locked, $plan, $actor),
                default => throw new RuntimeException("Unsupported order type [{$locked->type}]."),
            };

            if ($locked->subscription_id === null) {
                $locked->subscription_id = $subscription->id;
                $locked->save();
            }

            $this->forgetEntitlementCache($subscription);

            return $subscription->refresh();
        });
    }

    private function activateNew(Order $order, \App\Models\Plan $plan, ?User $actor): Subscription
    {
        $organization = Organization::query()
            ->whereKey($order->organization_id)
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureOrganizationHasNoNonTerminalSubscription($organization->id);

        $start = now();
        $end = $plan->billing_interval->addTo($start, $plan->billing_interval_count);

        $subscription = new Subscription([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'grace_ends_at' => null,
            'cancel_at_period_end' => false,
            'price_minor' => $plan->price_minor,
            'currency' => $plan->currency,
            'billing_interval' => $plan->billing_interval,
            'billing_interval_count' => $plan->billing_interval_count,
            'provider' => (string) config('billing.gateway', 'mock'),
            'last_renewed_at' => now(),
        ]);

        $subscription->save();

        $this->snapshotEntitlements($subscription, $plan);

        $this->recordEvent($subscription, SubscriptionEventType::Created, $actor, null, SubscriptionStatus::Active, [
            'plan' => $plan->slug,
            'order_id' => $order->id,
        ]);

        $this->recordEvent($subscription, SubscriptionEventType::Activated, $actor, null, SubscriptionStatus::Active, [
            'order_id' => $order->id,
            'amount_minor' => $order->amount_minor,
            'currency' => $order->currency,
        ]);

        return $subscription;
    }

    private function renew(Order $order, \App\Models\Plan $plan, ?User $actor): Subscription
    {
        $subscription = Subscription::query()
            ->whereKey($order->subscription_id)
            ->lockForUpdate()
            ->firstOrFail();

        $from = $subscription->status;

        $previousPlanId = $subscription->plan_id;

        $previousEnd = $subscription->current_period_end;

        [$start, $end] = $this->nextPeriod($subscription, $plan, $previousEnd);

        $subscription->fill([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'grace_ends_at' => null,
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
            'suspended_at' => null,
            'ended_at' => null,
            'price_minor' => $plan->price_minor,
            'currency' => $plan->currency,
            'billing_interval' => $plan->billing_interval,
            'billing_interval_count' => $plan->billing_interval_count,
            'pending_plan_id' => null,
            'last_renewed_at' => now(),
        ]);

        $subscription->save();

        $this->snapshotEntitlements($subscription, $plan);

        $eventType = match ($order->metadata['success_event'] ?? null) {
            SubscriptionEventType::TrialConverted->value => SubscriptionEventType::TrialConverted,
            default => SubscriptionEventType::Renewed,
        };

        $this->recordEvent($subscription, $eventType, $actor, $from, SubscriptionStatus::Active, [
            'order_id' => $order->id,
            'plan' => $plan->slug,
        ]);

        if ($previousPlanId !== $plan->id) {
            $this->recordEvent($subscription, SubscriptionEventType::PlanChanged, $actor, $from, SubscriptionStatus::Active, [
                'from_plan_id' => $previousPlanId,
                'to_plan_id' => $plan->id,
            ]);
        }

        return $subscription;
    }
}
