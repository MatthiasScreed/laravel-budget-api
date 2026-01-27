<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gaming\CategoryCreatedRequest;
use App\Http\Requests\Gaming\GoalAchievedRequest;
use App\Http\Requests\Gaming\TransactionCreatedRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamingActionController extends Controller
{
    /**
     * Action déclenchée après création d'une transaction
     */
    public function transactionCreated(TransactionCreatedRequest $request): JsonResponse
    {
        $user = $request->user();
        $transactionId = $request->validated('transaction_id');

        // Vérifier que la transaction appartient à l'utilisateur
        $transaction = $user->transactions()->find($transactionId);

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée',
            ], 404);
        }

        // Vérifier et débloquer les succès liés aux transactions
        $unlockedAchievements = $user->checkAndUnlockAchievements();

        // Mise à jour des streaks si nécessaire
        $this->updateTransactionStreaks($user);

        // XP bonus pour la transaction (petite récompense)
        $bonusXp = $this->calculateTransactionBonusXp($transaction);
        if ($bonusXp > 0) {
            $user->addXp($bonusXp);
        }

        $response = [
            'success' => true,
            'data' => [
                'unlocked_achievements' => $this->formatAchievements($unlockedAchievements),
                'bonus_xp' => $bonusXp,
                'total_transactions' => $user->transactions()->count(),
                'streak_updated' => true, // TODO: implémenter vraiment les streaks
            ],
            'message' => 'Action transaction traitée avec succès',
        ];

        // Ajouter les détails des nouveaux succès s'il y en a
        if (! empty($unlockedAchievements)) {
            $response['data']['achievement_notification'] = [
                'title' => 'Nouveau succès débloqué !',
                'count' => count($unlockedAchievements),
                'total_xp_gained' => array_sum(array_column($unlockedAchievements, 'points')),
            ];
        }

        return response()->json($response);
    }

    /**
     * Action déclenchée après atteinte d'un objectif
     */
    public function goalAchieved(GoalAchievedRequest $request): JsonResponse
    {
        $user = $request->user();
        $goalId = $request->validated('goal_id');

        // Vérifier que l'objectif appartient à l'utilisateur
        $goal = $user->financialGoals()->find($goalId);

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Objectif non trouvé',
            ], 404);
        }

        // XP spécial pour atteinte d'objectif
        $goalXp = $this->calculateGoalBonusXp($goal);
        $user->addXp($goalXp);

        // Vérifier les succès
        $unlockedAchievements = $user->checkAndUnlockAchievements();

        return response()->json([
            'success' => true,
            'data' => [
                'goal_bonus_xp' => $goalXp,
                'unlocked_achievements' => $this->formatAchievements($unlockedAchievements),
                'goal_info' => [
                    'name' => $goal->name,
                    'target_amount' => $goal->target_amount,
                    'completed_at' => $goal->completed_at,
                ],
                'total_completed_goals' => $user->financialGoals()->completed()->count(),
            ],
            'message' => 'Félicitations ! Objectif atteint et récompenses accordées',
        ]);
    }

    /**
     * Action déclenchée après création d'une catégorie
     */
    public function categoryCreated(CategoryCreatedRequest $request): JsonResponse
    {
        $user = $request->user();
        $categoryId = $request->validated('category_id');

        // Vérifier que la catégorie appartient à l'utilisateur
        $category = $user->categories()->find($categoryId);

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée',
            ], 404);
        }

        // Petit bonus XP pour l'organisation
        $organizationXp = 5;
        $user->addXp($organizationXp);

        // Vérifier les succès (notamment "Organisé")
        $unlockedAchievements = $user->checkAndUnlockAchievements();

        return response()->json([
            'success' => true,
            'data' => [
                'organization_bonus_xp' => $organizationXp,
                'unlocked_achievements' => $this->formatAchievements($unlockedAchievements),
                'category_info' => [
                    'name' => $category->name,
                    'type' => $category->type,
                ],
                'total_categories' => $user->categories()->count(),
            ],
            'message' => 'Catégorie créée et bonus accordé',
        ]);
    }

    /**
     * Action générique pour ajouter de l'XP manuellement (admin/debug)
     */
    public function addXp(Request $request): JsonResponse
    {
        $request->validate([
            'xp' => 'required|integer|min:1|max:1000',
            'reason' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $xp = $request->input('xp');
        $reason = $request->input('reason', 'Bonus manuel');

        $beforeStats = $user->getGamingStats();
        $levelUpResult = $user->addXp($xp);
        $afterStats = $user->fresh()->getGamingStats();

        return response()->json([
            'success' => true,
            'data' => [
                'xp_added' => $xp,
                'reason' => $reason,
                'leveled_up' => $levelUpResult['leveled_up'] ?? false,
                'levels_gained' => $levelUpResult['levels_gained'] ?? 0,
                'old_stats' => $beforeStats,
                'new_stats' => $afterStats,
            ],
            'message' => "XP ajouté avec succès: +{$xp} ({$reason})",
        ]);
    }

    /**
     * Formater les succès pour la réponse API
     */
    private function formatAchievements(array $achievements): array
    {
        return array_map(function ($achievement) {
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
        }, $achievements);
    }

    /**
     * Calculer le bonus XP pour une transaction
     *
     * @param  \App\Models\Transaction  $transaction
     */
    private function calculateTransactionBonusXp($transaction): int
    {
        // Bonus basique pour toute transaction
        $baseXp = 2;

        // Bonus selon le type
        if ($transaction->type === 'income') {
            $baseXp += 1; // Encourager les revenus
        }

        // Bonus pour les gros montants (plafonné)
        if ($transaction->amount >= 100) {
            $baseXp += min(3, floor($transaction->amount / 100));
        }

        return min(10, $baseXp); // Plafonner à 10 XP par transaction
    }

    /**
     * Calculer le bonus XP pour un objectif atteint
     *
     * @param  \App\Models\FinancialGoal  $goal
     */
    private function calculateGoalBonusXp($goal): int
    {
        // XP basé sur le montant de l'objectif
        $baseXp = 50; // Bonus fixe pour atteindre un objectif

        // Bonus selon le montant (1 XP par 100€, plafonné à 50)
        $amountBonus = min(50, floor($goal->target_amount / 100));

        // Bonus selon la durée (plus long = plus de XP)
        $durationBonus = 0;
        if ($goal->start_date && $goal->completed_at) {
            $months = $goal->start_date->diffInMonths($goal->completed_at);
            $durationBonus = min(25, $months * 5);
        }

        return $baseXp + $amountBonus + $durationBonus;
    }

    /**
     * Mettre à jour les streaks de transaction
     *
     * @param  \App\Models\User  $user
     */
    private function updateTransactionStreaks($user): void
    {
        // TODO: Implémenter la logique des streaks
        // Pour l'instant, on ne fait rien mais la structure est prête

        // Exemple de logique future :
        // - Vérifier si l'utilisateur a une transaction aujourd'hui
        // - Mettre à jour sa streak quotidienne
        // - Donner des bonus XP pour les longues streaks
    }
}
