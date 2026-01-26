<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TransactionCategorizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Job de catégorisation automatique des transactions
 *
 * Catégorise toutes les transactions non catégorisées
 * d'un utilisateur après un import Bridge
 */
class AutoCategorizeTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout du job (10 minutes)
     */
    public int $timeout = 600;

    /**
     * Nombre de tentatives
     */
    public int $tries = 2;

    /**
     * Constructeur
     */
    public function __construct(
        public int $userId,
        public ?int $batchSize = 50
    ) {}

    /**
     * Exécuter le job
     */
    public function handle(TransactionCategorizationService $service): void
    {
        Log::info('Auto-categorization started', [
            'user_id' => $this->userId,
        ]);

        $categorized = 0;
        $failed = 0;

        // Récupérer transactions non catégorisées par batch
        Transaction::where('user_id', $this->userId)
            ->whereNull('category_id')
            ->orderBy('transaction_date', 'desc')
            ->chunk($this->batchSize, function ($transactions) use ($service, &$categorized, &$failed) {
                foreach ($transactions as $transaction) {
                    try {
                        $category = $service->categorize($transaction);

                        if ($category) {
                            $transaction->update(['category_id' => $category->id]);
                            $categorized++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('Auto-categorization failed for transaction', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // Invalider cache utilisateur
        Cache::tags(["user:{$this->userId}"])->flush();

        Log::info('Auto-categorization completed', [
            'user_id' => $this->userId,
            'categorized' => $categorized,
            'failed' => $failed,
        ]);
    }

    /**
     * Gestion de l'échec du job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Auto-categorization job failed', [
            'user_id' => $this->userId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
