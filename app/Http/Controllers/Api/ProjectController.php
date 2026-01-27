<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\GamingService;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProjectController extends Controller
{
    protected BudgetService $budgetService;

    protected ProjectService $projectService;

    protected GamingService $gamingService;

    public function __construct(
        BudgetService $budgetService,
        ProjectService $projectService,
        GamingService $gamingService
    ) {
        $this->budgetService = $budgetService;
        $this->projectService = $projectService;
        $this->gamingService = $gamingService;
    }

    /**
     * Obtenir la liste des templates disponibles
     *
     * @return JsonResponse Templates de projets
     */
    public function getTemplates(): JsonResponse
    {
        try {
            $templates = $this->projectService->getAvailableTemplates();
            $popularProjects = $this->projectService->getPopularProjects();

            return response()->json([
                'success' => true,
                'data' => [
                    'templates' => $templates,
                    'popular' => $popularProjects,
                    'categories' => $this->getTemplateCategories(),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la récupération des templates');
        }
    }

    /**
     * Créer un nouveau projet basé sur un template
     *
     * @param  CreateProjectRequest  $request  Données validées
     * @return JsonResponse Projet créé
     */
    public function createFromTemplate(CreateProjectRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $project = $this->projectService->createProjectFromTemplate(
                $user,
                $request->template_type,
                $request->validated()
            );

            // Invalider le cache du dashboard
            $this->clearUserCache($user);

            return response()->json([
                'success' => true,
                'message' => 'Projet créé avec succès !',
                'data' => new ProjectResource($project),
                'gaming' => [
                    'xp_gained' => 50,
                    'new_level' => $user->getCurrentLevel(),
                    'achievements_unlocked' => $this->gamingService->checkAchievements($user),
                ],
            ], 201);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la création du projet');
        }
    }

    /**
     * Ajouter une transaction à un projet
     *
     * @param  CreateTransactionRequest  $request  Données validées
     * @return JsonResponse Transaction créée
     */
    public function addTransaction(CreateTransactionRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $transaction = $this->budgetService->createTransaction(
                $user,
                $request->validated()
            );

            // Si c'est une contribution à un objectif
            if ($request->has('financial_goal_id')) {
                $goal = FinancialGoal::findOrFail($request->financial_goal_id);

                $contribution = $this->budgetService->createGoalContribution(
                    $user,
                    $goal,
                    [
                        'amount' => $request->amount,
                        'date' => $request->transaction_date,
                        'transaction_id' => $transaction->id,
                        'description' => $request->description,
                    ]
                );
            }

            $this->clearUserCache($user);

            return response()->json([
                'success' => true,
                'message' => 'Transaction ajoutée avec succès !',
                'data' => [
                    'transaction' => $transaction->load('category'),
                    'contribution' => $contribution ?? null,
                ],
                'gaming' => [
                    'xp_gained' => $this->calculateTransactionXp($transaction),
                    'streak_updated' => true,
                    'achievements_unlocked' => $this->gamingService->checkAchievements($user),
                ],
            ], 201);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de l\'ajout de la transaction');
        }
    }

    /**
     * Obtenir le tableau de bord complet
     *
     * @param  Request  $request  Paramètres de requête
     * @return JsonResponse Dashboard complet
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $month = $request->has('month') ?
                \Carbon\Carbon::parse($request->month) :
                now();

            $dashboard = Cache::remember(
                "dashboard_{$user->id}_{$month->format('Y-m')}",
                300,
                function () use ($user, $month) {
                    return [
                        'budget' => $this->budgetService->getBudgetStats($user, $month),
                        'gaming' => $this->gamingService->getDashboard($user),
                        'projects' => $this->getUserProjects($user),
                        'suggestions' => $this->getPersonalizedSuggestions($user),
                        'quick_actions' => $this->getQuickActions($user),
                    ];
                }
            );

            return response()->json([
                'success' => true,
                'data' => new DashboardResource($dashboard),
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la récupération du dashboard');
        }
    }

    /**
     * Analyser les habitudes de l'utilisateur
     *
     * @param  Request  $request  Paramètres d'analyse
     * @return JsonResponse Analyse des habitudes
     */
    public function analyzeHabits(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $months = $request->get('months', 6);

            $analysis = $this->budgetService->analyzeSpendingHabits($user, $months);

            // Ajouter des insights gaming
            $analysis['gaming_insights'] = [
                'level_progression' => $this->analyzeGamingProgression($user),
                'achievement_progress' => $this->analyzeAchievementProgress($user),
                'recommendations' => $this->generateGamingRecommendations($user),
            ];

            return response()->json([
                'success' => true,
                'data' => $analysis,
                'period' => [
                    'months_analyzed' => $months,
                    'from' => now()->subMonths($months)->format('Y-m-d'),
                    'to' => now()->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de l\'analyse des habitudes');
        }
    }

    /**
     * Obtenir les projets de l'utilisateur
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Projets de l'utilisateur
     */
    protected function getUserProjects($user): array
    {
        $goals = $user->financialGoals()
            ->with(['contributions' => function ($query) {
                $query->orderBy('date', 'desc')->limit(5);
            }])
            ->active()
            ->orderBy('priority')
            ->get();

        return [
            'active_count' => $goals->count(),
            'total_target' => $goals->sum('target_amount'),
            'total_saved' => $goals->sum('current_amount'),
            'average_progress' => $goals->avg('progress_percentage'),
            'upcoming_deadlines' => $goals->filter(function ($goal) {
                return $goal->target_date &&
                    $goal->target_date->diffInDays(now()) <= 30;
            })->values(),
            'recent_contributions' => $goals->flatMap->contributions->take(10),
        ];
    }

    /**
     * Obtenir les suggestions personnalisées
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Suggestions personnalisées
     */
    protected function getPersonalizedSuggestions($user): array
    {
        return [
            'budget_optimization' => $this->generateBudgetSuggestions($user),
            'goal_recommendations' => $this->generateGoalSuggestions($user),
            'gaming_challenges' => $this->generateGamingChallenges($user),
        ];
    }

    /**
     * Obtenir les actions rapides
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Actions rapides disponibles
     */
    protected function getQuickActions($user): array
    {
        return [
            'add_transaction' => [
                'enabled' => true,
                'most_used_categories' => $user->getTopCategories(3),
            ],
            'contribute_to_goal' => [
                'enabled' => $user->financialGoals()->active()->exists(),
                'suggested_goals' => $user->financialGoals()
                    ->active()
                    ->orderBy('priority')
                    ->limit(3)
                    ->get(),
            ],
            'create_project' => [
                'enabled' => true,
                'suggested_templates' => collect($this->projectService->getPopularProjects())
                    ->take(3)
                    ->pluck('key'),
            ],
        ];
    }

    /**
     * Générer des suggestions budgétaires
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Suggestions budgétaires
     */
    protected function generateBudgetSuggestions($user): array
    {
        $monthlyStats = $this->budgetService->getBudgetStats($user);
        $suggestions = [];

        // Suggestion d'épargne
        if ($monthlyStats['monthly']['balance'] > 0) {
            $suggestions[] = [
                'type' => 'save_more',
                'title' => 'Optimisez votre épargne',
                'message' => "Vous avez un surplus de {$monthlyStats['monthly']['balance']}€ ce mois-ci",
                'action' => 'create_savings_goal',
            ];
        }

        return $suggestions;
    }

    /**
     * Générer des suggestions d'objectifs
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Suggestions d'objectifs
     */
    protected function generateGoalSuggestions($user): array
    {
        $suggestions = [];

        // Si aucun fonds d'urgence
        if (! $user->financialGoals()->where('type', 'emergency_fund')->exists()) {
            $suggestions[] = [
                'type' => 'emergency_fund',
                'template' => 'emergency_fund',
                'title' => 'Créez votre fonds d\'urgence',
                'priority' => 'high',
            ];
        }

        return $suggestions;
    }

    /**
     * Générer des défis gaming
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Défis gaming
     */
    protected function generateGamingChallenges($user): array
    {
        return [
            'daily_transaction' => [
                'title' => 'Série quotidienne',
                'description' => 'Enregistrez une transaction chaque jour',
                'current_streak' => $user->streaks()
                    ->where('type', 'daily_transaction')
                    ->first()?->current_count ?? 0,
                'target' => 7,
            ],
        ];
    }

    /**
     * Analyser la progression gaming
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Analyse gaming
     */
    protected function analyzeGamingProgression($user): array
    {
        $level = $user->level;

        return [
            'current_level' => $level?->level ?? 1,
            'xp_this_month' => $this->getMonthlyXp($user),
            'projected_next_level' => $this->projectNextLevel($user),
            'level_up_prediction' => $this->predictLevelUp($user),
        ];
    }

    /**
     * Analyser le progrès des succès
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Analyse des succès
     */
    protected function analyzeAchievementProgress($user): array
    {
        $totalAchievements = \App\Models\Achievement::active()->count();
        $unlockedAchievements = $user->achievements()->count();

        return [
            'completion_rate' => $totalAchievements > 0 ?
                ($unlockedAchievements / $totalAchievements) * 100 : 0,
            'unlocked_count' => $unlockedAchievements,
            'total_count' => $totalAchievements,
            'recent_achievements' => $user->getRecentAchievements(3),
        ];
    }

    /**
     * Générer des recommandations gaming
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Recommandations
     */
    protected function generateGamingRecommendations($user): array
    {
        return [
            'focus_areas' => ['Régularité des transactions', 'Épargne mensuelle'],
            'next_achievements' => \App\Models\Achievement::active()
                ->whereNotIn('id', $user->achievements()->pluck('achievement_id'))
                ->limit(3)
                ->get(),
            'streak_opportunities' => ['daily_transaction', 'weekly_goal'],
        ];
    }

    /**
     * Obtenir l'XP gagné ce mois
     *
     * @param  User  $user  Utilisateur concerné
     * @return int XP du mois
     */
    protected function getMonthlyXp($user): int
    {
        // À implémenter : calculer l'XP gagné ce mois
        return 0; // Placeholder
    }

    /**
     * Prédire le prochain niveau
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Prédiction
     */
    protected function projectNextLevel($user): array
    {
        // À implémenter : projection du prochain niveau
        return ['level' => $user->getCurrentLevel() + 1, 'eta_days' => 30];
    }

    /**
     * Prédire la montée de niveau
     *
     * @param  User  $user  Utilisateur concerné
     * @return string Prédiction
     */
    protected function predictLevelUp($user): string
    {
        return 'Dans environ 2 semaines'; // Placeholder
    }

    /**
     * Calculer l'XP d'une transaction
     *
     * @param  Transaction  $transaction  Transaction concernée
     * @return int XP calculé
     */
    protected function calculateTransactionXp(Transaction $transaction): int
    {
        return 5 + min(50, floor($transaction->amount / 100));
    }

    /**
     * Obtenir les catégories de templates
     *
     * @return array Catégories
     */
    protected function getTemplateCategories(): array
    {
        return [
            'popular' => ['travel', 'emergency_fund', 'car'],
            'long_term' => ['real_estate', 'investment', 'education'],
            'lifestyle' => ['event', 'home_improvement'],
            'business' => ['business', 'debt_payoff'],
        ];
    }

    /**
     * Vider le cache utilisateur
     *
     * @param  User  $user  Utilisateur concerné
     */
    protected function clearUserCache($user): void
    {
        Cache::forget("dashboard_{$user->id}_".now()->format('Y-m'));
        Cache::forget("gaming_dashboard_{$user->id}");
        Cache::forget("budget_stats_{$user->id}_".now()->format('Y-m'));
    }

    /**
     * Gérer les erreurs de manière centralisée
     *
     * @param  \Exception  $exception  Exception à traiter
     * @param  string  $message  Message d'erreur par défaut
     * @return JsonResponse Réponse d'erreur
     */
    protected function handleError(\Exception $exception, string $message): JsonResponse
    {
        \Log::error($message, [
            'exception' => $exception->getMessage(),
            'user_id' => auth()->id(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $statusCode = method_exists($exception, 'getStatusCode') ?
            $exception->getStatusCode() : 500;

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => config('app.debug') ? $exception->getMessage() : null,
        ], $statusCode);
    }
}
