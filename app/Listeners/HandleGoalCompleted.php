<?php

namespace App\Listeners;

use App\Events\GoalCompleted;
use App\Services\GamingService;
use App\Notifications\GoalCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleGoalCompleted implements ShouldQueue
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
    public function handle(GoalCompleted $event): void
    {
        $user = $event->user;
        $goal = $event->goal;

        try {
            // 1. XP massif pour objectif complété
            $bonusXp = $this->calculateGoalCompletionXp($goal);
            $this->gamingService->addExperience($user, $bonusXp, 'goal_completed');

            // 2. Notification de félicitations
            $user->notify(new GoalCompletedNotification($goal));

            // 3. Vérifier les succès liés aux objectifs complétés
            $user->checkAndUnlockAchievements();

            // 4. Vérifier les jalons d'objectifs (1er, 5e, 10e objectif complété)
            $this->checkGoalCompletionMilestones($user);

            // 5. Mettre à jour la streak "goal_completion" si elle existe
            $this->gamingService->updateStreak($user, 'goal_completion');

            \Log::info("Objectif complété - Récompenses distribuées", [
                'user_id' => $user->id,
                'goal_id' => $goal->id,
                'goal_name' => $goal->name,
                'target_amount' => $goal->target_amount,
                'xp_bonus' => $bonusXp
            ]);

        } catch (\Exception $e) {
            \Log::error("Erreur lors du traitement de la complétion d'objectif", [
                'user_id' => $user->id,
                'goal_id' => $goal->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate XP bonus based on goal difficulty
     */
    private function calculateGoalCompletionXp($goal): int
    {
        $baseXp = 200; // XP de base
        $amountBonus = min(500, floor($goal->target_amount / 1000)); // 1 XP par 1000€

        // Bonus selon priorité
        $priorityBonus = match($goal->priority) {
            'high' => 100,
            'medium' => 50,
            'low' => 25,
            default => 0
        };

        return $baseXp + $amountBonus + $priorityBonus;
    }

    /**
     * Check goal completion milestones
     */
    private function checkGoalCompletionMilestones($user): void
    {
        $completedGoals = $user->financialGoals()->where('status', 'completed')->count();

        $milestones = [1, 3, 5, 10, 25, 50];

        if (in_array($completedGoals, $milestones)) {
            $bonusXp = $completedGoals * 100; // Plus d'objectifs = plus de bonus
            $this->gamingService->addExperience($user, $bonusXp, 'goal_milestone');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(GoalCompleted $event, \Throwable $exception): void
    {
        \Log::error("Échec du traitement GoalCompleted", [
            'user_id' => $event->user->id,
            'goal_id' => $event->goal->id,
            'error' => $exception->getMessage()
        ]);
    }
}
