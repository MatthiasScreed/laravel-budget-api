<?php

namespace App\Listeners;

use App\Events\TransactionCreated;
use App\Services\GamingService;
use App\Notifications\TransactionCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleTransactionCreated implements ShouldQueue
{
    use InteractsWithQueue;

    protected GamingService $gamingService;

    /**
     * Create the event listener.
     */
    public function __construct(GamingService $gamingService)
    {
        $this->gamingService = $gamingService;
    }

    /**
     * Handle the event.
     */
    public function handle(TransactionCreated $event): void
    {
        $user = $event->user;
        $transaction = $event->transaction;

        try {
            // 1. Ajouter XP pour la transaction
            $xpAmount = $this->calculateTransactionXp($transaction);
            $this->gamingService->addExperience($user, $xpAmount, 'transaction_created');

            // 2. Mettre à jour la streak de transactions quotidiennes
            $this->gamingService->updateStreak($user, 'daily_transaction');

            // 3. Vérifier et débloquer les succès liés aux transactions
            $user->checkAndUnlockAchievements();

            // 4. Vérifier les jalons de transactions (ex: 10e, 50e, 100e transaction)
            $this->checkTransactionMilestones($user, $transaction);

            \Log::info("Transaction créée - XP ajoutés", [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'xp_added' => $xpAmount
            ]);

        } catch (\Exception $e) {
            \Log::error("Erreur lors du traitement de la création de transaction", [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate XP based on transaction
     */
    private function calculateTransactionXp($transaction): int
    {
        $baseXp = 10; // XP de base pour toute transaction
        $amountBonus = min(50, floor($transaction->amount / 100)); // 1 XP par 100€, max 50

        return $baseXp + $amountBonus;
    }

    /**
     * Check transaction milestones
     */
    private function checkTransactionMilestones($user, $transaction): void
    {
        $totalTransactions = $user->transactions()->count();

        $milestones = [10, 25, 50, 100, 250, 500, 1000];

        if (in_array($totalTransactions, $milestones)) {
            // Déclencher un succès spécial pour ce jalon
            $bonusXp = $totalTransactions * 2; // Plus de transactions = plus de bonus
            $this->gamingService->addExperience($user, $bonusXp, 'transaction_milestone');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(TransactionCreated $event, \Throwable $exception): void
    {
        \Log::error("Échec du traitement TransactionCreated", [
            'user_id' => $event->user->id,
            'transaction_id' => $event->transaction->id,
            'error' => $exception->getMessage()
        ]);
    }
}
