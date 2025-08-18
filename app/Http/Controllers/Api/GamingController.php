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
        $user = $request->user();

        // Statistiques principales
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
                    'unlocked_at' => $achievement->pivot->unlocked_at
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
                    // Si checkCriteria lève une exception, on met false
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
                    'can_unlock' => $canUnlock
                ];
            });

        // Activité récente (gaming)
        $activitySummary = [
            'transactions_this_week' => $user->transactions()
                ->where('transaction_date', '>=', now()->subWeek())
                ->count(),
            'xp_gained_this_week' => $user->achievements()
                ->wherePivot('unlocked_at', '>=', now()->subWeek())
                ->sum('points'),
            'achievements_this_month' => $user->achievements()
                ->wherePivot('unlocked_at', '>=', now()->subMonth())
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_achievements' => $recentAchievements,
                'next_achievements' => $nextAchievements, // 🎯 Cette clé est bien là !
                'activity_summary' => $activitySummary
            ],
            'message' => 'Dashboard gaming récupéré avec succès'
        ]);
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
                    'count' => 0, // 👈 AJOUTER CETTE CLÉ MANQUANTE
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
                'rarity_name' => $achievement->rarity_name ?? ucfirst($achievement->rarity),
                'rarity_color' => $achievement->rarity_color ?? '#3B82F6'
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
