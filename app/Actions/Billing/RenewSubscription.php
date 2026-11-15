<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\MockPaymentGateway;
use Illuminate\Support\Carbon;

/**
 * Renew a subscription (or convert a trial) by charging the target plan
 * through the payment gateway.
 *
 * The renewal order uses a deterministic idempotency key derived from the
 * subscription and the period being closed, so a repeated scheduled run can
 * never create a second order or charge twice.
 */
class RenewSubscription
{
    public function __construct(
        private readonly PlaceOrder $placeOrder,
        private readonly ConfirmOrderPayment $confirmPayment,
        private readonly FailSubscriptionRenewal $failRenewal,
    ) {
    }

    public function handle(
        Subscription $subscription,
        ?User $actor = null,
        bool $simulateSuccess = true,
        SubscriptionEventType $successEvent = SubscriptionEventType::Renewed,
    ): PaymentTransaction {
        $subscription->refresh();

        $plan = $subscription->pendingPlan()->first() ?? $subscription->plan()->firstOrFail();

        $organization = $subscription->organization()->firstOrFail();

        $periodEnd = $this->closingPeriodEnd($subscription);

        $key = "renewal:{$subscription->id}:{$periodEnd->timestamp}";

        $order = $this->placeOrder->handle(
            organization: $organization,
            plan: $plan,
            actor: $actor,
            type: PlaceOrder::TYPE_RENEWAL,
            subscription: $subscription,
            idempotencyKey: $key,
            metadata: ['success_event' => $successEvent->value],
        );

        $payer = $actor ?? $organization->owner()->firstOrFail();

        $outcome = $simulateSuccess ? MockPaymentGateway::SUCCESS : MockPaymentGateway::FAILURE;

        $transaction = $this->confirmPayment->handle($order, $payer, $outcome);

        if ($transaction->status !== PaymentStatus::Succeeded) {
            $this->failRenewal->handle($subscription, $order, $transaction, $actor);
        }

        return $transaction;
    }

    /**
     * The end of the period currently being closed. For a trial this is the
     * trial end, otherwise the current period end.
     */
    private function closingPeriodEnd(Subscription $subscription): Carbon
    {
        if ($subscription->status === SubscriptionStatus::Trialing && $subscription->trial_ends_at !== null) {
            return $subscription->trial_ends_at;
        }

        return $subscription->current_period_end;
    }
}
