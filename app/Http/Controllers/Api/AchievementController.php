<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    /**
     * Liste tous les succès avec statut pour l'utilisateur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Récupérer tous les succès actifs
        $achievements = Achievement::active()
            ->orderBy('rarity')
            ->orderBy('points')
            ->get();

        // IDs des succès débloqués par l'utilisateur
        $unlockedIds = $user->achievements()->pluck('achievements.id')->toArray();

        $formattedAchievements = $achievements->map(function ($achievement) use ($user, $unlockedIds) {
            $isUnlocked = in_array($achievement->id, $unlockedIds);

            return [
                'id' => $achievement->id,
                'name' => $achievement->name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'points' => $achievement->points,
                'type' => $achievement->type,
                'type_name' => $achievement->type_name,
                'rarity' => $achievement->rarity,
                'rarity_name' => $achievement->rarity_name,
                'rarity_color' => $achievement->rarity_color,
                'is_unlocked' => $isUnlocked,
                'can_unlock' => !$isUnlocked && $achievement->checkCriteria($user),
                'unlocked_at' => $isUnlocked ?
                    $user->achievements()->find($achievement->id)->pivot->unlocked_at :
                    null
            ];
        });

        // Statistiques des succès
        $stats = [
            'total_achievements' => $achievements->count(),
            'unlocked_count' => count($unlockedIds),
            'available_to_unlock' => $formattedAchievements->where('can_unlock', true)->count(),
            'completion_percentage' => $achievements->count() > 0 ?
                round((count($unlockedIds) / $achievements->count()) * 100, 1) : 0
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'achievements' => $formattedAchievements,
                'stats' => $stats
            ],
            'message' => 'Liste des succès récupérée avec succès'
        ]);
    }

    /**
     * Succès disponibles (pas encore débloqués)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function available(Request $request): JsonResponse
    {
        $user = $request->user();

        $availableAchievements = Achievement::active()
            ->whereNotIn('id', $user->achievements()->pluck('achievements.id'))
            ->orderBy('points')
            ->get();

        $formattedAchievements = $availableAchievements->map(function ($achievement) use ($user) {
            return [
                'id' => $achievement->id,
                'name' => $achievement->name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'points' => $achievement->points,
                'type' => $achievement->type,
                'type_name' => $achievement->type_name,
                'rarity' => $achievement->rarity,
                'rarity_name' => $achievement->rarity_name,
                'rarity_color' => $achievement->rarity_color,
                'can_unlock' => $achievement->checkCriteria($user),
                'criteria' => $achievement->criteria
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'achievements' => $formattedAchievements,
                'count' => $availableAchievements->count()
            ],
            'message' => 'Succès disponibles récupérés avec succès'
        ]);
    }

    /**
     * Succès débloqués par l'utilisateur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function unlocked(Request $request): JsonResponse
    {
        $user = $request->user();

        $unlockedAchievements = $user->achievements()
            ->orderByPivot('unlocked_at', 'desc')
            ->get();

        $formattedAchievements = $unlockedAchievements->map(function ($achievement) {
            return [
                'id' => $achievement->id,
                'name' => $achievement->name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'points' => $achievement->points,
                'type' => $achievement->type,
                'type_name' => $achievement->type_name,
                'rarity' => $achievement->rarity,
                'rarity_name' => $achievement->rarity_name,
                'rarity_color' => $achievement->rarity_color,
                'unlocked_at' => $achievement->pivot->unlocked_at,
                'is_recent' => $achievement->pivot->unlocked_at >= now()->subDays(7)
            ];
        });

        // Statistiques des succès débloqués
        $stats = [
            'total_unlocked' => $unlockedAchievements->count(),
            'total_xp_earned' => $unlockedAchievements->sum('points'),
            'recent_count' => $unlockedAchievements->where('pivot.unlocked_at', '>=', now()->subDays(7))->count(),
            'rarity_breakdown' => $unlockedAchievements->groupBy('rarity')->map->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'achievements' => $formattedAchievements,
                'stats' => $stats
            ],
            'message' => 'Succès débloqués récupérés avec succès'
        ]);
    }

    /**
     * Détails d'un succès spécifique
     *
     * @param Achievement $achievement
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Achievement $achievement, Request $request): JsonResponse
    {
        $user = $request->user();
        $isUnlocked = $achievement->isUnlockedBy($user);

        $achievementData = [
            'id' => $achievement->id,
            'name' => $achievement->name,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'points' => $achievement->points,
            'type' => $achievement->type,
            'type_name' => $achievement->type_name,
            'rarity' => $achievement->rarity,
            'rarity_name' => $achievement->rarity_name,
            'rarity_color' => $achievement->rarity_color,
            'criteria' => $achievement->criteria,
            'is_unlocked' => $isUnlocked,
            'can_unlock' => !$isUnlocked && $achievement->checkCriteria($user),
            'unlocked_at' => $isUnlocked ?
                $user->achievements()->find($achievement->id)->pivot->unlocked_at :
                null
        ];

        // Statistiques globales du succès
        $globalStats = [
            'unlock_count' => $achievement->users()->count(),
            'unlock_percentage' => \App\Models\User::count() > 0 ?
                round(($achievement->users()->count() / \App\Models\User::count()) * 100, 2) : 0
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'achievement' => $achievementData,
                'global_stats' => $globalStats
            ],
            'message' => 'Détails du succès récupérés avec succès'
        ]);
    }

    /**
     * Vérifier et débloquer les nouveaux succès
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAndUnlock(Request $request): JsonResponse
    {
        $user = $request->user();

        $beforeStats = $user->getGamingStats();
        $unlockedAchievements = $user->checkAndUnlockAchievements();
        $afterStats = $user->fresh()->getGamingStats();

        if (empty($unlockedAchievements)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'unlocked_achievements' => [],
                    'xp_gained' => 0,
                    'level_changed' => false
                ],
                'message' => 'Aucun nouveau succès à débloquer'
            ]);
        }

        $totalXpGained = array_sum(array_column($unlockedAchievements, 'points'));
        $levelChanged = $beforeStats['level_info']['current_level'] !== $afterStats['level_info']['current_level'];

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
                'level_changed' => $levelChanged,
                'old_level' => $beforeStats['level_info']['current_level'],
                'new_level' => $afterStats['level_info']['current_level'],
                'new_stats' => $afterStats
            ],
            'message' => count($unlockedAchievements) . ' nouveau(x) succès débloqué(s) !'
        ]);
    }
}
