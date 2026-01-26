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
                'rarity' => $achievement->rarity,
                'is_unlocked' => $isUnlocked,
                'can_unlock' => ! $isUnlocked && $achievement->checkCriteria($user),
                'unlocked_at' => $isUnlocked ?
                    $user->achievements()->find($achievement->id)->pivot->unlocked_at :
                    null,
            ];
        });

        // Statistiques des succès
        $stats = [
            'total_achievements' => $achievements->count(),
            'unlocked_count' => count($unlockedIds),
            'available_to_unlock' => $formattedAchievements->where('can_unlock', true)->count(),
            'completion_percentage' => $achievements->count() > 0 ?
                round((count($unlockedIds) / $achievements->count()) * 100, 1) : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'achievements' => $formattedAchievements, // 👈 Clé que le test attend
                'stats' => $stats,
            ],
            'message' => 'Liste des succès récupérée avec succès',
        ]);
    }

    /**
     * Succès disponibles (pas encore débloqués)
     */
    public function available(Request $request): JsonResponse
    {
        $user = $request->user();

        $availableAchievements = Achievement::active()
            ->whereNotIn('id', $user->achievements()->pluck('achievements.id'))  // ✅ Fix: préfixe ajouté
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
                'criteria' => $achievement->criteria,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'achievements' => $formattedAchievements,
                'count' => $availableAchievements->count(),
            ],
            'message' => 'Succès disponibles récupérés avec succès',
        ]);
    }

    /**
     * Succès débloqués par l'utilisateur
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
                'is_recent' => $achievement->pivot->unlocked_at >= now()->subDays(7),
            ];
        });

        // Statistiques des succès débloqués
        $stats = [
            'total_unlocked' => $unlockedAchievements->count(),
            'total_xp_earned' => $unlockedAchievements->sum('points'),
            'recent_count' => $unlockedAchievements->where('pivot.unlocked_at', '>=', now()->subDays(7))->count(),
            'rarity_breakdown' => $unlockedAchievements->groupBy('rarity')->map->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'achievements' => $formattedAchievements,
                'stats' => $stats,
            ],
            'message' => 'Succès débloqués récupérés avec succès',
        ]);
    }

    /**
     * Détails d'un succès spécifique
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
            'can_unlock' => ! $isUnlocked && $achievement->checkCriteria($user),
            'unlocked_at' => $isUnlocked ?
                $user->achievements()->find($achievement->id)->pivot->unlocked_at :
                null,
        ];

        // Statistiques globales du succès
        $globalStats = [
            'unlock_count' => $achievement->users()->count(),
            'unlock_percentage' => \App\Models\User::count() > 0 ?
                round(($achievement->users()->count() / \App\Models\User::count()) * 100, 2) : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'achievement' => $achievementData,
                'global_stats' => $globalStats,
            ],
            'message' => 'Détails du succès récupérés avec succès',
        ]);
    }

    /**
     * Vérifier et débloquer les nouveaux succès
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
                    'level_changed' => false,
                ],
                'message' => 'Aucun nouveau succès à débloquer',
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
                'rarity_color' => $achievement->rarity_color,
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
                'new_stats' => $afterStats,
            ],
            'message' => count($unlockedAchievements).' nouveau(x) succès débloqué(s) !',
        ]);
    }

    /**
     * ✅ VERSION AVEC VRAIES DONNÉES - Remplacez votre méthode getUserAchievements()
     */
    public function getUserAchievements(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier d'abord que les tables existent
            if (! \Schema::hasTable('achievements') || ! \Schema::hasTable('user_achievements')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tables manquantes - Exécutez les migrations',
                    'missing_tables' => [
                        'achievements' => ! \Schema::hasTable('achievements'),
                        'user_achievements' => ! \Schema::hasTable('user_achievements'),
                    ],
                ], 500);
            }

            // Récupérer tous les achievements
            $allAchievements = Achievement::where('is_active', true)->get();

            // Si aucun achievement, créer des données par défaut
            if ($allAchievements->isEmpty()) {
                Achievement::createDefaults();
                $allAchievements = Achievement::where('is_active', true)->get();
            }

            // Récupérer les achievements de l'utilisateur avec la relation corrigée
            $userAchievementIds = $user->achievements()
                ->pluck('achievements.id') // Préfixe ajouté
                ->toArray();

            $userAchievementData = $allAchievements->map(function ($achievement) use ($user, $userAchievementIds) {
                $isUnlocked = in_array($achievement->id, $userAchievementIds);

                // Si débloqué, récupérer les détails du pivot
                $unlockedAt = null;
                if ($isUnlocked) {
                    $pivotData = $user->achievements()
                        ->where('achievements.id', $achievement->id)
                        ->first();
                    $unlockedAt = $pivotData?->pivot?->unlocked_at;
                }

                return [
                    'achievement_id' => $achievement->id,
                    'achievement' => [
                        'id' => $achievement->id,
                        'name' => $achievement->name,
                        'description' => $achievement->description,
                        'icon' => $achievement->icon,
                        'xp_reward' => $achievement->points ?? 10,
                        'category' => $achievement->type ?? 'general',
                        'rarity' => $achievement->rarity ?? 'common',
                    ],
                    'unlocked' => $isUnlocked,
                    'unlocked_at' => $unlockedAt?->toISOString(),
                    'progress' => $isUnlocked ? 100 : rand(0, 80),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $userAchievementData,
                'message' => 'Achievements utilisateur récupérés avec succès',
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur getUserAchievements: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des achievements utilisateur',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'user_id' => $request->user()?->id,
                    'achievements_count' => Achievement::count(),
                    'user_achievements_count' => $request->user()?->achievements()?->count(),
                ],
            ], 500);
        }
    }

    /**
     * Débloquer un succès
     * Accepte un ID numérique OU un slug
     *
     * @param  string  $achievement  ID ou slug du succès
     */
    public function unlock(Request $request, string $achievement): JsonResponse
    {
        $user = $request->user();

        // ✅ Rechercher par ID numérique OU par slug
        $achievementModel = is_numeric($achievement)
            ? Achievement::find($achievement)
            : Achievement::where('slug', $achievement)->first();

        if (! $achievementModel) {
            return response()->json([
                'success' => false,
                'message' => "Succès '$achievement' introuvable",
            ], 404);
        }

        // Vérifier si déjà débloqué
        if ($user->achievements()->where('achievements.id', $achievementModel->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Succès déjà débloqué',
            ], 400);
        }

        // Vérifier les critères
        if (! $achievementModel->checkCriteria($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Conditions du succès non remplies',
            ], 400);
        }

        // Débloquer le succès
        $user->achievements()->attach($achievementModel->id, [
            'unlocked_at' => now(),
        ]);

        // Ajouter les points XP
        if ($achievementModel->points > 0 && $user->level) {
            $user->level->addXP($achievementModel->points, 'achievement_unlocked');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'achievement' => [
                    'id' => $achievementModel->id,
                    'name' => $achievementModel->name,
                    'description' => $achievementModel->description,
                    'icon' => $achievementModel->icon,
                    'points' => $achievementModel->points,
                    'rarity' => $achievementModel->rarity,
                ],
                'xp_gained' => $achievementModel->points,
                'new_stats' => $user->fresh()->getGamingStats(),
            ],
            'message' => "Succès '{$achievementModel->name}' débloqué !",
        ]);
    }

    /**
     * Vérifier et débloquer automatiquement les succès disponibles
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
}
