<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

            // Gérer selon le type d'événement
            match ($eventType) {
                'item.created' => $this->handleItemCreated($content),
                'item.refreshed' => $this->handleItemRefreshed($content),
                'transaction.created' => $this->handleTransactionCreated($content),
                'transaction.updated' => $this->handleTransactionUpdated($content),
                default => Log::info('ℹ️ Type événement non géré: '.$eventType)
            };

            return response()->json(['status' => 'received'], 200);

        } catch (\Throwable $e) {
            Log::error('❌ Erreur webhook', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Toujours retourner 200 pour éviter les retry de Bridge
            return response()->json(['status' => 'received'], 200);
        }
    }

    /**
     * Gérer la création d'une connexion bancaire
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

        // Créer la connexion bancaire
        $connection = BankConnection::create([
            'user_id' => $user->id,
            'provider' => 'bridge',
            'provider_connection_id' => (string) $itemId,
            'bank_name' => $content['bank_name'] ?? 'Banque connectée',
            'status' => 'active',
            'is_active' => true,
            'last_sync_at' => now(),
            'last_successful_sync_at' => now(),
            'metadata' => json_encode([
                'bridge_item_id' => $itemId,
                'webhook_data' => $content,
            ]),
        ]);

        Log::info('✅ Connexion créée !', [
            'connection_id' => $connection->id,
            'bank' => $connection->bank_name,
            'provider_id' => $connection->provider_connection_id,
        ]);

        // Gaming: XP pour connexion bancaire
        $this->awardXP($user, 100, 'bank_connected');
    }

    /**
     * Gérer le refresh d'une connexion (nouvelles transactions)
     */
    private function handleItemRefreshed(array $content): void
    {
        $itemId = $content['item_id'] ?? null;

        if (! $itemId) {
            Log::warning('⚠️ Item ID manquant pour refresh');

            return;
        }

        $connection = BankConnection::where('provider_connection_id', $itemId)
            ->first();

        if (! $connection) {
            Log::error('❌ Connexion non trouvée', ['item_id' => $itemId]);

            return;
        }

        $connection->update([
            'last_sync_at' => now(),
            'last_successful_sync_at' => now(),
        ]);

        Log::info('🔄 Connexion synchronisée', [
            'connection_id' => $connection->id,
        ]);

        // Gaming: XP pour sync
        $this->awardXP($connection->user, 10, 'bank_synced');
    }

    /**
     * 🆕 Gérer la création d'une transaction
     * AVEC CATÉGORISATION AUTOMATIQUE
     */
    private function handleTransactionCreated(array $content): void
    {
        $transactionId = $content['id'] ?? null;
        $itemId = $content['item_id'] ?? null;

        if (! $transactionId || ! $itemId) {
            Log::warning('⚠️ Données manquantes transaction.created');

            return;
        }

        // Trouver la connexion
        $connection = BankConnection::where('provider_connection_id', $itemId)
            ->first();

        if (! $connection) {
            Log::error('❌ Connexion non trouvée', ['item_id' => $itemId]);

            return;
        }

        try {
            DB::beginTransaction();

            // Vérifier si la transaction existe déjà
            $existingTransaction = Transaction::where('bridge_transaction_id', $transactionId)
                ->where('user_id', $connection->user_id)
                ->first();

            if ($existingTransaction) {
                Log::info('ℹ️ Transaction déjà importée', [
                    'transaction_id' => $existingTransaction->id,
                ]);
                DB::commit();

                return;
            }

            // Créer la transaction
            $amount = abs($content['amount'] ?? 0);
            $type = ($content['amount'] ?? 0) < 0 ? 'expense' : 'income';
            $description = $content['description'] ?? 'Transaction importée';

            $transaction = Transaction::create([
                'user_id' => $connection->user_id,
                'bank_connection_id' => $connection->id,
                'bridge_transaction_id' => $transactionId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'transaction_date' => $content['date'] ?? now(),
                'status' => 'pending', // En attente de catégorisation
                'is_from_bridge' => true,
                'auto_imported' => true,
                'metadata' => json_encode([
                    'bridge_data' => $content,
                ]),
            ]);

            Log::info('✅ Transaction créée', [
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
            ]);

            // 🎯 CATÉGORISATION AUTOMATIQUE
            $this->autoCategorizeTransaction($transaction);

            DB::commit();

            // Gaming: XP pour import automatique
            $this->awardXP($connection->user, 5, 'transaction_imported');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erreur création transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);
        }
    }

    /**
     * Gérer la mise à jour d'une transaction
     */
    private function handleTransactionUpdated(array $content): void
    {
        $transactionId = $content['id'] ?? null;

        if (! $transactionId) {
            Log::warning('⚠️ Transaction ID manquant pour update');

            return;
        }

        $transaction = Transaction::where('bridge_transaction_id', $transactionId)
            ->first();

        if (! $transaction) {
            Log::warning('⚠️ Transaction non trouvée', [
                'bridge_id' => $transactionId,
            ]);

            return;
        }

        // Mettre à jour si nécessaire
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

            Log::info('✅ Transaction mise à jour', [
                'transaction_id' => $transaction->id,
                'changes' => array_keys($updateData),
            ]);
        }
    }

    /**
     * 🎯 CATÉGORISER AUTOMATIQUEMENT UNE TRANSACTION
     */
    private function autoCategorizeTransaction(Transaction $transaction): void
    {
        try {
            Log::info('🤖 Tentative auto-catégorisation', [
                'transaction_id' => $transaction->id,
                'description' => $transaction->description,
            ]);

            $category = $this->categorizationService->categorize($transaction);

            if ($category) {
                $transaction->update([
                    'category_id' => $category->id,
                    'status' => 'completed',
                    'auto_categorized' => true,
                ]);

                Log::info('✅ Transaction auto-catégorisée', [
                    'transaction_id' => $transaction->id,
                    'category' => $category->name,
                ]);

                // Gaming: XP bonus pour auto-catégorisation réussie
                $this->awardXP($transaction->user, 3, 'auto_categorization');
            } else {
                Log::info('ℹ️ Aucune catégorie trouvée', [
                    'transaction_id' => $transaction->id,
                    'description' => $transaction->description,
                ]);

                // Créer une catégorie par défaut si nécessaire
                $this->createDefaultCategory($transaction);
            }

        } catch (\Exception $e) {
            Log::error('❌ Erreur auto-catégorisation', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Créer une catégorie par défaut pour les transactions non catégorisées
     */
    private function createDefaultCategory(Transaction $transaction): void
    {
        try {
            $user = $transaction->user;
            $type = $transaction->type;

            // Nom de la catégorie par défaut
            $categoryName = $type === 'income'
                ? 'Autres Revenus'
                : 'Autres Dépenses';

            // Vérifier si elle existe déjà
            $category = $user->categories()
                ->where('name', $categoryName)
                ->where('type', $type)
                ->first();

            // Créer si nécessaire
            if (! $category) {
                $category = $user->categories()->create([
                    'name' => $categoryName,
                    'type' => $type,
                    'color' => $type === 'income' ? '#10B981' : '#EF4444',
                    'icon' => $type === 'income' ? 'coins' : 'shopping-bag',
                    'is_active' => true,
                    'is_default' => true,
                ]);

                Log::info('✅ Catégorie par défaut créée', [
                    'category_id' => $category->id,
                    'name' => $categoryName,
                ]);
            }

            // Assigner la catégorie
            $transaction->update([
                'category_id' => $category->id,
                'status' => 'completed',
            ]);

            Log::info('✅ Catégorie par défaut assignée', [
                'transaction_id' => $transaction->id,
                'category' => $categoryName,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur création catégorie défaut', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Attribuer des XP gaming
     */
    private function awardXP(User $user, int $amount, string $reason): void
    {
        try {
            $gaming = app(GamingService::class);
            $gaming->addXP($user, $amount, $reason);

            Log::info("🎮 +{$amount} XP ajouté", [
                'user_id' => $user->id,
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            Log::warning('⚠️ Erreur attribution XP', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
