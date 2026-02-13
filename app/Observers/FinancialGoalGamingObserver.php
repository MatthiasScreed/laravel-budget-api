<?php

namespace App\Observers;

use App\Models\FinancialGoal;
use App\Services\ProgressiveGamingService;

class FinancialGoalGamingObserver
{
    public function __construct(
        private ProgressiveGamingService $gamingService
    ) {}

    /**
     * Après création d'un objectif
     */
    public function created(FinancialGoal $goal): void
    {
        $user = $goal->user;

        if (!$user) {
            return;
        }

        $context = [
            'goal_name' => $goal->name,
            'target_amount' => $goal->target_amount,
            'is_first' => $user->financialGoals()->count() === 1,
        ];

        try {
            $this->gamingService->processEvent($user, 'goal_created', $context);
        } catch (\Exception $e) {
            Log::warning('Gaming processing failed for goal creation', [
                'user_id' => $user->id,
                'goal_id' => $goal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Après mise à jour d'un objectif
     */
    public function updated(FinancialGoal $goal): void
    {
        $user = $goal->user;

        if (!$user) {
            return;
        }

        // Vérifier si l'objectif vient d'être complété
        if ($goal->wasChanged('completed_at') && $goal->completed_at) {
            $context = [
                'goal_name' => $goal->name,
                'target_amount' => $goal->target_amount,
                'current_amount' => $goal->current_amount,
            ];

            try {
                $this->gamingService->processEvent($user, 'goal_completed', $context);
            } catch (\Exception $e) {
                Log::warning('Gaming processing failed for goal completion', [
                    'user_id' => $user->id,
                    'goal_id' => $goal->id,
                    'error' => $e->getMessage(),
                ]);
            }
            return;
        }

        // Vérifier la progression
        if ($goal->wasChanged('current_amount')) {
            $progress = $goal->target_amount > 0
                ? ($goal->current_amount / $goal->target_amount) * 100
                : 0;

            $context = [
                'goal_name' => $goal->name,
                'progress' => round($progress, 1),
                'current_amount' => $goal->current_amount,
                'target_amount' => $goal->target_amount,
            ];

            try {
                $this->gamingService->processEvent($user, 'goal_progress', $context);
            } catch (\Exception $e) {
                Log::warning('Gaming processing failed for goal progress', [
                    'user_id' => $user->id,
                    'goal_id' => $goal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
