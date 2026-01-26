<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TransactionCategorizationService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job d'import de transactions depuis Bridge API
 *
 * Traite les transactions par batch de 100-200 pour optimiser
 * la performance et éviter les timeouts
 */
class ImportBridgeTransactions implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout du job (5 minutes)
     */
    public int $timeout = 300;

    /**
     * Nombre de tentatives
     */
    public int $tries = 3;

    /**
     * Constructeur avec paramètres nommés
     *
     * @param  int  $userId  ID de l'utilisateur
     * @param  int|string  $accountId  ID du compte bancaire (Bridge API)
     * @param  array  $transactionsBatch  Chunk de transactions (100-200)
     */
    public function __construct(
        public int $userId,
        public int|string $accountId,
        public array $transactionsBatch
    ) {}

    /**
     * Exécuter le job
     */
    public function handle(): void
    {
        // Vérifier si le batch a été annulé
        if ($this->batch()?->cancelled()) {
            return;
        }

        Log::info('📦 Import batch started', [
            'user_id' => $this->userId,
            'account_id' => $this->accountId,
            'count' => count($this->transactionsBatch),
        ]);

        try {
            $this->importTransactions();

            Log::info('✅ Import batch completed', [
                'user_id' => $this->userId,
                'count' => count($this->transactionsBatch),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Import batch failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Importer les transactions
     */
    protected function importTransactions(): void
    {
        DB::transaction(function () {
            $categorizationService = app(TransactionCategorizationService::class);

            foreach ($this->transactionsBatch as $txData) {
                $transaction = $this->createOrUpdateTransaction($txData);

                // Tenter catégorisation automatique
                if (! $transaction->category_id) {
                    $this->attemptCategorization($transaction, $categorizationService);
                }
            }
        });

        // Invalider cache utilisateur après import
        $this->invalidateUserCache();
    }

    /**
     * Créer ou mettre à jour une transaction
     */
    protected function createOrUpdateTransaction(array $data): Transaction
    {
        return Transaction::updateOrCreate(
            [
                'user_id' => $this->userId,
                'external_id' => $data['id'] ?? null,
            ],
            [
                'account_id' => $this->accountId,
                'amount' => $this->parseAmount($data),
                'currency' => $data['currency_code'] ?? 'EUR',
                'description' => $data['description'] ?? '',
                'label' => $data['clean_description'] ?? null,
                'transaction_date' => $this->parseDate($data),
                'type' => $this->determineType($data),
                'status' => $data['status'] ?? 'completed',
                'is_recurring' => $this->detectRecurring($data),
                'bridge_category_id' => $data['category_id'] ?? null,
                'merchant_name' => $this->extractMerchantName($data),
                'raw_data' => json_encode($data),
            ]
        );
    }

    /**
     * Parser le montant
     */
    protected function parseAmount(array $data): float
    {
        $amount = $data['amount'] ?? 0;

        // Bridge API retourne parfois montants en centimes
        if (isset($data['amount_in_cents']) && $data['amount_in_cents']) {
            return $amount / 100;
        }

        return abs((float) $amount);
    }

    /**
     * Parser la date
     */
    protected function parseDate(array $data): string
    {
        $date = $data['date'] ?? $data['updated_at'] ?? now();

        return is_string($date)
            ? $date
            : $date->format('Y-m-d');
    }

    /**
     * Déterminer le type (income/expense)
     */
    protected function determineType(array $data): string
    {
        $amount = $data['amount'] ?? 0;

        // Si montant positif = revenu, négatif = dépense
        return $amount >= 0 ? 'income' : 'expense';
    }

    /**
     * Détecter si récurrent
     */
    protected function detectRecurring(array $data): bool
    {
        // Bridge API peut fournir cette info
        if (isset($data['is_recurring'])) {
            return (bool) $data['is_recurring'];
        }

        // Sinon détection basique sur le libellé
        $label = $data['description'] ?? '';
        $keywords = ['ABONNEMENT', 'MENSUALITE', 'ECHEANCE', 'SUBSCRIPTION'];

        foreach ($keywords as $keyword) {
            if (str_contains(strtoupper($label), $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extraire nom du commerçant
     */
    protected function extractMerchantName(array $data): ?string
    {
        // Bridge peut fournir merchant_name
        if (! empty($data['merchant_name'])) {
            return $data['merchant_name'];
        }

        // Sinon extraire de clean_description
        if (! empty($data['clean_description'])) {
            $words = explode(' ', $data['clean_description']);

            return implode(' ', array_slice($words, 0, 3));
        }

        return null;
    }

    /**
     * Tenter catégorisation automatique
     */
    protected function attemptCategorization(
        Transaction $transaction,
        TransactionCategorizationService $service
    ): void {
        try {
            $category = $service->categorize($transaction);

            if ($category) {
                $transaction->update(['category_id' => $category->id]);
            }
        } catch (\Exception $e) {
            Log::warning('⚠️ Auto-categorization failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalider cache utilisateur
     */
    protected function invalidateUserCache(): void
    {
        Cache::tags(["user:{$this->userId}"])->flush();
    }

    /**
     * Gestion de l'échec du job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('💥 Import job failed permanently', [
            'user_id' => $this->userId,
            'account_id' => $this->accountId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
