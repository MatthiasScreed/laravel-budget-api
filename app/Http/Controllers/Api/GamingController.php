<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamingController extends Controller
{
    /**
     * Obtenir les statistiques gaming de l'utilisateur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // ✅ S'assurer que le UserLevel existe avant de récupérer les stats
        $this->ensureUserLevelExists($user);

        $stats = $user->getGamingStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Statistiques gaming récupérées avec succès'
        ]);
    }

    /**
     * Dashboard gaming complet
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // ✅ S'assurer que le UserLevel existe et recharger la relation
            $this->ensureUserLevelExists($user);

            // Informations de niveau (avec relation fraîche)
            $levelInfo = [
                'current_level' => $user->level->level,
                'total_xp' => $user->level->total_xp,
                'current_level_xp' => $user->level->current_level_xp,
                'next_level_xp' => $user->level->next_level_xp,
                'progress_percentage' => round($user->level->getProgressPercentage(), 2),
                'title' => $user->level->getTitle(),
                'xp_to_next_level' => max(0, $user->level->next_level_xp - $user->level->current_level_xp)
            ];

            // Compter les achievements
            $achievementsCount = $user->achievements()->count();

            // Streaks actifs (si disponible)
            $activeStreaks = [];
            if (class_exists(\App\Models\Streak::class)) {
                try {
                    $activeStreaks = $user->streaks()
                        ->where('current_count', '>', 0)
                        ->get(['type', 'current_count', 'best_count', 'last_activity_date']);
                } catch (\Exception $e) {
                    $activeStreaks = [];
                }
            }

            // ✅ FIX: Achievements récents avec préfixe de table pour éviter l'ambiguïté
            $recentAchievements = $user->achievements()
                ->wherePivot('unlocked_at', '>=', now()->subDays(7))
                ->orderByPivot('unlocked_at', 'desc')
                ->limit(5)
                ->get([
                    'achievements.id',           // ✅ Préfixe ajouté
                    'achievements.name',         // ✅ Préfixe ajouté
                    'achievements.description',  // ✅ Préfixe ajouté
                    'achievements.icon',         // ✅ Préfixe ajouté
                    'achievements.points',       // ✅ Préfixe ajouté
                    'achievements.rarity'        // ✅ Préfixe ajouté
                ])
                ->map(function ($achievement) {
                    return [
                        'id' => $achievement->id,
                        'name' => $achievement->name,
                        'description' => $achievement->description,
                        'icon' => $achievement->icon,
                        'points' => $achievement->points,
                        'rarity' => $achievement->rarity,
                        'unlocked_at' => $achievement->pivot->unlocked_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'level_info' => $levelInfo,
                    'achievements_count' => $achievementsCount,
                    'active_streaks' => $activeStreaks,
                    'recent_achievements' => $recentAchievements,
                    'stats' => [
                        'total_transactions' => $user->transactions()->count(),
                        'total_goals' => $user->financialGoals()->count(),
                        'weekly_xp' => $user->achievements()
                            ->wherePivot('unlocked_at', '>=', now()->subWeek())
                            ->sum('achievements.points')  // ✅ Préfixe ajouté ici aussi
                    ]
                ],
                'message' => 'Dashboard gaming récupéré avec succès'
            ]);

        } catch (\Exception $e) {
            \Log::error('Gaming dashboard error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du dashboard gaming',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Forcer la vérification des succès (action manuelle)
     *
     * @param Request $request
     * @return JsonResponse
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
                    'xp_gained' => 0
                ],
                'message' => 'Aucun nouveau succès à débloquer'
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
                'rarity_name' => $achievement->rarity_name,
                'rarity_color' => $achievement->rarity_color
            ];
        }, $unlockedAchievements);

        return response()->json([
            'success' => true,
            'data' => [
                'unlocked_achievements' => $achievements,
                'count' => count($unlockedAchievements),
                'xp_gained' => $totalXpGained,
                'new_stats' => $user->fresh()->getGamingStats()
            ],
            'message' => count($unlockedAchievements) . ' nouveau(x) succès débloqué(s) !'
        ]);
    }

    /**
     * S'assurer que le UserLevel existe pour l'utilisateur
     *
     * Cette méthode gère le cas où le UserLevel a été supprimé
     * mais que Laravel garde en cache la relation
     *
     * @param \App\Models\User $user
     * @return void
     */
    protected function ensureUserLevelExists(\App\Models\User $user): void
    {
        // ✅ Recharger la relation level depuis la base de données
        $user->load('level');

        // ✅ Vérifier si le UserLevel existe vraiment en base
        if (!$user->level || !$user->level->exists) {
            // Créer un nouveau UserLevel avec les valeurs par défaut
            $user->level()->create([
                'level' => 1,
                'total_xp' => 0,
                'current_level_xp' => 0,
                'next_level_xp' => 100
            ]);

            // ✅ Recharger la relation fraîche
            $user->load('level');
        }
    }
}
