<?php

namespace App\Support\Billing;

/**
 * Normalized result of a payment attempt.
 */
final class PaymentResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly bool $successful,
        public readonly string $provider,
        public readonly ?string $providerReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
        public readonly array $payload = [],
    ) {
    }
}
