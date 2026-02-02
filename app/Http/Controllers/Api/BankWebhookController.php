<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncBankTransactionsJob;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GamingService;
use App\Services\TransactionCategorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankWebhookController extends Controller
{
    protected TransactionCategorizationService $categorizationService;

    public function __construct(TransactionCategorizationService $service)
    {
        $this->categorizationService = $service;
    }

    /**
     * Gérer les webhooks Bridge API
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $data = $request->all();

        Log::info('📩 Webhook Bridge reçu', [
            'type' => $data['type'] ?? 'unknown',
            'body' => $data,
        ]);

        try {
            $eventType = $data['type'] ?? null;
            $content = $data['content'] ?? [];

            match ($eventType) {
                'item.created' => $this->handleItemCreated($content),
                'item.refreshed' => $this->handleItemRefreshed($content),
                'item.account.created' => $this->handleAccountCreated($content),
                'item.account.updated' => $this->handleAccountUpdated($content),
                'transaction.created' => $this->handleTransactionCreated($content),
                'transaction.updated' => $this->handleTransactionUpdated($content),
                default => Log::info('ℹ️ Type événement ignoré: '.$eventType)
            };

            return response()->json(['status' => 'received'], 200);

        } catch (\Throwable $e) {
            Log::error('❌ Erreur webhook', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'received'], 200);
        }
    }

    /**
     * ✅ Item créé - Connexion bancaire établie
     */
    private function handleItemCreated(array $content): void
    {
        $itemId = $content['item_id'] ?? null;
        $userUuid = $content['user_uuid'] ?? null;

        if (! $itemId || ! $userUuid) {
            Log::warning('⚠️ Données manquantes item.created');
            return;
        }

        $user = User::where('bridge_user_uuid', $userUuid)->first();

        if (! $user) {
            Log::error('❌ User non trouvé', ['uuid' => $userUuid]);
            return;
        }

        Log::info('👤 User trouvé', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        // Vérifier si connexion existe déjà
        $existing = BankConnection::where('provider_connection_id', (string) $itemId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            Log::info('ℹ️ Connexion existe déjà', ['connection_id' => $existing->id]);
            return;
        }

        // Créer la connexion bancaire
        $connection = BankConnection::create([
            'user_id' => $user->id,
            'provider' => 'bridge',
            'provider_connection_id' => (string) $itemId,
            'bank_name' => $content['bank_name'] ?? 'Banque connectée',
            'status' => 'active',
            'is_active' => true,
            'last_sync_at' => now(),
            'metadata' => [
                'bridge_item_id' => $itemId,
                'created_via' => 'webhook',
            ],
        ]);

        Log::info('✅ Connexion créée !', [
            'connection_id' => $connection->id,
            'bank' => $connection->bank_name,
        ]);

        $this->awardXP($user, 100, 'bank_connected');
    }

    /**
     * ✅ NOUVEAU: Compte créé
     */
    private function handleAccountCreated(array $content): void
    {
        $accountId = $content['account_id'] ?? null;
        $itemId = $content['item_id'] ?? null;
        $balance = $content['balance'] ?? 0;

        if (! $accountId || ! $itemId) {
            return;
        }

        $connection = BankConnection::where('provider_connection_id', (string) $itemId)->first();

        if (! $connection) {
            Log::warning('⚠️ Connexion non trouvée pour account.created', ['item_id' => $itemId]);
            return;
        }

        Log::info('🏦 Compte créé', [
            'account_id' => $accountId,
            'balance' => $balance,
            'connection_id' => $connection->id,
        ]);

        // Optionnel: créer un enregistrement BankAccount si tu as ce modèle
        // BankAccount::updateOrCreate(...)
    }

    /**
     * ✅ NOUVEAU: Compte mis à jour - LANCE LA SYNC !
     */
    private function handleAccountUpdated(array $content): void
    {
        $accountId = $content['account_id'] ?? null;
        $itemId = $content['item_id'] ?? null;
        $nbNew = $content['nb_new_transactions'] ?? 0;
        $nbUpdated = $content['nb_updated_transactions'] ?? 0;

        if (! $itemId) {
            return;
        }

        $connection = BankConnection::where('provider_connection_id', (string) $itemId)->first();

        if (! $connection) {
            Log::warning('⚠️ Connexion non trouvée pour account.updated', ['item_id' => $itemId]);
            return;
        }

        Log::info('📊 Compte mis à jour', [
            'account_id' => $accountId,
            'nb_new_transactions' => $nbNew,
            'nb_updated_transactions' => $nbUpdated,
            'connection_id' => $connection->id,
        ]);

        // ✅ Lancer la sync si nouvelles transactions
        if ($nbNew > 0 || $nbUpdated > 0) {
            // Éviter les doublons de jobs avec un cache simple
            $cacheKey = "sync_job_{$connection->id}";

            if (! cache()->has($cacheKey)) {
                cache()->put($cacheKey, true, 60); // 60 secondes de cooldown

                SyncBankTransactionsJob::dispatch($connection)
                    ->delay(now()->addSeconds(5)); // Petit délai pour laisser Bridge finir

                Log::info('🚀 Sync programmée', [
                    'connection_id' => $connection->id,
                    'reason' => "new={$nbNew}, updated={$nbUpdated}",
                ]);
            } else {
                Log::info('⏳ Sync déjà en cours', ['connection_id' => $connection->id]);
            }
        }
    }

    /**
     * ✅ Item refreshed - Sync terminée côté Bridge
     */
    private function handleItemRefreshed(array $content): void
    {
        $itemId = $content['item_id'] ?? null;
        $statusCode = $content['status_code'] ?? null;
        $fullRefresh = $content['full_refresh'] ?? false;

        if (! $itemId) {
            Log::warning('⚠️ Item ID manquant pour refresh');
            return;
        }

        $connection = BankConnection::where('provider_connection_id', (string) $itemId)->first();

        if (! $connection) {
            Log::error('❌ Connexion non trouvée', ['item_id' => $itemId]);
            return;
        }

        // Vérifier si OK (status_code 0 = succès)
        $isSuccess = in_array($statusCode, [0, null], true);

        if ($isSuccess) {
            $connection->update([
                'status' => 'active',
                'last_sync_at' => now(),
                'last_successful_sync_at' => now(),
                'last_error' => null,
            ]);

            Log::info('🔄 Connexion synchronisée', [
                'connection_id' => $connection->id,
                'full_refresh' => $fullRefresh,
            ]);

            // ✅ Lancer sync si full_refresh
            if ($fullRefresh) {
                $cacheKey = "sync_job_{$connection->id}";

                if (! cache()->has($cacheKey)) {
                    cache()->put($cacheKey, true, 60);

                    SyncBankTransactionsJob::dispatch($connection)
                        ->delay(now()->addSeconds(3));

                    Log::info('🚀 Sync full_refresh programmée', [
                        'connection_id' => $connection->id,
                    ]);
                }
            }

            $this->awardXP($connection->user, 10, 'bank_synced');

        } else {
            $connection->update([
                'status' => 'error',
                'last_error' => $content['status_code_info'] ?? 'Sync failed',
            ]);

            Log::error('❌ Erreur sync Bridge', [
                'connection_id' => $connection->id,
                'status_code' => $statusCode,
                'status_info' => $content['status_code_info'] ?? null,
            ]);
        }
    }

    /**
     * Gérer la création d'une transaction (webhook direct)
     */
    private function handleTransactionCreated(array $content): void
    {
        $transactionId = $content['id'] ?? null;
        $itemId = $content['item_id'] ?? null;

        if (! $transactionId || ! $itemId) {
            Log::warning('⚠️ Données manquantes transaction.created');
            return;
        }

        $connection = BankConnection::where('provider_connection_id', (string) $itemId)->first();

        if (! $connection) {
            Log::error('❌ Connexion non trouvée', ['item_id' => $itemId]);
            return;
        }

        try {
            DB::beginTransaction();

            // Vérifier si existe déjà
            $existing = Transaction::where('bridge_transaction_id', $transactionId)
                ->where('user_id', $connection->user_id)
                ->first();

            if ($existing) {
                Log::info('ℹ️ Transaction déjà importée', ['id' => $existing->id]);
                DB::commit();
                return;
            }

            $amount = abs($content['amount'] ?? 0);
            $type = ($content['amount'] ?? 0) < 0 ? 'expense' : 'income';

            $transaction = Transaction::create([
                'user_id' => $connection->user_id,
                'bank_connection_id' => $connection->id,
                'bridge_transaction_id' => $transactionId,
                'type' => $type,
                'amount' => $amount,
                'description' => $content['description'] ?? 'Transaction importée',
                'transaction_date' => $content['date'] ?? now(),
                'status' => 'pending',
                'is_from_bridge' => true,
                'auto_imported' => true,
            ]);

            Log::info('✅ Transaction créée', [
                'id' => $transaction->id,
                'amount' => $amount,
                'type' => $type,
            ]);

            $this->autoCategorizeTransaction($transaction);

            DB::commit();

            $this->awardXP($connection->user, 5, 'transaction_imported');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erreur création transaction', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Gérer la mise à jour d'une transaction
     */
    private function handleTransactionUpdated(array $content): void
    {
        $transactionId = $content['id'] ?? null;

        if (! $transactionId) {
            return;
        }

        $transaction = Transaction::where('bridge_transaction_id', $transactionId)->first();

        if (! $transaction) {
            return;
        }

        $updateData = [];

        if (isset($content['amount'])) {
            $updateData['amount'] = abs($content['amount']);
        }
        if (isset($content['description'])) {
            $updateData['description'] = $content['description'];
        }
        if (isset($content['date'])) {
            $updateData['transaction_date'] = $content['date'];
        }

        if (! empty($updateData)) {
            $transaction->update($updateData);
            Log::info('✅ Transaction mise à jour', ['id' => $transaction->id]);
        }
    }

    /**
     * Catégoriser automatiquement une transaction
     */
    private function autoCategorizeTransaction(Transaction $transaction): void
    {
        try {
            $category = $this->categorizationService->categorize($transaction);

            if ($category) {
                $transaction->update([
                    'category_id' => $category->id,
                    'status' => 'completed',
                    'auto_categorized' => true,
                ]);

                Log::info('✅ Auto-catégorisée', [
                    'id' => $transaction->id,
                    'category' => $category->name,
                ]);

                $this->awardXP($transaction->user, 3, 'auto_categorization');
            } else {
                $this->assignDefaultCategory($transaction);
            }
        } catch (\Exception $e) {
            Log::error('❌ Erreur auto-catégorisation', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Assigner une catégorie par défaut
     */
    private function assignDefaultCategory(Transaction $transaction): void
    {
        $user = $transaction->user;
        $type = $transaction->type;
        $name = $type === 'income' ? 'Autres Revenus' : 'Autres Dépenses';

        $category = $user->categories()
            ->where('name', $name)
            ->where('type', $type)
            ->first();

        if (! $category) {
            $category = $user->categories()->create([
                'name' => $name,
                'type' => $type,
                'color' => $type === 'income' ? '#10B981' : '#EF4444',
                'icon' => $type === 'income' ? 'coins' : 'shopping-bag',
                'is_active' => true,
            ]);
        }

        $transaction->update([
            'category_id' => $category->id,
            'status' => 'completed',
        ]);
    }

    /**
     * ✅ Attribuer des XP gaming - CORRIGÉ
     */
    private function awardXP(User $user, int $amount, string $reason): void
    {
        try {
            $gaming = app(GamingService::class);
            $gaming->addExperience($user, $amount, $reason); // ✅ CORRIGÉ

            Log::info("🎮 +{$amount} XP", [
                'user_id' => $user->id,
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            Log::warning('⚠️ Erreur XP', ['error' => $e->getMessage()]);
        }
    }
}
