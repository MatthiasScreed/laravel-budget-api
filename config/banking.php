<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bridge API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'intégration Bridge API (connexions bancaires PSD2)
    |
    */

    'bridge' => [
        // Credentials API Bridge
        'client_id' => env('BRIDGE_CLIENT_ID'),
        'client_secret' => env('BRIDGE_CLIENT_SECRET'),

        // URLs Bridge API
        'base_url' => env('BRIDGE_BASE_URL', 'https://api.bridgeapi.io'),
        'connect_url' => env('BRIDGE_CONNECT_URL', 'https://connect.bridge-api.io'),

        // ✅ CALLBACK URL - Doit être STABLE (Expose Pro recommandé)
        'callback_url' => env('BRIDGE_CALLBACK_URL', env('APP_URL').'/api/bank/callback'),

        // Version API
        'version' => env('BRIDGE_VERSION', '2025-01-15'),

        // Configuration
        'sandbox' => env('BRIDGE_SANDBOX', true),
        'max_connections' => env('BRIDGE_MAX_CONNECTIONS', 5), // Limite sandbox

        // Timeouts
        'timeout' => 30,
        'connect_timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Configuration
    |--------------------------------------------------------------------------
    */

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:5173'),
        'banking_page' => env('FRONTEND_URL', 'http://localhost:5173').'/banking',
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization Configuration
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'default_frequency_hours' => 6,
        'max_transaction_days' => 90,
        'auto_categorization_threshold' => 0.8, // 80% confiance minimum
        'batch_size' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Autres providers (pour évolution future)
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'bridge' => [
            'enabled' => true,
            'name' => 'Bridge',
            'countries' => ['FR', 'ES', 'IT', 'PT'],
        ],
        'budget_insight' => [
            'enabled' => false,
            'name' => 'Budget Insight',
        ],
    ],
];
