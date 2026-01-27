<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Détermine quelles opérations cross-origin peuvent être effectuées dans
    | les navigateurs web. Configuration pour permettre à Vue.js (frontend)
    | de communiquer avec Laravel (backend API).
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'api/bank/*',           // ✅ Ajoute ceci
        'api/auth/*',           // ✅ Ajoute ceci
        'api/gaming/*',         // ✅ Ajoute ceci
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://vuejs-budget-frontend-uy20rvnb.on-forge.com',
        'http://localhost:5173', // Pour le dev local
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
