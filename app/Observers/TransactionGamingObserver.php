<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\ProgressiveGamingService;
use Illuminate\Support\Facades\Log;

class TransactionGamingObserver
{
    public function __construct(
        private ProgressiveGamingService $gamingService
    ) {}

    /**
     * Après création d'une transaction
     */
    public function created(Transaction $transaction): void
    {
        $user = $transaction->user;

        if (!$user) {
            return;
        }

        // Déterminer le type d'événement
        $eventType = $transaction->type === 'income'
            ? 'transaction_income'
            : 'transaction_created';

        // Contexte pour le feedback
        $context = [
            'amount' => $transaction->amount,
            'type' => $transaction->type,
            'category' => $transaction->category?->name,
            'is_first' => $this->isFirstTransaction($user),
        ];

        // Déclencher le traitement gaming
        try {
            $result = $this->gamingService->processEvent($user, $eventType, $context);

            // Stocker le résultat pour le frontend (via cache temporaire)
            $this->storeFeedbackForResponse($user->id, $result);

        } catch (\Exception $e) {
            Log::warning('Gaming processing failed', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Vérifie si c'est la première transaction
     */
    private function isFirstTransaction($user): bool
    {
        return $user->transactions()->count() === 1;
    }

    /**
     * Stocke le feedback pour récupération par le frontend
     */
    private function storeFeedbackForResponse(int $userId, array $result): void
    {
        if (empty($result['feedback']) && empty($result['milestones']['newly_completed'])) {
            return;
        }

        $cacheKey = "pending_gaming_feedback_{$userId}";
        $existingFeedback = cache()->get($cacheKey, []);

        $existingFeedback[] = [
            'feedback' => $result['feedback'] ?? null,
            'milestones' => $result['milestones']['newly_completed'] ?? [],
            'show_points' => $result['show_points'] ?? false,
            'points' => $result['points'] ?? 0,
            'timestamp' => now()->timestamp,
        ];

        // Garder max 5 feedbacks en attente, TTL 5 minutes
        cache()->put(
            $cacheKey,
            array_slice($existingFeedback, -5),
            300
        );
    }
}
