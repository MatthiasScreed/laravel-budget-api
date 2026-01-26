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

    'allowed_origins' => ['*'], // ⚠️ Pour le dev, ensuite restreindre

    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.sharedwithexpose\.com$/',  // ✅ Pattern pour Expose
        '/^https:\/\/coinquest.*\.sharedwithexpose\.com$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Authorization',
        'Content-Type',
    ],

    'max_age' => 0,

    'supports_credentials' => true,  // ✅ Important pour Sanctum

];
