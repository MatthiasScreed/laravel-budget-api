<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankConnection;
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
 *
 * ✅ VERSION CORRIGÉE
 * - Solde total = Somme des balances des comptes bancaires
 * - Capacité d'épargne = Solde total - Dépenses du mois
 */
class DashboardController extends Controller
{
    // ==========================================
    // ENDPOINT PRINCIPAL
    // ==========================================

    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            Log::info('📊 Dashboard stats requested', ['user_id' => $user->id]);

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

    private function buildDashboardStats($user): array
    {
        $now = now();
        $monthDates = $this->getMonthDates($now);

        // ✅ CORRECTION: Solde total = Somme des comptes bancaires
        $totalBalance = $this->getTotalBankBalance($user);

        // Revenus et dépenses du mois
        $monthlyIncome = $this->getMonthlyIncome($user, $monthDates);
        $monthlyExpenses = $this->getMonthlyExpenses($user, $monthDates);

        // ✅ CORRECTION: Capacité d'épargne = Solde total - Dépenses du mois
        // C'est l'argent disponible MAINTENANT pour investir
        $savingsCapacity = $totalBalance - $monthlyExpenses;

        return [
            'total_balance' => round($totalBalance, 2),
            'current_month' => $this->getCurrentMonthStats($user, $monthDates),
            'savings_capacity' => [
                'amount' => round($savingsCapacity, 2),
                'is_positive' => $savingsCapacity > 0,
                'calculation' => [
                    'total_balance' => round($totalBalance, 2),
                    'monthly_expenses' => round($monthlyExpenses, 2),
                    'formula' => 'total_balance - monthly_expenses',
                ],
            ],
            'comparison' => $this->getMonthComparison($user, $now),
            'goals' => $this->getGoalsStats($user, $savingsCapacity),
            'streak' => $this->getActiveStreak($user),
            'period' => $this->getPeriodInfo($monthDates),
            'user' => $this->getUserInfo($user),
            // ✅ AJOUT: Détail des comptes bancaires
            'bank_accounts' => $this->getBankAccountsSummary($user),
        ];
    }

    // ==========================================
    // ✅ NOUVEAU: CALCUL DU SOLDE BANCAIRE RÉEL
    // ==========================================

    /**
     * Récupérer le solde total de tous les comptes bancaires
     * ✅ CORRIGÉ - Seuls les AVOIRS (checking, savings, investment)
     * Les crédits et prêts sont des DETTES, pas des avoirs
     */
    private function getTotalBankBalance($user): float
    {
        // Option 1: Depuis la table bank_accounts
        try {
            // ✅ Ne compter que les comptes "avoirs" (pas les crédits/prêts)
            $bankBalance = DB::table('bank_accounts')
                ->join('bank_connections', 'bank_accounts.bank_connection_id', '=', 'bank_connections.id')
                ->where('bank_connections.user_id', $user->id)
                ->where('bank_connections.status', 'active')
                ->where('bank_accounts.is_active', true)
                ->whereIn('bank_accounts.account_type', ['checking', 'savings', 'investment'])
                ->sum('bank_accounts.balance');

            if ($bankBalance != 0) {
                Log::info('💰 Solde bancaire (avoirs uniquement)', [
                    'user_id' => $user->id,
                    'balance' => $bankBalance
                ]);
                return (float) $bankBalance;
            }

            // Vérifier s'il y a des comptes mais tous à 0
            $hasAccounts = DB::table('bank_accounts')
                ->join('bank_connections', 'bank_accounts.bank_connection_id', '=', 'bank_connections.id')
                ->where('bank_connections.user_id', $user->id)
                ->where('bank_connections.status', 'active')
                ->exists();

            if ($hasAccounts) {
                // Il y a des comptes mais le solde est 0
                return 0.0;
            }

        } catch (\Exception $e) {
            Log::warning('⚠️ Erreur lecture bank_accounts', [
                'error' => $e->getMessage()
            ]);
        }

        // Option 2: Fallback - Solde initial + transactions
        try {
            $initialBalance = $user->initial_balance ?? 0;

            $transactionBalance = Transaction::where('user_id', $user->id)
                ->sum(DB::raw("CASE
                    WHEN type = 'income' THEN amount
                    WHEN type = 'expense' THEN -amount
                    ELSE 0
                END"));

            $calculatedBalance = $initialBalance + $transactionBalance;

            Log::info('💰 Solde calculé (fallback)', [
                'user_id' => $user->id,
                'initial' => $initialBalance,
                'transactions' => $transactionBalance,
                'total' => $calculatedBalance
            ]);

            return (float) $calculatedBalance;
        } catch (\Exception $e) {
            Log::error('❌ Erreur calcul solde', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Résumé des comptes bancaires pour l'affichage
     * ✅ CORRIGÉ avec les bons noms de colonnes
     */
    private function getBankAccountsSummary($user): array
    {
        try {
            $accounts = DB::table('bank_accounts')
                ->join('bank_connections', 'bank_accounts.bank_connection_id', '=', 'bank_connections.id')
                ->where('bank_connections.user_id', $user->id)
                ->where('bank_connections.status', 'active')
                ->where('bank_accounts.is_active', true)
                ->select([
                    'bank_accounts.id',
                    'bank_accounts.account_name',    // ✅ Corrigé
                    'bank_accounts.account_type',    // ✅ Corrigé
                    'bank_accounts.balance',
                    'bank_accounts.currency',
                    'bank_connections.bank_name'
                ])
                ->get();

            if ($accounts->isEmpty()) {
                return [
                    'count' => 0,
                    'accounts' => [],
                    'total_assets' => 0,
                    'total_liabilities' => 0,
                    'net_balance' => 0,
                    'has_bank_connection' => false,
                ];
            }

            // ✅ Séparer avoirs et dettes
            $assets = $accounts->whereIn('account_type', ['checking', 'savings', 'investment']);
            $liabilities = $accounts->whereIn('account_type', ['credit', 'loan']);

            $totalAssets = $assets->sum('balance');
            $totalLiabilities = abs($liabilities->sum('balance')); // Les dettes sont souvent négatives
            $netBalance = $totalAssets - $totalLiabilities;

            return [
                'count' => $accounts->count(),
                'accounts' => $accounts->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'name' => $account->account_name,
                        'type' => $account->account_type,
                        'balance' => round((float) $account->balance, 2),
                        'currency' => $account->currency,
                        'bank_name' => $account->bank_name,
                        'is_liability' => in_array($account->account_type, ['credit', 'loan']),
                    ];
                })->toArray(),
                'total_assets' => round($totalAssets, 2),
                'total_liabilities' => round($totalLiabilities, 2),
                'net_balance' => round($netBalance, 2),
                'has_bank_connection' => true,
            ];

        } catch (\Exception $e) {
            Log::warning('⚠️ Impossible de récupérer les comptes bancaires', [
                'error' => $e->getMessage()
            ]);

            return [
                'count' => 0,
                'accounts' => [],
                'total_assets' => 0,
                'total_liabilities' => 0,
                'net_balance' => 0,
                'has_bank_connection' => false,
                'error' => 'Connectez vos comptes bancaires pour voir le solde réel',
            ];
        }
    }

    // ==========================================
    // CALCULS DU MOIS EN COURS
    // ==========================================

    private function getMonthDates($date): array
    {
        return [
            'start' => $date->copy()->startOfMonth(),
            'end' => $date->copy()->endOfMonth(),
        ];
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

    private function getTransactionCount($user, array $dates): int
    {
        return Transaction::where('user_id', $user->id)
            ->whereBetween('transaction_date', [$dates['start'], $dates['end']])
            ->count();
    }

    // ==========================================
    // COMPARAISON MENSUELLE
    // ==========================================

    private function getMonthComparison($user, $now): array
    {
        $lastMonth = $now->copy()->subMonth();
        $lastMonthDates = $this->getMonthDates($lastMonth);
        $currentMonthDates = $this->getMonthDates($now);

        // ✅ Pour la comparaison, on utilise Revenus - Dépenses
        // (car le solde bancaire du mois dernier n'est plus accessible)
        $lastMonthIncome = $this->getMonthlyIncome($user, $lastMonthDates);
        $lastMonthExpenses = $this->getMonthlyExpenses($user, $lastMonthDates);
        $lastMonthNet = $lastMonthIncome - $lastMonthExpenses;

        $currentIncome = $this->getMonthlyIncome($user, $currentMonthDates);
        $currentExpenses = $this->getMonthlyExpenses($user, $currentMonthDates);
        $currentNet = $currentIncome - $currentExpenses;

        $changePercent = $this->calculateChange($lastMonthNet, $currentNet);

        return [
            'last_month_capacity' => round($lastMonthNet, 2),
            'current_month_capacity' => round($currentNet, 2),
            'change_percent' => $changePercent,
            'trend' => $this->getTrend($changePercent),
        ];
    }

    private function calculateChange(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : ($current < 0 ? -100 : 0);
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
    // GAMING & USER INFO
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

    private function getUserInfo($user): array
    {
        $user->loadMissing('level');
        $userLevel = $user->level;

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
            'level' => $userLevel?->level ?? 1,
            'xp' => $userLevel?->total_xp ?? 0,
            'achievements' => $achievementsCount,
        ];
    }

    // ==========================================
    // AUTRES ENDPOINTS
    // ==========================================

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
            return response()->json([
                'success' => false,
                'message' => 'Erreur calcul capacité',
            ], 500);
        }
    }

    public function refreshAll(): JsonResponse
    {
        try {
            $user = Auth::user();
            $stats = $this->buildDashboardStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Dashboard rafraîchi avec succès',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rafraîchissement',
            ], 500);
        }
    }
}
