<?php

namespace App\Support\Billing;

/**
 * Normalized checkout session returned by a payment gateway.
 */
final class CheckoutSession
{
    public function __construct(
        public readonly string $provider,
        public readonly string $reference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly bool $requiresConfirmation = true,
        public readonly ?string $redirectUrl = null,
    ) {
    }
}
