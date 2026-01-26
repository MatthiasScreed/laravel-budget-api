<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Service - VERSION ROBUSTE
 *
 * Compatible avec données existantes de Bridge API
 */
class DashboardService
{
    /**
     * Récupérer les statistiques complètes
     */
    public function getStats(User $user, bool $refresh = false): array
    {
        $cacheKey = "dashboard_stats_{$user->id}";
        $cacheDuration = 5 * 60; // 5 minutes

        if (! $refresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $stats = [
                'financial' => $this->getFinancialStats($user),
                'goals' => $this->getGoalStats($user),
                'gaming' => $this->getGamingStats($user),
                'recent_activity' => $this->getRecentActivity($user),
                'overview' => $this->getOverviewStats($user),
            ];

            Cache::put($cacheKey, $stats, $cacheDuration);

            return $stats;

        } catch (\Exception $e) {
            Log::error('Erreur getStats: '.$e->getMessage());

            // Retourner des données par défaut en cas d'erreur
            return $this->getDefaultStats();
        }
    }

    /**
     * Statistiques financières
     */
    protected function getFinancialStats(User $user): array
    {
        try {
            $currentMonth = now()->startOfMonth();

            // Utiliser Query Builder directement sur la table transactions
            $transactions = DB::table('transactions')
                ->where('user_id', $user->id)
                ->where('transaction_date', '>=', $currentMonth)
                ->get();

            // Calculer la balance totale
            $balance = DB::table('transactions')
                ->where('user_id', $user->id)
                ->selectRaw('SUM(CASE WHEN type = "income" THEN amount WHEN type = "expense" THEN -amount ELSE 0 END) as balance')
                ->value('balance') ?? 0;

            $monthlyIncome = $transactions->where('type', 'income')->sum('amount');
            $monthlyExpenses = $transactions->where('type', 'expense')->sum('amount');

            // Top catégorie de dépenses
            $topCategory = DB::table('transactions')
                ->where('user_id', $user->id)
                ->where('type', 'expense')
                ->where('transaction_date', '>=', $currentMonth)
                ->whereNotNull('category_id')
                ->select('category_id', DB::raw('SUM(amount) as total'))
                ->groupBy('category_id')
                ->orderByDesc('total')
                ->first();

            $topCategoryData = null;
            if ($topCategory) {
                $category = DB::table('categories')
                    ->where('id', $topCategory->category_id)
                    ->first();

                $topCategoryData = [
                    'name' => $category->name ?? 'Non catégorisée',
                    'amount' => round($topCategory->total, 2),
                ];
            }

            $savingsRate = $monthlyIncome > 0
                ? round((($monthlyIncome - $monthlyExpenses) / $monthlyIncome) * 100, 1)
                : 0;

            return [
                'balance' => round($balance, 2),
                'monthly_income' => round($monthlyIncome, 2),
                'monthly_expenses' => round($monthlyExpenses, 2),
                'total_transactions' => DB::table('transactions')->where('user_id', $user->id)->count(),
                'savings_rate' => $savingsRate,
                'top_category_expense' => $topCategoryData,
            ];

        } catch (\Exception $e) {
            Log::error('Erreur getFinancialStats: '.$e->getMessage());

            return [
                'balance' => 0,
                'monthly_income' => 0,
                'monthly_expenses' => 0,
                'total_transactions' => 0,
                'savings_rate' => 0,
                'top_category_expense' => null,
            ];
        }
    }

    /**
     * Statistiques des objectifs
     */
    protected function getGoalStats(User $user): array
    {
        try {
            $goalsData = DB::table('financial_goals')
                ->where('user_id', $user->id)
                ->select(
                    DB::raw('COUNT(*) as total_goals'),
                    DB::raw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_goals'),
                    DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_goals'),
                    DB::raw('SUM(CASE WHEN status = "active" THEN current_amount ELSE 0 END) as total_saved'),
                    DB::raw('SUM(CASE WHEN status = "active" THEN target_amount ELSE 0 END) as total_target')
                )
                ->first();

            $totalSaved = $goalsData->total_saved ?? 0;
            $totalTarget = $goalsData->total_target ?? 0;
            $averageProgress = $totalTarget > 0
                ? round(($totalSaved / $totalTarget) * 100, 1)
                : 0;

            return [
                'total_goals' => $goalsData->total_goals ?? 0,
                'active_goals' => $goalsData->active_goals ?? 0,
                'completed_goals' => $goalsData->completed_goals ?? 0,
                'total_saved' => round($totalSaved, 2),
                'total_target' => round($totalTarget, 2),
                'average_progress' => $averageProgress,
                'goals_on_track' => 0,
            ];

        } catch (\Exception $e) {
            Log::error('Erreur getGoalStats: '.$e->getMessage());

            return [
                'total_goals' => 0,
                'active_goals' => 0,
                'completed_goals' => 0,
                'total_saved' => 0,
                'total_target' => 0,
                'average_progress' => 0,
                'goals_on_track' => 0,
            ];
        }
    }

    /**
     * Statistiques gaming - AVEC VÉRIFICATION D'EXISTENCE DES TABLES
     */
    protected function getGamingStats(User $user): array
    {
        try {
            // Vérifier si la table user_levels existe ET a des données
            $userLevel = DB::table('user_levels')
                ->where('user_id', $user->id)
                ->first();

            // Si pas de données gaming, créer un enregistrement par défaut
            if (! $userLevel) {
                DB::table('user_levels')->insert([
                    'user_id' => $user->id,
                    'level' => 1,
                    'total_xp' => 0,
                    'current_level_xp' => 0,
                    'next_level_xp' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $userLevel = (object) [
                    'level' => 1,
                    'total_xp' => 0,
                    'current_level_xp' => 0,
                    'next_level_xp' => 100,
                ];
            }

            // XP hebdomadaire - vérifier si la table existe
            $weeklyXP = 0;
            try {
                $weekStart = now()->startOfWeek();
                $weeklyXP = DB::table('gaming_actions')
                    ->where('user_id', $user->id)
                    ->where('created_at', '>=', $weekStart)
                    ->sum('xp_earned') ?? 0;
            } catch (\Exception $e) {
                // Table n'existe pas ou est vide, continuer avec 0
            }

            // Achievements count
            $achievementsCount = 0;
            try {
                $achievementsCount = DB::table('user_achievements')
                    ->where('user_id', $user->id)
                    ->where('unlocked', true)
                    ->count();
            } catch (\Exception $e) {
                // Table n'existe pas, continuer avec 0
            }

            // Streaks actifs
            $activeStreaks = 0;
            try {
                $activeStreaks = DB::table('streaks')
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->count();
            } catch (\Exception $e) {
                // Table n'existe pas, continuer avec 0
            }

            return [
                'level' => $userLevel->level ?? 1,
                'total_xp' => $userLevel->total_xp ?? 0,
                'current_level_xp' => $userLevel->current_level_xp ?? 0,
                'next_level_xp' => $userLevel->next_level_xp ?? 100,
                'achievements_count' => $achievementsCount,
                'active_streaks' => $activeStreaks,
                'weekly_xp' => (int) $weeklyXP,
            ];

        } catch (\Exception $e) {
            Log::error('Erreur getGamingStats: '.$e->getMessage());

            // Retourner des valeurs par défaut
            return [
                'level' => 1,
                'total_xp' => 0,
                'current_level_xp' => 0,
                'next_level_xp' => 100,
                'achievements_count' => 0,
                'active_streaks' => 0,
                'weekly_xp' => 0,
            ];
        }
    }

    /**
     * Activité récente
     */
    protected function getRecentActivity(User $user): array
    {
        try {
            $recentTransactions = DB::table('transactions')
                ->where('user_id', $user->id)
                ->orderByDesc('transaction_date')
                ->limit(5)
                ->get()
                ->map(function ($transaction) {
                    $category = null;
                    if ($transaction->category_id) {
                        $cat = DB::table('categories')->where('id', $transaction->category_id)->first();
                        if ($cat) {
                            $category = [
                                'name' => $cat->name,
                                'icon' => $cat->icon ?? '📁',
                                'color' => $cat->color ?? '#666666',
                            ];
                        }
                    }

                    return [
                        'id' => $transaction->id,
                        'description' => $transaction->description,
                        'amount' => $transaction->amount,
                        'type' => $transaction->type,
                        'transaction_date' => $transaction->transaction_date,
                        'category' => $category,
                    ];
                });

            return [
                'recent_transactions' => $recentTransactions,
                'recent_achievements' => [],
                'recent_goal_progress' => [],
            ];

        } catch (\Exception $e) {
            Log::error('Erreur getRecentActivity: '.$e->getMessage());

            return [
                'recent_transactions' => [],
                'recent_achievements' => [],
                'recent_goal_progress' => [],
            ];
        }
    }

    /**
     * Vue d'ensemble
     */
    protected function getOverviewStats(User $user): array
    {
        try {
            $financialStats = $this->getFinancialStats($user);

            $healthScore = 50;
            if ($financialStats['balance'] > 0) {
                $healthScore += 20;
            }
            if ($financialStats['savings_rate'] > 0) {
                $healthScore += 15;
            }
            if ($financialStats['savings_rate'] > 20) {
                $healthScore += 15;
            }

            return [
                'health_score' => min(100, $healthScore),
                'spending_trend' => $financialStats['monthly_expenses'] > $financialStats['monthly_income'] ? 'up' : 'stable',
                'savings_trend' => $financialStats['savings_rate'] > 10 ? 'up' : 'stable',
                'next_goal_deadline' => null,
                'upcoming_milestones' => 0,
            ];

        } catch (\Exception $e) {
            return [
                'health_score' => 50,
                'spending_trend' => 'stable',
                'savings_trend' => 'stable',
                'next_goal_deadline' => null,
                'upcoming_milestones' => 0,
            ];
        }
    }

    /**
     * Dashboard gaming simplifié
     */
    public function getGamingDashboard(User $user): array
    {
        return [
            'stats' => $this->getGamingStats($user),
            'recent_achievements' => [],
            'progress' => [
                'level_progress' => 0,
                'next_level_xp' => 100,
                'weekly_xp' => 0,
            ],
            'leaderboard_position' => 0,
        ];
    }

    /**
     * Suggestions vides pour l'instant
     */
    public function getSuggestions(User $user): array
    {
        return [];
    }

    /**
     * Sync bancaires - stub pour l'instant
     */
    public function syncBankAccounts(User $user): void
    {
        // À implémenter avec Bridge API
        Log::info("Sync bank accounts for user {$user->id}");
    }

    /**
     * Check achievements - stub pour l'instant
     */
    public function checkAchievements(User $user): array
    {
        return [
            'new_achievements' => [],
            'xp_gained' => 0,
        ];
    }

    /**
     * Stats par défaut en cas d'erreur totale
     */
    protected function getDefaultStats(): array
    {
        return [
            'financial' => [
                'balance' => 0,
                'monthly_income' => 0,
                'monthly_expenses' => 0,
                'total_transactions' => 0,
                'savings_rate' => 0,
                'top_category_expense' => null,
            ],
            'goals' => [
                'total_goals' => 0,
                'active_goals' => 0,
                'completed_goals' => 0,
                'total_saved' => 0,
                'total_target' => 0,
                'average_progress' => 0,
                'goals_on_track' => 0,
            ],
            'gaming' => [
                'level' => 1,
                'total_xp' => 0,
                'current_level_xp' => 0,
                'next_level_xp' => 100,
                'achievements_count' => 0,
                'active_streaks' => 0,
                'weekly_xp' => 0,
            ],
            'recent_activity' => [
                'recent_transactions' => [],
                'recent_achievements' => [],
                'recent_goal_progress' => [],
            ],
            'overview' => [
                'health_score' => 50,
                'spending_trend' => 'stable',
                'savings_trend' => 'stable',
                'next_goal_deadline' => null,
                'upcoming_milestones' => 0,
            ],
        ];
    }
}
