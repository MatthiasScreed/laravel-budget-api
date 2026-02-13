<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Service de Gaming Progressif
 * Gère la configuration et l'adaptation du système gaming
 */
class ProgressiveGamingService
{
    /**
     * Configuration par défaut des notifications
     */
    protected array $defaultNotificationConfig = [
        'achievements' => true,
        'level_ups' => true,
        'streaks' => true,
        'challenges' => true,
        'xp_gains' => true,
        'sound_enabled' => true,
        'vibration_enabled' => true,
    ];

    /**
     * Obtenir la configuration gaming complète
     */
    public function getGamingConfig(): array
    {
        $user = Auth::user();

        try {
            return [
                'success' => true,
                'data' => [
                    'features' => $this->getFeatureConfig($user),
                    'notifications' => $this->getNotificationConfig($user),
                    'display' => $this->getDisplayConfig($user),
                    'xp_multipliers' => $this->getXpMultipliers($user),
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Gaming config error', [
                'userId' => $user?->id,
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtenir la configuration des notifications
     * ⚠️ MÉTHODE MANQUANTE QUI CAUSAIT L'ERREUR
     */
    public function getNotificationConfig(?User $user = null): array
    {
        // Récupérer les préférences utilisateur ou utiliser défaut
        $preferences = $user?->preferences ?? [];
        $notifPrefs = $preferences['gaming_notifications'] ?? [];

        return array_merge(
            $this->defaultNotificationConfig,
            $notifPrefs
        );
    }

    /**
     * Obtenir la configuration des fonctionnalités
     */
    protected function getFeatureConfig(?User $user): array
    {
        $level = $user?->level?->level ?? 1;

        return [
            'achievements_enabled' => true,
            'streaks_enabled' => true,
            'challenges_enabled' => $level >= 3,
            'leaderboard_enabled' => $level >= 5,
            'social_features' => $level >= 10,
        ];
    }

    /**
     * Obtenir la configuration d'affichage
     */
    protected function getDisplayConfig(?User $user): array
    {
        return [
            'show_xp_bar' => true,
            'show_level_badge' => true,
            'show_streak_indicator' => true,
            'compact_mode' => false,
            'animations_enabled' => true,
        ];
    }

    /**
     * Obtenir les multiplicateurs XP
     */
    protected function getXpMultipliers(?User $user): array
    {
        $baseMultiplier = 1.0;

        // Bonus streak actif
        $streakBonus = $this->calculateStreakBonus($user);

        // Bonus weekend
        $weekendBonus = $this->isWeekend() ? 0.25 : 0;

        return [
            'base' => $baseMultiplier,
            'streak_bonus' => $streakBonus,
            'weekend_bonus' => $weekendBonus,
            'total' => $baseMultiplier + $streakBonus + $weekendBonus,
        ];
    }

    /**
     * Calculer le bonus de streak
     */
    protected function calculateStreakBonus(?User $user): float
    {
        if (!$user) {
            return 0;
        }

        $activeStreak = $user->streaks()
            ->where('is_active', true)
            ->orderBy('current_count', 'desc')
            ->first();

        if (!$activeStreak) {
            return 0;
        }

        // 5% bonus par 7 jours de streak (max 50%)
        $bonus = floor($activeStreak->current_count / 7) * 0.05;

        return min($bonus, 0.5);
    }

    /**
     * Vérifier si c'est le weekend
     */
    protected function isWeekend(): bool
    {
        return in_array(now()->dayOfWeek, [0, 6]);
    }
}
