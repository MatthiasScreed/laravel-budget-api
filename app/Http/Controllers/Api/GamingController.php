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
