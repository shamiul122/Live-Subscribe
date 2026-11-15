<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancel a subscription at the end of the current period.
 *
 * Access and licenses remain usable until the period end; the subscription
 * then expires during billing:sync. Cancellation is reversible with resume
 * until the period ends.
 */
class CancelSubscription
{
    use EnsuresBillingPrivileges, ManagesSubscriptionState;

    public function handle(Subscription $subscription, User $actor): Subscription
    {
        $organization = $subscription->organization()->firstOrFail();

        $this->ensureBillingOwner($organization, $actor);

        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'subscription' => 'This subscription has already ended.',
                ]);
            }

            if ($locked->status === SubscriptionStatus::Cancelled) {
                return $locked;
            }

            $from = $locked->status;

            $locked->fill([
                'status' => SubscriptionStatus::Cancelled,
                'cancel_at_period_end' => true,
                'cancelled_at' => now(),
            ]);

            $locked->save();

            $this->recordEvent($locked, SubscriptionEventType::Cancelled, $actor, $from, SubscriptionStatus::Cancelled, [
                'ends_at' => $locked->current_period_end->toIso8601String(),
            ]);

            $this->forgetEntitlementCache($locked);

            return $locked->refresh();
        });
    }
}
