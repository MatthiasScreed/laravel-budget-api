<?php

namespace App\Services;

use App\Models\FinancialGoal;
use Carbon\Carbon;

class ProjectionCalculator
{
    private FinancialGoal $goal;

    private array $historicalData;

    public function __construct(FinancialGoal $goal)
    {
        $this->goal = $goal;
        $this->loadHistoricalData();
    }

    /**
     * Calculer la projection selon le type
     */
    public function calculate(string $type = 'realistic'): array
    {
        $baseCalculation = $this->calculateBase();

        return match ($type) {
            'optimistic' => $this->calculateOptimistic($baseCalculation),
            'pessimistic' => $this->calculatePessimistic($baseCalculation),
            default => $this->calculateRealistic($baseCalculation)
        };
    }

    /**
     * Calcul de base
     */
    private function calculateBase(): array
    {
        $remainingAmount = $this->goal->remaining_amount;
        $monthlyAverage = $this->getMonthlyAverage();

        if ($monthlyAverage <= 0) {
            return $this->getDefaultProjection();
        }

        $monthsNeeded = ceil($remainingAmount / $monthlyAverage);
        $projectedDate = now()->addMonths($monthsNeeded);

        return [
            'remaining_amount' => $remainingAmount,
            'monthly_average' => $monthlyAverage,
            'months_needed' => $monthsNeeded,
            'projected_date' => $projectedDate,
            'projected_amount' => $this->goal->current_amount + ($monthlyAverage * $monthsNeeded),
        ];
    }

    /**
     * Calcul réaliste
     */
    private function calculateRealistic(array $base): array
    {
        $variabilityFactor = $this->getVariabilityFactor();
        $seasonalityFactor = $this->getSeasonalityFactor();

        $adjustedMonthly = $base['monthly_average'] * (1 - $variabilityFactor) * $seasonalityFactor;
        $monthsNeeded = $adjustedMonthly > 0 ? ceil($base['remaining_amount'] / $adjustedMonthly) : 24;

        $projectedDate = now()->addMonths($monthsNeeded);
        $confidence = $this->calculateConfidence($base, 'realistic');

        return [
            'projected_date' => $projectedDate,
            'monthly_saving_required' => $adjustedMonthly,
            'projected_amount' => $this->goal->target_amount,
            'confidence_score' => $confidence,
            'assumptions' => [
                'historical_average' => $base['monthly_average'],
                'variability_adjustment' => $variabilityFactor,
                'seasonality_factor' => $seasonalityFactor,
                'data_points' => count($this->historicalData),
            ],
            'milestones' => $this->calculateMilestones($projectedDate, $adjustedMonthly),
            'recommendation' => $this->generateRecommendation($adjustedMonthly, $confidence),
            'calculation_data' => array_merge($base, [
                'type' => 'realistic',
                'adjustment_factors' => [
                    'variability' => $variabilityFactor,
                    'seasonality' => $seasonalityFactor,
                ],
            ]),
        ];
    }

    /**
     * Calcul optimiste
     */
    private function calculateOptimistic(array $base): array
    {
        $improvementFactor = 1.3; // 30% d'amélioration
        $optimisticMonthly = $base['monthly_average'] * $improvementFactor;
        $monthsNeeded = ceil($base['remaining_amount'] / $optimisticMonthly);

        $projectedDate = now()->addMonths($monthsNeeded);
        $confidence = $this->calculateConfidence($base, 'optimistic');

        return [
            'projected_date' => $projectedDate,
            'monthly_saving_required' => $optimisticMonthly,
            'projected_amount' => $this->goal->target_amount,
            'confidence_score' => $confidence,
            'assumptions' => [
                'historical_average' => $base['monthly_average'],
                'improvement_factor' => $improvementFactor,
                'scenario' => 'Amélioration des habitudes d\'épargne',
            ],
            'milestones' => $this->calculateMilestones($projectedDate, $optimisticMonthly),
            'recommendation' => 'Scénario optimiste basé sur une amélioration de vos habitudes d\'épargne.',
            'calculation_data' => array_merge($base, [
                'type' => 'optimistic',
                'improvement_factor' => $improvementFactor,
            ]),
        ];
    }

    /**
     * Calcul pessimiste
     */
    private function calculatePessimistic(array $base): array
    {
        $reductionFactor = 0.7; // 30% de réduction
        $pessimisticMonthly = $base['monthly_average'] * $reductionFactor;
        $monthsNeeded = $pessimisticMonthly > 0 ? ceil($base['remaining_amount'] / $pessimisticMonthly) : 36;

        $projectedDate = now()->addMonths($monthsNeeded);
        $confidence = $this->calculateConfidence($base, 'pessimistic');

        return [
            'projected_date' => $projectedDate,
            'monthly_saving_required' => $pessimisticMonthly,
            'projected_amount' => $this->goal->target_amount,
            'confidence_score' => $confidence,
            'assumptions' => [
                'historical_average' => $base['monthly_average'],
                'reduction_factor' => $reductionFactor,
                'scenario' => 'Prise en compte d\'imprévus financiers',
            ],
            'milestones' => $this->calculateMilestones($projectedDate, $pessimisticMonthly),
            'recommendation' => 'Scénario prudent tenant compte d\'éventuels imprévus financiers.',
            'calculation_data' => array_merge($base, [
                'type' => 'pessimistic',
                'reduction_factor' => $reductionFactor,
            ]),
        ];
    }

    /**
     * Charger les données historiques
     */
    private function loadHistoricalData(): void
    {
        $this->historicalData = $this->goal->contributions()
            ->where('date', '>=', now()->subMonths(12))
            ->orderBy('date')
            ->get()
            ->groupBy(function ($contribution) {
                return $contribution->date->format('Y-m');
            })
            ->map(function ($contributions) {
                return $contributions->sum('amount');
            })
            ->toArray();
    }

    /**
     * Obtenir la moyenne mensuelle
     */
    private function getMonthlyAverage(): float
    {
        if (empty($this->historicalData)) {
            return $this->goal->monthly_target ?? 0;
        }

        return array_sum($this->historicalData) / count($this->historicalData);
    }

    /**
     * Calculer le facteur de variabilité
     */
    private function getVariabilityFactor(): float
    {
        if (count($this->historicalData) < 3) {
            return 0.1; // Faible ajustement par défaut
        }

        $mean = $this->getMonthlyAverage();
        $variance = 0;

        foreach ($this->historicalData as $amount) {
            $variance += pow($amount - $mean, 2);
        }

        $standardDeviation = sqrt($variance / count($this->historicalData));
        $coefficientOfVariation = $mean > 0 ? $standardDeviation / $mean : 0;

        return min(0.3, $coefficientOfVariation); // Plafonner à 30%
    }

    /**
     * Calculer le facteur de saisonnalité
     */
    private function getSeasonalityFactor(): float
    {
        $currentMonth = now()->month;

        // Facteurs saisonniers simplifiés (peuvent être personnalisés)
        $seasonalFactors = [
            1 => 0.9,  // Janvier - après les fêtes
            2 => 1.0,  // Février
            3 => 1.0,  // Mars
            4 => 1.0,  // Avril
            5 => 1.0,  // Mai
            6 => 0.95, // Juin - vacances
            7 => 0.85, // Juillet - vacances
            8 => 0.85, // Août - vacances
            9 => 1.05, // Septembre - rentrée
            10 => 1.0, // Octobre
            11 => 0.9, // Novembre - préparation fêtes
            12 => 0.8,  // Décembre - fêtes
        ];

        return $seasonalFactors[$currentMonth] ?? 1.0;
    }

    /**
     * Calculer la confiance
     */
    private function calculateConfidence(array $base, string $type): float
    {
        $dataQuality = min(1.0, count($this->historicalData) / 6); // Max confiance avec 6 mois de données
        $consistency = 1 - $this->getVariabilityFactor();
        $goalRealism = $this->assessGoalRealism();

        $baseConfidence = ($dataQuality + $consistency + $goalRealism) / 3;

        return match ($type) {
            'optimistic' => $baseConfidence * 0.7, // Moins confiant pour optimiste
            'pessimistic' => $baseConfidence * 0.9, // Plus confiant pour pessimiste
            default => $baseConfidence
        };
    }

    /**
     * Évaluer le réalisme de l'objectif
     */
    private function assessGoalRealism(): float
    {
        if (! $this->goal->target_date) {
            return 0.5; // Moyennement réaliste sans date cible
        }

        $monthsAvailable = now()->diffInMonths($this->goal->target_date);
        $monthlyRequired = $monthsAvailable > 0 ? $this->goal->remaining_amount / $monthsAvailable : PHP_FLOAT_MAX;
        $monthlyAverage = $this->getMonthlyAverage();

        if ($monthlyAverage <= 0) {
            return 0.3; // Peu réaliste sans historique
        }

        $ratio = $monthlyRequired / $monthlyAverage;

        return match (true) {
            $ratio <= 1.0 => 1.0,    // Très réaliste
            $ratio <= 1.5 => 0.8,   // Réaliste
            $ratio <= 2.0 => 0.6,   // Moyennement réaliste
            $ratio <= 3.0 => 0.4,   // Peu réaliste
            default => 0.2           // Très peu réaliste
        };
    }

    /**
     * Calculer les étapes intermédiaires
     */
    private function calculateMilestones(Carbon $projectedDate, float $monthlyAmount): array
    {
        $milestones = [];
        $percentages = [25, 50, 75, 100];

        foreach ($percentages as $percentage) {
            $targetAmount = ($percentage / 100) * $this->goal->target_amount;
            $remainingForMilestone = max(0, $targetAmount - $this->goal->current_amount);

            if ($monthlyAmount > 0 && $remainingForMilestone > 0) {
                $monthsToMilestone = ceil($remainingForMilestone / $monthlyAmount);
                $milestoneDate = now()->addMonths($monthsToMilestone);
            } else {
                $milestoneDate = now();
            }

            $milestones[] = [
                'percentage' => $percentage,
                'target_amount' => $targetAmount,
                'date' => $milestoneDate->format('Y-m-d'),
                'description' => $percentage === 100 ? 'Objectif atteint' : "{$percentage}% de l'objectif",
            ];
        }

        return $milestones;
    }

    /**
     * Générer une recommandation
     */
    private function generateRecommendation(float $monthlyRequired, float $confidence): string
    {
        $currentAverage = $this->getMonthlyAverage();

        if ($confidence >= 0.8) {
            return 'Excellente trajectoire ! Continuez à épargner {'.number_format($monthlyRequired, 0).'} € par mois.';
        }

        if ($confidence >= 0.6) {
            return "Bonne progression. Essayez d'épargner régulièrement {".number_format($monthlyRequired, 0).'} € par mois.';
        }

        if ($monthlyRequired > $currentAverage * 1.5) {
            return 'Objectif ambitieux. Considérez réviser la date cible ou augmenter vos contributions à {'.number_format($monthlyRequired, 0).'} € par mois.';
        }

        return 'Améliorez la régularité de vos contributions pour atteindre {'.number_format($monthlyRequired, 0).'} € par mois.';
    }

    /**
     * Projection par défaut en cas de données insuffisantes
     */
    private function getDefaultProjection(): array
    {
        $defaultMonthly = $this->goal->monthly_target ?? 100;
        $monthsNeeded = ceil($this->goal->remaining_amount / $defaultMonthly);

        return [
            'remaining_amount' => $this->goal->remaining_amount,
            'monthly_average' => $defaultMonthly,
            'months_needed' => $monthsNeeded,
            'projected_date' => now()->addMonths($monthsNeeded),
            'projected_amount' => $this->goal->target_amount,
        ];
    }
}
