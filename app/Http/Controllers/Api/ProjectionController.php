<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Projection Controller - Complété
 * École 42: Méthodes ajoutées pour API frontend
 */
class ProjectionController extends Controller
{
    // ==========================================
    // ✅ MÉTHODE EXISTANTE (conservée)
    // ==========================================

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $projections = $this->calculateProjections($user);

            return response()->json([
                'success' => true,
                'data' => $projections,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur projections');
        }
    }

    // ==========================================
    // ✅ NOUVEAU: INSIGHTS IA
    // ==========================================

    /**
     * Retourne les insights IA personnalisés
     * Route: GET /api/projections/insights
     */
    public function insights(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Cache de 30 minutes
            $insights = Cache::remember("insights_{$user->id}", 1800, function () use ($user) {
                return $this->generateInsights($user);
            });

            return response()->json([
                'success' => true,
                'data' => $insights,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur insights');
        }
    }

    // ==========================================
    // ✅ NOUVEAU: RAFRAÎCHIR PROJECTIONS
    // ==========================================

    /**
     * Force le recalcul des projections
     * Route: POST /api/projections/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Vider le cache
            Cache::forget("projections_{$user->id}");
            Cache::forget("insights_{$user->id}");

            $projections = $this->calculateProjections($user);

            return response()->json([
                'success' => true,
                'data' => $projections,
                'message' => 'Projections recalculées',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur refresh projections');
        }
    }

    // ==========================================
    // ✅ NOUVEAU: PROJECTION PAR PÉRIODE
    // ==========================================

    /**
     * Retourne une projection spécifique
     * Route: GET /api/projections/{period}
     */
    public function getByPeriod(Request $request, string $period): JsonResponse
    {
        if (!in_array($period, ['3months', '6months', '12months'])) {
            return response()->json([
                'success' => false,
                'message' => 'Période invalide',
            ], 400);
        }

        try {
            $user = $request->user();
            $projection = $this->calculateSingleProjection($user, $period);

            return response()->json([
                'success' => true,
                'data' => $projection,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur projection période');
        }
    }

    // ==========================================
    // MÉTHODES PRIVÉES - CALCULS
    // ==========================================

    /**
     * Calcule toutes les projections
     */
    private function calculateProjections($user): array
    {
        $monthlyIncome = $this->getMonthlyIncome($user);
        $monthlyExpenses = $this->getMonthlyExpenses($user);
        $monthlySavings = $monthlyIncome - $monthlyExpenses;

        return [
            $this->buildProjection('3months', $monthlyIncome, $monthlyExpenses, $monthlySavings, 3),
            $this->buildProjection('6months', $monthlyIncome, $monthlyExpenses, $monthlySavings, 6),
            $this->buildProjection('12months', $monthlyIncome, $monthlyExpenses, $monthlySavings, 12),
        ];
    }

    /**
     * Calcule une seule projection
     */
    private function calculateSingleProjection($user, string $period): array
    {
        $months = $this->getMonthsFromPeriod($period);

        $monthlyIncome = $this->getMonthlyIncome($user);
        $monthlyExpenses = $this->getMonthlyExpenses($user);
        $monthlySavings = $monthlyIncome - $monthlyExpenses;

        return $this->buildProjection($period, $monthlyIncome, $monthlyExpenses, $monthlySavings, $months);
    }

    /**
     * Construit une projection
     */
    private function buildProjection(
        string $period,
        float $monthlyIncome,
        float $monthlyExpenses,
        float $monthlySavings,
        int $months
    ): array {
        $confidence = $this->calculateConfidence($months);

        return [
            'period' => $period,
            'period_label' => $this->getPeriodLabel($period),
            'projected_savings' => round($monthlySavings * $months, 2),
            'projected_income' => round($monthlyIncome * $months, 2),
            'projected_expenses' => round($monthlyExpenses * $months, 2),
            'confidence' => $confidence,
            'variance_min' => round($monthlySavings * $months * 0.85, 2),
            'variance_max' => round($monthlySavings * $months * 1.15, 2),
            'assumptions' => $this->getAssumptions($period),
        ];
    }

    /**
     * Génère les insights IA
     */
    private function generateInsights($user): array
    {
        $insights = [];

        $monthlyIncome = $this->getMonthlyIncome($user);
        $monthlyExpenses = $this->getMonthlyExpenses($user);
        $savingsCapacity = $monthlyIncome - $monthlyExpenses;

        // Insight 1: Potentiel d'épargne
        if ($savingsCapacity > 0) {
            $insights[] = [
                'id' => 'savings-potential',
                'type' => 'achievement',
                'priority' => 'high',
                'title' => 'Excellent potentiel d\'épargne',
                'description' => sprintf(
                    'À ce rythme, vous économiserez %.0f€ cette année.',
                    $savingsCapacity * 12
                ),
                'impact' => sprintf('+%.0f€', $savingsCapacity * 12),
                'actionable' => true,
                'action_label' => 'Créer un objectif',
                'action_route' => '/app/goals',
                'icon' => '🎯',
                'color' => '#48bb78',
            ];
        }

        // Insight 2: Catégorie à optimiser
        $topCategory = $this->getTopExpenseCategory($user);
        if ($topCategory && $topCategory['amount'] > 500) {
            $insights[] = [
                'id' => 'optimize-category',
                'type' => 'opportunity',
                'priority' => 'medium',
                'title' => sprintf('Optimisez vos dépenses en %s', $topCategory['name']),
                'description' => sprintf(
                    'Réduire de 10%% vous ferait économiser %.0f€ par mois.',
                    $topCategory['amount'] * 0.1
                ),
                'impact' => sprintf('+%.0f€/an', $topCategory['amount'] * 0.1 * 12),
                'actionable' => true,
                'action_label' => 'Voir les détails',
                'action_route' => '/app/categories',
                'icon' => '💡',
                'color' => '#667eea',
            ];
        }

        // Insight 3: Fonds d'urgence
        $emergencyFundTarget = $monthlyExpenses * 3;
        $currentBalance = $this->getCurrentBalance($user);

        if ($currentBalance < $emergencyFundTarget) {
            $insights[] = [
                'id' => 'emergency-fund',
                'type' => 'suggestion',
                'priority' => 'medium',
                'title' => 'Constituez un fonds d\'urgence',
                'description' => sprintf(
                    'Visez 3 mois de dépenses (%.0f€) pour plus de sécurité.',
                    $emergencyFundTarget
                ),
                'impact' => sprintf('%.0f€ restants', $emergencyFundTarget - $currentBalance),
                'actionable' => true,
                'action_label' => 'Créer l\'objectif',
                'action_route' => '/app/goals',
                'icon' => '🛡️',
                'color' => '#4299e1',
            ];
        }

        return $insights;
    }

    // ==========================================
    // MÉTHODES UTILITAIRES
    // ==========================================

    private function getMonthlyIncome($user): float
    {
        return (float) Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->where('transaction_date', '>=', now()->subMonths(3))
            ->avg('amount') * 1 ?? 0;
    }

    private function getMonthlyExpenses($user): float
    {
        $total = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('transaction_date', '>=', now()->subMonths(3))
            ->sum('amount');

        return (float) $total / 3;
    }

    private function getCurrentBalance($user): float
    {
        try {
            return (float) DB::table('bank_accounts')
                ->join('bank_connections', 'bank_accounts.bank_connection_id', '=', 'bank_connections.id')
                ->where('bank_connections.user_id', $user->id)
                ->where('bank_connections.status', 'active')
                ->sum('bank_accounts.balance');
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getTopExpenseCategory($user): ?array
    {
        $result = DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.type', 'expense')
            ->where('transactions.transaction_date', '>=', now()->subMonth())
            ->select(
                'categories.name',
                DB::raw('SUM(transactions.amount) as amount')
            )
            ->groupBy('categories.name')
            ->orderBy('amount', 'desc')
            ->first();

        return $result ? [
            'name' => $result->name,
            'amount' => $result->amount,
        ] : null;
    }

    private function getMonthsFromPeriod(string $period): int
    {
        return match($period) {
            '3months' => 3,
            '6months' => 6,
            '12months' => 12,
            default => 3,
        };
    }

    private function getPeriodLabel(string $period): string
    {
        return match($period) {
            '3months' => '3 mois',
            '6months' => '6 mois',
            '12months' => '12 mois',
            default => '3 mois',
        };
    }

    private function calculateConfidence(int $months): int
    {
        return match(true) {
            $months <= 3 => 90,
            $months <= 6 => 75,
            default => 60,
        };
    }

    private function getAssumptions(string $period): array
    {
        $base = ['Revenus stables', 'Dépenses constantes'];

        if ($period === '12months') {
            $base[] = 'Hors événements exceptionnels';
        }

        return $base;
    }

    private function handleError(\Exception $e, string $message): JsonResponse
    {
        Log::error($message, [
            'error' => $e->getMessage(),
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}
