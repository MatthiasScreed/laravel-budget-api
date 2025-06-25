<?php

namespace App\Listeners;

use App\Events\StreakMilestone;
use App\Services\GamingService;
use App\Notifications\StreakMilestoneNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleStreakMilestone implements ShouldQueue
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
    public function handle(StreakMilestone $event): void
    {
        $user = $event->user;
        $streak = $event->streak;
        $milestone = $event->milestone;
        $bonusXp = $event->bonusXp;

        try {
            // 1. Ajouter le bonus XP pour le jalon
            if ($bonusXp > 0) {
                $this->gamingService->addExperience($user, $bonusXp, 'streak_milestone');
            }

            // 2. Notification de félicitations
            $user->notify(new StreakMilestoneNotification($streak, $milestone));

            // 3. Vérifier les succès liés aux séries
            $user->checkAndUnlockAchievements();

            // 4. Récompenses spéciales pour les grandes séries
            $this->handleSpecialStreakRewards($user, $streak, $milestone);

            // 5. Mettre à jour les statistiques utilisateur
            $this->updateStreakStats($user, $streak, $milestone);

            \Log::info("Jalon de série atteint - Récompenses distribuées", [
                'user_id' => $user->id,
                'streak_id' => $streak->id,
                'streak_type' => $streak->type,
                'milestone' => $milestone,
                'bonus_xp' => $bonusXp,
                'current_count' => $streak->current_count
            ]);

        } catch (\Exception $e) {
            \Log::error("Erreur lors du traitement du jalon de série", [
                'user_id' => $user->id,
                'streak_id' => $streak->id,
                'milestone' => $milestone,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle special rewards for major streaks
     */
    private function handleSpecialStreakRewards($user, $streak, $milestone): void
    {
        // Récompenses spéciales pour des jalons importants
        $specialRewards = [
            'daily_login' => [
                30 => ['type' => 'badge', 'name' => 'Fidèle'],
                100 => ['type' => 'title', 'name' => 'Assidu'],
                365 => ['type' => 'premium', 'name' => 'Année complète'],
            ],
            'daily_transaction' => [
                7 => ['type' => 'badge', 'name' => 'Organisé'],
                30 => ['type' => 'badge', 'name' => 'Méthodique'],
                90 => ['type' => 'title', 'name' => 'Gestionnaire Pro'],
            ]
        ];

        if (isset($specialRewards[$streak->type][$milestone])) {
            $reward = $specialRewards[$streak->type][$milestone];

            // Ici vous pourriez débloquer des badges, titres, etc.
            \Log::info("Récompense spéciale débloquée", [
                'user_id' => $user->id,
                'streak_type' => $streak->type,
                'milestone' => $milestone,
                'reward' => $reward
            ]);
        }
    }

    /**
     * Update streak statistics
     */
    private function updateStreakStats($user, $streak, $milestone): void
    {
        // Mettre à jour des statistiques globales sur les séries
        // Vous pourriez avoir une table user_streak_stats

        $stats = [
            'longest_streak' => max($user->streaks()->max('best_count'), $streak->current_count),
            'total_milestones' => $user->streaks()->where('current_count', '>', 0)->count(),
            'active_streaks' => $user->streaks()->where('is_active', true)->count(),
        ];

        \Log::info("Statistiques de séries mises à jour", [
            'user_id' => $user->id,
            'stats' => $stats
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(StreakMilestone $event, \Throwable $exception): void
    {
        \Log::error("Échec du traitement StreakMilestone", [
            'user_id' => $event->user->id,
            'streak_id' => $event->streak->id,
            'milestone' => $event->milestone,
            'error' => $exception->getMessage()
        ]);
    }
}
