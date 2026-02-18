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
 * - action_data contient désormais create_goal pour création automatique
 * - URLs normalisées via ROUTE_MAP
 * - mapType() sans valeur invalide
 */
class FinancialInsightService
{
    private $user;

    private const VALID_TYPES = [
        'cost_reduction', 'savings_opportunity', 'behavioral_pattern',
        'goal_acceleration', 'budget_alert', 'unusual_spending', 'warning',
    ];

    private const ROUTE_MAP = [
        '/goals/create'  => '/app/goals',
        '/goals'         => '/app/goals',
        '/transactions'  => '/app/transactions',
        '/analytics'     => '/app/analytics',
        '/dashboard'     => '/app/dashboard',
    ];

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
            $rawInsights = array_filter([
                $this->analyzeSavings(),
                $this->analyzeCategories(),
                $this->analyzeGoals(),
                $this->analyzeTrends(),
            ]);

            return $this->persistInsights(
                array_slice(array_values($rawInsights), 0, 5)
            );

        } catch (\Exception $e) {
            Log::error('Erreur génération insights', [
                'user_id' => $this->user->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Persiste les insights bruts en BDD
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
     * Sauvegarde un insight individuel (évite les doublons du jour)
     */
    private function saveInsight(array $raw): ?FinancialInsight
    {
        $category   = $raw['category'] ?? '';
        $mappedType = $this->mapType($category);

        $existing = FinancialInsight::where('user_id', $this->user->id)
            ->where('type', $mappedType)
            ->whereDate('created_at', today())
            ->where('title', $raw['title'] ?? '')
            ->first();

        if ($existing) {
            return $existing;
        }

        // ✅ Construire action_data avec create_goal si applicable
        $actionData = $this->buildActionData($raw);

        return FinancialInsight::create([
            'user_id'          => $this->user->id,
            'type'             => $mappedType,
            'priority'         => $this->mapPriority($raw['priority'] ?? 'medium'),
            'title'            => $raw['title'] ?? 'Insight',
            'description'      => $raw['message'] ?? '',
            'icon'             => $this->mapIcon($category),
            'action_label'     => $raw['action'] ?? null,
            'action_data'      => $actionData,
            'potential_saving' => $raw['metadata']['saved'] ?? null,
            'metadata'         => $raw['metadata'] ?? null,
            'is_read'          => false,
            'is_dismissed'     => false,
        ]);
    }

    /**
     * ✅ Construit l'action_data avec le template create_goal si présent
     *
     * Le frontend lit action_data.create_goal pour créer automatiquement
     * l'objectif en BDD sans passer par un formulaire.
     */
    private function buildActionData(array $raw): ?array
    {
        $data = [];

        // URL de navigation après action
        if (isset($raw['action_url'])) {
            $data['url'] = $this->normalizeUrl($raw['action_url']);
        }

        // Template d'objectif à créer automatiquement
        if (isset($raw['create_goal'])) {
            $data['create_goal'] = $raw['create_goal'];
        }

        return empty($data) ? null : $data;
    }

    /**
     * ✅ Normalise les URLs vers les routes /app/...
     */
    private function normalizeUrl(string $url): string
    {
        $path  = strtok($url, '?');
        $query = strstr($url, '?') ?: '';

        if (isset(self::ROUTE_MAP[$path])) {
            return self::ROUTE_MAP[$path] . $query;
        }

        foreach (self::ROUTE_MAP as $from => $to) {
            if (str_starts_with($path, $from . '/')) {
                return $to . substr($path, strlen($from)) . $query;
            }
        }

        if (str_starts_with($url, '/app') || str_starts_with($url, 'http')) {
            return $url;
        }

        return '/app' . $url;
    }

    private function mapPriority(string $priority): int
    {
        return match ($priority) {
            'high'  => 1,
            'medium'=> 2,
            'low'   => 3,
            default => 2,
        };
    }

    private function mapType(string $category): string
    {
        return match ($category) {
            'savings'  => 'savings_opportunity',
            'spending' => 'cost_reduction',
            'goals'    => 'goal_acceleration',
            'trends'   => 'behavioral_pattern',
            default    => 'unusual_spending',
        };
    }

    private function mapIcon(string $category): string
    {
        return match ($category) {
            'savings'  => '💰',
            'spending' => '💳',
            'goals'    => '🎯',
            'trends'   => '📊',
            default    => '💡',
        };
    }

    // ==========================================
    // ANALYSE : Épargne
    // ==========================================

    /**
     * ✅ Analyse le taux d'épargne et propose un objectif pré-rempli
     *
     * create_goal est injecté dans action_data pour que le frontend
     * puisse créer l'objectif automatiquement en BDD.
     */
    private function analyzeSavings(): ?array
    {
        $rate = $this->getSavingsRate();

        if ($rate < 10) {
            // Calculer un montant cible intelligent (3 mois de revenus)
            $monthlyIncome = $this->getMonthlyIncome();
            $targetAmount  = round($monthlyIncome * 3, -2); // arrondi à la centaine
            $targetAmount  = max(500, $targetAmount);        // minimum 500€

            return [
                'type'       => 'warning',
                'category'   => 'savings',
                'title'      => 'Taux d\'épargne faible',
                'message'    => sprintf(
                    'Votre taux d\'épargne est de %.1f%%.'
                    . ' Visez au moins 10%% pour sécuriser vos finances.',
                    $rate
                ),
                'priority'   => 'high',
                'action'     => 'Créer un objectif d\'épargne',
                'action_url' => '/app/goals',
                // ✅ Template pour création automatique en BDD
                'create_goal' => [
                    'name'          => '🛡️ Fonds d\'urgence',
                    'description'   => 'Objectif créé automatiquement par le Coach IA pour sécuriser vos finances.',
                    'target_amount' => $targetAmount,
                    'target_date'   => now()->addMonths(12)->format('Y-m-d'),
                    'icon'          => '🛡️',
                    'priority'      => 'high',
                ],
                'metadata' => ['current_rate' => $rate],
            ];
        }

        if ($rate > 20) {
            return [
                'type'     => 'success',
                'category' => 'savings',
                'title'    => 'Excellent taux d\'épargne !',
                'message'  => sprintf(
                    'Bravo ! Vous épargnez %.1f%% de vos revenus.',
                    $rate
                ),
                'priority' => 'low',
                'metadata' => ['current_rate' => $rate],
            ];
        }

        return null;
    }

    /**
     * Calcule le revenu mensuel moyen
     */
    private function getMonthlyIncome(): float
    {
        $now = now();
        return (float) Transaction::where('user_id', $this->user->id)
            ->where('type', 'income')
            ->where('status', 'completed')
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->sum('amount');
    }

    /**
     * Calcule le taux d'épargne mensuel
     */
    private function getSavingsRate(): float
    {
        $now    = now();
        $income = $this->getMonthlyIncome();

        if ($income == 0) return 0;

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

    private function analyzeCategories(): ?array
    {
        $now = now();

        $topCategory = Transaction::where('user_id', $this->user->id)
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->select('category_id', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->with('category')
            ->first();

        if (!$topCategory || !$topCategory->category) return null;

        $total = $this->getMonthlyExpenseTotal($now);
        if ($total == 0) return null;

        $pct = ($topCategory->total / $total) * 100;
        if ($pct <= 30) return null;

        return [
            'type'       => 'warning',
            'category'   => 'spending',
            'title'      => 'Catégorie dominante',
            'message'    => sprintf(
                '%s représente %.0f%% de vos dépenses.',
                $topCategory->category->name,
                $pct
            ),
            'priority'   => 'medium',
            'action'     => 'Voir les détails',
            'action_url' => '/transactions?category=' . $topCategory->category_id,
            'metadata'   => [
                'category'   => $topCategory->category->name,
                'percentage' => $pct,
                'amount'     => $topCategory->total,
            ],
        ];
    }

    // ==========================================
    // ANALYSE : Objectifs
    // ==========================================

    /**
     * ✅ Analyse les objectifs — propose création auto si aucun objectif
     */
    private function analyzeGoals(): ?array
    {
        $goals = FinancialGoal::where('user_id', $this->user->id)
            ->where('status', 'active')
            ->get();

        if ($goals->isEmpty()) {
            return [
                'type'       => 'info',
                'category'   => 'goals',
                'title'      => 'Créez votre premier objectif',
                'message'    => 'Définissez un objectif financier pour suivre votre progression.',
                'priority'   => 'high',
                'action'     => 'Créer un objectif',
                'action_url' => '/app/goals',
                // ✅ Template d'objectif générique créé automatiquement
                'create_goal' => [
                    'name'          => '🎯 Mon premier objectif',
                    'description'   => 'Objectif créé automatiquement par le Coach IA.',
                    'target_amount' => 1000,
                    'target_date'   => now()->addMonths(6)->format('Y-m-d'),
                    'icon'          => '🎯',
                    'priority'      => 'medium',
                ],
                'metadata' => [],
            ];
        }

        $closest = $goals->sortByDesc(fn ($g) => $g->progress_percentage)->first();

        if ($closest->progress_percentage <= 80) return null;

        $remaining = $closest->target_amount - $closest->current_amount;

        return [
            'type'       => 'success',
            'category'   => 'goals',
            'title'      => 'Objectif presque atteint !',
            'message'    => sprintf(
                '%s : %.0f%% complété. Plus que %.2f€ !',
                $closest->name,
                $closest->progress_percentage,
                $remaining
            ),
            'priority'   => 'medium',
            'action'     => 'Voir l\'objectif',
            'action_url' => '/goals/' . $closest->id,
            'metadata'   => [
                'goal_id'  => $closest->id,
                'progress' => $closest->progress_percentage,
            ],
        ];
    }

    // ==========================================
    // ANALYSE : Tendances
    // ==========================================

    private function analyzeTrends(): ?array
    {
        $now      = now();
        $prev     = now()->subMonth();
        $current  = $this->getMonthlyExpenseTotal($now);
        $previous = $this->getMonthlyExpenseTotal($prev);

        if ($previous == 0) return null;

        $variation = (($current - $previous) / $previous) * 100;

        if ($variation > 20) {
            return [
                'type'       => 'warning',
                'category'   => 'trends',
                'title'      => 'Dépenses en hausse',
                'message'    => sprintf(
                    'Vos dépenses ont augmenté de %.0f%% ce mois-ci.',
                    $variation
                ),
                'priority'   => 'high',
                'action'     => 'Analyser les dépenses',
                'action_url' => '/transactions',
                'metadata'   => [
                    'variation' => $variation,
                    'current'   => $current,
                    'previous'  => $previous,
                ],
            ];
        }

        if ($variation < -20) {
            return [
                'type'     => 'success',
                'category' => 'trends',
                'title'    => 'Belles économies !',
                'message'  => sprintf(
                    'Vous avez réduit vos dépenses de %.0f%%.',
                    abs($variation)
                ),
                'priority' => 'low',
                'metadata' => [
                    'variation' => $variation,
                    'saved'     => abs($current - $previous),
                ],
            ];
        }

        return null;
    }

    // ==========================================
    // HELPER
    // ==========================================

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
