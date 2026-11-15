<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\SubscriptionEventType;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancel a plan change that was scheduled for the next renewal.
 */
class CancelScheduledPlanChange
{
    use EnsuresBillingPrivileges, ManagesSubscriptionState;

    public function handle(Subscription $subscription, User $actor): Subscription
    {
        $organization = $subscription->organization()->firstOrFail();

        $this->ensureBillingOwner($organization, $actor);

        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->pending_plan_id === null) {
                throw ValidationException::withMessages([
                    'plan' => 'There is no scheduled plan change to cancel.',
                ]);
            }

            $fromPlanId = $locked->pending_plan_id;

            $locked->pending_plan_id = null;
            $locked->save();

            $this->recordEvent($locked, SubscriptionEventType::PlanChangeCancelled, $actor, $locked->status, $locked->status, [
                'cancelled_plan_id' => $fromPlanId,
            ]);

            return $locked->refresh();
        });
    }
}
