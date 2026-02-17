<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FinancialGoal;
use App\Models\FinancialInsight;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialInsightService
{
    /**
     * Génère tous les insights pour un utilisateur
     * École 42: Fonction < 25 lignes
     */
    public function generateInsights(User $user): Collection
    {
        $insights = collect();

        $insights = $insights->merge($this->detectUnusedSubscriptions($user));
        $insights = $insights->merge($this->analyzeSavingsOpportunity($user));
        $insights = $insights->merge($this->detectUnusualSpending($user));
        $insights = $insights->merge($this->analyzeGoalProgress($user));
        $insights = $insights->merge($this->detectBehavioralPatterns($user));

        return $insights->sortBy('priority');
    }

    /**
     * Détecte les abonnements non utilisés
     */
    private function detectUnusedSubscriptions(User $user): Collection
    {
        $subscriptions = $this->findRecurringTransactions($user);
        $insights = collect();

        foreach ($subscriptions as $sub) {
            // Si aucune transaction similaire ce mois
            if (!$this->hasRecentActivity($user, $sub)) {
                $insights->push($this->createInsight([
                    'user_id' => $user->id,
                    'type' => 'cost_reduction',
                    'priority' => 1,
                    'title' => 'Abonnement non utilisé détecté',
                    'description' => "Vous payez {$sub->amount}€/mois pour \"{$sub->description}\" mais ne l'utilisez plus depuis 2 mois.",
                    'icon' => '💳',
                    'action_label' => 'Annuler cet abonnement',
                    'potential_saving' => $sub->amount * 12,
                    'action_data' => [
                        'type' => 'cancel_subscription',
                        'transaction_id' => $sub->id,
                    ],
                    'metadata' => [
                        'last_seen' => $sub->last_occurrence,
                        'monthly_cost' => $sub->amount,
                    ],
                ]));
            }
        }

        return $insights;
    }

    /**
     * Analyse les opportunités d'épargne
     */
    private function analyzeSavingsOpportunity(User $user): Collection
    {
        $insights = collect();
        $capacity = $this->calculateSavingsCapacity($user);
        $currentRate = $this->getCurrentSavingsRate($user);

        if ($capacity > $currentRate) {
            $gap = $capacity - $currentRate;
            $goalImpact = $this->calculateGoalAcceleration($user, $gap);

            $insights->push($this->createInsight([
                'user_id' => $user->id,
                'type' => 'savings_opportunity',
                'priority' => 2,
                'title' => "Potentiel d'épargne inexploité",
                'description' => "Vous pourriez économiser {$gap}€ de plus par mois sans impacter votre qualité de vie.",
                'icon' => '💰',
                'action_label' => 'Activer virement automatique',
                'potential_saving' => $gap * 12,
                'goal_impact' => $goalImpact,
                'action_data' => [
                    'type' => 'setup_auto_transfer',
                    'amount' => $gap,
                ],
            ]));
        }

        return $insights;
    }

    /**
     * Détecte les dépenses inhabituelles
     */
    private function detectUnusualSpending(User $user): Collection
    {
        $insights = collect();
        $currentMonth = $this->getCurrentMonthSpending($user);
        $average = $this->getAverageMonthlySpending($user);

        foreach ($currentMonth as $categoryId => $amount) {
            $avgForCategory = $average[$categoryId] ?? 0;

            if ($amount > $avgForCategory * 1.5) {
                $increase = (($amount / $avgForCategory) - 1) * 100;
                $category = Category::find($categoryId);

                $insights->push($this->createInsight([
                    'user_id' => $user->id,
                    'type' => 'unusual_spending',
                    'priority' => 2,
                    'title' => "Dépense inhabituelle détectée",
                    'description' => "Vos dépenses \"{$category->name}\" ont augmenté de {$increase}% ce mois ({$amount}€ vs {$avgForCategory}€ habituellement).",
                    'icon' => '⚠️',
                    'action_label' => 'Voir les transactions',
                    'metadata' => [
                        'category_id' => $categoryId,
                        'current_amount' => $amount,
                        'average_amount' => $avgForCategory,
                        'increase_percent' => round($increase, 1),
                    ],
                ]));
            }
        }

        return $insights;
    }

    /**
     * Analyse la progression des objectifs
     */
    private function analyzeGoalProgress(User $user): Collection
    {
        $insights = collect();
        $goals = FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        foreach ($goals as $goal) {
            $progress = $this->calculateGoalProgress($goal);
            $timeLeft = $this->calculateDaysRemaining($goal);
            $requiredMonthly = $this->calculateRequiredMonthlyAmount($goal);
            $currentMonthly = $this->getCurrentMonthlySavings($user);

            // Si en retard sur l'objectif
            if ($currentMonthly < $requiredMonthly && $timeLeft > 0) {
                $gap = $requiredMonthly - $currentMonthly;

                $insights->push($this->createInsight([
                    'user_id' => $user->id,
                    'type' => 'goal_acceleration',
                    'priority' => 2,
                    'title' => "Objectif \"{$goal->name}\" en retard",
                    'description' => "Pour atteindre votre objectif à temps, vous devez économiser {$requiredMonthly}€/mois (actuellement {$currentMonthly}€/mois). Il manque {$gap}€/mois.",
                    'icon' => '🎯',
                    'action_label' => 'Ajuster mon budget',
                    'metadata' => [
                        'goal_id' => $goal->id,
                        'required_monthly' => $requiredMonthly,
                        'current_monthly' => $currentMonthly,
                        'gap' => $gap,
                        'days_left' => $timeLeft,
                    ],
                ]));
            }

            // Si proche de l'objectif (>90%)
            if ($progress >= 90 && $progress < 100) {
                $remaining = $goal->target_amount - $goal->current_amount;

                $insights->push($this->createInsight([
                    'user_id' => $user->id,
                    'type' => 'goal_acceleration',
                    'priority' => 1,
                    'title' => "Objectif \"{$goal->name}\" bientôt atteint !",
                    'description' => "Plus que {$remaining}€ pour atteindre votre objectif. Vous y êtes presque ! 🎉",
                    'icon' => '🏆',
                    'action_label' => 'Voir mon objectif',
                    'metadata' => [
                        'goal_id' => $goal->id,
                        'progress' => $progress,
                        'remaining' => $remaining,
                    ],
                ]));
            }
        }

        return $insights;
    }

    /**
     * Détecte les patterns comportementaux
     */
    private function detectBehavioralPatterns(User $user): Collection
    {
        $insights = collect();

        // Pattern : Dépenses week-end
        $weekendSpending = $this->analyzeWeekendSpending($user);
        if ($weekendSpending['is_significant']) {
            $insights->push($this->createInsight([
                'user_id' => $user->id,
                'type' => 'behavioral_pattern',
                'priority' => 3,
                'title' => 'Pattern détecté : Dépenses week-end',
                'description' => "Vos dépenses augmentent de {$weekendSpending['increase']}% le week-end. En moyenne {$weekendSpending['avg_weekend']}€/week-end vs {$weekendSpending['avg_weekday']}€/jour en semaine.",
                'icon' => '📊',
                'action_label' => 'Voir les détails',
                'metadata' => $weekendSpending,
            ]));
        }

        // Pattern : Fin de mois
        $endOfMonthPattern = $this->analyzeEndOfMonthSpending($user);
        if ($endOfMonthPattern['is_detected']) {
            $insights->push($this->createInsight([
                'user_id' => $user->id,
                'type' => 'behavioral_pattern',
                'priority' => 3,
                'title' => 'Pattern détecté : Fin de mois difficile',
                'description' => "Vos dépenses diminuent significativement en fin de mois. Conseil : répartir vos dépenses de manière plus uniforme.",
                'icon' => '📉',
                'metadata' => $endOfMonthPattern,
            ]));
        }

        return $insights;
    }

    /**
     * Crée un insight et le sauvegarde
     * École 42: Fonction < 25 lignes
     */
    private function createInsight(array $data): FinancialInsight
    {
        // Vérifier si insight similaire existe déjà
        $existing = FinancialInsight::where('user_id', $data['user_id'])
            ->where('type', $data['type'])
            ->where('title', $data['title'])
            ->where('is_dismissed', false)
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->first();

        if ($existing) {
            return $existing;
        }

        return FinancialInsight::create($data);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Trouve les transactions récurrentes
     */
    private function findRecurringTransactions(User $user): Collection
    {
        return Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('is_recurring', true)
            ->get()
            ->groupBy('description')
            ->map(function ($group) {
                return $group->first();
            });
    }

    /**
     * Vérifie si une transaction récurrente a eu lieu récemment
     */
    private function hasRecentActivity(User $user, Transaction $subscription): bool
    {
        return Transaction::where('user_id', $user->id)
            ->where('description', $subscription->description)
            ->where('transaction_date', '>=', now()->subMonths(2))
            ->exists();
    }

    /**
     * Calcule la capacité d'épargne
     */
    private function calculateSavingsCapacity(User $user): float
    {
        $income = $this->getMonthlyIncome($user);
        $expenses = $this->getMonthlyExpenses($user);

        return max(0, $income - $expenses);
    }

    /**
     * Calcule le taux d'épargne actuel
     */
    private function getCurrentSavingsRate(User $user): float
    {
        $contributions = DB::table('goal_contributions')
            ->where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        return (float) $contributions;
    }

    /**
     * Calcule l'accélération des objectifs
     */
    private function calculateGoalAcceleration(User $user, float $additionalAmount): array
    {
        $goals = FinancialGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        return $goals->map(function ($goal) use ($additionalAmount) {
            $remaining = $goal->target_amount - $goal->current_amount;
            $currentMonthly = $this->getCurrentMonthlySavings($user);

            $monthsWithout = $remaining / max($currentMonthly, 1);
            $monthsWith = $remaining / max($currentMonthly + $additionalAmount, 1);

            return [
                'goal_id' => $goal->id,
                'goal_name' => $goal->name,
                'months_saved' => round($monthsWithout - $monthsWith, 1),
                'new_completion_date' => now()->addMonths($monthsWith)->format('Y-m-d'),
            ];
        })->toArray();
    }

    /**
     * Dépenses du mois en cours par catégorie
     */
    private function getCurrentMonthSpending(User $user): array
    {
        return Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as total')
            ->pluck('total', 'category_id')
            ->toArray();
    }

    /**
     * Dépenses moyennes mensuelles par catégorie
     */
    private function getAverageMonthlySpending(User $user): array
    {
        $months = 3; // Moyenne sur 3 mois

        return Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('transaction_date', '>=', now()->subMonths($months))
            ->groupBy('category_id')
            ->selectRaw('category_id, AVG(amount) as average')
            ->pluck('average', 'category_id')
            ->map(fn($avg) => round($avg, 2))
            ->toArray();
    }

    /**
     * Analyse les dépenses du week-end
     */
    private function analyzeWeekendSpending(User $user): array
    {
        $weekdayAvg = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotIn(DB::raw('DAYOFWEEK(transaction_date)'), [1, 7]) // Pas samedi/dimanche
            ->where('transaction_date', '>=', now()->subMonths(2))
            ->avg('amount');

        $weekendAvg = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereIn(DB::raw('DAYOFWEEK(transaction_date)'), [1, 7])
            ->where('transaction_date', '>=', now()->subMonths(2))
            ->avg('amount');

        $increase = $weekendAvg > $weekdayAvg
            ? (($weekendAvg / $weekdayAvg) - 1) * 100
            : 0;

        return [
            'is_significant' => $increase > 30,
            'increase' => round($increase, 1),
            'avg_weekend' => round($weekendAvg, 2),
            'avg_weekday' => round($weekdayAvg, 2),
        ];
    }

    /**
     * Analyse les dépenses de fin de mois
     */
    private function analyzeEndOfMonthSpending(User $user): array
    {
        $firstHalf = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereDay('transaction_date', '<=', 15)
            ->where('transaction_date', '>=', now()->subMonths(2))
            ->avg('amount');

        $secondHalf = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereDay('transaction_date', '>', 15)
            ->where('transaction_date', '>=', now()->subMonths(2))
            ->avg('amount');

        $decrease = $secondHalf < $firstHalf
            ? (1 - ($secondHalf / $firstHalf)) * 100
            : 0;

        return [
            'is_detected' => $decrease > 20,
            'decrease' => round($decrease, 1),
            'first_half_avg' => round($firstHalf, 2),
            'second_half_avg' => round($secondHalf, 2),
        ];
    }

    // Helpers simplifiés
    private function getMonthlyIncome(User $user): float
    {
        return Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');
    }

    private function getMonthlyExpenses(User $user): float
    {
        return Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');
    }

    private function getCurrentMonthlySavings(User $user): float
    {
        return $this->getCurrentSavingsRate($user);
    }

    private function calculateGoalProgress(FinancialGoal $goal): float
    {
        return ($goal->current_amount / $goal->target_amount) * 100;
    }

    private function calculateDaysRemaining(FinancialGoal $goal): int
    {
        return now()->diffInDays($goal->target_date, false);
    }

    private function calculateRequiredMonthlyAmount(FinancialGoal $goal): float
    {
        $remaining = $goal->target_amount - $goal->current_amount;
        $monthsLeft = max(now()->diffInMonths($goal->target_date), 1);

        return $remaining / $monthsLeft;
    }
}
