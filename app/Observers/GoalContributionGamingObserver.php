<?php

namespace App\Observers;

use App\Models\GoalContribution;
use App\Services\ProgressiveGamingService;

class GoalContributionGamingObserver
{
    public function __construct(
        private ProgressiveGamingService $gamingService
    ) {}

    /**
     * Après ajout d'une contribution
     */
    public function created(GoalContribution $contribution): void
    {
        $goal = $contribution->goal;
        $user = $goal?->user;

        if (!$user || !$goal) {
            return;
        }

        // Calculer le nouveau pourcentage
        $newProgress = $goal->target_amount > 0
            ? (($goal->current_amount + $contribution->amount) / $goal->target_amount) * 100
            : 0;

        $context = [
            'goal_name' => $goal->name,
            'contribution_amount' => $contribution->amount,
            'progress' => round($newProgress, 1),
            'current_amount' => $goal->current_amount + $contribution->amount,
            'target_amount' => $goal->target_amount,
        ];

        try {
            $this->gamingService->processEvent($user, 'goal_progress', $context);
        } catch (\Exception $e) {
            Log::warning('Gaming processing failed for contribution', [
                'user_id' => $user->id,
                'contribution_id' => $contribution->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
