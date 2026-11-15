<?php

namespace App\Actions\Billing;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Payments\MockPaymentGateway;
use App\Services\Billing\PaymentGatewayManager;
use App\Support\Billing\PaymentIntent;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Confirm a checkout and settle an order.
 *
 * The gateway call happens outside any database transaction or row lock. A
 * short cache lock serialises concurrent confirmations of the same order
 * without holding a database lock during the external call. Duplicate
 * confirmations are safe: a paid order is never applied twice.
 */
class ConfirmOrderPayment
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly ApplyPaidOrder $applyPaidOrder,
    ) {
    }

    public function handle(
        Order $order,
        User $actor,
        string $outcome,
        ?PaymentGateway $gateway = null,
    ): PaymentTransaction {
        $gateway ??= $this->gateways->driver();

        if (! $order->isPayable()) {
            return $this->existingTransactionOrFail($order);
        }

        $lock = Cache::lock("billing:order:{$order->id}", 30);

        try {
            return $lock->block(10, fn (): PaymentTransaction => $this->settle($order, $actor, $outcome, $gateway));
        } catch (LockTimeoutException) {
            throw new RuntimeException('Another payment for this order is still being processed. Please retry.');
        }
    }

    private function settle(
        Order $order,
        User $actor,
        string $outcome,
        PaymentGateway $gateway,
    ): PaymentTransaction {
        $order->refresh();

        if (! $order->isPayable()) {
            return $this->existingTransactionOrFail($order);
        }

        $organization = Organization::withTrashed()->whereKey($order->organization_id)->first();

        if ($organization === null || $organization->trashed()) {
            throw new RuntimeException('The organization for this order is no longer active.');
        }

        $intent = new PaymentIntent(
            orderId: $order->id,
            organizationId: $order->organization_id,
            amountMinor: $order->amount_minor,
            currency: $order->currency,
            idempotencyKey: $order->idempotency_key,
            description: $order->typeLabel(),
            metadata: ['order_type' => $order->type],
        );

        // External call: deliberately outside the transaction below.
        $result = $gateway->confirm($intent, $outcome);

        // Verify the gateway response before persisting anything.
        $verified = $result->successful
            && $result->amountMinor === $order->amount_minor
            && strtoupper($result->currency) === strtoupper($order->currency);

        $failureCode = $verified ? null : ($result->successful ? 'amount_mismatch' : $result->failureCode);
        $failureMessage = $verified ? null : ($result->successful ? 'The payment amount did not match the order.' : $result->failureMessage);

        return DB::transaction(function () use ($order, $actor, $result, $verified, $failureCode, $failureMessage): PaymentTransaction {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            // A concurrent confirmation may have completed while the gateway
            // call was in flight.
            if (! $locked->isPayable()) {
                return $this->existingTransactionOrFail($locked);
            }

            $transaction = new PaymentTransaction([
                'organization_id' => $locked->organization_id,
                'order_id' => $locked->id,
                'provider' => $result->provider,
                'provider_reference' => $verified ? $result->providerReference : null,
                'status' => $verified ? PaymentStatus::Succeeded : PaymentStatus::Failed,
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'payload' => $result->payload,
                'processed_at' => now(),
            ]);

            $transaction->save();

            if ($verified) {
                $locked->status = OrderStatus::Paid;
                $locked->paid_at = now();
                $locked->save();

                $this->applyPaidOrder->handle($locked, $actor);
            }

            return $transaction;
        });
    }

    /**
     * A paid order always has a succeeded transaction. Return it instead of
     * applying the order again.
     */
    private function existingTransactionOrFail(Order $order): PaymentTransaction
    {
        $transaction = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::Succeeded->value)
            ->latest('id')
            ->first();

        if ($transaction === null) {
            throw new RuntimeException('This order is no longer payable.');
        }

        return $transaction;
    }

    /**
     * Whether the mock gateway is allowed to simulate a successful renewal.
     */
    public function mockOutcome(): string
    {
        return (bool) config('billing.mock.auto_renew', true)
            ? MockPaymentGateway::SUCCESS
            : MockPaymentGateway::FAILURE;
    }
}
