<?php

namespace App\Services;

use App\Models\User;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class BankIntegrationService
{
    private GamingService $gamingService;
    private BudgetService $budgetService;

    public function __construct(
        GamingService $gamingService,
        BudgetService $budgetService
    ) {
        $this->gamingService = $gamingService;
        $this->budgetService = $budgetService;
    }

    /**
     * Initier connexion avec Bridge API
     */
    public function initiateBankConnection(User $user, array $params): array
    {
        try {
            // 1. Créer l'URL de connexion Bridge
            $response = Http::withHeaders([
                'Client-Id' => config('banking.bridge.client_id'),
                'Client-Secret' => config('banking.bridge.client_secret'),
                'Bridge-Version' => '2021-06-01'
            ])->post('https://api.bridgeapi.io/v2/connect/items/add', [
                'user_uuid' => $user->id,
                'prefill_email' => $user->email,
                'webhook_url' => config('app.url') . '/api/webhooks/bridge'
            ]);

            if (!$response->successful()) {
                throw new \Exception('Erreur Bridge API: ' . $response->body());
            }

            $data = $response->json();

            return [
                'success' => true,
                'connect_url' => $data['redirect_url'],
                'item_id' => $data['item_id'],
                'expires_at' => now()->addMinutes(15) // URL valable 15min
            ];

        } catch (\Exception $e) {
            Log::error('Bank connection initiation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Impossible d\'initier la connexion bancaire'
            ];
        }
    }

    /**
     * Finaliser la connexion après validation utilisateur
     */
    public function finalizeBankConnection(User $user, string $itemId): BankConnection
    {
        // 1. Récupérer les détails de la connexion
        $response = Http::withHeaders([
            'Client-Id' => config('banking.bridge.client_id'),
            'Client-Secret' => config('banking.bridge.client_secret')
        ])->get("https://api.bridgeapi.io/v2/items/{$itemId}");

        $itemData = $response->json();

        // 2. Récupérer les comptes associés
        $accountsResponse = Http::withHeaders([
            'Client-Id' => config('banking.bridge.client_id'),
            'Client-Secret' => config('banking.bridge.client_secret')
        ])->get("https://api.bridgeapi.io/v2/accounts", [
            'item_id' => $itemId
        ]);

        $accounts = $accountsResponse->json()['resources'];

        // 3. Créer la connexion dans notre BDD
        $connection = BankConnection::create([
            'user_id' => $user->id,
            'bank_name' => $itemData['bank']['name'],
            'bank_code' => $itemData['bank']['code'],
            'connection_id' => $itemId,
            'access_token_encrypted' => Crypt::encryptString($itemData['access_token']),
            'provider' => BankConnection::PROVIDER_BRIDGE,
            'status' => BankConnection::STATUS_ACTIVE,
            'auto_sync_enabled' => true,
            'sync_frequency_hours' => 6 // Sync toutes les 6h
        ]);

        // 4. XP pour connexion bancaire réussie
        $this->gamingService->addXP($user, 100, 'bank_connection');

        // 5. Lancer première synchronisation
        $this->syncTransactions($connection);

        return $connection;
    }

    /**
     * Synchroniser les transactions depuis la banque
     */
    public function syncTransactions(BankConnection $connection, int $days = 30): array
    {
        try {
            $accessToken = Crypt::decryptString($connection->access_token_encrypted);

            // 1. Récupérer les transactions depuis Bridge
            $response = Http::withHeaders([
                'Client-Id' => config('banking.bridge.client_id'),
                'Client-Secret' => config('banking.bridge.client_secret')
            ])->get('https://api.bridgeapi.io/v2/transactions', [
                'item_id' => $connection->connection_id,
                'since' => now()->subDays($days)->format('Y-m-d'),
                'limit' => 500
            ]);

            $bankTransactions = $response->json()['resources'];

            $imported = 0;
            $processed = 0;

            foreach ($bankTransactions as $bankTx) {
                // 2. Vérifier si déjà importée
                $exists = BankTransaction::where([
                    'bank_connection_id' => $connection->id,
                    'external_id' => $bankTx['id']
                ])->exists();

                if ($exists) continue;

                // 3. Créer BankTransaction
                $importedTx = BankTransaction::create([
                    'bank_connection_id' => $connection->id,
                    'external_id' => $bankTx['id'],
                    'amount' => $bankTx['amount'],
                    'description' => $bankTx['description'],
                    'transaction_date' => Carbon::parse($bankTx['date']),
                    'value_date' => Carbon::parse($bankTx['value_date'] ?? $bankTx['date']),
                    'account_balance_after' => $bankTx['account_balance'],
                    'merchant_name' => $bankTx['merchant']['name'] ?? null,
                    'merchant_category' => $bankTx['merchant']['category'] ?? null,
                    'raw_data' => $bankTx,
                    'processing_status' => BankTransaction::STATUS_IMPORTED,
                    'imported_at' => now()
                ]);

                $imported++;

                // 4. Catégorisation automatique par IA
                $this->categorizeTransactionWithAI($importedTx);

                // 5. Conversion en Transaction utilisateur
                if ($this->shouldAutoConvert($importedTx)) {
                    $this->convertToUserTransaction($importedTx);
                    $processed++;
                }
            }

            // 6. Marquer la sync comme réussie
            $connection->markSyncSuccess();

            // 7. XP pour synchronisation
            if ($imported > 0) {
                $this->gamingService->addXP(
                    $connection->user,
                    min(50, $imported * 2),
                    'auto_sync'
                );
            }

            return [
                'success' => true,
                'imported' => $imported,
                'processed' => $processed,
                'message' => "{$imported} transactions importées, {$processed} traitées"
            ];

        } catch (\Exception $e) {
            $connection->markSyncError($e->getMessage());

            Log::error('Bank sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la synchronisation'
            ];
        }
    }

    /**
     * Catégoriser automatiquement avec IA
     */
    private function categorizeTransactionWithAI(BankTransaction $bankTx): void
    {
        // Logique de catégorisation par IA basée sur :
        // - Description de la transaction
        // - Nom du marchand
        // - Catégorie marchand
        // - Historique utilisateur

        $description = $bankTx->getFormattedDescription();
        $merchantCategory = $bankTx->merchant_category;

        // Mapping basique (à améliorer avec vraie IA)
        $categoryMapping = [
            'grocery' => 'Alimentation',
            'gas_station' => 'Transport',
            'restaurant' => 'Restaurants',
            'pharmacy' => 'Santé',
            'clothing' => 'Shopping',
            'supermarket' => 'Alimentation'
        ];

        $suggestedCategoryName = $categoryMapping[$merchantCategory] ?? null;

        if ($suggestedCategoryName) {
            $category = Category::where([
                'user_id' => $bankTx->bankConnection->user_id,
                'name' => $suggestedCategoryName
            ])->first();

            if ($category) {
                $bankTx->update([
                    'suggested_category_id' => $category->id,
                    'confidence_score' => 0.85,
                    'processing_status' => BankTransaction::STATUS_CATEGORIZED,
                    'categorized_at' => now()
                ]);
            }
        }
    }

    /**
     * Déterminer si auto-convertir en Transaction
     */
    private function shouldAutoConvert(BankTransaction $bankTx): bool
    {
        // Règles pour conversion automatique :
        // - Score de confiance > 80%
        // - Montant > 5€ (éviter micro-transactions)
        // - Pas une transaction interne bancaire

        return $bankTx->confidence_score >= 0.80
            && $bankTx->getAbsoluteAmount() >= 5
            && !$this->isInternalBankTransaction($bankTx);
    }

    /**
     * Convertir BankTransaction en Transaction utilisateur
     */
    private function convertToUserTransaction(BankTransaction $bankTx): Transaction
    {
        $user = $bankTx->bankConnection->user;

        $transaction = $this->budgetService->createTransaction($user, [
            'amount' => $bankTx->getAbsoluteAmount(),
            'description' => $bankTx->getFormattedDescription(),
            'type' => $bankTx->isIncome() ? 'income' : 'expense',
            'category_id' => $bankTx->suggested_category_id,
            'transaction_date' => $bankTx->transaction_date->format('Y-m-d'),
            'payment_method' => 'card', // Par défaut pour transactions bancaires
            'source' => 'bank_import',
            'bank_connection_id' => $bankTx->bank_connection_id
        ]);

        // Marquer comme convertie
        $bankTx->update([
            'processing_status' => BankTransaction::STATUS_CONVERTED,
            'converted_transaction_id' => $transaction->id
        ]);

        return $transaction;
    }

    /**
     * Détecter les transactions internes bancaires
     */
    private function isInternalBankTransaction(BankTransaction $bankTx): bool
    {
        $description = strtolower($bankTx->description);

        $internalKeywords = [
            'frais', 'commission', 'agios', 'cotisation',
            'virement compte à compte', 'transfert interne'
        ];

        foreach ($internalKeywords as $keyword) {
            if (str_contains($description, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtenir le statut de toutes les connexions d'un utilisateur
     */
    public function getUserConnectionsStatus(User $user): array
    {
        $connections = $user->bankConnections()
            ->with('importedTransactions')
            ->get();

        return $connections->map(function ($connection) {
            return [
                'id' => $connection->id,
                'bank_name' => $connection->bank_name,
                'status' => $connection->status,
                'last_sync' => $connection->last_sync_at?->format('Y-m-d H:i'),
                'transactions_count' => $connection->importedTransactions()->count(),
                'needs_sync' => $connection->needsSync(),
                'auto_sync' => $connection->auto_sync_enabled
            ];
        })->toArray();
    }
}
