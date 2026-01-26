<?php

namespace App\Listeners;

use App\Events\LevelUp;
use App\Notifications\LevelUpNotification;
use App\Services\GamingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleLevelUp implements ShouldQueue
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
    public function handle(LevelUp $event): void
    {
        $user = $event->user;
        $levelData = $event->levelData;

        // ✅ Extraire les données du tableau levelData
        $newLevel = $levelData['new_level'] ?? 1;
        $previousLevel = $newLevel - ($levelData['levels_gained'] ?? 1);
        $totalXp = $levelData['total_xp'] ?? 0;

        try {
            // 1. Bonus XP pour le niveau atteint
            $levelBonus = $this->calculateLevelBonus($newLevel);
            $this->gamingService->addExperience($user, $levelBonus, 'level_bonus');

            // 2. Notification de félicitations (si la classe existe)
            try {
                $userLevel = $user->level;
                if ($userLevel && class_exists(LevelUpNotification::class)) {
                    $user->notify(new LevelUpNotification(
                        $userLevel,
                        $previousLevel,
                        $newLevel
                    ));
                }
            } catch (\Exception $e) {
                \Log::warning('Notification LevelUp impossible', [
                    'error' => $e->getMessage(),
                ]);
            }

            // 3. Débloquer contenu exclusif selon le niveau
            $this->unlockLevelRewards($user, $newLevel);

            // 4. Vérifier les jalons de niveaux spéciaux
            $this->checkLevelMilestones($user, $newLevel);

            // 5. Mettre à jour les permissions/accès utilisateur
            $this->updateUserPermissions($user, $newLevel);

            \Log::info('Montée de niveau - Récompenses distribuées', [
                'user_id' => $user->id,
                'previous_level' => $previousLevel,
                'new_level' => $newLevel,
                'total_xp' => $totalXp,
                'level_bonus' => $levelBonus,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur lors du traitement de la montée de niveau', [
                'user_id' => $user->id,
                'new_level' => $newLevel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate level up bonus
     */
    private function calculateLevelBonus($level): int
    {
        // Plus le niveau est élevé, plus le bonus est important
        return $level * 50;
    }

    /**
     * Unlock rewards based on level
     */
    private function unlockLevelRewards($user, $level): void
    {
        $rewards = [
            5 => 'custom_avatar',
            10 => 'premium_stats',
            15 => 'advanced_goals',
            20 => 'export_features',
            25 => 'priority_support',
            30 => 'beta_features',
            50 => 'lifetime_premium',
        ];

        if (isset($rewards[$level])) {
            // Ici vous pourriez débloquer des fonctionnalités premium
            \Log::info("Récompense débloquée au niveau {$level}", [
                'user_id' => $user->id,
                'reward' => $rewards[$level],
            ]);
        }
    }

    /**
     * Check special level milestones
     */
    private function checkLevelMilestones($user, $level): void
    {
        $specialLevels = [10, 25, 50, 75, 100];

        if (in_array($level, $specialLevels)) {
            // Succès spéciaux pour ces niveaux
            $bonusXp = $level * 100;
            $this->gamingService->addExperience($user, $bonusXp, 'special_level_milestone');
        }
    }

    /**
     * Update user permissions based on level
     */
    private function updateUserPermissions($user, $level): void
    {
        // Exemple : débloquer des fonctionnalités selon le niveau
        $permissions = [];

        if ($level >= 10) {
            $permissions[] = 'advanced_analytics';
        }

        if ($level >= 20) {
            $permissions[] = 'data_export';
        }

        if ($level >= 30) {
            $permissions[] = 'api_access';
        }

        // Sauvegarder les permissions (vous pourriez avoir une table user_permissions)
        if (! empty($permissions)) {
            \Log::info('Permissions mises à jour', [
                'user_id' => $user->id,
                'level' => $level,
                'new_permissions' => $permissions,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(LevelUp $event, \Throwable $exception): void
    {
        $levelData = $event->levelData;
        $newLevel = $levelData['new_level'] ?? '?';

        \Log::error('Échec du traitement LevelUp', [
            'user_id' => $event->user->id,
            'new_level' => $newLevel,
            'error' => $exception->getMessage(),
        ]);
    }
}
