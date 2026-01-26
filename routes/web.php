<?php

// routes/web.php - VERSION CORRIGÉE

use App\Http\Controllers\Api\BankController;
use Illuminate\Support\Facades\Http; // ✅ AJOUT: Import manquant
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Routes web pour pages publiques, callbacks, et outils de debug
|
*/

// ==========================================
// PAGE D'ACCUEIL
// ==========================================

Route::get('/', function () {
    return response()->json([
        'app' => config('app.name', 'CoinQuest'),
        'version' => config('app.version', '1.0.0'),
        'environment' => app()->environment(),
        'timestamp' => now()->toISOString(),
        'endpoints' => [
            'api' => url('/api'),
            'health' => url('/api/health'),
            'debug' => [
                'bridge_config' => url('/debug/bridge'),
                'bridge_test' => url('/test-bridge'),
            ],
        ],
    ]);
});

// ==========================================
// 🏦 CALLBACK BRIDGE (BACKUP ROUTE)
// ==========================================

// Si Bridge appelle /bank/callback au lieu de /api/bank/callback
Route::get('/bank/callback', [BankController::class, 'callback'])
    ->name('bank.callback.web');

// ==========================================
// 🔧 ROUTES DE DEBUG (DEVELOPMENT ONLY)
// ==========================================

// Debug: Configuration Bridge
Route::get('/debug/bridge', function () {
    // Sécurité: Désactiver en production
    if (app()->environment('production')) {
        abort(403, 'Debug routes disabled in production');
    }

    return response()->json([
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment(),

        // Variables .env (sensibles masquées)
        'env_variables' => [
            'BRIDGE_CLIENT_ID' => env('BRIDGE_CLIENT_ID') ? '✅ Défini' : '❌ Manquant',
            'BRIDGE_CLIENT_SECRET' => env('BRIDGE_CLIENT_SECRET') ? '✅ Défini ('.strlen(env('BRIDGE_CLIENT_SECRET')).' chars)' : '❌ Manquant',
            'BRIDGE_CALLBACK_URL' => env('BRIDGE_CALLBACK_URL'),
            'BRIDGE_WEBHOOK_URL' => env('BRIDGE_WEBHOOK_URL'),
            'BRIDGE_WEBHOOK_SECRET' => env('BRIDGE_WEBHOOK_SECRET') ? '✅ Défini' : '❌ Manquant',
            'FRONTEND_URL' => env('FRONTEND_URL'),
            'APP_URL' => env('APP_URL'),
        ],

        // Configuration chargée
        'config_loaded' => [
            'client_id' => config('banking.bridge.client_id') ? '✅ Chargé' : '❌ Non chargé',
            'client_secret' => config('banking.bridge.client_secret') ? '✅ Chargé' : '❌ Non chargé',
            'base_url' => config('banking.bridge.base_url'),
            'connect_url' => config('banking.bridge.connect_url'),
            'callback_url' => config('banking.bridge.callback_url'),
            'version' => config('banking.bridge.version'),
            'sandbox' => config('banking.bridge.sandbox'),
        ],

        // Validation
        'validation' => [
            'config_file_exists' => file_exists(config_path('banking.php')),
            'callback_url_https' => str_starts_with(config('banking.bridge.callback_url', ''), 'https://'),
            'frontend_url_set' => ! empty(config('banking.frontend.url')),
        ],
    ]);
})->name('debug.bridge.config');

// Test complet Bridge API
Route::get('/test-bridge', function () {
    // Sécurité: Désactiver en production
    if (app()->environment('production')) {
        abort(403, 'Test routes disabled in production');
    }

    $results = [
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment(),
        'overall_status' => '⏳ En cours...',
        'tests' => [],
    ];

    // ==========================================
    // TEST 1: Variables d'environnement
    // ==========================================
    $envVars = [
        'BRIDGE_CLIENT_ID' => env('BRIDGE_CLIENT_ID'),
        'BRIDGE_CLIENT_SECRET' => env('BRIDGE_CLIENT_SECRET'),
        'BRIDGE_CALLBACK_URL' => env('BRIDGE_CALLBACK_URL'),
    ];

    $results['tests']['environment_vars'] = [
        'status' => collect($envVars)->filter()->count() === count($envVars) ? '✅ OK' : '❌ FAIL',
        'details' => [
            'bridge_client_id' => ! empty($envVars['BRIDGE_CLIENT_ID']) ? '✅ Défini' : '❌ Manquant',
            'bridge_client_secret' => ! empty($envVars['BRIDGE_CLIENT_SECRET']) ? '✅ Défini' : '❌ Manquant',
            'bridge_callback_url' => ! empty($envVars['BRIDGE_CALLBACK_URL']) ? '✅ Défini: '.$envVars['BRIDGE_CALLBACK_URL'] : '❌ Manquant',
        ],
    ];

    // ==========================================
    // TEST 2: Configuration chargée
    // ==========================================
    $config = config('banking.bridge');
    $results['tests']['config'] = [
        'status' => ! empty($config) && ! empty($config['client_id']) ? '✅ OK' : '❌ FAIL',
        'details' => [
            'file_exists' => file_exists(config_path('banking.php')) ? '✅ Existe' : '❌ Manquant',
            'loaded' => ! empty($config) ? '✅ Chargé' : '❌ Non chargé',
            'client_id' => $config['client_id'] ?? '❌ Non configuré',
            'base_url' => $config['base_url'] ?? '❌ Non configuré',
            'version' => $config['version'] ?? '❌ Non configuré',
        ],
    ];

    // ==========================================
    // TEST 3: Connectivité Bridge API
    // ==========================================
    try {
        $baseUrl = $config['base_url'] ?? 'https://api.bridgeapi.io';
        $startTime = microtime(true);

        $statusResponse = Http::timeout(10)->get($baseUrl.'/v2/status');

        $responseTime = round((microtime(true) - $startTime) * 1000);

        $results['tests']['connectivity'] = [
            'status' => $statusResponse->successful() ? '✅ OK' : '❌ FAIL',
            'details' => [
                'reachable' => $statusResponse->successful() ? '✅ Accessible' : '❌ Inaccessible',
                'status_code' => $statusResponse->status(),
                'response_time' => $responseTime.'ms',
            ],
        ];
    } catch (\Exception $e) {
        $results['tests']['connectivity'] = [
            'status' => '❌ FAIL',
            'details' => [
                'error' => $e->getMessage(),
            ],
        ];
    }

    // ==========================================
    // TEST 4: Authentification Bridge
    // ==========================================
    try {
        $clientId = $config['client_id'] ?? null;
        $clientSecret = $config['client_secret'] ?? null;
        $baseUrl = $config['base_url'] ?? 'https://api.bridgeapi.io';

        if (! $clientId || ! $clientSecret) {
            $results['tests']['authentication'] = [
                'status' => '❌ FAIL',
                'details' => [
                    'error' => 'Credentials manquants dans config',
                ],
            ];
        } else {
            $authResponse = Http::timeout(15)
                ->withHeaders([
                    'Bridge-Version' => $config['version'] ?? '2021-06-01',
                    'Client-Id' => $clientId,
                    'Client-Secret' => $clientSecret,
                ])
                ->get($baseUrl.'/v2/banks');

            $banks = $authResponse->json('resources', []);
            $isValid = $authResponse->successful();

            $results['tests']['authentication'] = [
                'status' => $isValid ? '✅ OK' : '❌ FAIL',
                'details' => [
                    'credentials_valid' => $isValid ? '✅ Valides' : '❌ Invalides',
                    'status_code' => $authResponse->status(),
                    'banks_count' => count($banks),
                    'message' => $isValid
                        ? '✅ Authentification réussie - '.count($banks).' banques disponibles'
                        : '❌ Authentification échouée - Vérifiez vos credentials Bridge',
                ],
            ];
        }
    } catch (\Exception $e) {
        $results['tests']['authentication'] = [
            'status' => '❌ FAIL',
            'details' => [
                'error' => $e->getMessage(),
            ],
        ];
    }

    // ==========================================
    // TEST 5: Database & Tables
    // ==========================================
    try {
        $tables = [
            'bank_connections' => \Schema::hasTable('bank_connections'),
            'bank_transactions' => \Schema::hasTable('bank_transactions'),
            'users' => \Schema::hasTable('users'),
        ];

        $allExist = collect($tables)->every(fn ($exists) => $exists);

        $results['tests']['database'] = [
            'status' => $allExist ? '✅ OK' : '❌ FAIL',
            'details' => collect($tables)->map(fn ($exists, $table) => $exists ? "✅ Table {$table} existe" : "❌ Table {$table} manquante"
            )->values()->toArray(),
        ];
    } catch (\Exception $e) {
        $results['tests']['database'] = [
            'status' => '❌ FAIL',
            'details' => [
                'error' => $e->getMessage(),
            ],
        ];
    }

    // ==========================================
    // STATUT GLOBAL
    // ==========================================
    $allTestsPass = collect($results['tests'])->every(fn ($test) => ($test['status'] ?? '') === '✅ OK'
    );

    $results['overall_status'] = $allTestsPass
        ? '✅ TOUS LES TESTS PASSENT - Configuration Bridge prête !'
        : '❌ CERTAINS TESTS ÉCHOUENT - Consulter les détails ci-dessus';

    // ==========================================
    // RECOMMANDATIONS
    // ==========================================
    $recommendations = [];

    if (($results['tests']['environment_vars']['status'] ?? '') !== '✅ OK') {
        $recommendations[] = '1. Configurer les variables .env (BRIDGE_CLIENT_ID, BRIDGE_CLIENT_SECRET, BRIDGE_CALLBACK_URL)';
    }

    if (($results['tests']['config']['status'] ?? '') !== '✅ OK') {
        $recommendations[] = '2. Créer config/banking.php avec les paramètres Bridge';
    }

    if (($results['tests']['authentication']['status'] ?? '') !== '✅ OK') {
        $recommendations[] = '3. Vérifier les credentials Bridge sur https://dashboard.bridgeapi.io';
    }

    if (($results['tests']['database']['status'] ?? '') !== '✅ OK') {
        $recommendations[] = '4. Exécuter les migrations: php artisan migrate';
    }

    if (! empty($recommendations)) {
        $results['recommendations'] = $recommendations;
    }

    // Retourner JSON formaté
    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
})->name('debug.bridge.test');

// ==========================================
// ROUTES UTILITAIRES
// ==========================================

// Clear cache (dev only)
Route::get('/debug/clear-cache', function () {
    if (app()->environment('production')) {
        abort(403);
    }

    \Artisan::call('config:clear');
    \Artisan::call('route:clear');
    \Artisan::call('cache:clear');

    return response()->json([
        'success' => true,
        'message' => '✅ Cache cleared',
        'timestamp' => now()->toISOString(),
    ]);
})->name('debug.clear-cache');

// Liste des routes
Route::get('/debug/routes', function () {
    if (app()->environment('production')) {
        abort(403);
    }

    $routes = collect(\Route::getRoutes())->map(function ($route) {
        return [
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
        ];
    })->filter(function ($route) {
        // Filtrer uniquement les routes banking
        return str_contains($route['uri'], 'bank') || str_contains($route['uri'], 'bridge');
    })->values();

    return response()->json([
        'total' => $routes->count(),
        'routes' => $routes,
    ], 200, [], JSON_PRETTY_PRINT);
})->name('debug.routes');
