<?php

return [
    'default' => env('PAYMENT_PROVIDER_DEFAULT', 'stub'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],

    'p24' => [
        'merchant_id' => env('P24_MERCHANT_ID', ''),
        'crc' => env('P24_CRC', ''),
        'api_key' => env('P24_API_KEY', ''),
        'sandbox' => env('P24_SANDBOX', true),
    ],

    'idempotency' => [
        'expiry_hours' => env('IDEMPOTENCY_EXPIRY_HOURS', 24),
    ],
];
