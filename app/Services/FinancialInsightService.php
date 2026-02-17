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
 * Analyse les données pour fournir des recommandations personnalisées
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
     * Maximum 5 insights les plus pertinents
     */
    public function generateInsights(): array
    {
        try {
            $insights = [];

            // Analyse des opportunités d'épargne
            $savingsInsight = $this->analyzeSavings();
            if ($savingsInsight) {
                $insights[] = $savingsInsight;
            }

            // Analyse des dépenses par catégorie
            $categoryInsight = $this->analyzeCategories();
            if ($categoryInsight) {
                $insights[] = $categoryInsight;
            }

            // Analyse des objectifs
            $goalsInsight = $this->analyzeGoals();
            if ($goalsInsight) {
                $insights[] = $goalsInsight;
            }

            // Analyse des tendances
            $trendInsight = $this->analyzeTrends();
            if ($trendInsight) {
                $insights[] = $trendInsight;
            }

            // Limite à 5 insights
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
                    'Votre taux d\'épargne est de %.1f%%. ' .
                    'Visez au moins 10%% pour sécuriser vos finances.',
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
                    'Bravo ! Vous épargnez %.1f%% de vos revenus. ' .
                    'Continuez comme ça !',
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
     */
    private function getSavingsRate(): float
    {
        $month = now()->month;

        // Revenus du mois
        $income = Transaction::whereHas('account', function($q) {
            $q->where('user_id', $this->user->id);
        })
            ->whereMonth('date', $month)
            ->where('type', 'income')
            ->sum('amount');

        if ($income == 0) {
            return 0;
        }

        // Contributions aux objectifs
        $saved = GoalContribution::whereHas('goal', function($q) {
            $q->where('user_id', $this->user->id);
        })
            ->whereMonth('created_at', $month)
            ->sum('amount');

        return ($saved / $income) * 100;
    }

    /**
     * Analyse les dépenses par catégorie
     */
    private function analyzeCategories(): ?array
    {
        $topCategory = Transaction::whereHas('account', function($q) {
            $q->where('user_id', $this->user->id);
        })
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->select('category_id', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->with('category')
            ->first();

        if (!$topCategory || !$topCategory->category) {
            return null;
        }

        $total = Transaction::whereHas('account', function($q) {
            $q->where('user_id', $this->user->id);
        })
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->sum(DB::raw('ABS(amount)'));

        $percentage = ($topCategory->total / $total) * 100;

        if ($percentage > 30) {
            return [
                'type' => 'warning',
                'category' => 'spending',
                'title' => 'Catégorie dominante',
                'message' => sprintf(
                    '%s représente %.0f%% de vos dépenses. ' .
                    'Identifiez des économies possibles.',
                    $topCategory->category->name,
                    $percentage
                ),
                'priority' => 'medium',
                'action' => 'Voir les détails',
                'action_url' => '/transactions?category=' .
                    $topCategory->category_id,
                'metadata' => [
                    'category' => $topCategory->category->name,
                    'percentage' => $percentage,
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
                'message' => 'Définissez un objectif pour ' .
                    'concrétiser vos projets financiers.',
                'priority' => 'high',
                'action' => 'Créer un objectif',
                'action_url' => '/goals/create'
            ];
        }

        // Objectif le plus proche
        $closestGoal = $goals->sortByDesc(function($goal) {
            return $goal->progress_percentage;
        })->first();

        if ($closestGoal->progress_percentage > 80) {
            return [
                'type' => 'success',
                'category' => 'goals',
                'title' => 'Objectif presque atteint !',
                'message' => sprintf(
                    '%s : %.0f%% complété. Plus que %.2f€ !',
                    $closestGoal->name,
                    $closestGoal->progress_percentage,
                    $closestGoal->target_amount -
                    $closestGoal->current_amount
                ),
                'priority' => 'medium',
                'action' => 'Voir l\'objectif',
                'action_url' => '/goals/' . $closestGoal->id,
                'metadata' => [
                    'goal_id' => $closestGoal->id,
                    'progress' => $closestGoal->progress_percentage,
                    'remaining' => $closestGoal->target_amount -
                        $closestGoal->current_amount
                ]
            ];
        }

        return null;
    }

    /**
     * Analyse les tendances de dépenses
     */
    private function analyzeTrends(): ?array
    {
        $currentMonth = Transaction::whereHas('account', function($q) {
            $q->where('user_id', $this->user->id);
        })
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->sum(DB::raw('ABS(amount)'));

        $lastMonth = Transaction::whereHas('account', function($q) {
            $q->where('user_id', $this->user->id);
        })
            ->where('type', 'expense')
            ->whereMonth('date', now()->subMonth()->month)
            ->sum(DB::raw('ABS(amount)'));

        if ($lastMonth == 0) {
            return null;
        }

        $variation = (($currentMonth - $lastMonth) / $lastMonth) * 100;

        if ($variation > 20) {
            return [
                'type' => 'warning',
                'category' => 'trends',
                'title' => 'Dépenses en hausse',
                'message' => sprintf(
                    'Vos dépenses ont augmenté de %.0f%% ' .
                    'par rapport au mois dernier (+%.2f€).',
                    $variation,
                    $currentMonth - $lastMonth
                ),
                'priority' => 'high',
                'action' => 'Analyser les dépenses',
                'action_url' => '/transactions',
                'metadata' => [
                    'variation' => $variation,
                    'current' => $currentMonth,
                    'previous' => $lastMonth
                ]
            ];
        }

        if ($variation < -20) {
            return [
                'type' => 'success',
                'category' => 'trends',
                'title' => 'Belles économies !',
                'message' => sprintf(
                    'Vous avez réduit vos dépenses de %.0f%% ' .
                    'ce mois-ci (%.2f€ économisés).',
                    abs($variation),
                    abs($currentMonth - $lastMonth)
                ),
                'priority' => 'low',
                'metadata' => [
                    'variation' => $variation,
                    'saved' => abs($currentMonth - $lastMonth)
                ]
            ];
        }

        return null;
    }
}
