<?php

use App\Payments\MockPaymentGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | The active payment gateway driver. Only the mock gateway ships with
    | Step 3. Real providers are added later by registering a driver below and
    | pointing this value at it. No provider may require subscription logic
    | changes.
    |
    */

    'gateway' => env('BILLING_GATEWAY', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Registered gateways
    |--------------------------------------------------------------------------
    |
    | Maps a gateway name to the class implementing
    | App\Contracts\Payments\PaymentGateway.
    |
    */

    'gateways' => [
        'mock' => MockPaymentGateway::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Grace period
    |--------------------------------------------------------------------------
    |
    | Number of days a past_due subscription keeps access and valid licenses
    | after the billing period ends before it is expired.
    |
    */

    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Order lifetime
    |--------------------------------------------------------------------------
    |
    | Pending orders older than this many days are expired by billing:sync.
    |
    */

    'order_expiry_days' => (int) env('BILLING_ORDER_EXPIRY_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Mock gateway
    |--------------------------------------------------------------------------
    |
    | The mock gateway performs no network calls and charges no money. It is
    | enabled automatically in local/testing environments and must be enabled
    | explicitly anywhere else.
    |
    */

    'mock' => [
        'enabled' => filter_var(
            env('BILLING_MOCK_ENABLED', env('APP_ENV', 'production') !== 'production'),
            FILTER_VALIDATE_BOOL,
        ),

        // Scheduled billing:sync renews due subscriptions through the mock
        // gateway by default. Disable to force subscriptions into past_due.
        'auto_renew' => filter_var(
            env('BILLING_MOCK_AUTO_RENEW', true),
            FILTER_VALIDATE_BOOL,
        ),

        'reference_prefix' => 'mock',
    ],

];
