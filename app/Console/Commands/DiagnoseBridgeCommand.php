<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Commande de diagnostic Bridge API
 *
 * Usage: php artisan bridge:diagnose
 */
class DiagnoseBridgeCommand extends Command
{
    protected $signature = 'bridge:diagnose';

    protected $description = '🔍 Diagnostic complet de la configuration Bridge API';

    private array $errors = [];

    private array $warnings = [];

    private array $success = [];

    public function handle(): int
    {
        $this->info('🔍 Diagnostic Bridge API - CoinQuest');
        $this->newLine();

        // Tests
        $this->checkEnvironmentVariables();
        $this->checkConfigFiles();
        $this->checkDatabaseTables();
        $this->checkRoutes();
        $this->checkMiddleware();
        $this->checkBridgeApiConnection();
        $this->checkExposeConnection();

        // Résultats
        $this->displayResults();

        return $this->errors ? self::FAILURE : self::SUCCESS;
    }

    private function checkEnvironmentVariables(): void
    {
        $this->info('📋 Vérification variables d\'environnement...');

        $required = [
            'BRIDGE_CLIENT_ID' => env('BRIDGE_CLIENT_ID'),
            'BRIDGE_CLIENT_SECRET' => env('BRIDGE_CLIENT_SECRET'),
            'BRIDGE_CALLBACK_URL' => env('BRIDGE_CALLBACK_URL'),
            'FRONTEND_URL' => env('FRONTEND_URL'),
        ];

        foreach ($required as $key => $value) {
            if (empty($value)) {
                $this->errors[] = "❌ {$key} manquant dans .env";
            } else {
                $this->success[] = "✅ {$key} configuré";
            }
        }

        // Vérifier format callback URL
        $callbackUrl = env('BRIDGE_CALLBACK_URL');
        if ($callbackUrl && ! str_starts_with($callbackUrl, 'https://')) {
            $this->warnings[] = '⚠️  BRIDGE_CALLBACK_URL devrait commencer par https://';
        }

        $this->newLine();
    }

    private function checkConfigFiles(): void
    {
        $this->info('⚙️  Vérification fichiers de configuration...');

        $files = [
            'config/banking.php' => base_path('config/banking.php'),
            'config/cors.php' => base_path('config/cors.php'),
        ];

        foreach ($files as $name => $path) {
            if (file_exists($path)) {
                $this->success[] = "✅ {$name} existe";

                // Vérifier config banking
                if ($name === 'config/banking.php') {
                    $config = config('banking.bridge');
                    if (empty($config)) {
                        $this->errors[] = '❌ config/banking.php mal configuré';
                    }
                }
            } else {
                $this->errors[] = "❌ {$name} manquant";
            }
        }

        $this->newLine();
    }

    private function checkDatabaseTables(): void
    {
        $this->info('🗄️  Vérification tables de base de données...');

        $tables = [
            'bank_connections',
            'bank_transactions',
            'users',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->success[] = "✅ Table {$table} existe ({$count} enregistrements)";
            } else {
                $this->errors[] = "❌ Table {$table} manquante - Exécuter php artisan migrate";
            }
        }

        $this->newLine();
    }

    private function checkRoutes(): void
    {
        $this->info('🛣️  Vérification routes API...');

        $routes = [
            'api/bank/initiate' => 'POST',
            'api/bank/callback' => 'GET',
            'api/bank/connections' => 'GET',
        ];

        $allRoutes = collect(\Route::getRoutes())->map(function ($route) {
            return [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
            ];
        });

        foreach ($routes as $uri => $method) {
            $found = $allRoutes->first(function ($route) use ($uri, $method) {
                return str_contains($route['uri'], $uri) && in_array($method, $route['methods']);
            });

            if ($found) {
                $this->success[] = "✅ Route {$method} {$uri} enregistrée";
            } else {
                $this->errors[] = "❌ Route {$method} {$uri} manquante";
            }
        }

        $this->newLine();
    }

    private function checkMiddleware(): void
    {
        $this->info('🛡️  Vérification middleware...');

        $middlewareFile = app_path('Http/Middleware/HandleCors.php');

        if (file_exists($middlewareFile)) {
            $this->success[] = '✅ HandleCors middleware existe';

            // Vérifier dans bootstrap/app.php
            $appFile = base_path('bootstrap/app.php');
            if (file_exists($appFile)) {
                $content = file_get_contents($appFile);
                if (strpos($content, 'HandleCors') !== false) {
                    $this->success[] = '✅ HandleCors référencé dans bootstrap/app.php';
                } else {
                    $this->warnings[] = '⚠️  HandleCors non trouvé dans bootstrap/app.php';
                }
            }
        } else {
            $this->errors[] = '❌ HandleCors middleware manquant';
        }

        $this->newLine();
    }

    private function checkBridgeApiConnection(): void
    {
        $this->info('🌐 Test connexion Bridge API...');

        $baseUrl = config('banking.bridge.base_url', 'https://api.bridgeapi.io');

        try {
            $response = Http::timeout(5)->get($baseUrl.'/v2/banks');

            if ($response->successful()) {
                $banks = $response->json()['resources'] ?? [];
                $this->success[] = '✅ Bridge API accessible ('.count($banks).' banques disponibles)';
            } else {
                $this->warnings[] = '⚠️  Bridge API réponse non OK : '.$response->status();
            }
        } catch (\Exception $e) {
            $this->errors[] = '❌ Impossible de contacter Bridge API : '.$e->getMessage();
        }

        $this->newLine();
    }

    private function checkExposeConnection(): void
    {
        $this->info('📡 Vérification Expose...');

        $callbackUrl = env('BRIDGE_CALLBACK_URL');

        if (! $callbackUrl) {
            $this->errors[] = '❌ BRIDGE_CALLBACK_URL non configuré';

            return;
        }

        // Vérifier si c'est une URL Expose
        if (str_contains($callbackUrl, 'sharedwithexpose.com')) {
            try {
                $response = Http::timeout(5)->get($callbackUrl);

                if ($response->successful() || $response->status() === 404) {
                    $this->success[] = "✅ Expose accessible : {$callbackUrl}";
                } else {
                    $this->errors[] = '❌ Expose non accessible : '.$response->status();
                }
            } catch (\Exception $e) {
                $this->errors[] = '❌ Expose non joignable : '.$e->getMessage();
                $this->warnings[] = "⚠️  Vérifier que 'expose share' est actif";
            }
        } else {
            $this->warnings[] = "⚠️  Callback URL n'utilise pas Expose (dev local uniquement)";
        }

        $this->newLine();
    }

    private function displayResults(): void
    {
        $this->newLine(2);
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 RÉSUMÉ DU DIAGNOSTIC');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Succès
        if ($this->success) {
            $this->info('✅ SUCCÈS ('.count($this->success).')');
            foreach ($this->success as $msg) {
                $this->line('   '.$msg);
            }
            $this->newLine();
        }

        // Avertissements
        if ($this->warnings) {
            $this->warn('⚠️  AVERTISSEMENTS ('.count($this->warnings).')');
            foreach ($this->warnings as $msg) {
                $this->line('   '.$msg);
            }
            $this->newLine();
        }

        // Erreurs
        if ($this->errors) {
            $this->error('❌ ERREURS ('.count($this->errors).')');
            foreach ($this->errors as $msg) {
                $this->line('   '.$msg);
            }
            $this->newLine();
        }

        // Conclusion
        if ($this->errors) {
            $this->error('❌ Configuration incomplète - Corriger les erreurs ci-dessus');
            $this->newLine();
            $this->info('📚 Consulter le guide : php artisan bridge:setup');
        } else {
            $this->info('✅ Configuration Bridge API complète !');
            $this->newLine();
            $this->info('🚀 Prêt à tester la connexion bancaire');
        }

        $this->newLine();
    }
}
