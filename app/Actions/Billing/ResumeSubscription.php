<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reverse a pending cancellation before the period ends.
 */
class ResumeSubscription
{
    use EnsuresBillingPrivileges, ManagesSubscriptionState;

    public function handle(Subscription $subscription, User $actor): Subscription
    {
        $organization = $subscription->organization()->firstOrFail();

        $this->ensureBillingOwner($organization, $actor);

        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SubscriptionStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'subscription' => 'Only a cancelled subscription can be resumed.',
                ]);
            }

            if (now()->greaterThanOrEqualTo($locked->current_period_end)) {
                throw ValidationException::withMessages([
                    'subscription' => 'The billing period has ended; this subscription can no longer be resumed.',
                ]);
            }

            $restored = $locked->trial_ends_at !== null && now()->lessThan($locked->trial_ends_at)
                ? SubscriptionStatus::Trialing
                : SubscriptionStatus::Active;

            $locked->fill([
                'status' => $restored,
                'cancel_at_period_end' => false,
                'cancelled_at' => null,
            ]);

            $locked->save();

            $this->recordEvent($locked, SubscriptionEventType::Resumed, $actor, SubscriptionStatus::Cancelled, $restored);

            $this->forgetEntitlementCache($locked);

            return $locked->refresh();
        });
    }
}
