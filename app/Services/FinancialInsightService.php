<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\FinancialInsight;
use App\Models\FinancialGoal;
use App\Models\GoalContribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de génération d'insights financiers intelligents
 *
 * ✅ CORRECTIONS :
 * - Persiste les insights dans la table financial_insights
 * - Utilise where('user_id') au lieu de whereHas('bankAccount')
 * - Utilise transaction_date au lieu de date
 * - Mappe les champs vers le schéma FinancialInsight
 */
class FinancialInsightService
{
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Génère et persiste les insights
     */
    public function generateInsights(): array
    {
        try {
            $rawInsights = [];

            $savings = $this->analyzeSavings();
            if ($savings) {
                $rawInsights[] = $savings;
            }

            $categories = $this->analyzeCategories();
            if ($categories) {
                $rawInsights[] = $categories;
            }

            $goals = $this->analyzeGoals();
            if ($goals) {
                $rawInsights[] = $goals;
            }

            $trends = $this->analyzeTrends();
            if ($trends) {
                $rawInsights[] = $trends;
            }

            // ✅ Persister en BDD et retourner les modèles
            $saved = $this->persistInsights(
                array_slice($rawInsights, 0, 5)
            );

            return $saved;

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
     * ✅ Persiste les insights bruts en BDD
     *
     * Évite les doublons en vérifiant le type+catégorie
     * du même jour pour cet utilisateur
     */
    private function persistInsights(array $rawInsights): array
    {
        $saved = [];

        foreach ($rawInsights as $raw) {
            $insight = $this->saveInsight($raw);
            if ($insight) {
                $saved[] = $insight;
            }
        }

        return $saved;
    }

    /**
     * ✅ Sauvegarde un insight individuel
     */
    private function saveInsight(array $raw): ?FinancialInsight
    {
        $category = $raw['category'] ?? '';
        $mappedType = $this->mapType($category);

        // Éviter les doublons du même jour
        $existing = FinancialInsight::where('user_id', $this->user->id)
            ->where('type', $mappedType)
            ->whereDate('created_at', today())
            ->where('title', $raw['title'] ?? '')
            ->first();

        if ($existing) {
            return $existing;
        }

        return FinancialInsight::create([
            'user_id' => $this->user->id,
            'type' => $mappedType,
            'priority' => $this->mapPriority($raw['priority'] ?? 'medium'),
            'title' => $raw['title'] ?? 'Insight',
            'description' => $raw['message'] ?? '',
            'icon' => $this->mapIcon($category),
            'action_label' => $raw['action'] ?? null,
            'action_data' => isset($raw['action_url'])
                ? ['url' => $raw['action_url']]
                : null,
            'potential_saving' => $raw['metadata']['saved'] ?? null,
            'metadata' => $raw['metadata'] ?? null,
            'is_read' => false,
            'is_dismissed' => false,
        ]);
    }

    /**
     * ✅ Mappe la priorité texte vers un entier
     */
    private function mapPriority(string $priority): int
    {
        return match ($priority) {
            'high' => 1,
            'medium' => 2,
            'low' => 3,
            default => 2,
        };
    }

    /**
     * ✅ Mappe la catégorie vers un type ENUM valide
     *
     * ENUM autorisés : cost_reduction, savings_opportunity,
     * behavioral_pattern, goal_acceleration,
     * budget_alert, unusual_spending
     */
    private function mapType(string $category): string
    {
        return match ($category) {
            'savings' => 'savings_opportunity',
            'spending' => 'cost_reduction',
            'goals' => 'goal_acceleration',
            'trends' => 'behavioral_pattern',
            default => 'budget_alert',
        };
    }

    /**
     * ✅ Mappe la catégorie vers une icône emoji
     */
    private function mapIcon(string $category): string
    {
        return match ($category) {
            'savings' => '💰',
            'spending' => '💳',
            'goals' => '🎯',
            'trends' => '📊',
            default => '💡',
        };
    }

    // ==========================================
    // ANALYSE : Épargne
    // ==========================================

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
                    . 'Visez au moins 10%% pour sécuriser '
                    . 'vos finances.',
                    $rate
                ),
                'priority' => 'high',
                'action' => 'Créer un objectif d\'épargne',
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
     */
    private function getSavingsRate(): float
    {
        $now = now();

        $income = Transaction::where('user_id', $this->user->id)
            ->where('type', 'income')
            ->where('status', 'completed')
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->sum('amount');

        if ($income == 0) {
            return 0;
        }

        $saved = GoalContribution::whereHas('goal', function ($q) {
            $q->where('user_id', $this->user->id);
        })
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('amount');

        return ($saved / $income) * 100;
    }

    // ==========================================
    // ANALYSE : Catégories
    // ==========================================

    /**
     * Analyse les dépenses par catégorie
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

        $total = $this->getMonthlyExpenseTotal($now);

        if ($total == 0) {
            return null;
        }

        $pct = ($topCategory->total / $total) * 100;

        if ($pct <= 30) {
            return null;
        }

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

    // ==========================================
    // ANALYSE : Objectifs
    // ==========================================

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
                'message' => 'Définissez un objectif financier '
                    . 'pour suivre votre progression.',
                'priority' => 'high',
                'action' => 'Créer un objectif',
                'action_url' => '/goals/create',
                'metadata' => []
            ];
        }

        $closest = $goals->sortByDesc(function ($g) {
            return $g->progress_percentage;
        })->first();

        if ($closest->progress_percentage <= 80) {
            return null;
        }

        $remaining = $closest->target_amount
            - $closest->current_amount;

        return [
            'type' => 'success',
            'category' => 'goals',
            'title' => 'Objectif presque atteint !',
            'message' => sprintf(
                '%s : %.0f%% complété. Plus que %.2f€ !',
                $closest->name,
                $closest->progress_percentage,
                $remaining
            ),
            'priority' => 'medium',
            'action' => 'Voir l\'objectif',
            'action_url' => '/goals/' . $closest->id,
            'metadata' => [
                'goal_id' => $closest->id,
                'progress' => $closest->progress_percentage
            ]
        ];
    }

    // ==========================================
    // ANALYSE : Tendances
    // ==========================================

    /**
     * Analyse les tendances de dépenses
     */
    private function analyzeTrends(): ?array
    {
        $now = now();
        $prev = now()->subMonth();

        $current = $this->getMonthlyExpenseTotal($now);
        $previous = $this->getMonthlyExpenseTotal($prev);

        if ($previous == 0) {
            return null;
        }

        $variation = (($current - $previous) / $previous) * 100;

        if ($variation > 20) {
            return [
                'type' => 'warning',
                'category' => 'trends',
                'title' => 'Dépenses en hausse',
                'message' => sprintf(
                    'Vos dépenses ont augmenté de %.0f%% '
                    . 'ce mois-ci.',
                    $variation
                ),
                'priority' => 'high',
                'action' => 'Analyser les dépenses',
                'action_url' => '/transactions',
                'metadata' => [
                    'variation' => $variation,
                    'current' => $current,
                    'previous' => $previous
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
                    'saved' => abs($current - $previous)
                ]
            ];
        }

        return null;
    }

    // ==========================================
    // HELPER : Total dépenses mensuel
    // ==========================================

    /**
     * Calcule le total des dépenses pour un mois
     */
    private function getMonthlyExpenseTotal($date): float
    {
        return Transaction::where('user_id', $this->user->id)
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereYear('transaction_date', $date->year)
            ->whereMonth('transaction_date', $date->month)
            ->sum(DB::raw('ABS(amount)'));
    }
}
