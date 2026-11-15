<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Move a subscription into past_due after a failed renewal payment.
 *
 * The subscription keeps access until the grace period ends. The pending order
 * is left in place so the payment can be retried while the order is payable.
 */
class FailSubscriptionRenewal
{
    use ManagesSubscriptionState;

    public function handle(
        Subscription $subscription,
        Order $order,
        ?PaymentTransaction $transaction = null,
        ?User $actor = null,
    ): Subscription {
        return DB::transaction(function () use ($subscription, $order, $transaction, $actor): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal() || $locked->status === SubscriptionStatus::Suspended) {
                return $locked;
            }

            $from = $locked->status;

            $graceEndsAt = $locked->grace_ends_at ?? now()->addDays((int) config('billing.grace_days', 7));

            $locked->fill([
                'status' => SubscriptionStatus::PastDue,
                'grace_ends_at' => $graceEndsAt,
            ]);

            $locked->save();

            $this->recordEvent($locked, SubscriptionEventType::PaymentFailed, $actor, $from, SubscriptionStatus::PastDue, [
                'order_id' => $order->id,
                'transaction_id' => $transaction?->id,
                'failure_code' => $transaction?->failure_code,
            ]);

            $this->recordEvent($locked, SubscriptionEventType::PastDue, $actor, $from, SubscriptionStatus::PastDue, [
                'grace_ends_at' => $graceEndsAt->toIso8601String(),
            ]);

            $this->forgetEntitlementCache($locked);

            return $locked->refresh();
        });
    }
}
