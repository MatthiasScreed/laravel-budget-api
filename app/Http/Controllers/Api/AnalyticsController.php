<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get comprehensive dashboard analytics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        $period = $request->get('period', 'month'); // month, quarter, year, all

        $analytics = [
            'overview' => $this->getOverviewStats($user, $period),
            'cash_flow' => $this->getCashFlowAnalysis($user, $period),
            'category_breakdown' => $this->getCategoryBreakdown($user, $period),
            'trends' => $this->getTrends($user, $period),
            'goals_progress' => $this->getGoalsProgress($user),
            'gaming_stats' => $this->getGamingAnalytics($user, $period),
            'insights' => $this->generateInsights($user, $period)
        ];

        return $this->successResponse($analytics, 'Analytics dashboard récupérées avec succès');
    }

    /**
     * Get monthly financial report
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        $month = $request->get('month', now()->format('Y-m'));

        try {
            $date = Carbon::createFromFormat('Y-m', $month);
        } catch (\Exception $e) {
            return $this->errorResponse('Format de mois invalide. Utilisez Y-m (ex: 2024-03)');
        }

        $report = [
            'period' => [
                'month' => $date->format('Y-m'),
                'month_name' => $date->locale('fr')->format('F Y'),
                'days_in_month' => $date->daysInMonth,
                'days_elapsed' => min($date->day, $date->daysInMonth)
            ],
            'summary' => $this->getMonthlySummary($user, $date),
            'daily_breakdown' => $this->getDailyBreakdown($user, $date),
            'category_analysis' => $this->getMonthlyCategoryAnalysis($user, $date),
            'comparison' => $this->getMonthlyComparison($user, $date),
            'projections' => $this->getMonthlyProjections($user, $date),
            'recommendations' => $this->getMonthlyRecommendations($user, $date)
        ];

        return $this->successResponse($report, "Rapport mensuel pour {$date->format('F Y')} généré avec succès");
    }

    /**
     * Get yearly financial report
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function yearlyReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        $year = $request->get('year', now()->year);

        $report = [
            'year' => $year,
            'summary' => $this->getYearlySummary($user, $year),
            'monthly_breakdown' => $this->getYearlyMonthlyBreakdown($user, $year),
            'category_trends' => $this->getYearlyCategoryTrends($user, $year),
            'goals_achieved' => $this->getYearlyGoalsAchieved($user, $year),
            'gaming_progression' => $this->getYearlyGamingProgression($user, $year),
            'year_over_year' => $this->getYearOverYearComparison($user, $year)
        ];

        return $this->successResponse($report, "Rapport annuel {$year} généré avec succès");
    }

    /**
     * Get category breakdown analysis
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function categoryBreakdown(Request $request): JsonResponse
    {
        $user = Auth::user();
        $period = $request->get('period', 'month');
        $type = $request->get('type', 'expense'); // income, expense, both

        $analysis = [
            'summary' => $this->getCategoryBreakdownSummary($user, $period, $type),
            'detailed_breakdown' => $this->getDetailedCategoryBreakdown($user, $period, $type),
            'spending_patterns' => $this->getSpendingPatterns($user, $period, $type),
            'recommendations' => $this->getCategoryRecommendations($user, $period, $type)
        ];

        return $this->successResponse($analysis, 'Analyse des catégories générée avec succès');
    }

    /**
     * Get spending trends analysis
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function spendingTrends(Request $request): JsonResponse
    {
        $user = Auth::user();
        $months = min($request->get('months', 6), 24); // Max 24 mois

        $trends = [
            'overview' => $this->getSpendingTrendsOverview($user, $months),
            'by_category' => $this->getSpendingTrendsByCategory($user, $months),
            'seasonal_patterns' => $this->getSeasonalPatterns($user, $months),
            'anomalies' => $this->detectSpendingAnomalies($user, $months),
            'predictions' => $this->predictSpending($user, $months)
        ];

        return $this->successResponse($trends, 'Analyse des tendances générée avec succès');
    }

    /**
     * Get budget vs actual analysis
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function budgetAnalysis(Request $request): JsonResponse
    {
        $user = Auth::user();
        $period = $request->get('period', 'month');

        // Note: Nécessite un système de budget que vous pourrez implémenter plus tard
        $analysis = [
            'budget_summary' => $this->getBudgetSummary($user, $period),
            'variance_analysis' => $this->getVarianceAnalysis($user, $period),
            'budget_performance' => $this->getBudgetPerformance($user, $period),
            'recommendations' => $this->getBudgetRecommendations($user, $period)
        ];

        return $this->successResponse($analysis, 'Analyse budgétaire générée avec succès');
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats($user, $period): array
    {
        $query = $user->transactions();
        $this->applyPeriodFilter($query, $period);

        $income = $query->where('type', 'income')->sum('amount');
        $expenses = $query->where('type', 'expense')->sum('amount');

        return [
            'total_income' => $income,
            'total_expenses' => $expenses,
            'net_income' => $income - $expenses,
            'transaction_count' => $query->count(),
            'avg_transaction' => round($query->avg('amount'), 2),
            'largest_expense' => $user->transactions()->where('type', 'expense')->max('amount'),
            'categories_used' => $query->distinct('category_id')->count()
        ];
    }

    /**
     * Get cash flow analysis
     */
    private function getCashFlowAnalysis($user, $period): array
    {
        $query = $user->transactions();
        $this->applyPeriodFilter($query, $period);

        // Analyse par semaine/mois selon la période
        $groupBy = $period === 'month' ? 'WEEK' : 'MONTH';

        $cashFlow = $query
            ->selectRaw("
                {$groupBy}(transaction_date) as period,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expenses,
                SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as net
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'cash_flow_data' => $cashFlow,
            'average_weekly_income' => $cashFlow->avg('income'),
            'average_weekly_expenses' => $cashFlow->avg('expenses'),
            'best_week' => $cashFlow->max('net'),
            'worst_week' => $cashFlow->min('net'),
            'consistency_score' => $this->calculateConsistencyScore($cashFlow)
        ];
    }

    /**
     * Get category breakdown
     */
    private function getCategoryBreakdown($user, $period): array
    {
        $query = $user->transactions()->with('category');
        $this->applyPeriodFilter($query, $period);

        $breakdown = $query
            ->selectRaw('
                categories.name as category_name,
                categories.type as category_type,
                categories.color as category_color,
                categories.icon as category_icon,
                COUNT(transactions.id) as transaction_count,
                SUM(transactions.amount) as total_amount,
                AVG(transactions.amount) as avg_amount
            ')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name', 'categories.type', 'categories.color', 'categories.icon')
            ->orderBy('total_amount', 'desc')
            ->get();

        $totalAmount = $breakdown->sum('total_amount');

        return $breakdown->map(function ($item) use ($totalAmount) {
            return [
                'category_name' => $item->category_name,
                'category_type' => $item->category_type,
                'category_color' => $item->category_color,
                'category_icon' => $item->category_icon,
                'transaction_count' => $item->transaction_count,
                'total_amount' => $item->total_amount,
                'avg_amount' => round($item->avg_amount, 2),
                'percentage' => $totalAmount > 0 ? round(($item->total_amount / $totalAmount) * 100, 2) : 0
            ];
        })->toArray();
    }

    /**
     * Get trends analysis
     */
    private function getTrends($user, $period): array
    {
        // Comparer avec la période précédente
        $currentQuery = $user->transactions();
        $this->applyPeriodFilter($currentQuery, $period);

        $previousQuery = $user->transactions();
        $this->applyPreviousPeriodFilter($previousQuery, $period);

        $current = [
            'income' => $currentQuery->where('type', 'income')->sum('amount'),
            'expenses' => $currentQuery->where('type', 'expense')->sum('amount'),
            'transactions' => $currentQuery->count()
        ];

        $previous = [
            'income' => $previousQuery->where('type', 'income')->sum('amount'),
            'expenses' => $previousQuery->where('type', 'expense')->sum('amount'),
            'transactions' => $previousQuery->count()
        ];

        return [
            'income_change' => $this->calculateChangePercentage($previous['income'], $current['income']),
            'expenses_change' => $this->calculateChangePercentage($previous['expenses'], $current['expenses']),
            'transactions_change' => $this->calculateChangePercentage($previous['transactions'], $current['transactions']),
            'net_change' => $this->calculateChangePercentage(
                $previous['income'] - $previous['expenses'],
                $current['income'] - $current['expenses']
            )
        ];
    }

    /**
     * Get goals progress
     */
    private function getGoalsProgress($user): array
    {
        $goals = $user->financialGoals()->get();

        return [
            'total_goals' => $goals->count(),
            'active_goals' => $goals->where('status', 'active')->count(),
            'completed_goals' => $goals->where('status', 'completed')->count(),
            'total_target' => $goals->sum('target_amount'),
            'total_saved' => $goals->sum('current_amount'),
            'overall_progress' => $goals->count() > 0 ? round($goals->avg(function ($goal) {
                return $goal->getProgressPercentage();
            }), 2) : 0,
            'goals_on_track' => $goals->filter(function ($goal) {
                return $this->isGoalOnTrack($goal);
            })->count()
        ];
    }

    /**
     * Get gaming analytics
     */
    private function getGamingAnalytics($user, $period): array
    {
        return [
            'current_level' => $user->level?->level ?? 1,
            'total_xp' => $user->level?->total_xp ?? 0,
            'achievements_unlocked' => $user->achievements()->count(),
            'active_streaks' => $user->streaks()->where('is_active', true)->count(),
            'best_streak' => $user->streaks()->max('best_count') ?? 0,
            'xp_this_period' => $this->getXpForPeriod($user, $period),
            'achievements_this_period' => $this->getAchievementsForPeriod($user, $period)
        ];
    }

    /**
     * Generate insights based on data
     */
    private function generateInsights($user, $period): array
    {
        $insights = [];

        // Analyse des dépenses
        $topExpenseCategory = $user->transactions()
            ->where('type', 'expense')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw('categories.name, SUM(transactions.amount) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total', 'desc')
            ->first();

        if ($topExpenseCategory) {
            $insights[] = [
                'type' => 'spending',
                'title' => 'Principale catégorie de dépenses',
                'message' => "Vous dépensez le plus dans la catégorie '{$topExpenseCategory->name}' avec {$topExpenseCategory->total}€",
                'actionable' => true,
                'suggestion' => "Analysez vos dépenses en '{$topExpenseCategory->name}' pour identifier des économies possibles"
            ];
        }

        // Analyse des objectifs
        $nearGoals = $user->financialGoals()
            ->where('status', 'active')
            ->get()
            ->filter(function ($goal) {
                return $goal->getProgressPercentage() > 80;
            });

        if ($nearGoals->count() > 0) {
            $insights[] = [
                'type' => 'goals',
                'title' => 'Objectifs presque atteints',
                'message' => "Vous êtes proche d'atteindre {$nearGoals->count()} objectif(s) !",
                'actionable' => true,
                'suggestion' => 'Un petit effort supplémentaire pour finaliser ces objectifs'
            ];
        }

        return $insights;
    }

    /**
     * Apply period filter to query
     */
    private function applyPeriodFilter($query, $period): void
    {
        switch ($period) {
            case 'month':
                $query->whereMonth('transaction_date', now()->month)
                    ->whereYear('transaction_date', now()->year);
                break;
            case 'quarter':
                $query->whereBetween('transaction_date', [
                    now()->startOfQuarter(),
                    now()->endOfQuarter()
                ]);
                break;
            case 'year':
                $query->whereYear('transaction_date', now()->year);
                break;
            // 'all' = no filter
        }
    }

    /**
     * Apply previous period filter to query
     */
    private function applyPreviousPeriodFilter($query, $period): void
    {
        switch ($period) {
            case 'month':
                $query->whereMonth('transaction_date', now()->subMonth()->month)
                    ->whereYear('transaction_date', now()->subMonth()->year);
                break;
            case 'quarter':
                $query->whereBetween('transaction_date', [
                    now()->subQuarter()->startOfQuarter(),
                    now()->subQuarter()->endOfQuarter()
                ]);
                break;
            case 'year':
                $query->whereYear('transaction_date', now()->subYear()->year);
                break;
        }
    }

    /**
     * Calculate percentage change
     */
    private function calculateChangePercentage($previous, $current): array
    {
        if ($previous == 0) {
            return [
                'percentage' => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'stable',
                'value' => $current - $previous
            ];
        }

        $percentage = round((($current - $previous) / $previous) * 100, 2);

        return [
            'percentage' => abs($percentage),
            'direction' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'stable'),
            'value' => $current - $previous
        ];
    }

    /**
     * Calculate consistency score for cash flow
     */
    private function calculateConsistencyScore($cashFlow): float
    {
        if ($cashFlow->count() < 2) return 0;

        $netValues = $cashFlow->pluck('net')->toArray();
        $mean = array_sum($netValues) / count($netValues);
        $variance = array_sum(array_map(function($x) use ($mean) {
                return pow($x - $mean, 2);
            }, $netValues)) / count($netValues);

        $standardDeviation = sqrt($variance);

        // Score de 0 à 100 (plus c'est régulier, plus le score est élevé)
        return max(0, min(100, 100 - ($standardDeviation / abs($mean)) * 100));
    }

    /**
     * Check if goal is on track
     */
    private function isGoalOnTrack($goal): bool
    {
        $totalDays = Carbon::parse($goal->created_at)->diffInDays(Carbon::parse($goal->target_date));
        $daysPassed = Carbon::parse($goal->created_at)->diffInDays(now());

        if ($totalDays <= 0) return true;

        $expectedProgress = ($daysPassed / $totalDays) * 100;
        $actualProgress = $goal->getProgressPercentage();

        return $actualProgress >= $expectedProgress * 0.9;
    }

    /**
     * Get XP earned for period
     */
    private function getXpForPeriod($user, $period): int
    {
        // Cette méthode nécessitera un système de tracking XP par date
        // Pour l'instant, retourner 0
        return 0;
    }

    /**
     * Get achievements unlocked for period
     */
    private function getAchievementsForPeriod($user, $period): int
    {
        $query = $user->achievements();

        switch ($period) {
            case 'month':
                $query->wherePivot('unlocked_at', '>=', now()->startOfMonth());
                break;
            case 'quarter':
                $query->wherePivot('unlocked_at', '>=', now()->startOfQuarter());
                break;
            case 'year':
                $query->wherePivot('unlocked_at', '>=', now()->startOfYear());
                break;
        }

        return $query->count();
    }

    // Méthodes supplémentaires pour les rapports détaillés
    private function getMonthlySummary($user, $date): array
    {
        // Implémentation des statistiques mensuelles détaillées
        return [
            'income' => 0,
            'expenses' => 0,
            'net' => 0,
            'transactions_count' => 0
        ];
    }

    private function getDailyBreakdown($user, $date): array
    {
        // Implémentation du détail jour par jour
        return [];
    }

    private function getMonthlyCategoryAnalysis($user, $date): array
    {
        // Implémentation de l'analyse des catégories du mois
        return [];
    }

    private function getMonthlyComparison($user, $date): array
    {
        // Comparaison avec les mois précédents
        return [];
    }

    private function getMonthlyProjections($user, $date): array
    {
        // Projections pour la fin du mois
        return [];
    }

    private function getMonthlyRecommendations($user, $date): array
    {
        // Recommandations basées sur les données du mois
        return [];
    }

    // Autres méthodes helper pour les rapports annuels et analyses détaillées...
}
