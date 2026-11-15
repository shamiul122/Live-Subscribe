<?php

namespace App\Support\Billing;

/**
 * Normalized payment intent handed to a payment gateway.
 */
final class PaymentIntent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $orderId,
        public readonly int $organizationId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $idempotencyKey,
        public readonly string $description,
        public readonly array $metadata = [],
    ) {
    }
}
