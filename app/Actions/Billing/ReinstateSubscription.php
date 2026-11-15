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
 * Reinstate a suspended subscription. If the paid period has already ended the
 * subscription returns as past_due so the next sync attempts a renewal.
 */
class ReinstateSubscription
{
    use EnsuresBillingPrivileges, ManagesSubscriptionState;

    public function handle(Subscription $subscription, User $actor): Subscription
    {
        $organization = $subscription->organization()->firstOrFail();

        $this->ensureBillingManager($organization, $actor);

        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SubscriptionStatus::Suspended) {
                throw ValidationException::withMessages([
                    'subscription' => 'Only a suspended subscription can be reinstated.',
                ]);
            }

            $restored = now()->lessThan($locked->current_period_end)
                ? SubscriptionStatus::Active
                : SubscriptionStatus::PastDue;

            $locked->fill([
                'status' => $restored,
                'suspended_at' => null,
                'grace_ends_at' => $restored === SubscriptionStatus::PastDue
                    ? ($locked->grace_ends_at ?? now()->addDays((int) config('billing.grace_days', 7)))
                    : null,
            ]);

            $locked->save();

            License::query()
                ->where('subscription_id', $locked->id)
                ->where('status', LicenseStatus::Suspended->value)
                ->where(fn ($query) => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))
                ->update([
                    'status' => LicenseStatus::Active->value,
                    'suspended_at' => null,
                    'updated_at' => now(),
                ]);

            $this->recordEvent($locked, SubscriptionEventType::Reinstated, $actor, SubscriptionStatus::Suspended, $restored);

            $this->forgetEntitlementCache($locked);

            return $locked->refresh();
        });
    }
}
