<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\SubscriptionEventType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Schedule a plan change for the next renewal.
 *
 * No proration and no immediate switch: the current plan's terms stay in force
 * until the period ends, then the scheduled plan is applied at renewal.
 */
class SchedulePlanChange
{
    use EnsuresBillingPrivileges, ManagesSubscriptionState;

    public function handle(Subscription $subscription, User $actor, Plan $plan): Subscription
    {
        $organization = $subscription->organization()->firstOrFail();

        $this->ensureBillingOwner($organization, $actor);

        if (! $plan->isPurchasable()) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is not available.',
            ]);
        }

        if ($plan->id === $subscription->plan_id) {
            throw ValidationException::withMessages([
                'plan' => 'This is already the current plan.',
            ]);
        }

        if (strtoupper($plan->currency) !== strtoupper($subscription->currency)) {
            throw ValidationException::withMessages([
                'plan' => 'Plans with a different currency cannot be combined without an explicit conversion.',
            ]);
        }

        return DB::transaction(function () use ($subscription, $actor, $plan): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'plan' => 'This subscription has already ended.',
                ]);
            }

            $locked->pending_plan_id = $plan->id;
            $locked->save();

            $this->recordEvent($locked, SubscriptionEventType::PlanChangeScheduled, $actor, $locked->status, $locked->status, [
                'from_plan_id' => $locked->plan_id,
                'to_plan_id' => $plan->id,
                'effective_at' => $locked->current_period_end->toIso8601String(),
            ]);

            return $locked->refresh();
        });
    }
}
