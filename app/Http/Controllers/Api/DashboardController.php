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
    // ✅ NOUVEAU: ENDPOINT PRINCIPAL COMPLET
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
    // ✅ NOUVEAU: MÉTRIQUES UNIQUEMENT
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
    // ✅ NOUVEAU: RAFRAÎCHIR MÉTRIQUES
    // ==========================================

    /**
     * Force le recalcul des métriques
     * Route: POST /api/dashboard/metrics/refresh
     */
    public function refreshMetrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Vider le cache des métriques
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
     * Calcule les métriques financières
     */
    private function getMetricsData($user): array
    {
        $monthDates = $this->getMonthDates(now());

        $monthlyIncome = $this->getMonthlyIncome($user, $monthDates);
        $monthlyExpenses = $this->getMonthlyExpenses($user, $monthDates);
        $totalBalance = $this->getTotalBankBalance($user);
        $savingsCapacity = $monthlyIncome - $monthlyExpenses;
        $savingsRate = $monthlyIncome > 0 ?
            ($savingsCapacity / $monthlyIncome) * 100 : 0;

        return [
            'monthly_income' => round($monthlyIncome, 2),
            'monthly_expenses' => round($monthlyExpenses, 2),
            'savings_capacity' => round($savingsCapacity, 2),
            'savings_rate' => round($savingsRate, 2),
            'total_balance' => round($totalBalance, 2),
            'active_goals_count' => $this->getActiveGoalsCount($user),
        ];
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
                    'progress_percentage' => $goal->target_amount > 0 ?
                        round(($goal->current_amount / $goal->target_amount) * 100, 1) : 0,
                    'estimated_completion_date' => $this->estimateCompletionDate($goal),
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
            ->map(function ($category) use ($totalExpenses) {
                $percentage = $totalExpenses > 0 ?
                    ($category->amount / $totalExpenses) * 100 : 0;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'amount' => round($category->amount, 2),
                    'percentage' => round($percentage, 2),
                    'color' => $category->color ?? '#667eea',
                    'icon' => $category->icon ?? '📊',
                    'trend' => 'stable',
                    'trend_percentage' => 0,
                    'transaction_count' => $category->transaction_count,
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
        // Calculer les projections simples
        $monthDates = $this->getMonthDates(now());
        $monthlyIncome = $this->getMonthlyIncome($user, $monthDates);
        $monthlyExpenses = $this->getMonthlyExpenses($user, $monthDates);
        $monthlySavings = $monthlyIncome - $monthlyExpenses;

        return [
            [
                'period' => '3months',
                'period_label' => '3 mois',
                'projected_savings' => round($monthlySavings * 3, 2),
                'projected_income' => round($monthlyIncome * 3, 2),
                'projected_expenses' => round($monthlyExpenses * 3, 2),
                'confidence' => 90,
                'variance_min' => round($monthlySavings * 3 * 0.85, 2),
                'variance_max' => round($monthlySavings * 3 * 1.15, 2),
                'assumptions' => ['Revenus stables', 'Dépenses constantes'],
            ],
            [
                'period' => '6months',
                'period_label' => '6 mois',
                'projected_savings' => round($monthlySavings * 6, 2),
                'projected_income' => round($monthlyIncome * 6, 2),
                'projected_expenses' => round($monthlyExpenses * 6, 2),
                'confidence' => 75,
                'variance_min' => round($monthlySavings * 6 * 0.85, 2),
                'variance_max' => round($monthlySavings * 6 * 1.15, 2),
                'assumptions' => ['Revenus stables', 'Dépenses constantes'],
            ],
            [
                'period' => '12months',
                'period_label' => '12 mois',
                'projected_savings' => round($monthlySavings * 12, 2),
                'projected_income' => round($monthlyIncome * 12, 2),
                'projected_expenses' => round($monthlyExpenses * 12, 2),
                'confidence' => 60,
                'variance_min' => round($monthlySavings * 12 * 0.85, 2),
                'variance_max' => round($monthlySavings * 12 * 1.15, 2),
                'assumptions' => [
                    'Revenus stables',
                    'Dépenses constantes',
                    'Hors événements exceptionnels'
                ],
            ],
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

    public function getSavingsCapacity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $monthDates = $this->getMonthDates(now());

            $totalBalance = $this->getTotalBankBalance($user);
            $monthlyExpenses = $this->getMonthlyExpenses($user, $monthDates);
            $capacity = $totalBalance - $monthlyExpenses;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_balance' => round($totalBalance, 2),
                    'monthly_expenses' => round($monthlyExpenses, 2),
                    'savings_capacity' => round($capacity, 2),
                    'is_positive' => $capacity > 0,
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

    private function getTotalBankBalance($user): float
    {
        try {
            $balance = DB::table('bank_accounts')
                ->join('bank_connections', 'bank_accounts.bank_connection_id', '=', 'bank_connections.id')
                ->where('bank_connections.user_id', $user->id)
                ->where('bank_connections.status', 'active')
                ->where('bank_accounts.is_active', true)
                ->whereIn('bank_accounts.account_type', ['checking', 'savings', 'investment'])
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

    private function estimateCompletionDate($goal): ?string
    {
        if (!$goal->target_date) {
            return null;
        }
        return $goal->target_date;
    }

    private function buildDashboardStats($user): array
    {
        // Méthode conservée pour compatibilité
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
