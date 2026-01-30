<?php

namespace App\Services;

use App\Jobs\AutoCategorizeTransactions;
use App\Jobs\ImportBridgeTransactions;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ✅ Bridge API v3 2025-01-15 - VERSION CORRIGÉE
 * FIX: Gestion des utilisateurs Bridge existants
 */
class BankIntegrationService
{
    private GamingService $gamingService;
    private BudgetService $budgetService;
    private string $baseUrl;
    private string $version;
    private string $clientId;
    private string $clientSecret;
    protected int $chunkSize = 100;

    public function __construct(
        GamingService $gamingService,
        BudgetService $budgetService
    ) {
        $this->gamingService = $gamingService;
        $this->budgetService = $budgetService;

        $this->baseUrl = config('services.bridge.base_url', 'https://api.bridgeapi.io');
        $this->version = config('services.bridge.version', '2025-01-15');
        $this->clientId = config('services.bridge.client_id');
        $this->clientSecret = config('services.bridge.client_secret');
    }

    // ==========================================
    // ✅ AUTHENTIFICATION BRIDGE API - CORRIGÉE
    // ==========================================

    /**
     * ✅ ÉTAPE 1 : Créer OU récupérer un utilisateur Bridge
     * FIX: Gère le cas "already_exists"
     */
    public function createBridgeUser(User $user): array
    {
        $this->verifyBridgeConfig();

        // 1️⃣ Si l'utilisateur a déjà un bridge_user_uuid, le retourner
        if ($user->bridge_user_uuid) {
            Log::info('✅ Utilisateur Bridge déjà existant (from DB)', [
                'user_id' => $user->id,
                'bridge_uuid' => $user->bridge_user_uuid,
            ]);

            // Vérifier que l'UUID existe toujours chez Bridge
            $existingUser = $this->getBridgeUser($user->bridge_user_uuid);

            if ($existingUser) {
                return $existingUser;
            }

            // Si l'UUID n'existe plus chez Bridge, on va en créer un nouveau
            Log::warning('⚠️ UUID Bridge stocké mais introuvable, recréation...', [
                'user_id' => $user->id,
            ]);
            $user->update(['bridge_user_uuid' => null]);
        }

        $externalUserId = (string) $user->id;

        Log::info('🔡 Création utilisateur Bridge', [
            'user_id' => $user->id,
            'external_user_id' => $externalUserId,
        ]);

        // 2️⃣ Essayer de créer l'utilisateur
        $response = Http::withHeaders($this->getBaseHeaders())
            ->post("{$this->baseUrl}/v3/aggregation/users", [
                'external_user_id' => $externalUserId,
            ]);

        // 3️⃣ Si succès, sauvegarder et retourner
        if ($response->successful()) {
            $data = $response->json();

            $user->update([
                'bridge_user_uuid' => $data['uuid'],
            ]);

            Log::info('✅ Utilisateur Bridge créé', [
                'bridge_uuid' => $data['uuid'],
                'external_user_id' => $data['external_user_id'],
            ]);

            return $data;
        }

        // 4️⃣ Si erreur "already_exists", récupérer l'utilisateur existant
        $error = $response->json();

        if (isset($error['errors'][0]['code']) &&
            $error['errors'][0]['code'] === 'users.creation.already_exists_with_external_user_id') {

            Log::info('ℹ️ Utilisateur Bridge existe déjà, récupération...', [
                'external_user_id' => $externalUserId,
            ]);

            return $this->findBridgeUserByExternalId($externalUserId, $user);
        }

        // 5️⃣ Autre erreur
        Log::error('❌ Erreur création utilisateur Bridge', [
            'status' => $response->status(),
            'error' => $error,
        ]);

        throw new \Exception('Failed to create Bridge user: ' . ($error['message'] ?? $response->body()));
    }

    /**
     * ✅ NOUVEAU : Récupérer un utilisateur Bridge par UUID
     */
    private function getBridgeUser(string $bridgeUuid): ?array
    {
        try {
            Log::info('🔍 Vérification utilisateur Bridge', [
                'bridge_uuid' => $bridgeUuid,
            ]);

            $response = Http::withHeaders($this->getBaseHeaders())
                ->get("{$this->baseUrl}/v3/aggregation/users/{$bridgeUuid}");

            if ($response->successful()) {
                Log::info('✅ Utilisateur Bridge trouvé');
                return $response->json();
            }

            Log::warning('⚠️ Utilisateur Bridge introuvable', [
                'status' => $response->status(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('❌ Erreur vérification utilisateur Bridge', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * ✅ NOUVEAU : Trouver un utilisateur Bridge par external_user_id
     */
    private function findBridgeUserByExternalId(string $externalUserId, User $user): array
    {
        try {
            Log::info('🔎 Recherche utilisateur Bridge par external_id', [
                'external_user_id' => $externalUserId,
            ]);

            // Liste tous les utilisateurs Bridge
            $response = Http::withHeaders($this->getBaseHeaders())
                ->get("{$this->baseUrl}/v3/aggregation/users");

            if (!$response->successful()) {
                throw new \Exception('Failed to list Bridge users: ' . $response->body());
            }

            $users = $response->json()['resources'] ?? [];

            // Chercher notre utilisateur par external_user_id
            foreach ($users as $bridgeUser) {
                if (isset($bridgeUser['external_user_id']) &&
                    $bridgeUser['external_user_id'] === $externalUserId) {

                    $bridgeUuid = $bridgeUser['uuid'];

                    Log::info('✅ Utilisateur Bridge trouvé par external_id', [
                        'bridge_uuid' => $bridgeUuid,
                        'external_user_id' => $externalUserId,
                    ]);

                    // Sauvegarder dans la DB Laravel
                    $user->update([
                        'bridge_user_uuid' => $bridgeUuid,
                    ]);

                    return $bridgeUser;
                }
            }

            // Si vraiment introuvable (cas très rare)
            throw new \Exception("Bridge user with external_id '{$externalUserId}' not found in list");

        } catch (\Exception $e) {
            Log::error('❌ Erreur recherche utilisateur Bridge', [
                'external_user_id' => $externalUserId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * ✅ ÉTAPE 2 : Obtenir un access token (Bearer)
     * Token valide 2h - Mise en cache avec marge de sécurité de 5min
     */
    public function getAccessToken(User $user): string
    {
        $cacheKey = "bridge_token_{$user->id}";

        // Vérifier cache (avec marge de 5 minutes)
        $cached = Cache::get($cacheKey);
        if ($cached && Carbon::parse($cached['expires_at'])->subMinutes(5)->isFuture()) {
            Log::info('🔄 Token Bridge en cache', [
                'user_id' => $user->id,
                'expires_at' => $cached['expires_at'],
            ]);

            return $cached['access_token'];
        }

        // S'assurer que l'utilisateur Bridge existe
        if (!$user->bridge_user_uuid) {
            Log::info('⚠️ Utilisateur Bridge manquant, création...', [
                'user_id' => $user->id,
            ]);
            $this->createBridgeUser($user);
            $user->refresh();
        }

        Log::info('🔡 Obtention token Bridge', [
            'user_id' => $user->id,
            'bridge_uuid' => $user->bridge_user_uuid,
        ]);

        $response = Http::withHeaders($this->getBaseHeaders())
            ->post("{$this->baseUrl}/v3/aggregation/authorization/token", [
                'user_uuid' => $user->bridge_user_uuid,
            ]);

        if (!$response->successful()) {
            // Si utilisateur introuvable sur Bridge, le recréer
            if ($response->status() === 404) {
                Log::warning('⚠️ Utilisateur Bridge introuvable, recréation...', [
                    'user_id' => $user->id,
                ]);
                $user->update(['bridge_user_uuid' => null]);
                $this->createBridgeUser($user);

                return $this->getAccessToken($user);
            }

            $error = $response->json();
            Log::error('❌ Erreur obtention token', [
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new \Exception('Failed to get Bridge access token: ' . ($error['message'] ?? $response->body()));
        }

        $data = $response->json();

        // Mettre en cache avec TTL
        $expiresAt = Carbon::parse($data['expires_at']);
        $ttlSeconds = $expiresAt->diffInSeconds(now());

        Cache::put($cacheKey, [
            'access_token' => $data['access_token'],
            'expires_at' => $data['expires_at'],
        ], $ttlSeconds);

        Log::info('✅ Token Bridge obtenu', [
            'user_id' => $user->id,
            'expires_at' => $data['expires_at'],
        ]);

        return $data['access_token'];
    }

    /**
     * ✅ ÉTAPE 3 : Créer une Connect Session avec Bearer token
     */
    public function createConnectSession(User $user, array $options = []): array
    {
        $accessToken = $this->getAccessToken($user);

        // ✅ user_email est OBLIGATOIRE
        $body = [
            'user_email' => $user->email,
        ];

        // ✅ callback_url : OPTIONNEL mais doit être whitelisté dans Bridge Dashboard
        if (!empty($options['callback_url'])) {
            $body['callback_url'] = $options['callback_url'];

            Log::info('⚠️ callback_url fourni, assurez-vous qu\'il est whitelisté dans Bridge Dashboard', [
                'callback_url' => $options['callback_url'],
            ]);
        }

        // Optionnel : account_types ('payment' ou 'all')
        if (isset($options['account_types'])) {
            $body['account_types'] = $options['account_types'];
        }

        // Optionnel : item_id (pour reconnecter un item existant)
        if (isset($options['item_id'])) {
            $body['item_id'] = $options['item_id'];
        }

        // Optionnel : provider_id (pré-sélectionner une banque)
        if (isset($options['provider_id'])) {
            $body['provider_id'] = (int) $options['provider_id'];
        }

        Log::info('🔡 Création Connect Session', [
            'user_id' => $user->id,
            'body' => $body,
        ]);

        $response = Http::withHeaders($this->getAuthenticatedHeaders($accessToken))
            ->post("{$this->baseUrl}/v3/aggregation/connect-sessions", $body);

        if (!$response->successful()) {
            // Gestion expiration token (401)
            if ($response->status() === 401) {
                Log::warning('⚠️ Token expiré, refresh...', ['user_id' => $user->id]);
                Cache::forget("bridge_token_{$user->id}");
                $accessToken = $this->getAccessToken($user);

                // Retry une fois
                $response = Http::withHeaders($this->getAuthenticatedHeaders($accessToken))
                    ->post("{$this->baseUrl}/v3/aggregation/connect-sessions", $body);
            }

            if (!$response->successful()) {
                $error = $response->json();

                // Message spécifique pour callback_url_not_whitelisted
                if (isset($error['errors'][0]['code']) &&
                    $error['errors'][0]['code'] === 'connect_session.callback_url_not_whitelisted') {

                    Log::error('❌ callback_url non whitelisté dans Bridge Dashboard', [
                        'callback_url' => $options['callback_url'] ?? 'none',
                    ]);

                    throw new \Exception('callback_url not whitelisted in Bridge Dashboard. Please add your domain in Dashboard > Connect > Allowed domains');
                }

                Log::error('❌ Erreur Connect Session', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                throw new \Exception('Failed to create connect session: ' . ($error['message'] ?? $response->body()));
            }
        }

        $data = $response->json();

        Log::info('✅ Connect Session créée', [
            'session_id' => $data['id'] ?? null,
            'url' => $data['url'] ?? null,
        ]);

        return $data;
    }

    // ==========================================
    // GESTION DES CONNEXIONS BANCAIRES
    // ==========================================

    /**
     * ✅ Initier connexion bancaire
     */
    public function initiateBankConnection(User $user, array $data): array
    {
        try {
            $this->verifyBridgeConfig();

            $options = [
                'account_types' => $data['account_types'] ?? 'payment',
                'provider_id' => $data['provider_id'] ?? null,
            ];

            // ✅ callback_url : seulement si explicitement fourni
            if (!empty($data['return_url'])) {
                $options['callback_url'] = $data['return_url'];
            }

            Log::info('🔗 Initialisation connexion bancaire', [
                'user_id' => $user->id,
                'options' => $options,
            ]);

            $session = $this->createConnectSession($user, $options);

            return [
                'success' => true,
                'connect_url' => $session['url'],
                'session_id' => $session['id'] ?? null,
                'expires_at' => now()->addMinutes(30)->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('❌ Erreur initiation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Récupérer les items (connexions bancaires)
     */
    public function getItems(User $user): array
    {
        $accessToken = $this->getAccessToken($user);

        $response = Http::withHeaders($this->getAuthenticatedHeaders($accessToken))
            ->get("{$this->baseUrl}/v3/aggregation/items");

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch items: ' . $response->body());
        }

        return $response->json()['resources'] ?? [];
    }

    /**
     * ✅ Récupérer les comptes bancaires
     */
    public function getAccounts(User $user): array
    {
        $accessToken = $this->getAccessToken($user);

        $response = Http::withHeaders($this->getAuthenticatedHeaders($accessToken))
            ->get("{$this->baseUrl}/v3/aggregation/accounts");

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch accounts: ' . $response->body());
        }

        return $response->json()['resources'] ?? [];
    }

    /**
     * ✅ Récupérer les transactions
     */
    public function getTransactions(User $user, array $filters = []): array
    {
        $accessToken = $this->getAccessToken($user);

        $response = Http::withHeaders($this->getAuthenticatedHeaders($accessToken))
            ->get("{$this->baseUrl}/v3/aggregation/transactions", $filters);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch transactions: ' . $response->body());
        }

        return $response->json()['resources'] ?? [];
    }

    // ==========================================
    // BATCH PROCESSING
    // ==========================================

    public function syncTransactions(User $user): Batch
    {
        Log::info('🚀 Démarrage sync transactions', [
            'user_id' => $user->id,
        ]);

        $accounts = $this->getAccountsFromBridge($user);

        if (empty($accounts)) {
            Log::warning('⚠️ Aucun compte trouvé', ['user_id' => $user->id]);
            throw new \Exception('Aucun compte bancaire trouvé');
        }

        $jobs = [];
        $totalTransactions = 0;

        foreach ($accounts as $account) {
            $transactions = $this->getTransactionsFromBridge($user, $account['id']);

            if (empty($transactions)) {
                continue;
            }

            $totalTransactions += count($transactions);
            $chunks = collect($transactions)->chunk($this->chunkSize);

            foreach ($chunks as $chunk) {
                $jobs[] = new ImportBridgeTransactions(
                    userId: $user->id,
                    accountId: $account['id'],
                    transactionsBatch: $chunk->toArray()
                );
            }
        }

        if (empty($jobs)) {
            throw new \Exception('Aucune transaction à importer');
        }

        return Bus::batch($jobs)
            ->then(function (Batch $batch) use ($user) {
                AutoCategorizeTransactions::dispatch($user->id)
                    ->onQueue('categorization');
            })
            ->name("Import Bridge - User {$user->id}")
            ->onQueue('imports')
            ->allowFailures()
            ->dispatch();
    }

    protected function getAccountsFromBridge(User $user): array
    {
        try {
            return $this->getAccounts($user);
        } catch (\Exception $e) {
            Log::error('❌ Erreur récupération comptes', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function getTransactionsFromBridge(User $user, int $accountId): array
    {
        try {
            $filters = [
                'account_ids' => [$accountId],
                'since' => now()->subDays(90)->toISOString(),
                'limit' => 500,
            ];

            return $this->getTransactions($user, $filters);
        } catch (\Exception $e) {
            Log::error('❌ Erreur récupération transactions', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getBatchStatus(string $batchId): array
    {
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return ['status' => 'not_found'];
        }

        return [
            'status' => $this->getBatchStatusLabel($batch),
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'processed_jobs' => $batch->processedJobs(),
            'failed_jobs' => $batch->failedJobs,
            'progress' => $batch->progress(),
        ];
    }

    protected function getBatchStatusLabel(Batch $batch): string
    {
        if ($batch->cancelled()) return 'cancelled';
        if ($batch->finished()) return 'completed';
        if ($batch->failedJobs > 0) return 'partial_failure';
        return 'processing';
    }

    public function getUserConnectionsStatus(User $user): array
    {
        return $user->bankConnections()
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'bank_name' => $c->bank_name,
                'status' => $c->status,
                'last_sync' => $c->last_sync_at?->diffForHumans(),
            ])
            ->toArray();
    }

    public function deleteBridgeUser(User $user): bool
    {
        if (!$user->bridge_user_uuid) {
            return true;
        }

        $response = Http::withHeaders($this->getBaseHeaders())
            ->delete("{$this->baseUrl}/v3/aggregation/users/{$user->bridge_user_uuid}");

        if ($response->successful()) {
            Cache::forget("bridge_token_{$user->id}");
            $user->update(['bridge_user_uuid' => null]);
        }

        return $response->successful();
    }

    // ==========================================
    // MÉTHODES PRIVÉES
    // ==========================================

    private function verifyBridgeConfig(): void
    {
        if (empty($this->clientId)) {
            throw new \Exception('BRIDGE_CLIENT_ID manquant dans .env');
        }

        if (empty($this->clientSecret)) {
            throw new \Exception('BRIDGE_CLIENT_SECRET manquant dans .env');
        }
    }

    /**
     * ✅ Headers de base avec Basic Auth (CORRIGÉ)
     */
    private function getBaseHeaders(): array
    {
        $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");

        return [
            'Bridge-Version' => $this->version,
            'Authorization' => "Basic {$credentials}",  // ✅ CORRECT
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function getAuthenticatedHeaders(string $accessToken): array
    {
        return array_merge($this->getBaseHeaders(), [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);
    }
}
