<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\FinancialGoal;
use App\Models\Streak;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Controller - CoinQuest
 * École 42: Fonctions max 25 lignes, commentées
 */
class DashboardController extends Controller
{
    // ==========================================
    // ✅ ENDPOINT PRINCIPAL COMPLET
    // ==========================================

    /**
     * Retourne toutes les données du dashboard
     * Route: GET /api/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            Log::info('📊 Dashboard index requested', ['user_id' => $user->id]);

            $data = [
                'metrics' => $this->getMetricsData($user),
                'goals' => $this->getActiveGoalsData($user),
                'categories' => $this->getCategoriesBreakdown($user),
                'recent_transactions' => $this->getRecentTransactionsData($user),
                'projections' => $this->getProjectionsData($user),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur chargement dashboard');
        }
    }

    // ==========================================
    // ✅ MÉTRIQUES UNIQUEMENT
    // ==========================================

    /**
     * Retourne uniquement les métriques financières
     * Route: GET /api/dashboard/metrics
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $metrics = $this->getMetricsData($user);

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur métriques');
        }
    }

    // ==========================================
    // ✅ RAFRAÎCHIR MÉTRIQUES
    // ==========================================

    /**
     * Force le recalcul des métriques
     * Route: POST /api/dashboard/metrics/refresh
     */
    public function refreshMetrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            \Cache::forget("dashboard_metrics_{$user->id}");

            $metrics = $this->getMetricsData($user);

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'message' => 'Métriques rafraîchies',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur refresh métriques');
        }
    }

    // ==========================================
    // MÉTHODES PRIVÉES - CALCULS
    // ==========================================

    /**
     * ✅ CORRIGÉ: Calcule les métriques financières
     * savings_capacity = solde réel du compte (ce qui reste)
     */
    private function getMetricsData($user): array
    {
        $monthDates = $this->getMonthDates(now());

        $monthlyIncome = $this->getMonthlyIncome($user, $monthDates);
        $monthlyExpenses = $this->getMonthlyExpenses($user, $monthDates);

        // ✅ CORRIGÉ: Capacité d'épargne = SOLDE RÉEL DU COMPTE
        $savingsCapacity = $this->getSavingsCapacityAmount($user);

        // Variation du mois (pour info)
        $monthlyVariation = $monthlyIncome - $monthlyExpenses;

        // Taux d'épargne basé sur les revenus du mois
        $savingsRate = $monthlyIncome > 0
            ? ($monthlyVariation / $monthlyIncome) * 100
            : 0;

        return [
            'monthly_income' => round($monthlyIncome, 2),
            'monthly_expenses' => round($monthlyExpenses, 2),
            // ✅ CORRIGÉ: C'est le SOLDE DU COMPTE
            'savings_capacity' => round($savingsCapacity, 2),
            'savings_rate' => round($savingsRate, 1),
            'total_balance' => round($savingsCapacity, 2),
            'monthly_variation' => round($monthlyVariation, 2),
            'active_goals_count' => $this->getActiveGoalsCount($user),
        ];
    }

    /**
     * ✅ NOUVEAU: Calcule la capacité d'épargne (solde réel)
     * Priorité: Comptes bancaires Bridge > Solde calculé
     */
    private function getSavingsCapacityAmount($user): float
    {
        // 1. Essayer les comptes bancaires synchronisés (Bridge)
        $bankBalance = $this->getTotalBankBalance($user);

        if ($bankBalance > 0) {
            return $bankBalance;
        }

        // 2. Fallback: Solde calculé depuis transactions
        return $this->getCalculatedBalance($user);
    }

    /**
     * ✅ NOUVEAU: Solde calculé depuis l'historique des transactions
     */
    private function getCalculatedBalance($user): float
    {
        $income = Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->sum('amount');

        $expenses = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->sum('amount');

        return (float) ($income - $expenses);
    }

    /**
     * Récupère les objectifs actifs formatés
     */
    private function getActiveGoalsData($user): array
    {
        return FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->get()
            ->map(function ($goal) {
                $progress = $goal->target_amount > 0
                    ? ($goal->current_amount / $goal->target_amount) * 100
                    : 0;

                return [
                    'id' => $goal->id,
                    'name' => $goal->name,
                    'target_amount' => $goal->target_amount,
                    'current_amount' => $goal->current_amount,
                    'deadline' => $goal->target_date,
                    'priority' => $goal->priority,
                    'status' => $goal->status,
                    'category' => $goal->type,
                    'icon' => $goal->icon ?? '🎯',
                    'progress_percentage' => round($progress, 1),
                    'estimated_completion_date' => $goal->target_date,
                ];
            })
            ->toArray();
    }

    /**
     * Répartition des dépenses par catégorie
     */
    private function getCategoriesBreakdown($user): array
    {
        $monthDates = $this->getMonthDates(now());
        $totalExpenses = $this->getMonthlyExpenses($user, $monthDates);

        return DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.transaction_date', [
                $monthDates['start'],
                $monthDates['end']
            ])
            ->select(
                'categories.id',
                'categories.name',
                'categories.color',
                'categories.icon',
                DB::raw('SUM(transactions.amount) as amount'),
                DB::raw('COUNT(transactions.id) as transaction_count')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.icon')
            ->get()
            ->map(function ($cat) use ($totalExpenses) {
                $pct = $totalExpenses > 0 ? ($cat->amount / $totalExpenses) * 100 : 0;

                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'amount' => round($cat->amount, 2),
                    'percentage' => round($pct, 2),
                    'color' => $cat->color ?? '#667eea',
                    'icon' => $cat->icon ?? '📊',
                    'trend' => 'stable',
                    'trend_percentage' => 0,
                    'transaction_count' => $cat->transaction_count,
                ];
            })
            ->toArray();
    }

    /**
     * Transactions récentes pour le dashboard
     */
    private function getRecentTransactionsData($user, int $limit = 10): array
    {
        return Transaction::where('user_id', $user->id)
            ->with('category')
            ->orderBy('transaction_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'description' => $tx->description,
                    'amount' => $tx->amount,
                    'category' => $tx->category ? [
                        'id' => $tx->category->id,
                        'name' => $tx->category->name,
                        'icon' => $tx->category->icon ?? '💰',
                        'color' => $tx->category->color ?? '#667eea',
                    ] : null,
                    'date' => $tx->transaction_date,
                    'type' => $tx->type,
                    'category_icon' => $tx->category->icon ?? '💰',
                    'is_recurring' => $tx->is_recurring,
                    'created_at' => $tx->created_at,
                    'updated_at' => $tx->updated_at,
                ];
            })
            ->toArray();
    }

    /**
     * Données de projections pour le dashboard
     */
    private function getProjectionsData($user): array
    {
        $monthDates = $this->getMonthDates(now());
        $monthlyIncome = $this->getMonthlyIncome($user, $monthDates);
        $monthlyExpenses = $this->getMonthlyExpenses($user, $monthDates);
        $monthlySavings = $monthlyIncome - $monthlyExpenses;

        return [
            $this->buildProjection(
                '3months', '3 mois', 3, 90,
                $monthlyIncome, $monthlyExpenses, $monthlySavings
            ),
            $this->buildProjection(
                '6months', '6 mois', 6, 75,
                $monthlyIncome, $monthlyExpenses, $monthlySavings
            ),
            $this->buildProjection(
                '12months', '12 mois', 12, 60,
                $monthlyIncome, $monthlyExpenses, $monthlySavings
            ),
        ];
    }

    /**
     * Construit une projection complète
     */
    private function buildProjection(
        string $period,
        string $label,
        int $months,
        int $confidence,
        float $income,
        float $expenses,
        float $savings
    ): array {
        $projectedSavings = $savings * $months;
        $projectedIncome = $income * $months;
        $projectedExpenses = $expenses * $months;

        return [
            'period' => $period,
            'period_label' => $label,
            'projected_savings' => round($projectedSavings, 2),
            'projected_income' => round($projectedIncome, 2),
            'projected_expenses' => round($projectedExpenses, 2),
            'confidence' => $confidence,
            'variance_min' => round($projectedSavings * 0.85, 2),
            'variance_max' => round($projectedSavings * 1.15, 2),
            'assumptions' => ['Revenus stables', 'Dépenses constantes'],
        ];
    }

    // ==========================================
    // ENDPOINTS EXISTANTS (CONSERVÉS)
    // ==========================================

    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $stats = $this->buildDashboardStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur stats dashboard');
        }
    }

    /**
     * ✅ CORRIGÉ: Endpoint capacité d'épargne
     */
    public function getSavingsCapacity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $monthDates = $this->getMonthDates(now());

            // ✅ CORRIGÉ: Solde réel du compte
            $savingsCapacity = $this->getSavingsCapacityAmount($user);
            $monthlyIncome = $this->getMonthlyIncome($user, $monthDates);
            $monthlyExpenses = $this->getMonthlyExpenses($user, $monthDates);

            return response()->json([
                'success' => true,
                'data' => [
                    // ✅ Capacité = solde du compte
                    'savings_capacity' => round($savingsCapacity, 2),
                    'is_positive' => $savingsCapacity >= 0,
                    'monthly_income' => round($monthlyIncome, 2),
                    'monthly_expenses' => round($monthlyExpenses, 2),
                    'monthly_variation' => round($monthlyIncome - $monthlyExpenses, 2),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur calcul capacité');
        }
    }

    public function refreshAll(): JsonResponse
    {
        try {
            $user = Auth::user();
            \Cache::forget("dashboard_metrics_{$user->id}");
            $stats = $this->buildDashboardStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Dashboard rafraîchi avec succès',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur rafraîchissement');
        }
    }

    // ==========================================
    // MÉTHODES UTILITAIRES
    // ==========================================

    private function getMonthDates($date): array
    {
        return [
            'start' => $date->copy()->startOfMonth(),
            'end' => $date->copy()->endOfMonth(),
        ];
    }

    private function getMonthlyIncome($user, array $dates): float
    {
        return (float) Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$dates['start'], $dates['end']])
            ->sum('amount');
    }

    private function getMonthlyExpenses($user, array $dates): float
    {
        return (float) Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$dates['start'], $dates['end']])
            ->sum('amount');
    }

    /**
     * Solde des comptes bancaires synchronisés (Bridge)
     */
    private function getTotalBankBalance($user): float
    {
        try {
            $balance = DB::table('bank_accounts')
                ->join('bank_connections', 'bank_accounts.bank_connection_id', '=', 'bank_connections.id')
                ->where('bank_connections.user_id', $user->id)
                ->where('bank_connections.status', 'active')
                ->where('bank_accounts.is_active', true)
                ->sum('bank_accounts.balance');

            return (float) $balance;
        } catch (\Exception $e) {
            Log::warning('Erreur lecture bank_accounts', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    private function getActiveGoalsCount($user): int
    {
        return FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();
    }

    private function buildDashboardStats($user): array
    {
        return [
            'metrics' => $this->getMetricsData($user),
            'goals' => $this->getActiveGoalsData($user),
            'categories' => $this->getCategoriesBreakdown($user),
        ];
    }

    private function handleError(\Exception $e, string $message): JsonResponse
    {
        Log::error($message, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}
