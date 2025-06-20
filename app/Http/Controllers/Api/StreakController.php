<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Streak;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreakController extends Controller
{
    protected StreakService $streakService;

    public function __construct(StreakService $streakService)
    {
        $this->streakService = $streakService;
    }

    /**
     * Obtenir toutes les streaks de l'utilisateur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $streakData = $this->streakService->getUserStreaks($user);

        return response()->json([
            'success' => true,
            'data' => $streakData,
            'message' => 'Streaks récupérées avec succès'
        ]);
    }

    /**
     * Déclencher manuellement une streak
     *
     * @param Request $request
     * @param string $type
     * @return JsonResponse
     */
    public function trigger(Request $request, string $type): JsonResponse
    {
        $user = $request->user();

        if (!in_array($type, array_keys(Streak::TYPES))) {
            return response()->json([
                'success' => false,
                'message' => 'Type de streak invalide'
            ], 400);
        }

        $result = $this->streakService->triggerStreak($user, $type);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['message']
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Réclamer le bonus d'une streak
     *
     * @param Request $request
     * @param string $type
     * @return JsonResponse
     */
    public function claimBonus(Request $request, string $type): JsonResponse
    {
        $user = $request->user();
        $result = $this->streakService->claimStreakBonus($user, $type);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['message']
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Obtenir le leaderboard des streaks
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $type = $request->get('type', Streak::TYPE_DAILY_LOGIN);
        $limit = min($request->get('limit', 10), 50);

        if (!in_array($type, array_keys(Streak::TYPES))) {
            return response()->json([
                'success' => false,
                'message' => 'Type de streak invalide'
            ], 400);
        }

        $leaderboardData = $this->streakService->getLeaderboard($type, $limit);
        $userRank = $this->streakService->getUserRank($request->user(), $type);

        return response()->json([
            'success' => true,
            'data' => array_merge($leaderboardData, [
                'your_rank' => $userRank
            ]),
            'message' => 'Leaderboard récupéré avec succès'
        ]);
    }

    /**
     * Vérifier les streaks expirées
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkExpired(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->streakService->checkExpiredStreaks($user);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['count'] > 0
                ? "{$result['count']} streak(s) expirée(s)"
                : 'Aucune streak expirée'
        ]);
    }

    /**
     * Obtenir les détails d'une streak spécifique
     *
     * @param Request $request
     * @param string $type
     * @return JsonResponse
     */
    public function show(Request $request, string $type): JsonResponse
    {
        $user = $request->user();
        $streak = $user->streaks()->where('type', $type)->first();

        if (!$streak) {
            return response()->json([
                'success' => false,
                'message' => 'Streak non trouvée'
            ], 404);
        }

        $streakData = [
            'id' => $streak->id,
            'type' => $streak->type,
            'type_name' => Streak::TYPES[$streak->type] ?? $streak->type,
            'current_count' => $streak->current_count,
            'best_count' => $streak->best_count,
            'last_activity_date' => $streak->last_activity_date?->toDateString(),
            'is_active' => $streak->is_active,
            'risk_level' => $streak->getRiskLevel(),
            'next_milestone' => $streak->getNextMilestone(),
            'is_at_milestone' => $streak->isAtMilestone(),
            'can_claim_bonus' => $streak->canClaimBonus(),
            'bonus_xp_available' => $streak->calculateBonusXp(),
            'milestones' => $streak->getMilestones(),
            'bonus_claimed_at' => $streak->bonus_claimed_at?->toDateTimeString()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'streak' => $streakData
            ],
            'message' => 'Détails de la streak récupérés'
        ]);
    }

    /**
     * Réactiver une streak
     *
     * @param Request $request
     * @param string $type
     * @return JsonResponse
     */
    public function reactivate(Request $request, string $type): JsonResponse
    {
        $user = $request->user();
        $streak = $user->streaks()->where('type', $type)->first();

        if (!$streak) {
            return response()->json([
                'success' => false,
                'message' => 'Streak non trouvée'
            ], 404);
        }

        $streak->reactivate();

        return response()->json([
            'success' => true,
            'data' => [
                'streak' => [
                    'type' => $streak->type,
                    'current_count' => $streak->current_count,
                    'is_active' => $streak->is_active
                ]
            ],
            'message' => 'Streak réactivée avec succès'
        ]);
    }
}
