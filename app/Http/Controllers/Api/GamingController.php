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
                    'rarity_name' => $achievement->rarity_name,
                    'rarity_color' => $achievement->rarity_color,
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
                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'points' => $achievement->points,
                    'rarity' => $achievement->rarity,
                    'rarity_name' => $achievement->rarity_name,
                    'can_unlock' => $achievement->checkCriteria($user)
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
                'next_achievements' => $nextAchievements,
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
}
