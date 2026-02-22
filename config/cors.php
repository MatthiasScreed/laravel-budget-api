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
        'http://localhost:3000',    // ✅ Vite dev (port actuel)
        'http://localhost:5173',    // ✅ Vite dev (port alternatif)
        'http://127.0.0.1:3000',   // ✅ Variante
        'http://127.0.0.1:5173',   // ✅ Variante
        'https://vuejs-budget-frontend-uyz0rvnb.on-forge.com', // Production
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
