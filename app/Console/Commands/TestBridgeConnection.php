<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestBridgeConnection extends Command
{
    protected $signature = 'bridge:test';

    protected $description = 'Tester la connexion à Bridge API v3 (2025-01-15)';

    public function handle()
    {
        $this->info('🔍 DIAGNOSTIC BRIDGE API v3 (2025-01-15)');
        $this->info('==========================================');
        $this->newLine();

        // 1. Vérifier la configuration
        $this->info('1. Configuration :');
        if (! $this->checkConfig()) {
            return 1;
        }
        $this->newLine();

        // 2. Tester la connexion
        $this->info('2. Test connexion API :');
        $this->testApiConnection();
        $this->newLine();

        // 3. Lister les providers (pas banks !)
        $this->info('3. Liste des providers :');
        $this->listProviders();
        $this->newLine();

        // 4. Tester le flow OAuth complet
        $this->info('4. Test flow OAuth v3 :');
        $this->testOAuthFlow();
    }

    private function checkConfig(): bool
    {
        $clientId = config('banking.bridge.client_id');
        $clientSecret = config('banking.bridge.client_secret');
        $baseUrl = config('banking.bridge.base_url');

        $this->line('   Client ID: '.($clientId ? '✅ Présent' : '❌ MANQUANT'));
        $this->line('   Client Secret: '.($clientSecret ? '✅ Présent' : '❌ MANQUANT'));
        $this->line("   Base URL: {$baseUrl}");
        $this->line('   Version: 2025-01-15 (v3)');

        if (! $clientId || ! $clientSecret) {
            $this->error('❌ Configuration incomplète !');
            $this->warn('Configure BRIDGE_CLIENT_ID et BRIDGE_CLIENT_SECRET dans .env');

            return false;
        }

        return true;
    }

    private function testApiConnection(): void
    {
        try {
            // ✅ ENDPOINT v3 CORRECT : /v3/providers
            $response = Http::withHeaders([
                'Client-Id' => config('banking.bridge.client_id'),
                'Client-Secret' => config('banking.bridge.client_secret'),
                'Bridge-Version' => '2025-01-15',
            ])->timeout(10)->get(config('banking.bridge.base_url').'/v3/providers', [
                'limit' => 1,
            ]);

            if ($response->successful()) {
                $this->line('   ✅ Connexion Bridge API v3 réussie');
                $this->line('   ✅ HTTP '.$response->status());
            } elseif ($response->status() === 401) {
                $this->error('   ❌ ERREUR 401: Credentials invalides');
                $this->warn('   Vérifie BRIDGE_CLIENT_ID et BRIDGE_CLIENT_SECRET');
            } elseif ($response->status() === 403) {
                $this->error('   ❌ ERREUR 403: Accès refusé');
                $this->warn('   Ton compte sandbox n\'a peut-être pas accès à la v3');
            } else {
                $this->error('   ❌ ERREUR HTTP '.$response->status());
                $this->line('   Réponse: '.substr($response->body(), 0, 200));
            }

        } catch (\Exception $e) {
            $this->error('   ❌ Exception: '.$e->getMessage());
        }
    }

    private function listProviders(): void
    {
        try {
            // ✅ ENDPOINT v3 CORRECT : /v3/providers
            $response = Http::withHeaders([
                'Client-Id' => config('banking.bridge.client_id'),
                'Client-Secret' => config('banking.bridge.client_secret'),
                'Bridge-Version' => '2025-01-15',
            ])->get(config('banking.bridge.base_url').'/v3/providers', [
                'country' => 'FR',
                'limit' => 5,
            ]);

            if ($response->successful()) {
                $providers = $response->json()['resources'] ?? [];

                $this->line('   ✅ '.count($providers).' providers disponibles');

                if (count($providers) > 0) {
                    $this->newLine();
                    $this->line('   Exemples :');
                    foreach (array_slice($providers, 0, 5) as $provider) {
                        $this->line('   - '.($provider['name'] ?? 'N/A').' (ID: '.($provider['id'] ?? 'N/A').')');
                    }
                }
            } else {
                $this->error('   ❌ Impossible de lister les providers');
                $this->line('   Status: '.$response->status());
                $this->line('   Body: '.substr($response->body(), 0, 200));
            }

        } catch (\Exception $e) {
            $this->error('   ❌ Exception: '.$e->getMessage());
        }
    }

    private function testOAuthFlow(): void
    {
        try {
            $this->line('   → Test création user Bridge...');

            // ÉTAPE 1 : Créer un user Bridge
            $this->line('   → Tentative 1 : Sans body...');

            // ✅ TENTATIVE 1 : Sans body du tout
            $userResponse = Http::withHeaders([
                'Client-Id' => config('banking.bridge.client_id'),
                'Client-Secret' => config('banking.bridge.client_secret'),
                'Bridge-Version' => '2025-01-15',
                'Accept' => 'application/json',
            ])->post(config('banking.bridge.base_url').'/v3/aggregation/users');

            if (! $userResponse->successful()) {
                $this->error('   ❌ Échec création user Bridge (tentative 1)');
                $this->line('   Status: '.$userResponse->status());
                $this->line('   Body: '.$userResponse->body());

                $this->line('   → Tentative 2 : Avec external_user_id...');

                $userResponse = Http::withHeaders([
                    'Client-Id' => config('banking.bridge.client_id'),
                    'Client-Secret' => config('banking.bridge.client_secret'),
                    'Bridge-Version' => '2025-01-15',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post(config('banking.bridge.base_url').'/v3/aggregation/users', [
                    'external_user_id' => 'test_'.time(),
                ]);

                if (! $userResponse->successful()) {
                    $this->error('   ❌ Échec création user Bridge (tentative 2)');
                    $this->line('   Status: '.$userResponse->status());
                    $this->line('   Body: '.$userResponse->body());

                    // Afficher les détails de débogage
                    $this->newLine();
                    $this->warn('   ⚠️  Informations de débogage :');
                    $this->line('   Client ID: '.substr(config('banking.bridge.client_id'), 0, 20).'...');
                    $this->line('   Base URL: '.config('banking.bridge.base_url'));
                    $this->line('   Version: 2025-01-15');
                    $this->newLine();
                    $this->warn('   💡 Vérifie que :');
                    $this->line('   1. Ton compte sandbox a accès à la v3');
                    $this->line('   2. Tes credentials sont valides');
                    $this->line('   3. L\'endpoint /v3/aggregation/users existe bien');

                    return;
                }
            }

            if (! $userResponse->successful()) {
                $this->error('   ❌ Échec création user Bridge');
                $this->line('   Status: '.$userResponse->status());
                $this->line('   Body: '.$userResponse->body());

                return;
            }

            $userData = $userResponse->json();
            $userUuid = $userData['uuid'] ?? null;

            if (! $userUuid) {
                $this->error('   ❌ UUID manquant dans la réponse');

                return;
            }

            $this->line('   ✅ User Bridge créé : '.substr($userUuid, 0, 8).'...');

            // ÉTAPE 2 : Obtenir un access_token
            $this->line('   → Test obtention access_token...');

            $tokenResponse = Http::withHeaders([
                'Client-Id' => config('banking.bridge.client_id'),
                'Client-Secret' => config('banking.bridge.client_secret'),
                'Bridge-Version' => '2025-01-15',
                'Content-Type' => 'application/json',
            ])->post(config('banking.bridge.base_url').'/v3/aggregation/authorization/token', [
                'user_uuid' => $userUuid,
            ]);

            if (! $tokenResponse->successful()) {
                $this->error('   ❌ Échec obtention token');
                $this->line('   Status: '.$tokenResponse->status());
                $this->line('   Body: '.$tokenResponse->body());

                return;
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (! $accessToken) {
                $this->error('   ❌ Access token manquant');

                return;
            }

            $this->line('   ✅ Access token obtenu : '.substr($accessToken, 0, 20).'...');

            // ÉTAPE 3 : Créer une session Connect
            $this->line('   → Test création session Connect...');

            $sessionResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Bridge-Version' => '2025-01-15',
                'Content-Type' => 'application/json',
            ])->post(config('banking.bridge.base_url').'/v3/aggregation/connect-sessions', [
                'user_email' => 'test@coinquest.local',
                'callback_url' => config('app.url').'/banking/callback',
            ]);

            if (! $sessionResponse->successful()) {
                $this->error('   ❌ Échec création session Connect');
                $this->line('   Status: '.$sessionResponse->status());
                $this->line('   Body: '.$sessionResponse->body());

                return;
            }

            $sessionData = $sessionResponse->json();
            $connectUrl = $sessionData['url'] ?? null;

            if ($connectUrl) {
                $this->line('   ✅ Session Connect créée !');
                $this->line('   ✅ URL: '.substr($connectUrl, 0, 50).'...');
                $this->newLine();
                $this->info('   🎉 FLOW OAuth v3 complet fonctionne !');
            } else {
                $this->error('   ❌ URL de connexion manquante');
            }

        } catch (\Exception $e) {
            $this->error('   ❌ Exception: '.$e->getMessage());
        }
    }
}
