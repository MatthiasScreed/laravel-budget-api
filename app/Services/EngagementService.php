<?php

namespace App\Services;

use App\Models\GamingEvent;
use App\Models\User;
use App\Models\UserAction;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EngagementService
{
    protected GamingService $gamingService;

    public function __construct(GamingService $gamingService)
    {
        $this->gamingService = $gamingService;
    }

    /**
     * Tracker une action utilisateur et déclencher les rewards
     */
    public function trackUserAction(
        User $user,
        string $actionType,
        ?string $context = null,
        ?array $metadata = null
    ): array {
        DB::beginTransaction();

        try {
            // 1. Enregistrer l'action
            $action = UserAction::trackAction(
                $user->id,
                $actionType,
                $context,
                $metadata
            );

            // 2. Appliquer les multiplicateurs d'événements actifs
            $baseXp = $action->xp_gained;
            $finalXp = $this->applyEventMultipliers($baseXp);

            // 3. Ajouter l'XP via le GamingService existant
            $gamingResult = $this->gamingService->addExperience(
                $user,
                $finalXp,
                $actionType
            );

            // 4. Créer notification si XP gagné
            if ($finalXp > 0) {
                UserNotification::createXpGained(
                    $user->id,
                    $finalXp,
                    $this->getActionDescription($actionType)
                );
            }

            // 5. Vérifier les achievements et level ups
            $newAchievements = [];
            $levelUpInfo = null;

            if (isset($gamingResult['achievements'])) {
                foreach ($gamingResult['achievements'] as $achievement) {
                    $newAchievements[] = $achievement;
                    UserNotification::createAchievementUnlocked($user->id, $achievement);
                }
            }

            if (isset($gamingResult['level_up'])) {
                $levelUpInfo = $gamingResult['level_up'];
                UserNotification::createLevelUp(
                    $user->id,
                    $levelUpInfo['old_level'],
                    $levelUpInfo['new_level']
                );
            }

            // 6. Mettre à jour les stats d'engagement
            $this->updateEngagementScore($user);

            DB::commit();

            // 7. Broadcaster les événements
            event(new UserEngaged($user, $actionType, $finalXp));

            return [
                'action_tracked' => true,
                'xp_gained' => $finalXp,
                'base_xp' => $baseXp,
                'multiplier_applied' => $finalXp > $baseXp,
                'total_xp' => $user->fresh()->level?->total_xp ?? 0,
                'current_level' => $user->fresh()->level?->level ?? 1,
                'achievements_unlocked' => $newAchievements,
                'level_up' => $levelUpInfo,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Engagement tracking failed: '.$e->getMessage());

            return [
                'action_tracked' => false,
                'error' => 'Failed to track action',
                'xp_gained' => 0,
            ];
        }
    }

    /**
     * Appliquer les multiplicateurs des événements actifs
     */
    protected function applyEventMultipliers(int $baseXp): int
    {
        $activeEvents = Cache::remember('active_gaming_events', 300, function () {
            return GamingEvent::active()->get();
        });

        $finalXp = $baseXp;

        foreach ($activeEvents as $event) {
            $finalXp = $event->applyMultiplier($finalXp);
        }

        return $finalXp;
    }

    /**
     * Calculer et mettre à jour le score d'engagement d'un utilisateur
     */
    public function updateEngagementScore(User $user): float
    {
        $score = Cache::remember("engagement_score_{$user->id}", 1800, function () use ($user) {
            // Facteurs d'engagement (derniers 7 jours)
            $weekAgo = now()->subWeek();

            $dailyLogins = UserAction::where('user_id', $user->id)
                ->where('action_type', 'daily_login')
                ->where('created_at', '>=', $weekAgo)
                ->count();

            $totalActions = UserAction::where('user_id', $user->id)
                ->where('created_at', '>=', $weekAgo)
                ->count();

            $transactionActions = UserAction::where('user_id', $user->id)
                ->where('action_type', 'transaction_add')
                ->where('created_at', '>=', $weekAgo)
                ->count();

            $goalInteractions = UserAction::where('user_id', $user->id)
                ->whereIn('action_type', ['goal_create', 'goal_contribute'])
                ->where('created_at', '>=', $weekAgo)
                ->count();

            // Calcul du score (0-100)
            $score = 0;
            $score += min(35, $dailyLogins * 5); // Max 35 points (7 jours * 5)
            $score += min(30, $totalActions * 0.5); // Max 30 points
            $score += min(20, $transactionActions * 2); // Max 20 points
            $score += min(15, $goalInteractions * 3); // Max 15 points

            return round($score, 2);
        });

        $user->update(['engagement_score' => $score]);

        return $score;
    }

    /**
     * Obtenir les statistiques d'engagement d'un utilisateur
     */
    public function getEngagementStats(User $user): array
    {
        $weekAgo = now()->subWeek();
        $monthAgo = now()->subMonth();

        return Cache::remember("engagement_stats_{$user->id}", 900, function () use ($user, $weekAgo, $monthAgo) {
            return [
                'current_score' => $user->engagement_score ?? 0,
                'weekly_stats' => [
                    'total_actions' => UserAction::where('user_id', $user->id)
                        ->where('created_at', '>=', $weekAgo)->count(),
                    'daily_logins' => UserAction::where('user_id', $user->id)
                        ->where('action_type', 'daily_login')
                        ->where('created_at', '>=', $weekAgo)->count(),
                    'xp_earned' => UserAction::where('user_id', $user->id)
                        ->where('created_at', '>=', $weekAgo)
                        ->sum('xp_gained'),
                ],
                'monthly_stats' => [
                    'total_actions' => UserAction::where('user_id', $user->id)
                        ->where('created_at', '>=', $monthAgo)->count(),
                    'xp_earned' => UserAction::where('user_id', $user->id)
                        ->where('created_at', '>=', $monthAgo)
                        ->sum('xp_gained'),
                ],
                'streak_info' => $this->calculateCurrentStreak($user),
                'active_events' => GamingEvent::active()->get(),
                'rank_info' => $this->getUserRankInfo($user),
            ];
        });
    }

    /**
     * Description lisible des actions pour les notifications
     */
    protected function getActionDescription(string $actionType): string
    {
        return match ($actionType) {
            'page_view' => 'Page consultée',
            'button_click' => 'Interface utilisée',
            'transaction_add' => 'Transaction ajoutée !',
            'goal_create' => 'Nouvel objectif créé !',
            'goal_contribute' => 'Contribution à un objectif',
            'achievement_view' => 'Achievements consultés',
            'share_success' => 'Succès partagé !',
            'daily_login' => 'Connexion quotidienne',
            default => 'Action effectuée'
        };
    }

    /**
     * Calculer le streak actuel de connexion
     */
    protected function calculateCurrentStreak(User $user): array
    {
        $currentStreak = 0;
        $maxStreak = 0;
        $lastLoginDate = null;

        $recentLogins = UserAction::where('user_id', $user->id)
            ->where('action_type', 'daily_login')
            ->orderBy('created_at', 'desc')
            ->take(30)
            ->pluck('created_at')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->unique()
            ->values();

        if ($recentLogins->isEmpty()) {
            return ['current' => 0, 'max' => 0, 'last_login' => null];
        }

        // Calculer le streak actuel
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        if ($recentLogins->first() === $today || $recentLogins->first() === $yesterday) {
            $currentDate = now()->format('Y-m-d');

            foreach ($recentLogins as $loginDate) {
                if ($loginDate === $currentDate ||
                    $loginDate === now()->subDays($currentStreak + 1)->format('Y-m-d')) {
                    $currentStreak++;
                    $currentDate = now()->subDays($currentStreak)->format('Y-m-d');
                } else {
                    break;
                }
            }
        }

        return [
            'current' => $currentStreak,
            'max' => max($currentStreak, $user->max_streak ?? 0),
            'last_login' => $recentLogins->first(),
        ];
    }

    /**
     * Obtenir les infos de classement de l'utilisateur
     */
    protected function getUserRankInfo(User $user): array
    {
        $userLevel = $user->level?->level ?? 1;
        $userXp = $user->level?->total_xp ?? 0;

        $rank = Cache::remember("user_rank_{$user->id}", 600, function () use ($userXp) {
            return User::join('user_levels', 'users.id', '=', 'user_levels.user_id')
                ->where('user_levels.total_xp', '>', $userXp)
                ->count() + 1;
        });

        $totalUsers = Cache::remember('total_active_users', 600, function () {
            return User::whereHas('level')->count();
        });

        return [
            'current_rank' => $rank,
            'total_users' => $totalUsers,
            'percentile' => $totalUsers > 0 ? round((($totalUsers - $rank) / $totalUsers) * 100, 1) : 0,
            'league_tier' => $this->calculateLeagueTier($userLevel, $rank, $totalUsers),
        ];
    }

    /**
     * Calculer la ligue de l'utilisateur
     */
    protected function calculateLeagueTier(int $level, int $rank, int $totalUsers): string
    {
        if ($level >= 50 || $rank <= max(1, $totalUsers * 0.01)) {
            return 'diamond';
        }
        if ($level >= 30 || $rank <= max(1, $totalUsers * 0.05)) {
            return 'platinum';
        }
        if ($level >= 20 || $rank <= max(1, $totalUsers * 0.15)) {
            return 'gold';
        }
        if ($level >= 10 || $rank <= max(1, $totalUsers * 0.35)) {
            return 'silver';
        }

        return 'bronze';
    }
}
