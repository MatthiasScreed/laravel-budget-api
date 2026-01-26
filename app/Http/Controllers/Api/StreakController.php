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
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $streakData = $this->streakService->getUserStreaks($user);

        return response()->json([
            'success' => true,
            'data' => $streakData,
            'message' => 'Streaks récupérées avec succès',
        ]);
    }

    /**
     * Mettre à jour une série spécifique par type
     *
     * @param  string  $type  Type de série (daily_login, weekly_budget, etc.)
     */
    public function updateStreakByType(Request $request, string $type): JsonResponse
    {
        $user = $request->user();

        // Récupérer ou créer la série
        $streak = $user->streaks()->firstOrCreate(
            ['streak_type' => $type],
            [
                'current_count' => 0,
                'best_count' => 0,
                'last_activity_at' => null,
                'is_active' => true,
            ]
        );

        // Vérifier si la série peut être maintenue
        if ($streak->last_activity_at) {
            $hoursSinceLastActivity = now()->diffInHours($streak->last_activity_at);

            // Si plus de 48h, la série est brisée
            if ($hoursSinceLastActivity > 48) {
                $streak->current_count = 1;
                $streak->is_active = true;
            }
            // Si moins de 24h, on ne compte pas (déjà fait aujourd'hui)
            elseif ($hoursSinceLastActivity < 24) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'streak' => [
                            'type' => $streak->streak_type,
                            'current_count' => $streak->current_count,
                            'best_count' => $streak->best_count,
                            'is_active' => $streak->is_active,
                            'last_activity_at' => $streak->last_activity_at,
                            'next_milestone' => $this->getNextMilestone($streak->current_count),
                        ],
                        'already_updated_today' => true,
                    ],
                    'message' => 'Série déjà mise à jour aujourd\'hui',
                ]);
            }
            // Entre 24h et 48h, on continue la série
            else {
                $streak->current_count++;
            }
        } else {
            // Première activité
            $streak->current_count = 1;
        }

        // Mettre à jour le meilleur score
        if ($streak->current_count > $streak->best_count) {
            $streak->best_count = $streak->current_count;
        }

        $streak->last_activity_at = now();
        $streak->save();

        // Ajouter de l'XP basé sur la longueur de la série
        $xpGained = $this->calculateStreakXP($streak->current_count);
        if ($user->level && $xpGained > 0) {
            $user->level->addXP($xpGained, "streak_{$type}");
        }

        // Vérifier les succès liés aux séries
        $user->checkAndUnlockAchievements();

        return response()->json([
            'success' => true,
            'data' => [
                'streak' => [
                    'type' => $streak->streak_type,
                    'current_count' => $streak->current_count,
                    'best_count' => $streak->best_count,
                    'is_active' => $streak->is_active,
                    'last_activity_at' => $streak->last_activity_at,
                    'next_milestone' => $this->getNextMilestone($streak->current_count),
                ],
                'xp_gained' => $xpGained,
                'new_level' => $user->level->level,
            ],
            'message' => "Série {$type} mise à jour ! Jour {$streak->current_count}",
        ]);
    }

    /**
     * Calculer l'XP gagné pour une série
     *
     * @param  int  $count  Nombre de jours de série
     * @return int XP gagné
     */
    protected function calculateStreakXP(int $count): int
    {
        // XP de base : 10 points
        $baseXP = 10;

        // Bonus tous les 7 jours
        $weekBonus = floor($count / 7) * 20;

        // Bonus pour les paliers spéciaux
        $milestoneBonus = match ($count) {
            30 => 100,
            60 => 200,
            100 => 500,
            365 => 2000,
            default => 0
        };

        return $baseXP + $weekBonus + $milestoneBonus;
    }

    /**
     * Obtenir le prochain palier de série
     *
     * @param  int  $count  Nombre actuel
     * @return int|null Prochain palier
     */
    protected function getNextMilestone(int $count): ?int
    {
        $milestones = [7, 14, 30, 60, 100, 365];

        foreach ($milestones as $milestone) {
            if ($count < $milestone) {
                return $milestone;
            }
        }

        return null;
    }

    /**
     * Mise à jour globale (méthode existante maintenue)
     */
    public function updateStreak(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string',
        ]);

        return $this->updateStreakByType($request, $request->input('type'));
    }

    /**
     * Déclencher manuellement une streak
     */
    public function trigger(Request $request, string $type): JsonResponse
    {
        $user = $request->user();

        if (! in_array($type, array_keys(Streak::TYPES ?? []))) {
            return response()->json([
                'success' => false,
                'message' => 'Type de streak invalide',
            ], 400);
        }

        $result = $this->streakService->triggerStreak($user, $type);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Réclamer le bonus d'une streak
     */
    public function claimBonus(Request $request, string $type): JsonResponse
    {
        $user = $request->user();
        $result = $this->streakService->claimStreakBonus($user, $type);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Obtenir le leaderboard des streaks
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $type = $request->get('type', Streak::TYPE_DAILY_LOGIN ?? 'daily_login');
        $limit = min($request->get('limit', 10), 50);

        if (! in_array($type, array_keys(Streak::TYPES ?? []))) {
            return response()->json([
                'success' => false,
                'message' => 'Type de streak invalide',
            ], 400);
        }

        try {
            $leaderboardData = $this->streakService->getLeaderboard($type, $limit);
            $userRank = $this->streakService->getUserRank($request->user(), $type);

            return response()->json([
                'success' => true,
                'data' => array_merge($leaderboardData, [
                    'your_rank' => $userRank,
                ]),
                'message' => 'Leaderboard récupéré avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du leaderboard',
            ], 500);
        }
    }

    /**
     * Vérifier les streaks expirées
     */
    public function checkExpired(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->streakService->checkExpiredStreaks($user);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => $result['count'] > 0
                    ? "{$result['count']} streak(s) expirée(s)"
                    : 'Aucune streak expirée',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification des streaks expirées',
            ], 500);
        }
    }

    /**
     * Obtenir les détails d'une streak spécifique
     */
    public function show(Request $request, string $type): JsonResponse
    {
        $user = $request->user();
        $streak = $user->streaks()->where('type', $type)->first();

        if (! $streak) {
            return response()->json([
                'success' => false,
                'message' => 'Streak non trouvée',
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
            'risk_level' => method_exists($streak, 'getRiskLevel') ? $streak->getRiskLevel() : 'low',
            'next_milestone' => method_exists($streak, 'getNextMilestone') ? $streak->getNextMilestone() : null,
            'is_at_milestone' => method_exists($streak, 'isAtMilestone') ? $streak->isAtMilestone() : false,
            'can_claim_bonus' => method_exists($streak, 'canClaimBonus') ? $streak->canClaimBonus() : false,
            'bonus_xp_available' => method_exists($streak, 'calculateBonusXp') ? $streak->calculateBonusXp() : 0,
            'bonus_claimed_at' => $streak->bonus_claimed_at?->toDateTimeString(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'streak' => $streakData,
            ],
            'message' => 'Détails de la streak récupérés',
        ]);
    }

    /**
     * Réactiver une streak
     */
    public function reactivate(Request $request, string $type): JsonResponse
    {
        $user = $request->user();
        $streak = $user->streaks()->where('type', $type)->first();

        if (! $streak) {
            return response()->json([
                'success' => false,
                'message' => 'Streak non trouvée',
            ], 404);
        }

        try {
            if (method_exists($streak, 'reactivate')) {
                $streak->reactivate();
            } else {
                $streak->is_active = true;
                $streak->current_count = 0;
                $streak->last_activity_date = now();
                $streak->save();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'streak' => [
                        'type' => $streak->type,
                        'current_count' => $streak->current_count,
                        'is_active' => $streak->is_active,
                    ],
                ],
                'message' => 'Streak réactivée avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réactivation',
            ], 500);
        }
    }

    /**
     * ✅ MÉTHODE HELPER: Obtenir le nom d'un type de streak
     */
    protected function getStreakName(string $type): string
    {
        $names = [
            'daily_login' => 'Connexion quotidienne',
            'daily_transaction' => 'Transaction quotidienne',
            'weekly_savings' => 'Épargne hebdomadaire',
            'budget_respect' => 'Respect du budget',
            'goal_progress' => 'Progression des objectifs',
        ];

        return $names[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * ✅ AJOUT: Gestion test du daily_login (permet plusieurs par jour)
     */
    private function handleTestDailyLogin($user, $type): JsonResponse
    {
        // Simuler un gain d'XP et de streak
        $xpGained = 5;
        $streakCount = rand(1, 10);

        // Ajouter l'XP au niveau utilisateur si possible
        if ($user->level) {
            $user->level->addXp($xpGained);
        }

        // Enregistrer l'action dans user_actions si la table existe
        try {
            \DB::table('user_actions')->insert([
                'user_id' => $user->id,
                'action_type' => $type,
                'context' => 'test_mode',
                'xp_gained' => $xpGained,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Ignorer si la table n'existe pas
        }

        return response()->json([
            'success' => true,
            'message' => 'Daily login streak mise à jour (mode test)',
            'data' => [
                'streak_type' => $type,
                'streak_count' => $streakCount,
                'xp_gained' => $xpGained,
                'bonus_applied' => $streakCount >= 7,
                'next_bonus_in' => max(0, 7 - ($streakCount % 7)),
                'test_mode' => true,
            ],
        ]);
    }

    /**
     * ✅ AJOUT: Gestion normale des streaks
     */
    private function handleNormalStreak($user, $type): JsonResponse
    {
        // Vérifier si déjà fait aujourd'hui
        $today = now()->toDateString();

        try {
            $existingAction = \DB::table('user_actions')
                ->where('user_id', $user->id)
                ->where('action_type', $type)
                ->whereDate('created_at', $today)
                ->exists();

            if ($existingAction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Action déjà comptabilisée aujourd\'hui',
                    'data' => [
                        'streak_type' => $type,
                        'already_done_today' => true,
                        'next_available_at' => now()->addDay()->startOfDay()->toISOString(),
                    ],
                ]);
            }

            // Créer la nouvelle action
            \DB::table('user_actions')->insert([
                'user_id' => $user->id,
                'action_type' => $type,
                'context' => $type,
                'xp_gained' => 5,
                'created_at' => now(),
            ]);

            // Ajouter l'XP
            if ($user->level) {
                $user->level->addXp(5);
            }

            return response()->json([
                'success' => true,
                'message' => 'Streak mise à jour avec succès',
                'data' => [
                    'streak_type' => $type,
                    'xp_gained' => 5,
                    'action_recorded' => true,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la streak',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
