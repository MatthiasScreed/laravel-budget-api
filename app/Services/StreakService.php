<?php

namespace App\Services;

use App\Models\Streak;
use App\Models\User;

class StreakService
{
    public const MAX_FREEZES = 3;

    /**
     * Déclencher une streak pour un utilisateur
     */
    public function triggerStreak(User $user, string $type): array
    {
        $streak = $user->streaks()->where('type', $type)->first();

        if (! $streak) {
            $streak = $user->streaks()->create([
                'type'               => $type,
                'current_count'      => 0,
                'best_count'         => 0,
                'last_activity_date' => null,
                'is_active'          => true,
            ]);
        }

        $success = $streak->incrementStreak();

        if (! $success) {
            return [
                'success' => false,
                'message' => 'Action déjà comptabilisée aujourd\'hui',
                'streak'  => $this->formatStreakData($streak),
            ];
        }

        $bonusXp = $this->calculateStreakBonus($streak);

        if ($bonusXp > 0) {
            $user->addXp($bonusXp);
        }

        // 🧊 Attribuer un freeze aux milestones 7j et 30j
        $freezeAwarded = $this->awardFreezeAtMilestone($user, $streak);

        return [
            'success'        => true,
            'message'        => 'Streak mise à jour avec succès',
            'streak'         => $this->formatStreakData($streak->fresh()),
            'bonus_xp'       => $bonusXp,
            'is_milestone'   => $streak->isAtMilestone(),
            'next_milestone' => $streak->getNextMilestone(),
            'freeze_awarded' => $freezeAwarded,
        ];
    }

    /**
     * 🧊 Attribuer un freeze à l'utilisateur
     */
    public function awardFreeze(User $user, string $reason = 'milestone'): bool
    {
        if ($user->streak_freezes >= self::MAX_FREEZES) {
            return false;
        }

        $user->increment('streak_freezes');

        \Log::info('Streak freeze attribué', [
            'user_id' => $user->id,
            'reason'  => $reason,
            'total'   => $user->fresh()->streak_freezes,
        ]);

        return true;
    }

    /**
     * 🧊 Utiliser un freeze pour sauver une streak expirée
     */
    public function applyFreezeIfAvailable(User $user, Streak $streak): bool
    {
        if ($user->streak_freezes <= 0 || ! $streak->isEligibleForFreeze()) {
            return false;
        }

        $user->decrement('streak_freezes');
        $user->increment('streak_freezes_used');

        // Prolonge la streak à hier pour qu'elle survive
        $streak->last_activity_date = now()->subDay();
        $streak->save();

        \Log::info('Streak freeze utilisé automatiquement', [
            'user_id'      => $user->id,
            'streak_type'  => $streak->type,
            'streak_count' => $streak->current_count,
            'freezes_left' => $user->fresh()->streak_freezes,
        ]);

        return true;
    }

    /**
     * Calculer le bonus XP pour une streak
     */
    protected function calculateStreakBonus(Streak $streak): int
    {
        $baseXp         = 5;
        $milestoneBonus = 0;

        if ($streak->isAtMilestone()) {
            $milestoneBonus = match ($streak->current_count) {
                3   => 10,
                7   => 25,
                14  => 50,
                21  => 75,
                30  => 100,
                60  => 200,
                100 => 300,
                365 => 1000,
                default => 0
            };
        }

        $typeMultiplier = match ($streak->type) {
            Streak::TYPE_DAILY_LOGIN       => 1.0,
            Streak::TYPE_DAILY_TRANSACTION => 1.5,
            Streak::TYPE_WEEKLY_BUDGET     => 2.0,
            Streak::TYPE_MONTHLY_SAVING    => 3.0,
            default                        => 1.0
        };

        return intval(($baseXp * $typeMultiplier) + $milestoneBonus);
    }

    /**
     * Formater les données de streak pour l'API
     */
    protected function formatStreakData(Streak $streak): array
    {
        return [
            'id'                 => $streak->id,
            'type'               => $streak->type,
            'type_name'          => Streak::TYPES[$streak->type] ?? $streak->type,
            'current_count'      => $streak->current_count,
            'best_count'         => $streak->best_count,
            'last_activity_date' => $streak->last_activity_date?->toDateString(),
            'is_active'          => $streak->is_active,
            'risk_level'         => $streak->getRiskLevel(),
            'next_milestone'     => $streak->getNextMilestone(),
            'is_at_milestone'    => $streak->isAtMilestone(),
            'can_claim_bonus'    => $streak->canClaimBonus(),
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
            'streaks'               => $streaks->map(fn ($s) => $this->formatStreakData($s)),
            'total_active'          => $streaks->where('is_active', true)->count(),
            'best_streak'           => $streaks->max('best_count') ?? 0,
            'total_bonus_available' => $streaks->sum(fn ($s) => $s->calculateBonusXp()),
            'freezes_available'     => $user->streak_freezes,
            'freezes_used'          => $user->streak_freezes_used,
            'max_freezes'           => self::MAX_FREEZES,
        ];
    }

    /**
     * Réclamer le bonus d'une streak
     */
    public function claimStreakBonus(User $user, string $streakType): array
    {
        $streak = $user->streaks()->where('type', $streakType)->first();

        if (! $streak) {
            return ['success' => false, 'message' => 'Streak non trouvée'];
        }

        if (! $streak->canClaimBonus()) {
            return ['success' => false, 'message' => 'Aucun bonus disponible pour cette streak'];
        }

        $bonusXp       = $streak->claimBonus();
        $levelUpResult = $user->addXp($bonusXp);

        return [
            'success'      => true,
            'message'      => 'Bonus réclamé avec succès',
            'bonus_xp'     => $bonusXp,
            'new_total_xp' => $user->fresh()->getTotalXp(),
            'new_level'    => $user->fresh()->getCurrentLevel(),
            'leveled_up'   => $levelUpResult['leveled_up'] ?? false,
            'streak'       => $this->formatStreakData($streak->fresh()),
        ];
    }

    /**
     * Vérifier et briser les streaks expirées — avec protection freeze
     */
    public function checkExpiredStreaks(User $user): array
    {
        $streaks       = $user->streaks()->active()->get();
        $brokenStreaks = [];
        $frozenStreaks = [];

        foreach ($streaks as $streak) {
            if (! $streak->checkIfBroken()) {
                continue;
            }

            // Tentative de sauvegarde par freeze
            if ($this->applyFreezeIfAvailable($user, $streak)) {
                $streak->is_active = true;
                $streak->save();
                $frozenStreaks[] = $this->formatStreakData($streak->fresh());
            } else {
                $brokenStreaks[] = $this->formatStreakData($streak);
            }
        }

        return [
            'broken_streaks' => $brokenStreaks,
            'frozen_streaks' => $frozenStreaks,
            'count'          => count($brokenStreaks),
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
                    'rank'          => $index + 1,
                    'user_name'     => $streak->user->name,
                    'user_id'       => $streak->user->id,
                    'best_count'    => $streak->best_count,
                    'current_count' => $streak->current_count,
                    'is_active'     => $streak->is_active,
                    'type'          => $streak->type,
                    'type_name'     => Streak::TYPES[$streak->type] ?? $streak->type,
                ];
            }),
            'streak_type' => $type,
            'type_name'   => Streak::TYPES[$type] ?? $type,
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
     */
    public function updateStreak(User $user, string $streakType): bool
    {
        try {
            $streakService = app(\App\Services\StreakService::class);
            $result        = $streakService->triggerStreak($user, $streakType);

            return $result['success'] ?? false;
        } catch (\Exception $e) {
            \Log::error("Error updating streak {$streakType} for user {$user->id}: ".$e->getMessage());

            return false;
        }
    }

    // ==========================================
    // HELPERS PRIVÉS
    // ==========================================

    /**
     * Attribuer un freeze aux milestones 7j et 30j
     */
    private function awardFreezeAtMilestone(User $user, Streak $streak): bool
    {
        if (! $streak->isAtMilestone()) {
            return false;
        }

        if (! in_array($streak->current_count, [7, 30])) {
            return false;
        }

        return $this->awardFreeze($user, "milestone_{$streak->current_count}j");
    }
}
