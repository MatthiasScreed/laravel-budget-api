<?php

namespace App\Services;

use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service de calcul de capacité d'épargne
 *
 * Calcule combien l'utilisateur peut épargner mensuellement
 * en fonction de ses revenus et dépenses
 */
class SavingsCapacityService
{
    /**
     * Calculer la capacité d'épargne mensuelle
     *
     * @param  int  $months  Nombre de mois à analyser (défaut: 3)
     */
    public function calculate(User $user, int $months = 3): array
    {
        try {
            // 1. Récupérer les transactions des X derniers mois
            $startDate = now()->subMonths($months)->startOfMonth();

            $transactions = Transaction::where('user_id', $user->id)
                ->where('transaction_date', '>=', $startDate)
                ->get();

            // 2. Calculer revenus et dépenses moyens
            $monthlyData = $this->calculateMonthlyAverages($transactions, $months);

            // 3. Calculer la capacité d'épargne
            $savingsCapacity = $monthlyData['avg_income'] - $monthlyData['avg_expenses'];

            // 4. Calculer le taux d'épargne
            $savingsRate = $monthlyData['avg_income'] > 0
                ? ($savingsCapacity / $monthlyData['avg_income']) * 100
                : 0;

            // 5. Analyser les catégories de dépenses
            $expensesByCategory = $this->analyzeExpensesByCategory($transactions);

            // 6. Identifier les dépenses compressibles
            $optimizableExpenses = $this->findOptimizableExpenses($expensesByCategory);

            return [
                'current_capacity' => round($savingsCapacity, 2),
                'monthly_income' => round($monthlyData['avg_income'], 2),
                'monthly_expenses' => round($monthlyData['avg_expenses'], 2),
                'savings_rate' => round($savingsRate, 1),
                'analysis_period_months' => $months,
                'total_transactions' => $transactions->count(),
                'expenses_by_category' => $expensesByCategory,
                'optimizable_expenses' => $optimizableExpenses,
                'recommendations' => $this->generateRecommendations(
                    $savingsCapacity,
                    $savingsRate,
                    $optimizableExpenses
                ),
            ];

        } catch (\Exception $e) {
            Log::error('Erreur calcul capacité épargne', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultCapacity();
        }
    }

    /**
     * Calculer les moyennes mensuelles
     */
    protected function calculateMonthlyAverages($transactions, int $months): array
    {
        $income = $transactions->where('type', 'income')->sum('amount');
        $expenses = $transactions->where('type', 'expense')->sum('amount');

        return [
            'avg_income' => $income / max($months, 1),
            'avg_expenses' => $expenses / max($months, 1),
            'total_income' => $income,
            'total_expenses' => $expenses,
        ];
    }

    /**
     * Analyser les dépenses par catégorie
     */
    protected function analyzeExpensesByCategory($transactions): array
    {
        $expenses = $transactions->where('type', 'expense');
        $totalExpenses = $expenses->sum('amount');

        $byCategory = $expenses->groupBy('category_id')->map(function ($items) use ($totalExpenses) {
            $categoryTotal = $items->sum('amount');

            return [
                'category_id' => $items->first()->category_id,
                'category_name' => $items->first()->category->name ?? 'Non catégorisé',
                'total' => round($categoryTotal, 2),
                'percentage' => $totalExpenses > 0
                    ? round(($categoryTotal / $totalExpenses) * 100, 1)
                    : 0,
                'transaction_count' => $items->count(),
                'avg_per_transaction' => round($categoryTotal / $items->count(), 2),
            ];
        })->sortByDesc('total')->values()->toArray();

        return $byCategory;
    }

    /**
     * Identifier les dépenses optimisables
     * (dépenses non essentielles > 10% du total)
     */
    protected function findOptimizableExpenses(array $expensesByCategory): array
    {
        // Catégories considérées comme optimisables
        $optimizableCategories = [
            'restaurants',
            'divertissement',
            'shopping',
            'loisirs',
            'subscriptions',
            'autres',
        ];

        $optimizable = array_filter($expensesByCategory, function ($category) use ($optimizableCategories) {
            $categoryName = strtolower($category['category_name']);

            foreach ($optimizableCategories as $opt) {
                if (str_contains($categoryName, $opt)) {
                    return $category['percentage'] > 10;
                }
            }

            return false;
        });

        return array_values($optimizable);
    }

    /**
     * Générer des recommandations personnalisées
     */
    protected function generateRecommendations(
        float $capacity,
        float $rate,
        array $optimizable
    ): array {
        $recommendations = [];

        // Recommandation selon le taux d'épargne
        if ($rate < 10) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Taux d\'épargne faible',
                'message' => "Votre taux d'épargne est de {$rate}%. L'idéal est entre 10% et 20%.",
                'priority' => 'high',
            ];
        } elseif ($rate >= 20) {
            $recommendations[] = [
                'type' => 'success',
                'title' => 'Excellent taux d\'épargne !',
                'message' => "Votre taux d'épargne de {$rate}% est excellent ! Continuez comme ça.",
                'priority' => 'low',
            ];
        }

        // Recommandations sur les dépenses optimisables
        if (! empty($optimizable)) {
            $topOptimizable = $optimizable[0];

            $recommendations[] = [
                'type' => 'info',
                'title' => 'Optimisation possible',
                'message' => "Vos dépenses en {$topOptimizable['category_name']} représentent {$topOptimizable['percentage']}% de vos dépenses. Réduire de 20% libérerait ".round($topOptimizable['total'] * 0.2, 2).'€/mois.',
                'priority' => 'medium',
            ];
        }

        // Recommandation selon la capacité absolue
        if ($capacity < 0) {
            $recommendations[] = [
                'type' => 'danger',
                'title' => 'Attention : Déficit',
                'message' => 'Vos dépenses dépassent vos revenus de '.abs(round($capacity, 2)).'€/mois.',
                'priority' => 'urgent',
            ];
        } elseif ($capacity > 0 && $capacity < 100) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Marge d\'épargne limitée',
                'message' => "Votre capacité d'épargne est de {$capacity}€/mois. Essayez d'optimiser vos dépenses.",
                'priority' => 'medium',
            ];
        }

        return $recommendations;
    }

    /**
     * Capacité par défaut en cas d'erreur
     */
    protected function getDefaultCapacity(): array
    {
        return [
            'current_capacity' => 0,
            'monthly_income' => 0,
            'monthly_expenses' => 0,
            'savings_rate' => 0,
            'analysis_period_months' => 0,
            'total_transactions' => 0,
            'expenses_by_category' => [],
            'optimizable_expenses' => [],
            'recommendations' => [],
        ];
    }

    /**
     * Calculer la distribution optimale vers les objectifs
     *
     * @param  float  $availableAmount  Montant disponible à répartir
     */
    public function distributeToGoals(User $user, float $availableAmount): array
    {
        $goals = FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('priority', 'asc')
            ->orderBy('target_date', 'asc')
            ->get();

        if ($goals->isEmpty()) {
            return [
                'distributions' => [],
                'unallocated' => $availableAmount,
                'message' => 'Aucun objectif actif',
            ];
        }

        $distributions = [];
        $remaining = $availableAmount;

        foreach ($goals as $goal) {
            if ($remaining <= 0) {
                break;
            }

            // Calculer le montant mensuel idéal pour atteindre l'objectif
            $monthsRemaining = max(1, now()->diffInMonths($goal->target_date));
            $amountNeeded = max(0, $goal->target_amount - $goal->current_amount);
            $idealMonthly = $amountNeeded / $monthsRemaining;

            // Allouer au maximum l'idéal, mais pas plus que ce qui reste
            $allocated = min($idealMonthly, $remaining);

            $distributions[] = [
                'goal_id' => $goal->id,
                'goal_name' => $goal->name,
                'allocated_amount' => round($allocated, 2),
                'ideal_amount' => round($idealMonthly, 2),
                'percentage_of_capacity' => $availableAmount > 0
                    ? round(($allocated / $availableAmount) * 100, 1)
                    : 0,
            ];

            $remaining -= $allocated;
        }

        return [
            'distributions' => $distributions,
            'unallocated' => round($remaining, 2),
            'total_allocated' => round($availableAmount - $remaining, 2),
        ];
    }
}
