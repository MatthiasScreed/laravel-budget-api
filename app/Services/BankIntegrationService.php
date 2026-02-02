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
 * FIX: Sauvegarde UUID avec assignation directe (bypass fillable)
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
    protected int $timeout = 30;

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
    // ✅ HELPER CRITIQUE : Sauvegarder UUID
    // ==========================================

    /**
     * ✅ Sauvegarde l'UUID Bridge de manière fiable
     * Utilise assignation directe + save() pour bypasser $fillable
     */
    private function saveBridgeUuid(User $user, ?string $uuid): void
    {
        // Assignation directe (bypass fillable)
        $user->bridge_user_uuid = $uuid;
        $user->save();

        // Double vérification en DB
        $user->refresh();

        Log::info('💾 UUID Bridge sauvegardé', [
            'user_id' => $user->id,
            'uuid_saved' => $user->bridge_user_uuid,
            'uuid_expected' => $uuid,
            'match' => $user->bridge_user_uuid === $uuid,
        ]);

        if ($user->bridge_user_uuid !== $uuid) {
            throw new \Exception("UUID Bridge non sauvegardé en DB - Vérifier la colonne existe");
        }
    }

    // ==========================================
    // ✅ AUTHENTIFICATION BRIDGE API - CORRIGÉE
    // ==========================================

    /**
     * ✅ ÉTAPE 1 : Créer OU récupérer un utilisateur Bridge
     */
    public function createBridgeUser(User $user): User
    {
        $this->verifyBridgeConfig();

        // 1️⃣ Si l'utilisateur a déjà un bridge_user_uuid, vérifier qu'il existe
        if ($user->bridge_user_uuid) {
            Log::info('✅ Utilisateur Bridge existant (from DB)', [
                'user_id' => $user->id,
                'bridge_uuid' => $user->bridge_user_uuid,
            ]);

            $existingUser = $this->getBridgeUser($user->bridge_user_uuid);
            if ($existingUser) {
                return $user;
            }

            Log::warning('⚠️ UUID Bridge stocké mais introuvable, recréation...', [
                'user_id' => $user->id,
            ]);
            $this->saveBridgeUuid($user, null);
        }

        $externalUserId = (string) $user->id;

        Log::info('📡 Création utilisateur Bridge', [
            'user_id' => $user->id,
            'external_user_id' => $externalUserId,
        ]);

        // 2️⃣ Essayer de créer l'utilisateur
        $response = $this->http()->withHeaders($this->getBaseHeaders())
            ->post("{$this->baseUrl}/v3/aggregation/users", [
                'external_user_id' => $externalUserId,
            ]);

        // 3️⃣ Si succès, sauvegarder et retourner
        if ($response->successful()) {
            $data = $response->json();

            // ✅ Sauvegarde fiable
            $this->saveBridgeUuid($user, $data['uuid']);

            Log::info('✅ Utilisateur Bridge créé', [
                'bridge_uuid' => $data['uuid'],
                'external_user_id' => $data['external_user_id'],
            ]);

            return $user;
        }

        // 4️⃣ Si erreur "already_exists", récupérer l'utilisateur existant
        $error = $response->json();

        if (isset($error['errors'][0]['code']) &&
            $error['errors'][0]['code'] === 'users.creation.already_exists_with_external_user_id') {

            Log::info('ℹ️ Utilisateur Bridge existe déjà, récupération...', [
                'external_user_id' => $externalUserId,
            ]);

            $bridgeUser = $this->findBridgeUserByExternalId($externalUserId);

            // ✅ Sauvegarde fiable
            $this->saveBridgeUuid($user, $bridgeUser['uuid']);

            Log::info('✅ UUID Bridge récupéré et sauvegardé', [
                'bridge_uuid' => $bridgeUser['uuid'],
            ]);

            return $user;
        }

        // 5️⃣ Autre erreur
        Log::error('❌ Erreur création utilisateur Bridge', [
            'status' => $response->status(),
            'error' => $error,
        ]);

        throw new \Exception('Failed to create Bridge user: '.($error['message'] ?? $response->body()));
    }

    /**
     * ✅ Récupérer un utilisateur Bridge par UUID
     */
    private function getBridgeUser(string $bridgeUuid): ?array
    {
        try {
            $response = $this->http()->withHeaders($this->getBaseHeaders())
                ->get("{$this->baseUrl}/v3/aggregation/users/{$bridgeUuid}");

            if ($response->successful()) {
                return $response->json();
            }
            return null;
        } catch (\Exception $e) {
            Log::error('❌ Erreur vérification utilisateur Bridge', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ✅ Trouver un utilisateur Bridge par external_user_id
     */
    private function findBridgeUserByExternalId(string $externalUserId): array
    {
        Log::info('🔎 Recherche utilisateur Bridge par external_id', [
            'external_user_id' => $externalUserId,
        ]);

        $response = $this->http()->withHeaders($this->getBaseHeaders())
            ->get("{$this->baseUrl}/v3/aggregation/users");

        if (! $response->successful()) {
            throw new \Exception('Failed to list Bridge users: '.$response->body());
        }

        $users = $response->json()['resources'] ?? [];

        foreach ($users as $bridgeUser) {
            if (($bridgeUser['external_user_id'] ?? null) === $externalUserId) {
                Log::info('✅ Utilisateur Bridge trouvé', [
                    'bridge_uuid' => $bridgeUser['uuid'],
                ]);
                return $bridgeUser;
            }
        }

        throw new \Exception("Bridge user with external_id '{$externalUserId}' not found");
    }

    /**
     * ✅ ÉTAPE 2 : Obtenir un access token (Bearer)
     */
    public function getAccessToken(User $user): string
    {
        $cacheKey = "bridge_token_{$user->id}";

        // Vérifier cache
        $cached = Cache::get($cacheKey);
        if ($cached && Carbon::parse($cached['expires_at'])->subMinutes(5)->isFuture()) {
            return $cached['access_token'];
        }

        // S'assurer que l'utilisateur Bridge existe
        if (! $user->bridge_user_uuid) {
            Log::info('⚠️ Utilisateur Bridge manquant, création...', ['user_id' => $user->id]);
            $user = $this->createBridgeUser($user);
        }

        // ✅ Vérification explicite après création
        if (! $user->bridge_user_uuid) {
            Log::error('❌ UUID toujours manquant après création', ['user_id' => $user->id]);
            throw new \Exception('Bridge user UUID missing after creation - check database column');
        }

        Log::info('📡 Obtention token Bridge', [
            'user_id' => $user->id,
            'bridge_uuid' => $user->bridge_user_uuid,
        ]);

        $response = $this->http()->withHeaders($this->getBaseHeaders())
            ->post("{$this->baseUrl}/v3/aggregation/authorization/token", [
                'user_uuid' => $user->bridge_user_uuid,
            ]);

        if (! $response->successful()) {
            if ($response->status() === 404) {
                Log::warning('⚠️ Utilisateur Bridge introuvable, recréation...');
                $this->saveBridgeUuid($user, null);
                $this->createBridgeUser($user);
                return $this->getAccessToken($user);
            }

            $error = $response->json();
            throw new \Exception('Failed to get Bridge token: '.($error['message'] ?? $response->body()));
        }

        $data = $response->json();

        // Mettre en cache
        $expiresAt = Carbon::parse($data['expires_at']);
        Cache::put($cacheKey, [
            'access_token' => $data['access_token'],
            'expires_at' => $data['expires_at'],
        ], $expiresAt->diffInSeconds(now()));

        return $data['access_token'];
    }

    /**
     * ✅ ÉTAPE 3 : Créer une Connect Session
     */
    public function createConnectSession(User $user, array $options = []): array
    {
        $accessToken = $this->getAccessToken($user);

        $body = ['user_email' => $user->email];

        if (! empty($options['callback_url'])) {
            $body['callback_url'] = $options['callback_url'];
        }
        if (isset($options['account_types'])) {
            $body['account_types'] = $options['account_types'];
        }
        if (isset($options['item_id'])) {
            $body['item_id'] = $options['item_id'];
        }
        if (isset($options['provider_id'])) {
            $body['provider_id'] = (int) $options['provider_id'];
        }

        $response = $this->http()->withHeaders($this->getAuthenticatedHeaders($accessToken))
            ->post("{$this->baseUrl}/v3/aggregation/connect-sessions", $body);

        if (! $response->successful()) {
            if ($response->status() === 401) {
                Cache::forget("bridge_token_{$user->id}");
                $accessToken = $this->getAccessToken($user);
                $response = $this->http()->withHeaders($this->getAuthenticatedHeaders($accessToken))
                    ->post("{$this->baseUrl}/v3/aggregation/connect-sessions", $body);
            }

            if (! $response->successful()) {
                $error = $response->json();
                throw new \Exception('Failed to create connect session: '.($error['message'] ?? $response->body()));
            }
        }

        return $response->json();
    }

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

            if (! empty($data['return_url'])) {
                $options['callback_url'] = $data['return_url'];
            }

            $session = $this->createConnectSession($user, $options);

            return [
                'success' => true,
                'connect_url' => $session['url'],
                'session_id' => $session['id'] ?? null,
                'expires_at' => now()->addMinutes(30)->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('❌ Erreur initiation', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // GESTION DES CONNEXIONS BANCAIRES
    // ==========================================

    public function getItems(User $user): array
    {
        $accessToken = $this->getAccessToken($user);
        $response = $this->http()->withHeaders($this->getAuthenticatedHeaders($accessToken))
            ->get("{$this->baseUrl}/v3/aggregation/items");

        if (! $response->successful()) {
            throw new \Exception('Failed to fetch items: '.$response->body());
        }
        return $response->json()['resources'] ?? [];
    }

    public function getAccounts(User $user): array
    {
        $accessToken = $this->getAccessToken($user);
        $response = $this->http()->withHeaders($this->getAuthenticatedHeaders($accessToken))
            ->get("{$this->baseUrl}/v3/aggregation/accounts");

        if (! $response->successful()) {
            throw new \Exception('Failed to fetch accounts: '.$response->body());
        }
        return $response->json()['resources'] ?? [];
    }

    public function getTransactions(User $user, array $filters = []): array
    {
        $accessToken = $this->getAccessToken($user);
        $response = $this->http()->withHeaders($this->getAuthenticatedHeaders($accessToken))
            ->get("{$this->baseUrl}/v3/aggregation/transactions", $filters);

        if (! $response->successful()) {
            throw new \Exception('Failed to fetch transactions: '.$response->body());
        }
        return $response->json()['resources'] ?? [];
    }

    // ==========================================
    // BATCH PROCESSING
    // ==========================================

    public function syncTransactions(User $user): Batch
    {
        $accounts = $this->getAccountsFromBridge($user);
        if (empty($accounts)) {
            throw new \Exception('Aucun compte bancaire trouvé');
        }

        $jobs = [];
        foreach ($accounts as $account) {
            $transactions = $this->getTransactionsFromBridge($user, $account['id']);
            if (empty($transactions)) continue;

            foreach (collect($transactions)->chunk($this->chunkSize) as $chunk) {
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
            ->then(fn (Batch $batch) => AutoCategorizeTransactions::dispatch($user->id)->onQueue('categorization'))
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
            return [];
        }
    }

    protected function getTransactionsFromBridge(User $user, int $accountId): array
    {
        try {
            return $this->getTransactions($user, [
                'account_ids' => [$accountId],
                'since' => now()->subDays(90)->toISOString(),
                'limit' => 500,
            ]);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getBatchStatus(string $batchId): array
    {
        $batch = Bus::findBatch($batchId);
        if (! $batch) return ['status' => 'not_found'];

        return [
            'status' => $batch->cancelled() ? 'cancelled' : ($batch->finished() ? 'completed' : 'processing'),
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'processed_jobs' => $batch->processedJobs(),
            'failed_jobs' => $batch->failedJobs,
            'progress' => $batch->progress(),
        ];
    }

    public function deleteBridgeUser(User $user): bool
    {
        if (! $user->bridge_user_uuid) return true;

        $response = $this->http()->withHeaders($this->getBaseHeaders())
            ->delete("{$this->baseUrl}/v3/aggregation/users/{$user->bridge_user_uuid}");

        if ($response->successful()) {
            Cache::forget("bridge_token_{$user->id}");
            $this->saveBridgeUuid($user, null);
        }
        return $response->successful();
    }

    // ==========================================
    // MÉTHODES PRIVÉES
    // ==========================================

    private function verifyBridgeConfig(): void
    {
        if (empty($this->clientId)) throw new \Exception('BRIDGE_CLIENT_ID manquant');
        if (empty($this->clientSecret)) throw new \Exception('BRIDGE_CLIENT_SECRET manquant');
    }

    private function getBaseHeaders(): array
    {
        return [
            'Bridge-Version' => $this->version,
            'Client-Id' => $this->clientId,
            'Client-Secret' => $this->clientSecret,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function getAuthenticatedHeaders(string $accessToken): array
    {
        return array_merge($this->getBaseHeaders(), ['Authorization' => 'Bearer '.$accessToken]);
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeout);
    }
}
