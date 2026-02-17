<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\FinancialGoal;
use App\Models\GoalContribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de génération d'insights financiers intelligents
 *
 * ✅ CORRECTIONS APPLIQUÉES :
 * - whereHas('bankAccount') → where('user_id') (pas de relation bankAccount sur Transaction)
 * - whereMonth('date') → whereMonth('transaction_date') (bon nom de colonne)
 * - Utilisation des scopes existants du modèle Transaction
 */
class FinancialInsightService
{
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Génère tous les insights pour l'utilisateur
     */
    public function generateInsights(): array
    {
        try {
            $insights = [];

            $savingsInsight = $this->analyzeSavings();
            if ($savingsInsight) {
                $insights[] = $savingsInsight;
            }

            $categoryInsight = $this->analyzeCategories();
            if ($categoryInsight) {
                $insights[] = $categoryInsight;
            }

            $goalsInsight = $this->analyzeGoals();
            if ($goalsInsight) {
                $insights[] = $goalsInsight;
            }

            $trendInsight = $this->analyzeTrends();
            if ($trendInsight) {
                $insights[] = $trendInsight;
            }

            return array_slice($insights, 0, 5);

        } catch (\Exception $e) {
            Log::error('Erreur génération insights', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Analyse les opportunités d'épargne
     */
    private function analyzeSavings(): ?array
    {
        $rate = $this->getSavingsRate();

        if ($rate < 10) {
            return [
                'type' => 'warning',
                'category' => 'savings',
                'title' => 'Taux d\'épargne faible',
                'message' => sprintf(
                    'Votre taux d\'épargne est de %.1f%%. '
                    . 'Visez au moins 10%% pour sécuriser vos finances.',
                    $rate
                ),
                'priority' => 'high',
                'action' => 'Créez un objectif d\'épargne',
                'action_url' => '/goals/create',
                'metadata' => ['current_rate' => $rate]
            ];
        }

        if ($rate > 20) {
            return [
                'type' => 'success',
                'category' => 'savings',
                'title' => 'Excellent taux d\'épargne !',
                'message' => sprintf(
                    'Bravo ! Vous épargnez %.1f%% de vos revenus.',
                    $rate
                ),
                'priority' => 'low',
                'metadata' => ['current_rate' => $rate]
            ];
        }

        return null;
    }

    /**
     * Calcule le taux d'épargne mensuel
     *
     * ✅ CORRIGÉ : utilise user_id + transaction_date
     */
    private function getSavingsRate(): float
    {
        $now = now();

        // ✅ Revenus du mois via user_id + transaction_date
        $income = Transaction::where('user_id', $this->user->id)
            ->where('type', 'income')
            ->where('status', 'completed')
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->sum('amount');

        if ($income == 0) {
            return 0;
        }

        // ✅ Épargne via les contributions aux objectifs
        $saved = GoalContribution::whereHas('goal', function ($q) {
            $q->where('user_id', $this->user->id);
        })
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('amount');

        return ($saved / $income) * 100;
    }

    /**
     * Analyse les dépenses par catégorie
     *
     * ✅ CORRIGÉ : utilise user_id + transaction_date
     */
    private function analyzeCategories(): ?array
    {
        $now = now();

        $topCategory = Transaction::where('user_id', $this->user->id)
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->select(
                'category_id',
                DB::raw('SUM(ABS(amount)) as total')
            )
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->with('category')
            ->first();

        if (!$topCategory || !$topCategory->category) {
            return null;
        }

        $total = Transaction::where('user_id', $this->user->id)
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->sum(DB::raw('ABS(amount)'));

        if ($total == 0) {
            return null;
        }

        $pct = ($topCategory->total / $total) * 100;

        if ($pct > 30) {
            return [
                'type' => 'warning',
                'category' => 'spending',
                'title' => 'Catégorie dominante',
                'message' => sprintf(
                    '%s représente %.0f%% de vos dépenses.',
                    $topCategory->category->name,
                    $pct
                ),
                'priority' => 'medium',
                'action' => 'Voir les détails',
                'action_url' => '/transactions?category='
                    . $topCategory->category_id,
                'metadata' => [
                    'category' => $topCategory->category->name,
                    'percentage' => $pct,
                    'amount' => $topCategory->total
                ]
            ];
        }

        return null;
    }

    /**
     * Analyse les objectifs financiers
     */
    private function analyzeGoals(): ?array
    {
        $goals = FinancialGoal::where('user_id', $this->user->id)
            ->where('status', 'active')
            ->get();

        if ($goals->isEmpty()) {
            return [
                'type' => 'info',
                'category' => 'goals',
                'title' => 'Créez votre premier objectif',
                'message' => 'Définissez un objectif financier.',
                'priority' => 'high',
                'action' => 'Créer un objectif',
                'action_url' => '/goals/create'
            ];
        }

        $closestGoal = $goals->sortByDesc(function ($goal) {
            return $goal->progress_percentage;
        })->first();

        if ($closestGoal->progress_percentage > 80) {
            $remaining = $closestGoal->target_amount
                - $closestGoal->current_amount;

            return [
                'type' => 'success',
                'category' => 'goals',
                'title' => 'Objectif presque atteint !',
                'message' => sprintf(
                    '%s : %.0f%% complété. Plus que %.2f€ !',
                    $closestGoal->name,
                    $closestGoal->progress_percentage,
                    $remaining
                ),
                'priority' => 'medium',
                'action' => 'Voir l\'objectif',
                'action_url' => '/goals/' . $closestGoal->id,
                'metadata' => [
                    'goal_id' => $closestGoal->id,
                    'progress' => $closestGoal->progress_percentage
                ]
            ];
        }

        return null;
    }

    /**
     * Analyse les tendances de dépenses
     *
     * ✅ CORRIGÉ : utilise user_id + transaction_date
     */
    private function analyzeTrends(): ?array
    {
        $now = now();
        $lastMonth = now()->subMonth();

        $currentMonth = Transaction::where('user_id', $this->user->id)
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->sum(DB::raw('ABS(amount)'));

        $previousMonth = Transaction::where('user_id', $this->user->id)
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereYear('transaction_date', $lastMonth->year)
            ->whereMonth('transaction_date', $lastMonth->month)
            ->sum(DB::raw('ABS(amount)'));

        if ($previousMonth == 0) {
            return null;
        }

        $variation = (($currentMonth - $previousMonth) / $previousMonth) * 100;

        if ($variation > 20) {
            return [
                'type' => 'warning',
                'category' => 'trends',
                'title' => 'Dépenses en hausse',
                'message' => sprintf(
                    'Vos dépenses ont augmenté de %.0f%%.',
                    $variation
                ),
                'priority' => 'high',
                'action' => 'Analyser les dépenses',
                'action_url' => '/transactions',
                'metadata' => [
                    'variation' => $variation,
                    'current' => $currentMonth,
                    'previous' => $previousMonth
                ]
            ];
        }

        if ($variation < -20) {
            return [
                'type' => 'success',
                'category' => 'trends',
                'title' => 'Belles économies !',
                'message' => sprintf(
                    'Vous avez réduit vos dépenses de %.0f%%.',
                    abs($variation)
                ),
                'priority' => 'low',
                'metadata' => [
                    'variation' => $variation,
                    'saved' => abs($currentMonth - $previousMonth)
                ]
            ];
        }

        return null;
    }
}
