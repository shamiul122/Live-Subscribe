<?php

namespace App\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Support\Billing\CheckoutSession;
use App\Support\Billing\PaymentIntent;
use App\Support\Billing\PaymentResult;
use InvalidArgumentException;
use RuntimeException;

/**
 * Local-only payment simulator. It performs no network calls and never moves
 * money. It is disabled outside local/testing unless explicitly enabled.
 */
final class MockPaymentGateway implements PaymentGateway
{
    public const SUCCESS = 'success';

    public const FAILURE = 'failure';

    public function name(): string
    {
        return 'mock';
    }

    public function createCheckoutSession(PaymentIntent $intent): CheckoutSession
    {
        $this->ensureEnabled();

        $prefix = (string) config('billing.mock.reference_prefix', 'mock');

        return new CheckoutSession(
            provider: $this->name(),
            reference: $prefix.'_'.bin2hex(random_bytes(8)),
            amountMinor: $intent->amountMinor,
            currency: $intent->currency,
            requiresConfirmation: true,
        );
    }

    public function confirm(PaymentIntent $intent, string $outcome): PaymentResult
    {
        $this->ensureEnabled();

        if (! in_array($outcome, [self::SUCCESS, self::FAILURE], true)) {
            throw new InvalidArgumentException('Unsupported mock payment outcome.');
        }

        if ($outcome === self::FAILURE) {
            return new PaymentResult(
                successful: false,
                provider: $this->name(),
                providerReference: null,
                amountMinor: $intent->amountMinor,
                currency: $intent->currency,
                failureCode: 'mock_declined',
                failureMessage: 'The mock payment was declined.',
                payload: [
                    'simulated' => true,
                    'outcome' => self::FAILURE,
                    'idempotency_key' => $intent->idempotencyKey,
                ],
            );
        }

        return new PaymentResult(
            successful: true,
            provider: $this->name(),
            providerReference: 'mock_'.bin2hex(random_bytes(12)),
            amountMinor: $intent->amountMinor,
            currency: $intent->currency,
            payload: [
                'simulated' => true,
                'outcome' => self::SUCCESS,
                'idempotency_key' => $intent->idempotencyKey,
            ],
        );
    }

    /**
     * Refuse to simulate payments in an environment where the mock gateway
     * has not been explicitly enabled.
     */
    private function ensureEnabled(): void
    {
        if (! (bool) config('billing.mock.enabled', false)) {
            throw new RuntimeException('The mock payment gateway is disabled in this environment.');
        }
    }
}
