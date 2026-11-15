<?php

namespace App\Services\Billing;

use App\Contracts\Payments\PaymentGateway;
use InvalidArgumentException;

/**
 * Resolves the configured payment gateway driver. Future providers only need
 * a new class registered in config/billing.php.
 */
final class PaymentGatewayManager
{
    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= (string) config('billing.gateway', 'mock');

        /** @var array<string, class-string<PaymentGateway>> $gateways */
        $gateways = config('billing.gateways', []);

        if (! isset($gateways[$name])) {
            throw new InvalidArgumentException("Payment gateway [{$name}] is not registered.");
        }

        $gateway = app($gateways[$name]);

        if (! $gateway instanceof PaymentGateway) {
            throw new InvalidArgumentException("Payment gateway [{$name}] must implement PaymentGateway.");
        }

        return $gateway;
    }
}
