<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use App\Models\Projection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Contrôleur pour les projections financières avec IA
 * Prédictions intelligentes basées sur l'historique utilisateur
 */
class ProjectionController extends Controller
{
    /**
     * Obtenir les projections générales de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Cache de 1 heure pour les projections
            $projections = Cache::remember("projections_{$user->id}", 3600, function () use ($user) {
                return [
                    'balance_evolution' => $this->projectBalanceEvolution($user),
                    'goal_achievements' => $this->projectGoalAchievements($user),
                    'spending_trends' => $this->projectSpendingTrends($user),
                    'savings_potential' => $this->calculateSavingsPotential($user),
                    'risk_analysis' => $this->analyzeFinancialRisks($user),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $projections,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la génération des projections');
        }
    }

    /**
     * Projection spécifique pour un objectif financier
     */
    public function goalProjection(Request $request, int $goalId): JsonResponse
    {
        try {
            $user = $request->user();
            $goal = $user->financialGoals()->findOrFail($goalId);

            $projection = $this->generateGoalProjection($user, $goal);

            return response()->json([
                'success' => true,
                'data' => $projection,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la projection d\'objectif');
        }
    }

    /**
     * Projection de revenus basée sur l'historique
     */
    public function incomeProjection(Request $request): JsonResponse
    {
        $request->validate([
            'months' => 'integer|min:1|max:24',
        ]);

        try {
            $user = $request->user();
            $months = $request->input('months', 6);

            $projection = $this->projectIncome($user, $months);

            return response()->json([
                'success' => true,
                'data' => $projection,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la projection de revenus');
        }
    }

    /**
     * Analyse prédictive des dépenses par catégorie
     */
    public function expenseProjection(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'months' => 'integer|min:1|max:12',
        ]);

        try {
            $user = $request->user();
            $categoryId = $request->input('category_id');
            $months = $request->input('months', 3);

            $projection = $this->projectExpenses($user, $months, $categoryId);

            return response()->json([
                'success' => true,
                'data' => $projection,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la projection de dépenses');
        }
    }

    /**
     * Suggestions d'optimisation basées sur l'IA
     */
    public function optimizationSuggestions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $suggestions = Cache::remember("optimization_{$user->id}", 1800, function () use ($user) {
                return [
                    'immediate_actions' => $this->getImmediateActions($user),
                    'medium_term_strategies' => $this->getMediumTermStrategies($user),
                    'long_term_planning' => $this->getLongTermPlanning($user),
                    'risk_mitigation' => $this->getRiskMitigation($user),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $suggestions,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la génération des suggestions');
        }
    }

    // ==========================================
    // MÉTHODES DE PROJECTION PRIVÉES
    // ==========================================

    /**
     * Projeter l'évolution du solde
     */
    private function projectBalanceEvolution(User $user): array
    {
        $transactions = $user->transactions()
            ->where('created_at', '>=', now()->subMonths(6))
            ->orderBy('created_at')
            ->get();

        if ($transactions->isEmpty()) {
            return ['message' => 'Pas assez de données pour une projection'];
        }

        // Calcul de la tendance mensuelle
        $monthlyTrend = $this->calculateMonthlyTrend($transactions);

        // Projection sur 6 mois
        $projections = [];
        $currentBalance = $user->getBalance();

        for ($i = 1; $i <= 6; $i++) {
            $currentBalance += $monthlyTrend['average_monthly_flow'];
            $projections[] = [
                'month' => now()->addMonths($i)->format('Y-m'),
                'projected_balance' => round($currentBalance, 2),
                'confidence' => $this->calculateConfidence($monthlyTrend, $i),
            ];
        }

        return [
            'current_balance' => $user->getBalance(),
            'monthly_trend' => $monthlyTrend,
            'projections' => $projections,
            'insights' => $this->generateBalanceInsights($monthlyTrend),
        ];
    }

    /**
     * Projeter les achievements d'objectifs
     */
    private function projectGoalAchievements(User $user): array
    {
        $activeGoals = $user->financialGoals()->active()->get();

        return $activeGoals->map(function ($goal) use ($user) {
            return $this->generateGoalProjection($user, $goal);
        })->toArray();
    }

    /**
     * Générer la projection pour un objectif spécifique
     */
    private function generateGoalProjection(User $user, FinancialGoal $goal): array
    {
        $currentAmount = $goal->current_amount;
        $targetAmount = $goal->target_amount;
        $remaining = $targetAmount - $currentAmount;

        // Calculer la progression mensuelle moyenne
        $contributions = $goal->contributions()
            ->where('created_at', '>=', now()->subMonths(3))
            ->get();

        $avgMonthlyContribution = $contributions->isEmpty() ? 0 :
            $contributions->sum('amount') / max(1, $contributions->count());

        // Calcul du temps estimé
        $monthsToTarget = $avgMonthlyContribution > 0 ?
            ceil($remaining / $avgMonthlyContribution) : null;

        return [
            'goal_id' => $goal->id,
            'goal_name' => $goal->name,
            'current_progress' => round(($currentAmount / $targetAmount) * 100, 1),
            'remaining_amount' => $remaining,
            'avg_monthly_contribution' => round($avgMonthlyContribution, 2),
            'estimated_completion' => $monthsToTarget ?
                now()->addMonths($monthsToTarget)->format('Y-m-d') : null,
            'months_to_target' => $monthsToTarget,
            'difficulty' => $this->assessGoalDifficulty($goal, $avgMonthlyContribution),
            'suggestions' => $this->generateGoalSuggestions($goal, $avgMonthlyContribution),
        ];
    }

    /**
     * Projeter les tendances de dépenses
     */
    private function projectSpendingTrends(User $user): array
    {
        $categories = $user->categories()->get();

        return $categories->map(function ($category) use ($user) {
            $monthlySpending = $this->calculateCategoryMonthlyAverage($user, $category);

            return [
                'category_name' => $category->name,
                'monthly_average' => round($monthlySpending, 2),
                'projected_yearly' => round($monthlySpending * 12, 2),
                'trend' => $this->calculateCategoryTrend($user, $category),
                'optimization_potential' => $this->getCategoryOptimization($monthlySpending),
            ];
        })->toArray();
    }

    /**
     * Calculer le potentiel d'épargne
     */
    private function calculateSavingsPotential(User $user): array
    {
        $monthlyIncome = $this->calculateMonthlyIncome($user);
        $monthlyExpenses = $this->calculateMonthlyExpenses($user);
        $currentSavings = max(0, $monthlyIncome - $monthlyExpenses);

        return [
            'monthly_income' => round($monthlyIncome, 2),
            'monthly_expenses' => round($monthlyExpenses, 2),
            'current_savings_rate' => $monthlyIncome > 0 ?
                round(($currentSavings / $monthlyIncome) * 100, 1) : 0,
            'optimization_potential' => $this->findSavingsOptimizations($user),
            'recommended_savings_rate' => 20, // 20% recommandé
            'potential_monthly_savings' => round($currentSavings * 1.2, 2), // +20% optimisé
        ];
    }

    /**
     * Analyser les risques financiers
     */
    private function analyzeFinancialRisks(User $user): array
    {
        return [
            'emergency_fund_status' => $this->assessEmergencyFund($user),
            'spending_volatility' => $this->calculateSpendingVolatility($user),
            'income_stability' => $this->calculateIncomeStability($user),
            'debt_ratio' => $this->calculateDebtRatio($user),
            'overall_risk_score' => $this->calculateOverallRisk($user),
        ];
    }

    // ==========================================
    // MÉTHODES DE CALCUL UTILITAIRES
    // ==========================================

    /**
     * Calculer la tendance mensuelle
     */
    private function calculateMonthlyTrend($transactions): array
    {
        $monthlyData = $transactions->groupBy(function ($transaction) {
            return $transaction->created_at->format('Y-m');
        })->map(function ($monthTransactions) {
            $income = $monthTransactions->where('type', 'income')->sum('amount');
            $expenses = $monthTransactions->where('type', 'expense')->sum('amount');

            return $income - $expenses;
        });

        return [
            'average_monthly_flow' => round($monthlyData->average(), 2),
            'volatility' => round($monthlyData->isEmpty() ? 0 :
                sqrt($monthlyData->map(fn ($flow) => pow($flow - $monthlyData->average(), 2))->average()), 2),
            'trend_direction' => $monthlyData->count() >= 2 ?
                ($monthlyData->last() > $monthlyData->first() ? 'positive' : 'negative') : 'stable',
        ];
    }

    /**
     * Calculer la confiance de la projection
     */
    private function calculateConfidence(array $trend, int $monthsAhead): float
    {
        $baseConfidence = 0.8;
        $volatilityPenalty = min(0.3, $trend['volatility'] / 1000);
        $timePenalty = $monthsAhead * 0.05;

        return max(0.2, $baseConfidence - $volatilityPenalty - $timePenalty);
    }

    /**
     * Générer des insights sur l'évolution du solde
     */
    private function generateBalanceInsights(array $trend): array
    {
        $insights = [];

        if ($trend['average_monthly_flow'] > 0) {
            $insights[] = "📈 Tendance positive : +{$trend['average_monthly_flow']}€/mois";
        } else {
            $insights[] = "📉 Attention : {$trend['average_monthly_flow']}€/mois";
        }

        if ($trend['volatility'] > 500) {
            $insights[] = "⚠️ Revenus/dépenses irréguliers : planifiez un fonds d'urgence";
        }

        return $insights;
    }

    /**
     * Évaluer la difficulté d'un objectif
     */
    private function assessGoalDifficulty(FinancialGoal $goal, float $avgContribution): string
    {
        if ($avgContribution <= 0) {
            return 'impossible';
        }

        $monthsNeeded = ($goal->target_amount - $goal->current_amount) / $avgContribution;

        return match (true) {
            $monthsNeeded <= 6 => 'facile',
            $monthsNeeded <= 12 => 'modéré',
            $monthsNeeded <= 24 => 'difficile',
            default => 'très difficile'
        };
    }

    /**
     * Générer des suggestions pour un objectif
     */
    private function generateGoalSuggestions(FinancialGoal $goal, float $avgContribution): array
    {
        $suggestions = [];

        if ($avgContribution < 100) {
            $suggestions[] = '💡 Automatisez un virement de 50€/mois';
        }

        if ($goal->deadline && $goal->deadline->isPast()) {
            $suggestions[] = '⏰ Révisez la date limite ou augmentez vos contributions';
        }

        $remaining = $goal->target_amount - $goal->current_amount;
        if ($remaining > 0 && $avgContribution > 0) {
            $suggestedIncrease = ceil($remaining / 12); // Atteindre en 1 an
            $suggestions[] = "🎯 Contribution suggérée : {$suggestedIncrease}€/mois";
        }

        return $suggestions;
    }

    /**
     * Calculer la moyenne mensuelle d'une catégorie
     */
    private function calculateCategoryMonthlyAverage(User $user, $category): float
    {
        $transactions = $user->transactions()
            ->where('category_id', $category->id)
            ->where('created_at', '>=', now()->subMonths(6))
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $totalMonths = max(1, $transactions->first()->created_at->diffInMonths(now()));

        return $transactions->sum('amount') / $totalMonths;
    }

    /**
     * Calculer la tendance d'une catégorie
     */
    private function calculateCategoryTrend(User $user, $category): string
    {
        $recent = $user->transactions()
            ->where('category_id', $category->id)
            ->where('created_at', '>=', now()->subMonths(2))
            ->sum('amount');

        $older = $user->transactions()
            ->where('category_id', $category->id)
            ->where('created_at', '>=', now()->subMonths(4))
            ->where('created_at', '<', now()->subMonths(2))
            ->sum('amount');

        if ($older == 0) {
            return 'stable';
        }

        $change = ($recent - $older) / $older;

        return match (true) {
            $change > 0.1 => 'hausse',
            $change < -0.1 => 'baisse',
            default => 'stable'
        };
    }

    /**
     * Obtenir le potentiel d'optimisation d'une catégorie
     */
    private function getCategoryOptimization(float $monthlySpending): array
    {
        if ($monthlySpending < 50) {
            return ['level' => 'low', 'message' => 'Dépenses déjà optimisées'];
        }
        if ($monthlySpending < 200) {
            return ['level' => 'medium', 'message' => 'Potentiel d\'économie de 10-15%'];
        }

        return ['level' => 'high', 'message' => 'Fort potentiel d\'économie (20%+)'];
    }

    /**
     * Calculer le revenu mensuel moyen
     */
    private function calculateMonthlyIncome(User $user): float
    {
        $income = $user->transactions()
            ->where('type', 'income')
            ->where('created_at', '>=', now()->subMonths(3))
            ->avg('amount');

        return $income ?? 0;
    }

    /**
     * Calculer les dépenses mensuelles moyennes
     */
    private function calculateMonthlyExpenses(User $user): float
    {
        $expenses = $user->transactions()
            ->where('type', 'expense')
            ->where('created_at', '>=', now()->subMonths(3))
            ->sum('amount');

        return $expenses / 3; // Moyenne sur 3 mois
    }

    /**
     * Trouver des optimisations d'épargne
     */
    private function findSavingsOptimizations(User $user): array
    {
        $categories = $user->categories()
            ->where('type', 'expense')
            ->get()
            ->map(function ($category) use ($user) {
                $monthlyAvg = $this->calculateCategoryMonthlyAverage($user, $category);

                return [
                    'category' => $category->name,
                    'monthly_average' => $monthlyAvg,
                    'optimization_potential' => min(50, $monthlyAvg * 0.15), // 15% max
                ];
            })
            ->where('monthly_average', '>', 50)
            ->sortByDesc('optimization_potential')
            ->take(3)
            ->values();

        return $categories->toArray();
    }

    /**
     * Évaluer le fonds d'urgence
     */
    private function assessEmergencyFund(User $user): array
    {
        $monthlyExpenses = $this->calculateMonthlyExpenses($user);
        $currentBalance = $user->getBalance();
        $emergencyFundGoal = $user->financialGoals()
            ->where('type', 'emergency')
            ->first();

        $recommendedAmount = $monthlyExpenses * 3; // 3 mois de dépenses
        $coverage = $monthlyExpenses > 0 ? $currentBalance / $monthlyExpenses : 0;

        return [
            'current_coverage_months' => round($coverage, 1),
            'recommended_amount' => round($recommendedAmount, 2),
            'current_amount' => $emergencyFundGoal?->current_amount ?? 0,
            'status' => match (true) {
                $coverage >= 6 => 'excellent',
                $coverage >= 3 => 'bon',
                $coverage >= 1 => 'insuffisant',
                default => 'critique'
            },
        ];
    }

    /**
     * Calculer la volatilité des dépenses
     */
    private function calculateSpendingVolatility(User $user): float
    {
        $monthlyExpenses = $user->transactions()
            ->where('type', 'expense')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->pluck('total');

        if ($monthlyExpenses->count() < 2) {
            return 0;
        }

        $avg = $monthlyExpenses->average();
        $variance = $monthlyExpenses->map(fn ($expense) => pow($expense - $avg, 2))->average();

        return $avg > 0 ? round(sqrt($variance) / $avg, 3) : 0;
    }

    /**
     * Calculer la stabilité des revenus
     */
    private function calculateIncomeStability(User $user): float
    {
        $monthlyIncome = $user->transactions()
            ->where('type', 'income')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->pluck('total');

        if ($monthlyIncome->count() < 2) {
            return 0;
        }

        $coefficientVariation = $monthlyIncome->average() > 0 ?
            $monthlyIncome->standardDeviation() / $monthlyIncome->average() : 1;

        return max(0, 1 - $coefficientVariation); // Plus proche de 1 = plus stable
    }

    /**
     * Calculer le ratio d'endettement
     */
    private function calculateDebtRatio(User $user): float
    {
        $monthlyIncome = $this->calculateMonthlyIncome($user);
        $debtPayments = $user->transactions()
            ->where('type', 'expense')
            ->whereHas('category', function ($query) {
                $query->where('name', 'like', '%dette%')
                    ->orWhere('name', 'like', '%crédit%')
                    ->orWhere('name', 'like', '%prêt%');
            })
            ->where('created_at', '>=', now()->subMonths(3))
            ->avg('amount');

        return $monthlyIncome > 0 ? round(($debtPayments ?? 0) / $monthlyIncome, 3) : 0;
    }

    /**
     * Calculer le score de risque global
     */
    private function calculateOverallRisk(User $user): array
    {
        $volatility = $this->calculateSpendingVolatility($user);
        $stability = $this->calculateIncomeStability($user);
        $debtRatio = $this->calculateDebtRatio($user);
        $emergencyFund = $this->assessEmergencyFund($user);

        $riskScore = ($volatility * 0.3) + ((1 - $stability) * 0.3) + ($debtRatio * 0.2) +
            ($emergencyFund['current_coverage_months'] < 3 ? 0.2 : 0);

        return [
            'score' => round($riskScore, 2),
            'level' => match (true) {
                $riskScore < 0.3 => 'faible',
                $riskScore < 0.6 => 'modéré',
                default => 'élevé'
            },
            'factors' => [
                'volatility' => round($volatility, 2),
                'income_stability' => round($stability, 2),
                'debt_ratio' => round($debtRatio, 2),
                'emergency_coverage' => $emergencyFund['current_coverage_months'],
            ],
        ];
    }

    /**
     * Obtenir les actions immédiates
     */
    private function getImmediateActions(User $user): array
    {
        return [
            'Créer un budget mensuel strict',
            "Automatiser l'épargne (10% des revenus)",
            'Éliminer une dépense superflue identifiée',
        ];
    }

    /**
     * Obtenir les stratégies à moyen terme
     */
    private function getMediumTermStrategies(User $user): array
    {
        return [
            "Constituer 3 mois de fonds d'urgence",
            'Diversifier les sources de revenus',
            'Optimiser les gros postes de dépense',
        ];
    }

    /**
     * Obtenir la planification à long terme
     */
    private function getLongTermPlanning(User $user): array
    {
        return [
            'Investir dans un PEA/assurance-vie',
            'Planifier les gros projets (immobilier)',
            'Préparer la retraite',
        ];
    }

    /**
     * Obtenir la mitigation des risques
     */
    private function getRiskMitigation(User $user): array
    {
        return [
            'Souscrire une assurance adaptée',
            'Diversifier les placements',
            'Maintenir une épargne de précaution',
        ];
    }

    /**
     * Projeter les revenus
     */
    private function projectIncome(User $user, int $months): array
    {
        $historicalIncome = $user->transactions()
            ->where('type', 'income')
            ->where('created_at', '>=', now()->subMonths(6))
            ->orderBy('created_at')
            ->get();

        if ($historicalIncome->isEmpty()) {
            return ['message' => 'Pas de données de revenus pour projection'];
        }

        $monthlyAverage = $historicalIncome->avg('amount');
        $projections = [];

        for ($i = 1; $i <= $months; $i++) {
            $projections[] = [
                'month' => now()->addMonths($i)->format('Y-m'),
                'projected_income' => round($monthlyAverage, 2),
                'confidence' => $this->calculateConfidence(['volatility' => 100], $i),
            ];
        }

        return [
            'monthly_average' => round($monthlyAverage, 2),
            'projections' => $projections,
            'data_quality' => $historicalIncome->count() >= 3 ? 'good' : 'limited',
        ];
    }

    /**
     * Projeter les dépenses
     */
    private function projectExpenses(User $user, int $months, ?int $categoryId = null): array
    {
        $query = $user->transactions()->where('type', 'expense');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $expenses = $query->where('created_at', '>=', now()->subMonths(3))->get();

        if ($expenses->isEmpty()) {
            return ['message' => 'Pas de données de dépenses pour projection'];
        }

        $monthlyAverage = $expenses->sum('amount') / 3;
        $projections = [];

        for ($i = 1; $i <= $months; $i++) {
            $projections[] = [
                'month' => now()->addMonths($i)->format('Y-m'),
                'projected_expenses' => round($monthlyAverage, 2),
            ];
        }

        return [
            'monthly_average' => round($monthlyAverage, 2),
            'projections' => $projections,
            'category_name' => $categoryId ?
                $user->categories()->find($categoryId)?->name : 'Toutes catégories',
        ];
    }

    /**
     * Gérer les erreurs de manière centralisée
     */
    private function handleError(\Exception $exception, string $message): JsonResponse
    {
        \Log::error($message, [
            'exception' => $exception->getMessage(),
            'user_id' => auth()->id(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => config('app.debug') ? $exception->getMessage() : null,
        ], 500);
    }
}
