<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\LicenseStatus;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\License;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Suspend a subscription. Access is revoked immediately and active licenses
 * are suspended; the subscription can be reinstated.
 */
class SuspendSubscription
{
    use EnsuresBillingPrivileges, ManagesSubscriptionState;

    public function handle(Subscription $subscription, User $actor): Subscription
    {
        $organization = $subscription->organization()->firstOrFail();

        $this->ensureBillingManager($organization, $actor);

        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'subscription' => 'This subscription has already ended.',
                ]);
            }

            if ($locked->status === SubscriptionStatus::Suspended) {
                return $locked;
            }

            $from = $locked->status;

            $locked->fill([
                'status' => SubscriptionStatus::Suspended,
                'suspended_at' => now(),
            ]);

            $locked->save();

            License::query()
                ->where('subscription_id', $locked->id)
                ->where('status', LicenseStatus::Active->value)
                ->update([
                    'status' => LicenseStatus::Suspended->value,
                    'suspended_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->recordEvent($locked, SubscriptionEventType::Suspended, $actor, $from, SubscriptionStatus::Suspended);

            $this->forgetEntitlementCache($locked);

            return $locked->refresh();
        });
    }
}
