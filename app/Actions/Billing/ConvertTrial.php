<?php

namespace App\Actions\Billing;

use App\Enums\SubscriptionEventType;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;

/**
 * Convert an ended trial into a paid subscription.
 *
 * Trial conversion is a renewal of the first period with a distinct audit
 * event so the history clearly shows the trial-to-paid transition.
 */
class ConvertTrial
{
    public function __construct(private readonly RenewSubscription $renewSubscription)
    {
    }

    public function handle(Subscription $subscription, ?User $actor = null, bool $simulateSuccess = true): PaymentTransaction
    {
        return $this->renewSubscription->handle(
            subscription: $subscription,
            actor: $actor,
            simulateSuccess: $simulateSuccess,
            successEvent: SubscriptionEventType::TrialConverted,
        );
    }
}
