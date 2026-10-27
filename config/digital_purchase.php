<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sandbox Payment Gateway
    |--------------------------------------------------------------------------
    | When enabled, a "Sandbox Gateway" appears in checkout that allows 1-click
    | simulated payments without touching real gateways or PayPal credentials.
    */
    'sandbox_gateway_enabled' => (bool) env('PAYMENT_GATEWAY_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Dedicated Card Vault Encryption Key
    |--------------------------------------------------------------------------
    | Allows rotating APP_KEY without invalidating encrypted gift card codes.
    */
    'vault_key' => env('CARD_VAULT_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | FoxReload Configuration
    |--------------------------------------------------------------------------
    */
    'foxreload' => [
        'is_mock' => (bool) env('FOXRELOAD_IS_MOCK', true),
        'currency' => env('FOXRELOAD_CURRENCY', 'usd'),
        'balance_alert_threshold_usd' => (float) env('FOXRELOAD_BALANCE_ALERT_THRESHOLD', 50.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud and Purchase Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_orders_per_user_per_hour' => 10,
        'max_daily_order_amount_usd' => 500.0,
    ],
];
