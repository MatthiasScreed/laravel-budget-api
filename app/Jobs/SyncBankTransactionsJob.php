<?php

namespace App\Jobs;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncBankTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public BankConnection $bankConnection;

    public $tries = 3;

    public $timeout = 120;

    public function __construct(BankConnection $bankConnection)
    {
        $this->bankConnection = $bankConnection;
    }

    /**
     * ⚡ Exécution principale du job
     */
    public function handle(): void
    {
        try {
            // ✅ CRITIQUE : Rafraîchir depuis BDD pour avoir les dernières données
            $this->bankConnection->refresh();

            Log::info('🔄 Sync démarrée', [
                'connection_id' => $this->bankConnection->id,
                'provider_connection_id' => $this->bankConnection->provider_connection_id,
                'bank' => $this->bankConnection->bank_name,
            ]);

            // ✅ VALIDATION : Vérifier que provider_connection_id existe
            if (empty($this->bankConnection->provider_connection_id)) {
                throw new \Exception(
                    'provider_connection_id manquant pour connection #'.
                    $this->bankConnection->id
                );
            }

            // 1. Obtenir token Bridge
            $token = $this->getBridgeToken();

            // 2. ✅ NOUVEAU : Synchroniser les comptes bancaires D'ABORD
            $this->syncBankAccounts($token);

            // 3. Récupérer les comptes
            $accounts = $this->fetchAccounts($token);

            // 4. Synchroniser les transactions
            $imported = 0;
            foreach ($accounts as $account) {
                $imported += $this->syncAccountTransactions($token, $account);
            }

            // 5. Marquer succès
            $this->bankConnection->markSyncSuccess();

            Log::info('✅ Sync terminée', [
                'connection_id' => $this->bankConnection->id,
                'provider_connection_id' => $this->bankConnection->provider_connection_id,
                'imported' => $imported,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur sync', [
                'connection_id' => $this->bankConnection->id,
                'provider_connection_id' => $this->bankConnection->provider_connection_id ?? 'NULL',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->bankConnection->markSyncError($e->getMessage());
            throw $e;
        }
    }

    /**
     * 🔑 Obtenir token Bridge pour l'utilisateur
     */
    private function getBridgeToken(): string
    {
        $user = $this->bankConnection->user;

        if (! $user->bridge_user_uuid) {
            throw new \Exception('Bridge user UUID manquant');
        }

        $response = Http::timeout(60)
            ->retry(2, 100)
            ->withHeaders([
                'Client-Id' => config('services.bridge.client_id'),
                'Client-Secret' => config('services.bridge.client_secret'),
                'Bridge-Version' => '2025-01-15',
                'Content-Type' => 'application/json',
            ])
            ->post(
                'https://api.bridgeapi.io/v3/aggregation/authorization/token',
                ['user_uuid' => $user->bridge_user_uuid]
            );

        if (! $response->successful()) {
            throw new \Exception('Erreur token Bridge: '.$response->body());
        }

        return $response->json()['access_token'];
    }

    /**
     * 🏦 ✅ NOUVEAU : Synchroniser les comptes bancaires depuis Bridge
     */
    private function syncBankAccounts(string $token): void
    {
        try {
            Log::info('🏦 Récupération des comptes', [
                'item_id' => $this->bankConnection->provider_connection_id,
            ]);

            // Récupérer les comptes depuis Bridge
            $response = Http::timeout(60)
                ->retry(2, 100)
                ->withHeaders([
                    'Client-Id' => config('services.bridge.client_id'),
                    'Client-Secret' => config('services.bridge.client_secret'),
                    'Bridge-Version' => '2025-01-15',
                    'Authorization' => "Bearer $token",
                ])
                ->get('https://api.bridgeapi.io/v3/aggregation/accounts', [
                    'item_id' => $this->bankConnection->provider_connection_id,
                ]);

            if (! $response->successful()) {
                Log::error('❌ Erreur récupération comptes', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $accounts = $response->json()['resources'] ?? [];

            Log::info('✅ Comptes récupérés', [
                'count' => count($accounts),
                'item_id' => $this->bankConnection->provider_connection_id,
            ]);

            // Créer/Mettre à jour chaque compte
            foreach ($accounts as $accountData) {
                BankAccount::updateOrCreate(
                    [
                        'bank_connection_id' => $this->bankConnection->id,
                        'external_id' => $accountData['id'],
                    ],
                    [
                        'account_name' => $accountData['name'] ?? 'Compte',
                        'account_type' => $this->mapAccountType($accountData['type'] ?? 'checking'),
                        'balance' => $accountData['balance'] ?? 0,
                        'currency' => $accountData['currency_code'] ?? 'EUR',
                        'iban' => $accountData['iban'] ?? null,
                        'is_active' => ($accountData['status'] ?? 'active') === 'active',
                        'last_balance_update' => now(),
                        'metadata' => $accountData,
                    ]
                );

                Log::info('✅ Compte synchronisé', [
                    'name' => $accountData['name'] ?? 'Compte',
                    'balance' => $accountData['balance'] ?? 0,
                    'type' => $accountData['type'] ?? 'checking',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ Erreur sync comptes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 🗺️ Mapper le type de compte Bridge vers notre enum
     */
    private function mapAccountType(string $bridgeType): string
    {
        return match (strtolower($bridgeType)) {
            'checking', 'current' => 'checking',
            'savings', 'livret' => 'savings',
            'credit_card', 'card' => 'credit',
            'investment', 'securities' => 'investment',
            'loan', 'mortgage' => 'loan',
            default => 'checking'
        };
    }

    /**
     * 🏦 Récupérer les comptes bancaires
     */
    private function fetchAccounts(string $token): array
    {
        $itemId = $this->bankConnection->provider_connection_id;

        if (empty($itemId)) {
            throw new \Exception(
                'provider_connection_id vide pour connection #'.
                $this->bankConnection->id
            );
        }

        Log::info('📥 Fetch accounts', [
            'item_id' => $itemId,
            'connection_id' => $this->bankConnection->id,
        ]);

        // ✅ Vérifier que l'item existe
        if (! $this->itemExists($token, $itemId)) {
            throw new \Exception(
                "L'item Bridge $itemId n'existe pas encore. ".
                "Veuillez attendre la fin de l'initialisation."
            );
        }

        // ✅ Récupérer les accounts via l'endpoint /accounts avec filtre
        $response = Http::timeout(60)
            ->retry(2, 100)
            ->withHeaders([
                'Client-Id' => config('services.bridge.client_id'),
                'Client-Secret' => config('services.bridge.client_secret'),
                'Bridge-Version' => '2025-01-15',
                'Authorization' => "Bearer $token",
            ])
            ->get('https://api.bridgeapi.io/v3/aggregation/accounts', [
                'item_id' => $itemId,
            ]);

        if (! $response->successful()) {
            throw new \Exception(
                'Erreur fetch accounts: '.$response->body()
            );
        }

        $accounts = $response->json()['resources'] ?? [];

        Log::info('✅ Accounts récupérés', [
            'count' => count($accounts),
            'item_id' => $itemId,
        ]);

        return $accounts;
    }

    /**
     * ✅ Vérifier que l'item existe chez Bridge
     */
    private function itemExists(string $token, string $itemId): bool
    {
        try {
            Log::info('🔍 Vérification existence item', [
                'item_id' => $itemId,
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Client-Id' => config('services.bridge.client_id'),
                    'Client-Secret' => config('services.bridge.client_secret'),
                    'Bridge-Version' => '2025-01-15',
                    'Authorization' => "Bearer $token",
                ])
                ->get("https://api.bridgeapi.io/v3/aggregation/items/{$itemId}");

            if ($response->successful()) {
                $itemData = $response->json();
                $status = $itemData['status'] ?? null;
                $statusCode = $itemData['status_code'] ?? null;
                $statusInfo = $itemData['status_code_info'] ?? null;

                Log::info('✅ Item trouvé', [
                    'item_id' => $itemId,
                    'status' => $status,
                    'status_code' => $statusCode,
                    'status_info' => $statusInfo,
                ]);

                // ✅ FIX : Accepter status_code null, 0 ou '0' comme OK
                $isOk = in_array($statusCode, [0, '0', null], true)
                    || strtolower($statusInfo ?? '') === 'ok';

                if (! $isOk) {
                    Log::warning('⚠️ Item status non OK', [
                        'item_id' => $itemId,
                        'status_code' => $statusCode,
                        'status_info' => $statusInfo,
                    ]);

                    $this->bankConnection->update([
                        'status' => 'error',
                        'last_error' => "Item Bridge status: {$statusCode} - ".($statusInfo ?? 'Unknown'),
                    ]);

                    return false;
                }

                return true;
            }

            // 404 = item n'existe pas
            if ($response->status() === 404) {
                Log::warning('⏳ Item n\'existe pas', ['item_id' => $itemId]);
                $this->bankConnection->update([
                    'status' => 'expired',
                    'last_error' => 'L\'item Bridge a expiré ou n\'existe plus',
                ]);

                return false;
            }

            Log::error('❌ Erreur vérification item', [
                'item_id' => $itemId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('❌ Exception vérification item', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 💳 Synchroniser transactions d'un compte
     */
    private function syncAccountTransactions(string $token, array $account): int
    {
        $accountId = $account['id'];
        $imported = 0;

        Log::info('🔄 Sync compte', [
            'account_id' => $accountId,
            'account_name' => $account['name'] ?? 'Unknown',
        ]);

        // Récupérer les transactions des 90 derniers jours
        $since = now()->subDays(90)->format('Y-m-d');

        // ✅ Endpoint /transactions avec filtre account_id
        $response = Http::timeout(60)
            ->retry(2, 100)
            ->withHeaders([
                'Client-Id' => config('services.bridge.client_id'),
                'Client-Secret' => config('services.bridge.client_secret'),
                'Bridge-Version' => '2025-01-15',
                'Authorization' => "Bearer $token",
            ])
            ->get('https://api.bridgeapi.io/v3/aggregation/transactions', [
                'account_id' => $accountId,
                'since' => $since,
                'limit' => 500,
            ]);

        if (! $response->successful()) {
            Log::warning('⚠️ Erreur fetch transactions', [
                'account_id' => $accountId,
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            return 0;
        }

        $transactions = $response->json()['resources'] ?? [];

        Log::info('📊 Transactions trouvées', [
            'account_id' => $accountId,
            'count' => count($transactions),
        ]);

        foreach ($transactions as $txData) {
            if ($this->importTransaction($txData, $accountId)) {
                $imported++;
            }
        }

        Log::info('✅ Import terminé', [
            'account_id' => $accountId,
            'imported' => $imported,
            'total' => count($transactions),
        ]);

        return $imported;
    }

    /**
     * 📥 Importer une transaction - VERSION CORRIGÉE
     */
    private function importTransaction(array $txData, string $accountId): bool
    {
        $externalId = (string) $txData['id'];
        $userId = $this->bankConnection->user_id;

        // ✅ Vérifier doublon GLOBAL (même user, même external_id)
        $existingTx = BankTransaction::where('external_id', $externalId)
            ->whereHas('bankConnection', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->first();

        if ($existingTx) {
            // ✅ Si existe mais sur autre connexion, mettre à jour la connexion
            if ($existingTx->bank_connection_id !== $this->bankConnection->id) {
                $existingTx->update([
                    'bank_connection_id' => $this->bankConnection->id
                ]);
                Log::info('🔄 Transaction migrée vers nouvelle connexion', [
                    'external_id' => $externalId,
                    'old_connection' => $existingTx->bank_connection_id,
                    'new_connection' => $this->bankConnection->id
                ]);
            }
            return false; // Pas une nouvelle import
        }

        // 🏷️ Catégoriser
        $suggestedCategory = $this->suggestCategory($txData);
        $confidence = $this->calculateConfidence($txData);

        try {
            BankTransaction::create([
                'bank_connection_id' => $this->bankConnection->id,
                'external_id' => $externalId,
                'amount' => $txData['amount'] ?? 0,
                'description' => $txData['clean_description'] ?? $txData['description'] ?? 'Transaction',
                'transaction_date' => $txData['date'] ?? now()->toDateString(),
                'value_date' => $txData['value_date'] ?? null,
                'account_balance_after' => $txData['account_balance_after'] ?? null,
                'merchant_name' => $txData['bank_description'] ?? null,
                'merchant_category' => $txData['category'] ?? null,
                'raw_data' => $txData,
                'processing_status' => BankTransaction::STATUS_IMPORTED,
                'suggested_category_id' => $suggestedCategory,
                'confidence_score' => $confidence,
                'imported_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('❌ Erreur import transaction', [
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 🤖 Suggérer catégorie avec IA
     */
    private function suggestCategory(array $txData): ?int
    {
        $description = strtolower($txData['description'] ?? '');
        $merchantCategory = $txData['category'] ?? null;

        // Mapping Bridge categories → Categories
        $categoryMap = [
            'food' => 'Alimentation',
            'shopping' => 'Shopping',
            'transport' => 'Transport',
            'bills' => 'Factures',
            'income' => 'Salaire',
        ];

        $categoryName = $categoryMap[$merchantCategory] ?? null;

        if ($categoryName) {
            return Category::where('name', $categoryName)
                ->where('user_id', $this->bankConnection->user_id)
                ->orWhereNull('user_id')
                ->first()?->id;
        }

        // Fallback: analyse description
        return $this->analyzeDescription($description);
    }

    /**
     * 📊 Analyser description pour catégorie
     */
    private function analyzeDescription(string $description): ?int
    {
        $keywords = [
            'Alimentation' => [
                'carrefour', 'lidl', 'auchan',
                'restaurant', 'mcdo', 'resto',
            ],
            'Transport' => [
                'essence', 'carburant', 'sncf',
                'uber', 'ratp', 'parking',
            ],
            'Shopping' => [
                'amazon', 'fnac', 'decathlon',
                'zara', 'h&m',
            ],
        ];

        foreach ($keywords as $categoryName => $words) {
            foreach ($words as $word) {
                if (str_contains($description, $word)) {
                    return Category::where('name', $categoryName)
                        ->where('user_id', $this->bankConnection->user_id)
                        ->orWhereNull('user_id')
                        ->first()?->id;
                }
            }
        }

        return null;
    }

    /**
     * 📈 Calculer score de confiance
     */
    private function calculateConfidence(array $txData): float
    {
        $confidence = 0.5; // Base

        // +0.2 si merchant_name présent
        if (! empty($txData['merchant_name'])) {
            $confidence += 0.2;
        }

        // +0.2 si category présente
        if (! empty($txData['category'])) {
            $confidence += 0.2;
        }

        // +0.1 si description claire
        if (isset($txData['description'])
            && strlen($txData['description']) > 10) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }
}
