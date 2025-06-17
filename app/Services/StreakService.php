<?php

namespace App\Services;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class StreakService
{

    /**
     * 🔥 DÉCLENCHER STREAK DEPUIS N'IMPORTE OÙ
     */
    public function triggerStreak(User $user, string $streakType): array
    {
        try {
            $streak = $user->streaks()->firstOrCreate([
                'type' => $streakType
            ]);

            $wasIncremented = $streak->increment();

            if (!$wasIncremented) {
                return [
                    'success' => false,
                    'message' => 'Streak déjà comptabilisée aujourd\'hui',
                    'streak' => $this->formatStreak($streak)
                ];
            }

            // Calculer bonus XP automatique
            $bonusXp = $this->calculateStreakBonus($streak);
            if ($bonusXp > 0) {
                $user->addXp($bonusXp);
            }

            // Vérifier achievements liés aux streaks
            $this->checkStreakAchievements($user, $streak);

            return [
                'success' => true,
                'message' => $this->getStreakMessage($streak),
                'streak' => $this->formatStreak($streak),
                'bonus_xp' => $bonusXp,
                'is_milestone' => $streak->isAtMilestone()
            ];

        } catch (\Exception $e) {
            Log::error('Erreur streak service', [
                'user_id' => $user->id,
                'streak_type' => $streakType,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la streak'
            ];
        }
    }

    /**
     * Message contextualisé
     */
    protected function getStreakMessage(Streak $streak): string
    {
        $count = $streak->current_count;
        $type = Streak::TYPES[$streak->type] ?? 'activité';

        if ($streak->isAtMilestone()) {
            return "🎉 Incroyable ! {$count} jours de {$type} ! Milestone atteint !";
        }

        return match(true) {
            $count >= 30 => "🔥 {$count} jours de {$type} ! Tu es en feu !",
            $count >= 14 => "💪 {$count} jours de {$type} ! Excellente régularité !",
            $count >= 7 => "⭐ {$count} jours de {$type} ! Une semaine complète !",
            $count >= 3 => "👍 {$count} jours de {$type} ! Continue comme ça !",
            default => "🎯 Première journée de {$type} ! C'est parti !"
        };
    }

    /**
     * Formater pour l'API
     */
    protected function formatStreak(Streak $streak): array
    {
        return [
            'type' => $streak->type,
            'type_name' => Streak::TYPES[$streak->type] ?? $streak->type,
            'current_count' => $streak->current_count,
            'best_count' => $streak->best_count,
            'last_activity' => $streak->last_activity_date?->format('Y-m-d'),
            'next_milestone' => $streak->getNextMilestone(),
            'risk_level' => $streak->getRiskLevel(),
            'bonus_available' => $streak->canClaimBonus(),
            'potential_bonus_xp' => $streak->calculateBonusXp()
        ];
    }

    /**
     * Vérifier achievements liés aux streaks
     */
    protected function checkStreakAchievements(User $user, Streak $streak): void
    {
        // Cette méthode sera appelée automatiquement
        // et vérifiera les achievements basés sur les streaks
        $user->checkAndUnlockAchievements();
    }

    /**
     * Obtenir toutes les streaks d'un utilisateur
     */
    public function getUserStreaks(User $user): array
    {
        return $user->streaks()->active()->get()
            ->map(fn($streak) => $this->formatStreak($streak))
            ->toArray();
    }

    /**
     * Récupérer une streak spécifique
     */
    public function getStreak(User $user, string $type): ?array
    {
        $streak = $user->streaks()->where('type', $type)->first();
        return $streak ? $this->formatStreak($streak) : null;
    }


}
