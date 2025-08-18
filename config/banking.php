<?php

return [
    'bridge' => [
        'client_id' => env('BRIDGE_CLIENT_ID'),
        'client_secret' => env('BRIDGE_CLIENT_SECRET'),
        'webhook_secret' => env('BRIDGE_WEBHOOK_SECRET'),
        'environment' => env('BRIDGE_ENVIRONMENT', 'sandbox'),
        'base_url' => env('BRIDGE_ENVIRONMENT', 'sandbox') === 'production'
            ? 'https://api.bridgeapi.io'
            : 'https://api.bridgeapi.io',
    ],

    'auto_categorization' => [
        'enabled' => true,
        'confidence_threshold' => 0.75,
        'auto_convert_threshold' => 0.85
    ],

    'sync' => [
        'default_frequency_hours' => 6,
        'max_days_history' => 90,
        'batch_size' => 100
    ]
];
