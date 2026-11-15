<?php

namespace App\Actions\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;

/**
 * Advance every subscription whose term has ended.
 *
 * This is the single scheduled entry point. It is idempotent: deterministic
 * renewal order keys, status guards and terminal states mean running it twice
 * never double-charges or double-advances.
 */
class SyncSubscriptionStatuses
{
    public function __construct(
        private readonly ConvertTrial $convertTrial,
        private readonly RenewSubscription $renewSubscription,
        private readonly ExpireSubscription $expireSubscription,
        private readonly ExpirePendingOrders $expirePendingOrders,
        private readonly ExpireLicenses $expireLicenses,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function handle(bool $dryRun = false): array
    {
        $now = now();
        $simulateSuccess = (bool) config('billing.mock.auto_renew', true);

        $trialsDue = Subscription::query()
            ->where('status', SubscriptionStatus::Trialing->value)
            ->where('trial_ends_at', '<=', $now)
            ->orderBy('id')
            ->get();

        $renewalsDue = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('current_period_end', '<=', $now)
            ->orderBy('id')
            ->get();

        $cancellationsDue = Subscription::query()
            ->where('status', SubscriptionStatus::Cancelled->value)
            ->where('current_period_end', '<=', $now)
            ->orderBy('id')
            ->get();

        $graceDue = Subscription::query()
            ->where('status', SubscriptionStatus::PastDue->value)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->orderBy('id')
            ->get();

        $result = [
            'trials_converted' => $trialsDue->count(),
            'subscriptions_renewed' => $renewalsDue->count(),
            'subscriptions_expired' => $cancellationsDue->count() + $graceDue->count(),
            'orders_expired' => 0,
            'licenses_expired' => 0,
            'dry_run' => $dryRun ? 1 : 0,
        ];

        if ($dryRun) {
            return $result;
        }

        foreach ($trialsDue as $subscription) {
            $this->convertTrial->handle($subscription, null, $simulateSuccess);
        }

        foreach ($renewalsDue as $subscription) {
            $this->renewSubscription->handle($subscription, null, $simulateSuccess);
        }

        foreach ($cancellationsDue as $subscription) {
            $this->expireSubscription->handle($subscription);
        }

        foreach ($graceDue as $subscription) {
            $this->expireSubscription->handle($subscription);
        }

        $result['orders_expired'] = $this->expirePendingOrders->handle();
        $result['licenses_expired'] = $this->expireLicenses->handle();

        return $result;
    }

    /**
     * Renew a single subscription on demand (for example "pay now" after a
     * failed renewal).
     */
    public function renewNow(Subscription $subscription, ?User $actor = null): void
    {
        $this->renewSubscription->handle($subscription, $actor, true);
    }
}
