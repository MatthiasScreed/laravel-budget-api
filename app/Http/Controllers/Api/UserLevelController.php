<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLevelController extends Controller
{
    /**
     * Informations du niveau de l'utilisateur connecté
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $userLevel = $user->level;

        if (! $userLevel) {
            // Créer le niveau s'il n'existe pas
            $userLevel = $user->level()->create();
        }

        $levelData = [
            'current_level' => $userLevel->level,
            'total_xp' => $userLevel->total_xp,
            'current_level_xp' => $userLevel->current_level_xp,
            'next_level_xp' => $userLevel->next_level_xp,
            'progress_percentage' => round($userLevel->getProgressPercentage(), 2),
            'title' => $userLevel->getTitle(),
            'level_color' => $userLevel->getLevelColor(),
            'xp_to_next_level' => $userLevel->next_level_xp - $userLevel->current_level_xp,
        ];

        // XP requis pour quelques niveaux suivants
        $nextLevels = [];
        for ($i = 1; $i <= 3; $i++) {
            $targetLevel = $userLevel->level + $i;
            $nextLevels[] = [
                'level' => $targetLevel,
                'xp_required' => UserLevel::getXpRequiredForLevel($targetLevel),
                'xp_needed' => UserLevel::getXpRequiredForLevel($targetLevel) - $userLevel->total_xp,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'level_info' => $levelData,
                'next_levels' => $nextLevels,
                'level_history' => $this->getLevelHistory($user),
            ],
            'message' => 'Informations de niveau récupérées avec succès',
        ]);
    }

    /**
     * Progression détaillée vers le niveau suivant
     */
    public function progress(Request $request): JsonResponse
    {
        $user = $request->user();
        $userLevel = $user->level;

        if (! $userLevel) {
            $userLevel = $user->level()->create();
        }

        // Calcul de la progression
        $progressData = [
            'current_level' => $userLevel->level,
            'next_level' => $userLevel->level + 1,
            'current_xp_in_level' => $userLevel->current_level_xp,
            'xp_needed_for_next' => $userLevel->next_level_xp,
            'xp_remaining' => $userLevel->next_level_xp - $userLevel->current_level_xp,
            'progress_percentage' => round($userLevel->getProgressPercentage(), 2),
            'total_xp' => $userLevel->total_xp,
        ];

        // Sources récentes d'XP (via les succès débloqués récemment)
        $recentXpSources = $user->achievements()
            ->wherePivot('unlocked_at', '>=', now()->subDays(7))
            ->orderByPivot('unlocked_at', 'desc')
            ->get(['achievements.name', 'achievements.points'])
            ->map(function ($achievement) {
                return [
                    'source' => 'Succès: '.$achievement->name,
                    'xp' => $achievement->points,
                    'date' => $achievement->pivot->unlocked_at,
                ];
            });

        // Projection pour atteindre le niveau suivant
        $avgXpPerWeek = $recentXpSources->sum('xp'); // XP de la semaine
        $weeksToNextLevel = $avgXpPerWeek > 0 ?
            ceil($progressData['xp_remaining'] / $avgXpPerWeek) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'progress' => $progressData,
                'recent_xp_sources' => $recentXpSources,
                'projection' => [
                    'avg_xp_per_week' => $avgXpPerWeek,
                    'weeks_to_next_level' => $weeksToNextLevel,
                    'estimated_date' => $weeksToNextLevel ?
                        now()->addWeeks($weeksToNextLevel)->format('Y-m-d') : null,
                ],
            ],
            'message' => 'Progression récupérée avec succès',
        ]);
    }

    /**
     * Leaderboard des niveaux
     */
    public function leaderboard(Request $request): JsonResponse
    {
        // Top utilisateurs par XP total
        $topUsers = User::join('user_levels', 'users.id', '=', 'user_levels.user_id')
            ->select('users.id', 'users.name', 'user_levels.level', 'user_levels.total_xp')
            ->orderBy('user_levels.total_xp', 'desc')
            ->orderBy('user_levels.level', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'level' => $user->level,
                    'total_xp' => $user->total_xp,
                    'title' => UserLevel::where('user_id', $user->id)->first()?->getTitle() ?? 'Débutant',
                ];
            });

        // Position de l'utilisateur actuel
        $currentUser = $request->user();
        $currentUserRank = User::join('user_levels', 'users.id', '=', 'user_levels.user_id')
            ->where('user_levels.total_xp', '>', $currentUser->getTotalXp())
            ->count() + 1;

        $currentUserInfo = [
            'rank' => $currentUserRank,
            'level' => $currentUser->getCurrentLevel(),
            'total_xp' => $currentUser->getTotalXp(),
            'title' => $currentUser->getTitle(),
        ];

        // Statistiques globales
        $globalStats = [
            'total_users' => User::count(),
            'avg_level' => round(UserLevel::avg('level'), 1),
            'highest_level' => UserLevel::max('level'),
            'total_xp_distributed' => UserLevel::sum('total_xp'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $topUsers,
                'current_user' => $currentUserInfo,
                'global_stats' => $globalStats,
            ],
            'message' => 'Leaderboard récupéré avec succès',
        ]);
    }

    /**
     * Historique des niveaux de l'utilisateur
     */
    private function getLevelHistory(User $user): array
    {
        // Pour l'instant, on simule l'historique avec les succès
        // Plus tard, on pourra avoir une table level_history

        $milestones = [];

        // Niveau 1 (création du compte)
        $milestones[] = [
            'level' => 1,
            'reached_at' => $user->created_at,
            'event' => 'Création du compte',
        ];

        // Ajout des succès comme milestones
        $achievements = $user->achievements()
            ->orderByPivot('unlocked_at')
            ->get();

        $currentXp = 0;
        $currentLevel = 1;

        foreach ($achievements as $achievement) {
            $currentXp += $achievement->points;
            $newLevel = $this->calculateLevelFromXp($currentXp);

            if ($newLevel > $currentLevel) {
                $milestones[] = [
                    'level' => $newLevel,
                    'reached_at' => $achievement->pivot->unlocked_at,
                    'event' => 'Succès: '.$achievement->name,
                ];
                $currentLevel = $newLevel;
            }
        }

        return $milestones;
    }

    /**
     * Calculer le niveau à partir de l'XP total
     */
    private function calculateLevelFromXp(int $totalXp): int
    {
        $level = 1;
        while (UserLevel::getXpRequiredForLevel($level + 1) <= $totalXp) {
            $level++;
        }

        return $level;
    }
}
