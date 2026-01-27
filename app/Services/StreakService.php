<?php

namespace App\Services;

use App\Models\Streak;
use App\Models\User;

class StreakService
{
    /**
     * Déclencher une streak pour un utilisateur
     */
    public function triggerStreak(User $user, string $type): array
    {
        $streak = $user->streaks()->where('type', $type)->first();

        if (! $streak) {
            $streak = $user->streaks()->create([
                'type' => $type,
                'current_count' => 0,
                'best_count' => 0,
                'last_activity_date' => null,
                'is_active' => true,
            ]);
        }

        $success = $streak->incrementStreak();

        if (! $success) {
            return [
                'success' => false,
                'message' => 'Action déjà comptabilisée aujourd\'hui',
                'streak' => $this->formatStreakData($streak),
            ];
        }

        $bonusXp = $this->calculateStreakBonus($streak);

        if ($bonusXp > 0) {
            $user->addXp($bonusXp);
        }

        return [
            'success' => true,
            'message' => 'Streak mise à jour avec succès',
            'streak' => $this->formatStreakData($streak->fresh()),
            'bonus_xp' => $bonusXp,
            'is_milestone' => $streak->isAtMilestone(),
            'next_milestone' => $streak->getNextMilestone(),
        ];
    }

    /**
     * Calculer le bonus XP pour une streak
     */
    protected function calculateStreakBonus(Streak $streak): int
    {
        $baseXp = 5; // XP de base pour chaque jour
        $milestoneBonus = 0;

        // Bonus pour les milestones
        if ($streak->isAtMilestone()) {
            $milestoneBonus = match ($streak->current_count) {
                3 => 10,
                7 => 25,
                14 => 50,
                21 => 75,
                30 => 100,
                60 => 200,
                100 => 300,
                365 => 1000,
                default => 0
            };
        }

        // Bonus selon le type de streak
        $typeMultiplier = match ($streak->type) {
            Streak::TYPE_DAILY_LOGIN => 1.0,
            Streak::TYPE_DAILY_TRANSACTION => 1.5,
            Streak::TYPE_WEEKLY_BUDGET => 2.0,
            Streak::TYPE_MONTHLY_SAVING => 3.0,
            default => 1.0
        };

        return intval(($baseXp * $typeMultiplier) + $milestoneBonus);
    }

    /**
     * Formater les données de streak pour l'API
     */
    protected function formatStreakData(Streak $streak): array
    {
        return [
            'id' => $streak->id,
            'type' => $streak->type,
            'type_name' => Streak::TYPES[$streak->type] ?? $streak->type,
            'current_count' => $streak->current_count,
            'best_count' => $streak->best_count,
            'last_activity_date' => $streak->last_activity_date?->toDateString(),
            'is_active' => $streak->is_active,
            'risk_level' => $streak->getRiskLevel(),
            'next_milestone' => $streak->getNextMilestone(),
            'is_at_milestone' => $streak->isAtMilestone(),
            'can_claim_bonus' => $streak->canClaimBonus(),
            'bonus_xp_available' => $streak->calculateBonusXp(),
        ];
    }

    /**
     * Obtenir toutes les streaks d'un utilisateur
     */
    public function getUserStreaks(User $user): array
    {
        $streaks = $user->streaks()->get();

        return [
            'streaks' => $streaks->map(fn ($streak) => $this->formatStreakData($streak)),
            'total_active' => $streaks->where('is_active', true)->count(),
            'best_streak' => $streaks->max('best_count') ?? 0,
            'total_bonus_available' => $streaks->sum(fn ($s) => $s->calculateBonusXp()),
        ];
    }

    /**
     * Réclamer le bonus d'une streak
     */
    public function claimStreakBonus(User $user, string $streakType): array
    {
        $streak = $user->streaks()->where('type', $streakType)->first();

        if (! $streak) {
            return [
                'success' => false,
                'message' => 'Streak non trouvée',
            ];
        }

        if (! $streak->canClaimBonus()) {
            return [
                'success' => false,
                'message' => 'Aucun bonus disponible pour cette streak',
            ];
        }

        $bonusXp = $streak->claimBonus();
        $levelUpResult = $user->addXp($bonusXp);

        return [
            'success' => true,
            'message' => 'Bonus réclamé avec succès',
            'bonus_xp' => $bonusXp,
            'new_total_xp' => $user->fresh()->getTotalXp(),
            'new_level' => $user->fresh()->getCurrentLevel(),
            'leveled_up' => $levelUpResult['leveled_up'] ?? false,
            'streak' => $this->formatStreakData($streak->fresh()),
        ];
    }

    /**
     * Vérifier et briser les streaks expirées
     */
    public function checkExpiredStreaks(User $user): array
    {
        $streaks = $user->streaks()->active()->get();
        $brokenStreaks = [];

        foreach ($streaks as $streak) {
            if ($streak->checkIfBroken()) {
                $brokenStreaks[] = $this->formatStreakData($streak);
            }
        }

        return [
            'broken_streaks' => $brokenStreaks,
            'count' => count($brokenStreaks),
        ];
    }

    /**
     * Obtenir le leaderboard des streaks
     */
    public function getLeaderboard(string $type, int $limit = 10): array
    {
        $topStreaks = Streak::where('type', $type)
            ->with('user:id,name')
            ->orderBy('best_count', 'desc')
            ->orderBy('current_count', 'desc')
            ->limit($limit)
            ->get();

        return [
            'leaderboard' => $topStreaks->map(function ($streak, $index) {
                return [
                    'rank' => $index + 1,
                    'user_name' => $streak->user->name,
                    'user_id' => $streak->user->id,
                    'best_count' => $streak->best_count,
                    'current_count' => $streak->current_count,
                    'is_active' => $streak->is_active,
                    'type' => $streak->type,
                    'type_name' => Streak::TYPES[$streak->type] ?? $streak->type,
                ];
            }),
            'streak_type' => $type,
            'type_name' => Streak::TYPES[$type] ?? $type,
        ];
    }

    /**
     * Obtenir le rang d'un utilisateur pour un type de streak
     */
    public function getUserRank(User $user, string $type): ?int
    {
        $userStreak = $user->streaks()->where('type', $type)->first();

        if (! $userStreak) {
            return null;
        }

        $betterCount = Streak::where('type', $type)
            ->where(function ($query) use ($userStreak) {
                $query->where('best_count', '>', $userStreak->best_count)
                    ->orWhere(function ($subQuery) use ($userStreak) {
                        $subQuery->where('best_count', $userStreak->best_count)
                            ->where('current_count', '>', $userStreak->current_count);
                    });
            })
            ->count();

        return $betterCount + 1;
    }

    /**
     * Mettre à jour une série pour un utilisateur
     *
     * ✅ Déléguer au StreakService au lieu de gérer directement
     *
     * @param  User  $user  Utilisateur concerné
     * @param  string  $streakType  Type de série
     * @return bool Série mise à jour avec succès
     */
    public function updateStreak(User $user, string $streakType): bool
    {
        try {
            // ✅ Utiliser le StreakService dédié
            $streakService = app(\App\Services\StreakService::class);
            $result = $streakService->triggerStreak($user, $streakType);

            return $result['success'] ?? false;

        } catch (\Exception $e) {
            \Log::error("Error updating streak {$streakType} for user {$user->id}: ".$e->getMessage());

            return false;
        }
    }
}
