<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GamingController extends Controller
{
    /**
     * Obtenir les statistiques gaming (alias pour stats)
     */
    public function getStats(Request $request): JsonResponse
    {
        return $this->stats($request);
    }

    /**
     * Obtenir les statistiques gaming de l'utilisateur
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->ensureUserLevelExists($user);

        $stats = $user->getGamingStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Statistiques gaming récupérées avec succès',
        ]);
    }

    /**
     * ✅ NOUVELLE MÉTHODE: Données du joueur (appelée par frontend)
     */
    public function getPlayerData(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureUserLevelExists($user);

        $playerData = [
            'id' => $user->id,
            'name' => $user->name,
            'level' => $user->level->level,
            'total_xp' => $user->level->total_xp,
            'current_level_xp' => $user->level->current_level_xp,
            'next_level_xp' => $user->level->next_level_xp,
            'achievements_unlocked' => $user->achievements()->count(),
            'streak_days' => $this->calculateActiveStreakDays($user),
            'user_level' => [
                'id' => $user->level->id,
                'user_id' => $user->id,
                'level' => $user->level->level,
                'total_xp' => $user->level->total_xp,
                'current_level_xp' => $user->level->current_level_xp,
                'next_level_xp' => $user->level->next_level_xp,
                'created_at' => $user->level->created_at->toISOString(),
                'updated_at' => $user->level->updated_at->toISOString(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $playerData,
            'message' => 'Données joueur récupérées',
        ]);
    }

    /**
     * Mettre à jour les préférences du joueur
     */
    public function updatePlayer(UpdatePlayerRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            // Mettre à jour les données utilisateur
            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }

            if (isset($validated['preferences'])) {
                $user->gaming_preferences = $validated['preferences'];
            }

            if (isset($validated['notification_settings'])) {
                $user->notification_settings = $validated['notification_settings'];
            }

            $user->save();

            return response()->json([
                'success' => true,
                'data' => $user->fresh(['level', 'achievements', 'streaks']),
                'message' => 'Données du joueur mises à jour avec succès',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du joueur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ NOUVELLE MÉTHODE: Ajouter de l'XP (appelée par frontend)
     */
    public function addXP(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:1|max:1000',
            'source' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $this->ensureUserLevelExists($user);

        $amount = $request->input('amount');
        $source = $request->input('source', 'manual');
        $description = $request->input('description', "Gain de {$amount} XP");

        // Sauvegarder le niveau actuel pour vérifier le level up
        $oldLevel = $user->level->level;
        $oldTotalXP = $user->level->total_xp;

        // Ajouter l'XP
        $user->level->total_xp += $amount;
        $user->level->current_level_xp += $amount;

        // Vérifier les level ups
        $leveledUp = false;
        $newLevel = $user->level->level;

        while ($user->level->current_level_xp >= $user->level->next_level_xp) {
            $user->level->current_level_xp -= $user->level->next_level_xp;
            $user->level->level += 1;
            $newLevel = $user->level->level;

            // Calculer XP nécessaire pour le niveau suivant
            $user->level->next_level_xp = $this->calculateXPForLevel($newLevel + 1);

            $leveledUp = true;
        }

        $user->level->save();

        // Vérifier les achievements débloqués
        $achievements = [];
        try {
            $unlockedAchievements = $user->checkAndUnlockAchievements();
            $achievements = array_map(function ($ach) {
                return [
                    'id' => $ach->id,
                    'name' => $ach->name,
                    'points' => $ach->points,
                ];
            }, $unlockedAchievements);
        } catch (\Exception $e) {
            // Ignorer les erreurs d'achievements
        }

        $result = [
            'xp_gained' => $amount,
            'level_up' => $leveledUp,
            'old_level' => $oldLevel,
            'achievements_unlocked' => array_column($achievements, 'name'),
        ];

        if ($leveledUp) {
            $result['new_level'] = $newLevel;
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $leveledUp
                ? "Level UP! Niveau {$newLevel} atteint (+{$amount} XP)"
                : "+{$amount} XP gagné",
        ]);
    }

    /**
     * Dashboard gaming complet (alias pour getDashboard)
     */
    public function getDashboard(Request $request): JsonResponse
    {
        return $this->dashboard($request);
    }


    /**
     * Résumé gaming rapide pour l'accueil ou mini-widgets
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureUserLevelExists($user);

        $stats = $user->getGamingStats();
        $levelInfo = $stats['level_info'] ?? [];

        // ✅ Calculer les points depuis les achievements débloqués
        $totalPoints = $user->achievements()->sum('points');

        $summary = [
            'level' => $levelInfo['current_level'] ?? 1,
            'xp' => $levelInfo['total_xp'] ?? 0,
            'xp_for_next_level' => $levelInfo['next_level_xp'] ?? 100,
            'progress_percent' => $levelInfo['progress_percentage'] ?? 0,
            'points' => $totalPoints,
            'rank' => $levelInfo['title'] ?? 'Novice',
            'active_streaks_count' => $user->streaks()->where('is_active', true)->count(),
            'achievements_unlocked_count' => $stats['achievements_count'] ?? 0,
            'recent_xp_gained' => $user->achievements()
                ->wherePivot('unlocked_at', '>=', now()->subWeek())
                ->sum('points'),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
            'message' => 'Résumé gaming récupéré avec succès',
        ]);
    }

    /**
     * ✅ Classement des utilisateurs par points XP
     */
    public function getLeaderboard(Request $request): JsonResponse
    {
        $limit = min($request->input('limit', 10), 50);
        $user = $request->user();
        $this->ensureUserLevelExists($user);

        // ✅ Récupérer les niveaux avec les utilisateurs
        $levels = \App\Models\UserLevel::with('user')
            ->orderByDesc('total_xp')
            ->limit($limit)
            ->get();

        $leaderboard = $levels->map(function ($level, $index) use ($user) {
            $userName = 'Joueur Anonyme';

            if ($level->user && $level->user->name) {
                $userName = $this->anonymizeName($level->user->name);
            }

            return [
                'rank' => $index + 1,
                'user_id' => $level->user_id,
                'user_name' => $userName,
                'level' => $level->level,
                'total_xp' => $level->total_xp,
                'title' => $this->getLevelTitle($level->level),
                'is_current_user' => $level->user_id === $user->id,
            ];
        });

        // ✅ Calculer le rang de l'utilisateur actuel
        $userRank = \App\Models\UserLevel::where('total_xp', '>', $user->level->total_xp ?? 0)
                ->count() + 1;

        $totalPlayers = \App\Models\UserLevel::count();

        // ✅ Vérifier si l'utilisateur est dans le top affiché
        $userInLeaderboard = $leaderboard->contains('is_current_user', true);

        // Si l'utilisateur n'est pas dans le top, l'ajouter à la fin
        $userEntry = null;
        if (!$userInLeaderboard) {
            $userEntry = [
                'rank' => $userRank,
                'user_id' => $user->id,
                'user_name' => $this->anonymizeName($user->name),
                'level' => $user->level->level ?? 1,
                'total_xp' => $user->level->total_xp ?? 0,
                'title' => $this->getLevelTitle($user->level->level ?? 1),
                'is_current_user' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard,
                'user_rank' => $userRank,
                'total_players' => $totalPlayers,
                'user_entry' => $userEntry,
            ],
        ]);
    }


    /**
     * ✅ Rang de l'utilisateur connecté
     */
    public function getUserRanking(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureUserLevelExists($user);

        $userRank = \App\Models\UserLevel::where('total_xp', '>', $user->level->total_xp)
                ->count() + 1;

        $totalPlayers = \App\Models\UserLevel::count();

        return response()->json([
            'success' => true,
            'data' => [
                'rank' => $userRank,
                'total_players' => $totalPlayers,
                'percentile' => $totalPlayers > 0
                    ? round((1 - ($userRank / $totalPlayers)) * 100)
                    : 100,
                'level' => $user->level->level,
                'total_xp' => $user->level->total_xp,
            ],
        ]);
    }

    /**
     * Anonymiser le nom (prénom + initiale)
     */
    protected function anonymizeName(string $name): string
    {
        $parts = explode(' ', trim($name));
        $firstName = $parts[0] ?? 'Joueur';
        $lastInitial = isset($parts[1]) && strlen($parts[1]) > 0
            ? strtoupper(substr($parts[1], 0, 1)) . '.'
            : '';

        return trim($firstName . ' ' . $lastInitial);
    }

    /**
     * Titre selon le niveau
     */
    protected function getLevelTitle(int $level): string
    {
        return match (true) {
            $level >= 50 => 'Légende',
            $level >= 40 => 'Maître',
            $level >= 30 => 'Expert',
            $level >= 20 => 'Confirmé',
            $level >= 10 => 'Avancé',
            $level >= 5 => 'Apprenti',
            default => 'Débutant',
        };
    }


    /**
     * Dashboard gaming complet
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = $user->getGamingStats();

        // Succès récents (7 derniers jours)
        $recentAchievements = $user->achievements()
            ->wherePivot('unlocked_at', '>=', now()->subDays(7))
            ->orderByPivot('unlocked_at', 'desc')
            ->limit(5)
            ->get(['achievements.id', 'achievements.name', 'achievements.icon', 'achievements.points', 'achievements.rarity'])
            ->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'icon' => $achievement->icon,
                    'points' => $achievement->points,
                    'rarity' => $achievement->rarity,
                    'rarity_name' => $achievement->rarity_name ?? ucfirst($achievement->rarity),
                    'rarity_color' => $achievement->rarity_color ?? '#3B82F6',
                    'unlocked_at' => $achievement->pivot->unlocked_at,
                ];
            });

        // Prochains succès à débloquer
        $nextAchievements = \App\Models\Achievement::active()
            ->whereNotIn('id', $user->achievements()->pluck('achievements.id'))
            ->orderBy('points')
            ->limit(3)
            ->get(['id', 'name', 'description', 'icon', 'points', 'rarity'])
            ->map(function ($achievement) use ($user) {
                $canUnlock = false;
                try {
                    $canUnlock = $achievement->checkCriteria($user);
                } catch (\Exception $e) {
                    $canUnlock = false;
                }

                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'points' => $achievement->points,
                    'rarity' => $achievement->rarity,
                    'rarity_name' => ucfirst($achievement->rarity),
                    'can_unlock' => $canUnlock,
                ];
            });

        // Activité récente
        $activitySummary = [
            'transactions_this_week' => $user->transactions()
                ->where('transaction_date', '>=', now()->subWeek())
                ->count(),
            'xp_gained_this_week' => $user->achievements()
                ->wherePivot('unlocked_at', '>=', now()->subWeek())
                ->sum('points'),
            'achievements_this_month' => $user->achievements()
                ->wherePivot('unlocked_at', '>=', now()->subMonth())
                ->count(),
        ];

        // ✅ Ajouter les streaks pour le dashboard
        $streaks = [];
        if (class_exists(\App\Models\Streak::class)) {
            try {
                $streaks = $user->streaks()
                    ->where('is_active', true)
                    ->get(['id', 'type', 'current_count', 'best_count', 'last_activity_date'])
                    ->map(function ($streak) {
                        return [
                            'id' => $streak->id,
                            'type' => $streak->type,
                            'name' => $this->getStreakName($streak->type),
                            'current_count' => $streak->current_count,
                            'best_count' => $streak->best_count,
                            'is_active' => true,
                            'last_activity' => $streak->last_activity_date,
                        ];
                    });
            } catch (\Exception $e) {
                // Ignorer les erreurs de streaks
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_achievements' => $recentAchievements,
                'next_achievements' => $nextAchievements,
                'activity_summary' => $activitySummary,
                'streaks' => $streaks,
            ],
            'message' => 'Dashboard gaming récupéré avec succès',
        ]);
    }

    /**
     * Forcer la vérification des succès (action manuelle)
     */
    public function checkAchievements(Request $request): JsonResponse
    {
        $user = $request->user();

        $unlockedAchievements = $user->checkAndUnlockAchievements();

        if (empty($unlockedAchievements)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'unlocked_achievements' => [],
                    'count' => 0,
                    'xp_gained' => 0,
                ],
                'message' => 'Aucun nouveau succès à débloquer',
            ]);
        }

        $totalXpGained = array_sum(array_column($unlockedAchievements, 'points'));

        $achievements = array_map(function ($achievement) {
            return [
                'id' => $achievement->id,
                'name' => $achievement->name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'points' => $achievement->points,
                'rarity' => $achievement->rarity,
                'rarity_name' => $achievement->rarity_name ?? ucfirst($achievement->rarity),
                'rarity_color' => $achievement->rarity_color ?? '#3B82F6',
            ];
        }, $unlockedAchievements);

        return response()->json([
            'success' => true,
            'data' => [
                'unlocked_achievements' => $achievements,
                'count' => count($unlockedAchievements),
                'xp_gained' => $totalXpGained,
                'new_stats' => $user->fresh()->getGamingStats(),
            ],
            'message' => count($unlockedAchievements).' nouveau(x) succès débloqué(s) !',
        ]);
    }

    /**
     * ✅ MÉTHODE HELPER: Calculer XP pour un niveau
     */
    protected function calculateXPForLevel(int $level): int
    {
        return (int) round(100 * pow(1.5, $level - 1));
    }

    /**
     * ✅ MÉTHODE HELPER: Calculer les jours de streak actifs
     */
    protected function calculateActiveStreakDays(\App\Models\User $user): int
    {
        if (! class_exists(\App\Models\Streak::class)) {
            return 0;
        }

        try {
            return $user->streaks()
                ->where('is_active', true)
                ->sum('current_count');
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * ✅ MÉTHODE HELPER: Nom des types de streaks
     */
    protected function getStreakName(string $type): string
    {
        $names = [
            'daily_login' => 'Connexion quotidienne',
            'daily_transaction' => 'Transaction quotidienne',
            'weekly_savings' => 'Épargne hebdomadaire',
            'budget_respect' => 'Respect du budget',
        ];

        return $names[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * S'assurer que le UserLevel existe pour l'utilisateur
     */
    protected function ensureUserLevelExists(\App\Models\User $user): void
    {
        $user->load('level');

        if (! $user->level || ! $user->level->exists) {
            $user->level()->create([
                'level' => 1,
                'total_xp' => 0,
                'current_level_xp' => 0,
                'next_level_xp' => 100,
            ]);

            $user->load('level');
        }
    }
}
