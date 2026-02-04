<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use App\Models\Streak;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Controller - CoinQuest
 *
 * Gère les statistiques du dashboard avec :
 * - Capacité d'épargne = Solde bancaire - Dépenses du mois
 * - Distribution intelligente aux objectifs
 * - Suggestions d'accélération
 *
 * ✅ VERSION CORRIGÉE - Stats gaming avec valeurs SCALAIRES
 */
class DashboardController extends Controller
{
    // ==========================================
    // ENDPOINT PRINCIPAL
    // ==========================================

    /**
     * Récupérer les statistiques complètes du dashboard
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            Log::info('📊 Dashboard stats requested', [
                'user_id' => $user->id,
            ]);

            $stats = $this->buildDashboardStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur stats dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ==========================================
    // CONSTRUCTION DES STATISTIQUES
    // ==========================================

    /**
     * Construire l'ensemble des statistiques du dashboard
     */
    private function buildDashboardStats($user): array
    {
        $now = now();
        $monthDates = $this->getMonthDates($now);

        // Calculs financiers de base
        $totalBalance = $this->getTotalBalance($user);
        $monthlyIncome = $this->getMonthlyIncome($user, $monthDates);
        $monthlyExpenses = $this->getMonthlyExpenses($user, $monthDates);

        // ✅ CORRECTION: Capacité d'épargne = Revenus - Dépenses du mois
        // C'est ce qu'il reste à la fin du mois pour épargner
        $savingsCapacity = $monthlyIncome - $monthlyExpenses;

        return [
            'total_balance' => round($totalBalance, 2),
            'current_month' => $this->getCurrentMonthStats($user, $monthDates),
            'savings_capacity' => [
                'amount' => round($savingsCapacity, 2),
                'is_positive' => $savingsCapacity > 0,
                'calculation' => [
                    'monthly_income' => round($monthlyIncome, 2),
                    'monthly_expenses' => round($monthlyExpenses, 2),
                    'formula' => 'monthly_income - monthly_expenses',
                ],
            ],
            'comparison' => $this->getMonthComparison($user, $now),
            'goals' => $this->getGoalsStats($user, $savingsCapacity),
            'streak' => $this->getActiveStreak($user),
            'period' => $this->getPeriodInfo($monthDates),
            // ✅ CORRECTION ICI - Utiliser la méthode corrigée
            'user' => $this->getUserInfo($user),
        ];
    }

    // ==========================================
    // CALCULS FINANCIERS DE BASE
    // ==========================================

    private function getMonthDates($date): array
    {
        return [
            'start' => $date->copy()->startOfMonth(),
            'end' => $date->copy()->endOfMonth(),
        ];
    }

    private function getTotalBalance($user): float
    {
        return Transaction::where('user_id', $user->id)
            ->sum(DB::raw('CASE
                WHEN type = "income" THEN amount
                WHEN type = "expense" THEN -amount
                ELSE 0 END'
            ));
    }

    private function getCurrentMonthStats($user, array $dates): array
    {
        $income = $this->getMonthlyIncome($user, $dates);
        $expenses = $this->getMonthlyExpenses($user, $dates);
        $net = $income - $expenses;
        $transactionCount = $this->getTransactionCount($user, $dates);

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'net' => round($net, 2),
            'transactions_count' => $transactionCount,
        ];
    }

    private function getMonthlyIncome($user, array $dates): float
    {
        return Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$dates['start'], $dates['end']])
            ->sum('amount');
    }

    private function getMonthlyExpenses($user, array $dates): float
    {
        return Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$dates['start'], $dates['end']])
            ->sum('amount');
    }

    private function getTransactionCount($user, array $dates): int
    {
        return Transaction::where('user_id', $user->id)
            ->whereBetween('transaction_date', [$dates['start'], $dates['end']])
            ->count();
    }

    // ==========================================
    // COMPARAISON TEMPORELLE
    // ==========================================

    private function getMonthComparison($user, $now): array
    {
        $lastMonth = $now->copy()->subMonth();
        $lastMonthDates = $this->getMonthDates($lastMonth);

        // ✅ CORRECTION: Capacité = Revenus - Dépenses (pas Solde - Dépenses)
        $lastMonthIncome = $this->getMonthlyIncome($user, $lastMonthDates);
        $lastMonthExpenses = $this->getMonthlyExpenses($user, $lastMonthDates);
        $lastMonthCapacity = $lastMonthIncome - $lastMonthExpenses;

        $currentMonthDates = $this->getMonthDates($now);
        $currentIncome = $this->getMonthlyIncome($user, $currentMonthDates);
        $currentExpenses = $this->getMonthlyExpenses($user, $currentMonthDates);
        $currentCapacity = $currentIncome - $currentExpenses;

        $changePercent = $this->calculateChange($lastMonthCapacity, $currentCapacity);

        return [
            'last_month_capacity' => round($lastMonthCapacity, 2),
            'current_month_capacity' => round($currentCapacity, 2),
            'change_percent' => $changePercent,
            'trend' => $this->getTrend($changePercent),
        ];
    }

    private function calculateChange(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function getTrend(float $changePercent): string
    {
        if ($changePercent > 5) return 'up';
        if ($changePercent < -5) return 'down';
        return 'stable';
    }

    // ==========================================
    // STATISTIQUES DES OBJECTIFS
    // ==========================================

    private function getGoalsStats($user, float $savingsCapacity): array
    {
        $activeGoals = FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        $totalMonthlyTargets = FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->sum('monthly_target');

        $goalsWithTarget = FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('monthly_target', '>', 0)
            ->count();

        $totalSaved = FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->sum('current_amount');

        $totalTarget = FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->sum('target_amount');

        return [
            'active_count' => $activeGoals,
            'goals_with_target' => $goalsWithTarget,
            'available_capacity' => round(max(0, $savingsCapacity), 2),
            'total_monthly_targets' => round($totalMonthlyTargets, 2),
            'total_saved' => round($totalSaved, 2),
            'total_target' => round($totalTarget, 2),
            'capacity_status' => $this->getCapacityStatus($savingsCapacity, $totalMonthlyTargets),
        ];
    }

    private function getCapacityStatus(float $capacity, float $monthlyTargets): array
    {
        if ($monthlyTargets == 0) {
            return [
                'status' => 'not_configured',
                'message' => 'Configurez vos contributions mensuelles',
                'color' => 'gray',
            ];
        }

        if ($capacity <= 0) {
            return [
                'status' => 'insufficient',
                'message' => 'Capacité d\'épargne insuffisante',
                'deficit' => round($monthlyTargets, 2),
                'color' => 'red',
            ];
        }

        $ratio = $capacity / $monthlyTargets;

        if ($ratio >= 1) {
            return [
                'status' => 'excellent',
                'message' => 'Capacité suffisante pour vos objectifs',
                'surplus' => round($capacity - $monthlyTargets, 2),
                'color' => 'green',
            ];
        }

        if ($ratio >= 0.8) {
            return [
                'status' => 'warning',
                'message' => 'Capacité limite pour vos objectifs',
                'deficit' => round($monthlyTargets - $capacity, 2),
                'color' => 'orange',
            ];
        }

        return [
            'status' => 'deficit',
            'message' => 'Contributions supérieures à votre capacité',
            'deficit' => round($monthlyTargets - $capacity, 2),
            'color' => 'red',
        ];
    }

    // ==========================================
    // GAMING & AUTRES INFOS
    // ==========================================

    private function getActiveStreak($user): ?array
    {
        $streak = Streak::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$streak) {
            return null;
        }

        return [
            'days' => $streak->current_count,
            'type' => $streak->type,
            'best' => $streak->best_count ?? $streak->current_count,
        ];
    }

    private function getPeriodInfo(array $dates): array
    {
        return [
            'start' => $dates['start']->toDateString(),
            'end' => $dates['end']->toDateString(),
            'label' => ucfirst($dates['start']->translatedFormat('F Y')),
        ];
    }

    /**
     * ✅ CORRECTION : Extraire les VALEURS SCALAIRES, pas les objets
     *
     * AVANT (bug) : 'level' => $user->level  → retourne l'objet UserLevel entier
     * APRÈS (fix) : 'level' => $user->level?->level ?? 1  → retourne juste le nombre
     */
    private function getUserInfo($user): array
    {
        // ✅ Charger la relation si pas encore chargée
        $user->loadMissing('level');

        // ✅ Extraire les valeurs SCALAIRES depuis l'objet UserLevel
        $userLevel = $user->level;

        // Compter les achievements débloqués
        $achievementsCount = 0;
        try {
            $achievementsCount = DB::table('user_achievements')
                ->where('user_id', $user->id)
                ->where('unlocked', true)
                ->count();
        } catch (\Exception $e) {
            // Table n'existe peut-être pas
        }

        return [
            // ✅ CORRECTION : $user->level est un OBJET, on extrait ->level
            'level' => $userLevel?->level ?? 1,
            // ✅ CORRECTION : Extraire total_xp depuis l'objet
            'xp' => $userLevel?->total_xp ?? 0,
            // ✅ CORRECTION : Compter les achievements, pas retourner la relation
            'achievements' => $achievementsCount,
        ];
    }

    // ==========================================
    // ENDPOINTS SUPPLÉMENTAIRES
    // ==========================================

    public function getGoalDistribution(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // ✅ CORRECTION: Capacité = Revenus - Dépenses du mois
            $monthDates = $this->getMonthDates(now());
            $monthlyIncome = $this->getMonthlyIncome($user, $monthDates);
            $monthlyExpenses = $this->getMonthlyExpenses($user, $monthDates);
            $capacity = max(0, $monthlyIncome - $monthlyExpenses);

            $goals = FinancialGoal::where('user_id', $user->id)
                ->where('status', 'active')
                ->orderBy('priority', 'asc')
                ->orderBy('target_date', 'asc')
                ->get();

            $suggestions = $this->generateDistributionSuggestions($goals, $capacity);

            return response()->json([
                'success' => true,
                'data' => [
                    'savings_capacity' => round($capacity, 2),
                    'goals' => $goals->map(function ($goal) {
                        return [
                            'id' => $goal->id,
                            'name' => $goal->name,
                            'icon' => $goal->icon,
                            'color' => $goal->color,
                            'target_amount' => $goal->target_amount,
                            'current_amount' => $goal->current_amount,
                            'remaining_amount' => $goal->remaining_amount,
                            'monthly_target' => $goal->monthly_target,
                            'months_remaining' => $goal->months_remaining,
                            'projected_completion_date' => $goal->projected_completion_date,
                            'progress_percentage' => $goal->progress_percentage,
                            'can_accelerate' => $goal->can_accelerate,
                        ];
                    }),
                    'suggestions' => $suggestions,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur distribution objectifs', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur distribution aux objectifs',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function generateDistributionSuggestions($goals, float $capacity): array
    {
        $suggestions = [];

        if ($goals->isEmpty() || $capacity <= 0) {
            return $suggestions;
        }

        $totalMonthly = $goals->sum('monthly_target');

        // Suggestion 1 : Répartition égale
        if ($goals->count() > 0) {
            $equalAmount = $capacity / $goals->count();

            $suggestions[] = [
                'type' => 'equal',
                'title' => 'Répartition égale',
                'description' => 'Distribuer équitablement sur tous les objectifs',
                'total_amount' => round($capacity, 2),
                'distribution' => $goals->map(function ($goal) use ($equalAmount) {
                    $amount = min($equalAmount, $goal->remaining_amount);
                    return [
                        'goal_id' => $goal->id,
                        'goal_name' => $goal->name,
                        'amount' => round($amount, 2),
                        'simulation' => $goal->simulateContribution($amount),
                    ];
                })->toArray(),
            ];
        }

        // Suggestion 2 : Priorité à l'objectif le plus proche
        $closestGoal = $goals
            ->filter(fn ($g) => $g->months_remaining !== null && $g->months_remaining > 0)
            ->sortBy('months_remaining')
            ->first();

        if ($closestGoal && $capacity >= $closestGoal->remaining_amount) {
            $suggestions[] = [
                'type' => 'complete_closest',
                'title' => 'Compléter le plus proche',
                'description' => "Financer complètement '{$closestGoal->name}' et répartir le reste",
                'total_amount' => round($capacity, 2),
                'distribution' => [[
                    'goal_id' => $closestGoal->id,
                    'goal_name' => $closestGoal->name,
                    'amount' => round($closestGoal->remaining_amount, 2),
                    'simulation' => $closestGoal->simulateContribution($closestGoal->remaining_amount),
                ]],
            ];
        }

        // Suggestion 3 : Accélérer tous d'1 mois
        if ($totalMonthly > 0 && $capacity >= $totalMonthly) {
            $suggestions[] = [
                'type' => 'accelerate_all',
                'title' => 'Accélérer tous d\'1 mois',
                'description' => 'Gagner 1 mois sur tous les objectifs',
                'total_amount' => round(min($capacity, $totalMonthly), 2),
                'distribution' => $goals
                    ->filter(fn ($g) => $g->monthly_target > 0)
                    ->map(function ($goal) {
                        $amount = min($goal->monthly_target, $goal->remaining_amount);
                        return [
                            'goal_id' => $goal->id,
                            'goal_name' => $goal->name,
                            'amount' => round($amount, 2),
                            'simulation' => $goal->simulateContribution($amount),
                        ];
                    })->toArray(),
            ];
        }

        // Suggestion 4 : Priorités haute d'abord
        $highPriorityGoals = $goals->where('priority', '<=', 2);
        if ($highPriorityGoals->count() > 0) {
            $perGoalAmount = $capacity / $highPriorityGoals->count();

            $suggestions[] = [
                'type' => 'priority',
                'title' => 'Objectifs prioritaires',
                'description' => 'Focus sur les objectifs à haute priorité',
                'total_amount' => round($capacity, 2),
                'distribution' => $highPriorityGoals->map(function ($goal) use ($perGoalAmount) {
                    $amount = min($perGoalAmount, $goal->remaining_amount);
                    return [
                        'goal_id' => $goal->id,
                        'goal_name' => $goal->name,
                        'amount' => round($amount, 2),
                        'simulation' => $goal->simulateContribution($amount),
                    ];
                })->toArray(),
            ];
        }

        return $suggestions;
    }

    public function refreshAll(): JsonResponse
    {
        try {
            $user = Auth::user();

            Log::info('🔄 Refresh dashboard', ['user_id' => $user->id]);

            $stats = $this->buildDashboardStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Dashboard rafraîchi avec succès',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur refresh dashboard', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rafraîchissement',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
