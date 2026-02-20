<?php

namespace App\Services;

use App\Events\GoalCompleted;
use App\Events\GoalCreated;
use App\Events\TransactionCreated;
use App\Models\FinancialGoal;
use App\Models\GoalContribution;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    protected GamingService $gamingService;

    public function __construct(GamingService $gamingService)
    {
        $this->gamingService = $gamingService;
    }

    /**
     * Créer une nouvelle transaction
     *
     * @param  User  $user  Utilisateur propriétaire
     * @param  array  $data  Données de la transaction
     * @return Transaction Transaction créée
     */
    public function createTransaction(User $user, array $data): Transaction
    {
        DB::beginTransaction();

        try {
            $transaction = $user->transactions()->create([
                'category_id' => $data['category_id'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'transaction_date' => $data['transaction_date'] ?? now(),
                'description' => $data['description'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'status' => 'completed',
            ]);

            // 🎮 GAMING SÉCURISÉ
            try {
                event(new TransactionCreated($user, $transaction));
                $xpAmount = $this->calculateTransactionXp($transaction);
                $this->gamingService->addExperience($user, $xpAmount, 'transaction');
            } catch (\Exception $gamingError) {
                \Log::warning('Gaming error: '.$gamingError->getMessage());
            }

            DB::commit();

            return $transaction;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculer l'XP pour une transaction
     *
     * @param  Transaction  $transaction  Transaction concernée
     * @return int Points d'expérience
     */
    public function calculateTransactionXp(Transaction $transaction): int
    {
        $baseXp = 5; // XP de base pour toute transaction
        $amountBonus = min(50, floor($transaction->amount / 100)); // 1 XP par 100€

        return $baseXp + $amountBonus;
    }

    /**
     * Mettre à jour une transaction existante
     *
     * @param  Transaction  $transaction  Transaction à modifier
     * @param  array  $data  Nouvelles données
     * @return Transaction Transaction mise à jour
     */
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        $allowedFields = [
            'category_id', 'amount', 'transaction_date',
            'description', 'payment_method', 'reference',
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));
        $transaction->update($updateData);

        // Invalider le cache des statistiques
        $this->clearUserStatsCache($transaction->user);

        return $transaction->fresh();
    }

    /**
     * Créer une contribution à un objectif financier
     *
     * @param  User  $user  Utilisateur concerné
     * @param  FinancialGoal  $goal  Objectif financier
     * @param  array  $data  Données de la contribution
     * @return GoalContribution Contribution créée
     */
    public function createGoalContribution(User $user, FinancialGoal $goal, array $data): GoalContribution
    {
        DB::beginTransaction();

        try {
            $contribution = $goal->contributions()->create([
                'amount' => $data['amount'],
                'date' => $data['date'] ?? now(),
                'description' => $data['description'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'is_automatic' => $data['is_automatic'] ?? false,
            ]);

            // Recalculer le montant actuel de l'objectif
            $goal->recalculateCurrentAmount();

            // Vérifier si l'objectif est atteint
            if ($goal->is_reached && $goal->status === 'active') {
                $goal->markAsCompleted();
                event(new GoalCompleted($user, $goal));

                // XP bonus pour objectif atteint
                $this->gamingService->addExperience($user, 200, 'goal_completed');
            }

            // XP pour la contribution
            $xpAmount = $this->calculateContributionXp($contribution);
            $this->gamingService->addExperience($user, $xpAmount, 'contribution');

            DB::commit();

            return $contribution;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculer l'XP pour une contribution
     *
     * @param  GoalContribution  $contribution  Contribution concernée
     * @return int Points d'expérience
     */
    protected function calculateContributionXp(GoalContribution $contribution): int
    {
        $baseXp = 10; // XP de base pour toute contribution
        $amountBonus = min(100, floor($contribution->amount / 50)); // 1 XP par 50€

        return $baseXp + $amountBonus;
    }

    /**
     * Obtenir les statistiques budgétaires d'un utilisateur
     *
     * @param  User  $user  Utilisateur concerné
     * @param  Carbon|null  $month  Mois concerné
     * @return array Statistiques complètes
     */
    public function getBudgetStats(User $user, ?Carbon $month = null): array
    {
        $month = $month ?? now();
        $cacheKey = "budget_stats_{$user->id}_{$month->format('Y-m')}";

        return Cache::remember($cacheKey, 300, function () use ($user, $month) {
            return [
                'monthly' => $this->getMonthlyStats($user, $month),
                'categories' => $this->getCategoryStats($user, $month),
                'goals' => $this->getGoalStats($user),
                'trends' => $this->getTrends($user, $month),
                'summary' => $this->getBudgetSummary($user, $month),
            ];
        });
    }

    /**
     * Obtenir les statistiques mensuelles
     *
     * @param  User  $user  Utilisateur concerné
     * @param  Carbon  $month  Mois concerné
     * @return array Statistiques mensuelles
     */
    protected function getMonthlyStats(User $user, Carbon $month): array
    {
        $income = $user->transactions()
            ->income()
            ->completed()
            ->whereYear('transaction_date', $month->year)
            ->whereMonth('transaction_date', $month->month)
            ->sum('amount');

        $expenses = $user->transactions()
            ->expense()
            ->completed()
            ->whereYear('transaction_date', $month->year)
            ->whereMonth('transaction_date', $month->month)
            ->sum('amount');

        return [
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $income - $expenses,
            'savings_rate' => $income > 0 ? (($income - $expenses) / $income) * 100 : 0,
        ];
    }

    /**
     * Obtenir les statistiques par catégorie
     *
     * @param  User  $user  Utilisateur concerné
     * @param  Carbon  $month  Mois concerné
     * @return Collection Statistiques par catégorie
     */
    protected function getCategoryStats(User $user, Carbon $month): Collection
    {
        return $user->categories()
            ->with(['transactions' => function ($query) use ($month) {
                $query->completed()
                    ->whereYear('transaction_date', $month->year)
                    ->whereMonth('transaction_date', $month->month);
            }])
            ->get()
            ->map(function ($category) {
                $transactions = $category->transactions;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'color' => $category->color,
                    'total_amount' => $transactions->sum('amount'),
                    'transactions_count' => $transactions->count(),
                    'average_amount' => $transactions->avg('amount') ?? 0,
                ];
            });
    }

    /**
     * Obtenir les statistiques des objectifs
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Statistiques des objectifs
     */
    protected function getGoalStats(User $user): array
    {
        $goals = $user->financialGoals()->get();

        return [
            'total_goals' => $goals->count(),
            'active_goals' => $goals->where('status', 'active')->count(),
            'completed_goals' => $goals->where('status', 'completed')->count(),
            'total_target_amount' => $goals->sum('target_amount'),
            'total_saved_amount' => $goals->sum('current_amount'),
            'average_progress' => $goals->avg('progress_percentage') ?? 0,
        ];
    }

    /**
     * Obtenir les tendances budgétaires
     *
     * @param  User  $user  Utilisateur concerné
     * @param  Carbon  $month  Mois de référence
     * @return array Tendances sur 6 mois
     */
    protected function getTrends(User $user, Carbon $month): array
    {
        $trends = [];

        for ($i = 5; $i >= 0; $i--) {
            $targetMonth = $month->copy()->subMonths($i);
            $monthlyStats = $this->getMonthlyStats($user, $targetMonth);

            $trends[] = [
                'month' => $targetMonth->format('Y-m'),
                'month_name' => $targetMonth->format('M Y'),
                'income' => $monthlyStats['income'],
                'expenses' => $monthlyStats['expenses'],
                'balance' => $monthlyStats['balance'],
            ];
        }

        return $trends;
    }

    /**
     * Obtenir le résumé budgétaire
     *
     * @param  User  $user  Utilisateur concerné
     * @param  Carbon  $month  Mois concerné
     * @return array Résumé budgétaire
     */
    protected function getBudgetSummary(User $user, Carbon $month): array
    {
        $monthlyStats = $this->getMonthlyStats($user, $month);
        $previousMonth = $this->getMonthlyStats($user, $month->copy()->subMonth());

        return [
            'current_month' => $monthlyStats,
            'previous_month' => $previousMonth,
            'income_change' => $this->calculatePercentageChange(
                $previousMonth['income'],
                $monthlyStats['income']
            ),
            'expense_change' => $this->calculatePercentageChange(
                $previousMonth['expenses'],
                $monthlyStats['expenses']
            ),
            'balance_change' => $this->calculatePercentageChange(
                $previousMonth['balance'],
                $monthlyStats['balance']
            ),
        ];
    }

    /**
     * Calculer le pourcentage de changement
     *
     * @param  float  $oldValue  Ancienne valeur
     * @param  float  $newValue  Nouvelle valeur
     * @return float Pourcentage de changement
     */
    protected function calculatePercentageChange(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return (($newValue - $oldValue) / $oldValue) * 100;
    }

    /**
     * Vider le cache des statistiques utilisateur
     *
     * @param  User  $user  Utilisateur concerné
     */
    protected function clearUserStatsCache(User $user): void
    {
        $patterns = [
            "budget_stats_{$user->id}_*",
            "gaming_dashboard_{$user->id}",
            "user_achievements_check_{$user->id}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Analyser les habitudes de dépenses
     *
     * @param  User  $user  Utilisateur concerné
     * @param  int  $months  Nombre de mois à analyser
     * @return array Analyse des habitudes
     */
    public function analyzeSpendingHabits(User $user, int $months = 6): array
    {
        $startDate = now()->subMonths($months);

        $transactions = $user->transactions()
            ->completed()
            ->where('transaction_date', '>=', $startDate)
            ->with('category')
            ->get();

        return [
            'most_used_categories' => $this->getMostUsedCategories($transactions),
            'spending_patterns' => $this->getSpendingPatterns($transactions),
            'payment_methods' => $this->getPaymentMethodStats($transactions),
            'weekly_patterns' => $this->getWeeklyPatterns($transactions),
        ];
    }

    /**
     * Obtenir les catégories les plus utilisées
     *
     * @param  Collection  $transactions  Collection de transactions
     * @return Collection Catégories triées par usage
     */
    protected function getMostUsedCategories(Collection $transactions): Collection
    {
        return $transactions->groupBy('category_id')
            ->map(function ($group) {
                $category = $group->first()->category;

                return [
                    'category' => $category,
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                    'average_amount' => $group->avg('amount'),
                ];
            })
            ->sortByDesc('total_amount')
            ->take(10)
            ->values();
    }

    /**
     * Obtenir les patterns de dépenses
     *
     * @param  Collection  $transactions  Collection de transactions
     * @return array Patterns de dépenses
     */
    protected function getSpendingPatterns(Collection $transactions): array
    {
        $byDay = $transactions->groupBy(function ($transaction) {
            return $transaction->transaction_date->dayOfWeek;
        });

        return [
            'busiest_day' => $byDay->sortByDesc(function ($group) {
                return $group->count();
            })->keys()->first(),
            'highest_spending_day' => $byDay->sortByDesc(function ($group) {
                return $group->sum('amount');
            })->keys()->first(),
        ];
    }

    /**
     * Obtenir les statistiques des méthodes de paiement
     *
     * @param  Collection  $transactions  Collection de transactions
     * @return Collection Statistiques par méthode
     */
    protected function getPaymentMethodStats(Collection $transactions): Collection
    {
        return $transactions->groupBy('payment_method')
            ->map(function ($group, $method) {
                return [
                    'method' => $method ?: 'Non spécifié',
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                    'percentage' => 0, // Calculé après
                ];
            })
            ->values();
    }

    /**
     * Obtenir les patterns hebdomadaires
     *
     * @param  Collection  $transactions  Collection de transactions
     * @return array Patterns par jour de la semaine
     */
    protected function getWeeklyPatterns(Collection $transactions): array
    {
        $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

        $byDay = $transactions->groupBy(function ($transaction) {
            return $transaction->transaction_date->dayOfWeek;
        });

        $patterns = [];
        for ($i = 0; $i < 7; $i++) {
            $dayTransactions = $byDay->get($i, collect());
            $patterns[] = [
                'day' => $days[$i],
                'count' => $dayTransactions->count(),
                'average_amount' => $dayTransactions->avg('amount') ?? 0,
            ];
        }

        return $patterns;
    }

    /**
     * Mettre à jour une série (stub pour éviter les erreurs)
     */
    public function updateStreak(User $user, string $streakType): void
    {
        // TODO: Implémenter la logique des séries plus tard
        \Log::info("Streak update called for user {$user->id}, type: {$streakType}");
    }

    /**
     * Créer un nouvel objectif financier avec événements gaming
     *
     * @param  User  $user  Utilisateur concerné
     * @param  array  $data  Données de l'objectif
     * @return FinancialGoal Objectif créé
     */
    public function createGoal(User $user, array $data): FinancialGoal
    {
        DB::beginTransaction();

        try {
            // ✅ Normaliser la priorité : 'low'|'medium'|'high' → 1-5
            $data['priority'] = $this->normalizePriority($data['priority'] ?? null);

            // ✅ Champs obligatoires / valeurs par défaut
            $data['user_id']    = $user->id;
            $data['start_date'] = $data['start_date'] ?? now()->toDateString();

            // ✅ Filtrer uniquement les colonnes acceptées par le modèle
            $allowed = [
                'user_id', 'name', 'description', 'target_amount', 'current_amount',
                'target_date', 'start_date', 'status', 'type', 'priority',
                'color', 'icon', 'monthly_target', 'is_automatic',
                'automatic_amount', 'automatic_frequency', 'notes', 'tags',
            ];
            $data = array_intersect_key($data, array_flip($allowed));

            // ✅ Calculer next_automatic_date si contributions automatiques
            if (!empty($data['is_automatic'])) {
                $data['next_automatic_date'] = $this->calculateNextAutomaticDate(
                    $data['automatic_frequency'] ?? 'monthly'
                );
            }

            $goal = FinancialGoal::create($data);

            // ✅ Gaming (dans le try, mais erreurs non bloquantes)
            try {
                event(new GoalCreated($user, $goal));
                $this->gamingService->addExperience($user, 25, 'goal_created');
                $this->gamingService->updateStreak($user, 'goal_creation');
                $this->gamingService->checkAchievements($user);
            } catch (\Exception $gamingException) {
                \Log::warning('Gaming post-goal non bloquant : ' . $gamingException->getMessage());
            }

            DB::commit();
            return $goal;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('BudgetService::createGoal failed', [
                'user_id' => $user->id,
                'data'    => $data,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Normalise la priorité vers un entier 1-5
     * Accepte : 'low'|'medium'|'high'|'urgent'|int|null
     */
    private function normalizePriority(mixed $priority): int
    {
        if (is_int($priority) && $priority >= 1 && $priority <= 5) {
            return $priority;
        }

        return match((string) $priority) {
            'urgent', '1' => 1,
            'high',   '2' => 2,
            'medium', '3' => 3,
            'low',    '4' => 4,
            default        => 3,   // fallback medium
        };
    }

    /**
     * Calculer la prochaine date de contribution automatique
     *
     * @param  string  $frequency  Fréquence des contributions
     * @return string Date au format Y-m-d
     */
    protected function calculateNextAutomaticDate(string $frequency): string
    {
        return match ($frequency) {
            'weekly' => now()->addWeek()->toDateString(),
            'monthly' => now()->addMonth()->toDateString(),
            'quarterly' => now()->addQuarter()->toDateString(),
            default => now()->addMonth()->toDateString()
        };
    }
}
