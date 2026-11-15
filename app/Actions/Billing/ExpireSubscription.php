<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\LicenseStatus;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\License;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Move a subscription to its terminal expired state.
 *
 * Expiry is permanent: the organization may start a new subscription, but this
 * one can never be revived. Active licenses are expired with it.
 */
class ExpireSubscription
{
    use ManagesSubscriptionState;

    public function handle(Subscription $subscription, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return $locked;
            }

            $from = $locked->status;

            $locked->fill([
                'status' => SubscriptionStatus::Expired,
                'ended_at' => now(),
                'cancel_at_period_end' => false,
            ]);

            $locked->save();

            License::query()
                ->where('subscription_id', $locked->id)
                ->whereIn('status', [LicenseStatus::Active->value, LicenseStatus::Suspended->value])
                ->update([
                    'status' => LicenseStatus::Expired->value,
                    'updated_at' => now(),
                ]);

            $this->recordEvent($locked, SubscriptionEventType::Expired, $actor, $from, SubscriptionStatus::Expired);

            $this->forgetEntitlementCache($locked);

            return $locked->refresh();
        });
    }
}
