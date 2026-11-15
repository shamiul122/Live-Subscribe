<?php

namespace App\Contracts\Payments;

use App\Support\Billing\CheckoutSession;
use App\Support\Billing\PaymentIntent;
use App\Support\Billing\PaymentResult;

/**
 * Provider-agnostic payment boundary.
 *
 * Step 3 ships only MockPaymentGateway. A real provider adapter implements
 * this same interface, is registered in config/billing.php, and is selected
 * with config('billing.gateway'). Subscription, entitlement and license logic
 * must never depend on a concrete provider.
 */
interface PaymentGateway
{
    /**
     * The driver name, e.g. "mock".
     */
    public function name(): string;

    /**
     * Begin a checkout for an order. For the mock gateway this returns an
     * internal reference; for a real provider it would return a redirect URL.
     */
    public function createCheckoutSession(PaymentIntent $intent): CheckoutSession;

    /**
     * Confirm a checkout.
     *
     * The mock gateway accepts an explicit "success" or "failure" simulation.
     * A real gateway must verify the provider's response server-side and must
     * never trust a browser-reported outcome.
     */
    public function confirm(PaymentIntent $intent, string $outcome): PaymentResult;
}
