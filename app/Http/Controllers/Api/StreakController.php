<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Streak;
use App\Services\StreakService;
use Illuminate\Http\Request;

class StreakController extends Controller
{
    protected StreakService $streakService;

    public function __construct(StreakService $streakService)
    {
        $this->streakService = $streakService;
    }

    /**
     * 📊 Voir toutes ses streaks
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $streaks = $this->streakService->getUserStreaks($user);

        return response()->json([
            'success' => true,
            'data' => [
                'streaks' => $streaks,
                'total_active' => count($streaks),
                'best_streak' => collect($streaks)->max('best_count') ?? 0
            ]
        ]);
    }

    /**
     * 🎁 Réclamer bonus d'une streak
     */
    public function claimBonus(Request $request, string $streakType)
    {
        $user = $request->user();
        $streak = $user->streaks()->where('type', $streakType)->first();

        if (!$streak) {
            return response()->json([
                'success' => false,
                'message' => 'Streak non trouvée'
            ], 404);
        }

        if (!$streak->canClaimBonus()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun bonus disponible pour cette streak'
            ], 400);
        }

        $bonusXp = $streak->claimBonus();
        $user->addXp($bonusXp);

        return response()->json([
            'success' => true,
            'data' => [
                'bonus_xp' => $bonusXp,
                'new_total_xp' => $user->getTotalXp(),
                'new_level' => $user->getCurrentLevel(),
                'streak' => $this->streakService->getStreak($user, $streakType)
            ],
            'message' => "🎁 Bonus de {$bonusXp} XP réclamé !"
        ]);
    }

    /**
     * 📈 Leaderboard des streaks
     */
    public function leaderboard(Request $request)
    {
        $streakType = $request->get('type', Streak::TYPE_DAILY_LOGIN);

        $topStreaks = Streak::where('type', $streakType)
            ->with('user:id,name')
            ->orderBy('best_count', 'desc')
            ->limit(20)
            ->get()
            ->map(function($streak, $index) {
                return [
                    'rank' => $index + 1,
                    'user_name' => $streak->user->name,
                    'best_count' => $streak->best_count,
                    'current_count' => $streak->current_count,
                    'is_active' => $streak->is_active
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $topStreaks,
                'streak_type' => $streakType,
                'your_rank' => $this->getUserRank($request->user(), $streakType)
            ]
        ]);
    }

    /**
     * Obtenir le rang de l'utilisateur
     */
    protected function getUserRank($user, $streakType): ?int
    {
        $userStreak = $user->streaks()->where('type', $streakType)->first();
        if (!$userStreak) return null;

        return Streak::where('type', $streakType)
                ->where('best_count', '>', $userStreak->best_count)
                ->count() + 1;
    }


}
