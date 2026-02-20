<?php

namespace App\Listeners;

use App\Events\GoalCreated;
use App\Models\FinancialInsight;
use App\Services\GamingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener sur GoalCreated :
 *   1. Ajoute XP gaming
 *   2. ✅ Dismiss les insights "pas d'objectif" devenus obsolètes
 */
class HandleGoalCreated implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Titres d'insights invalidés par la création d'un objectif
     */
    private const OBSOLETE_TITLES = [
        'Créez votre premier objectif',
        'Taux d\'épargne faible',
    ];

    public function __construct(private GamingService $gamingService)
    {
    }

    public function handle(GoalCreated $event): void
    {
        $user = $event->user;

        $this->addGamingXp($user);
        $this->dismissObsoleteInsights($user);
    }

    // ========================================
    // PRIVATE
    // ========================================

    private function addGamingXp($user): void
    {
        try {
            $this->gamingService->addExperience($user, 25, 'goal_created');
            $this->gamingService->updateStreak($user, 'goal_creation');
            $this->gamingService->checkAchievements($user);
        } catch (\Exception $e) {
            Log::warning('HandleGoalCreated — XP error', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * ✅ Marque comme "acted" les insights qui incitaient à créer un objectif.
     *
     * On utilise markAsActed() (acted_at + is_read) plutôt que delete()
     * pour conserver l'historique et créditer l'action à l'utilisateur.
     * Seuls les insights non encore actés et non rejetés sont traités.
     */
    private function dismissObsoleteInsights($user): void
    {
        try {
            $insights = FinancialInsight::where('user_id', $user->id)
                ->where('type', 'goal_acceleration')
                ->whereIn('title', self::OBSOLETE_TITLES)
                ->whereNull('acted_at')
                ->where('is_dismissed', false)
                ->get();

            foreach ($insights as $insight) {
                $insight->markAsActed();
            }

            if ($insights->isNotEmpty()) {
                Log::info('HandleGoalCreated — insights obsolètes marqués comme actés', [
                    'user_id' => $user->id,
                    'count'   => $insights->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('HandleGoalCreated — erreur dismiss insights', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
