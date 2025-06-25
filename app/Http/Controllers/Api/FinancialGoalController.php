<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use App\Services\BudgetService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialGoalController extends Controller
{
    use ApiResponseTrait;

    protected BudgetService $budgetService;

    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    /**
     * Display a listing of financial goals with pagination and filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Auth::user()->financialGoals()->with(['contributions']);

        // Filtres spécifiques aux objectifs
        $this->applyGoalFilters($query, $request);

        // Recherche et pagination
        $searchColumns = ['name', 'description'];
        $goals = $this->applyPaginationAndFilters($query, $request, $searchColumns);

        // Enrichir les données avec les calculs
        $enrichedGoals = collect($goals->items())->map(function ($goal) {
            return $this->enrichGoalData($goal);
        });

        return response()->json([
            'success' => true,
            'message' => 'Objectifs financiers récupérés avec succès',
            'data' => $enrichedGoals,
            'pagination' => [
                'current_page' => $goals->currentPage(),
                'per_page' => $goals->perPage(),
                'total' => $goals->total(),
                'last_page' => $goals->lastPage(),
                'from' => $goals->firstItem(),
                'to' => $goals->lastItem(),
                'has_more_pages' => $goals->hasMorePages()
            ],
            'summary' => $this->getGoalsSummary($request)
        ]);
    }

    /**
     * Store a newly created financial goal
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_amount' => 'required|numeric|min:1',
            'target_date' => 'required|date|after:today',
            'category' => 'nullable|string|max:100',
            'priority' => 'required|in:low,medium,high',
            'is_public' => 'boolean',
            'auto_contribution' => 'boolean',
            'auto_contribution_amount' => 'nullable|numeric|min:0',
            'auto_contribution_frequency' => 'nullable|in:weekly,monthly',
        ]);

        $data['user_id'] = Auth::id();
        $data['current_amount'] = 0;
        $data['status'] = 'active';
        $data['is_public'] = $data['is_public'] ?? false;
        $data['auto_contribution'] = $data['auto_contribution'] ?? false;

        try {
            DB::beginTransaction();

            $goal = FinancialGoal::create($data);

            // Déclencher événement gaming
            event(new \App\Events\GoalCreated(Auth::user(), $goal));

            DB::commit();

            return $this->createdResponse(
                $this->enrichGoalData($goal),
                'Objectif financier créé avec succès'
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Erreur lors de la création de l\'objectif: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display the specified financial goal
     *
     * @param FinancialGoal $financialGoal
     * @return JsonResponse
     */
    public function show(FinancialGoal $financialGoal): JsonResponse
    {
        if (!$this->userOwnsGoal($financialGoal)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cet objectif');
        }

        $financialGoal->load([
            'contributions' => function ($query) {
                $query->latest()->limit(10);
            }
        ]);

        $goalData = $this->enrichGoalData($financialGoal);

        // Ajouter des statistiques détaillées
        $goalData['detailed_stats'] = [
            'total_contributions' => $financialGoal->contributions()->count(),
            'average_contribution' => round($financialGoal->contributions()->avg('amount'), 2),
            'largest_contribution' => $financialGoal->contributions()->max('amount'),
            'days_since_last_contribution' => $financialGoal->contributions()->latest()->value('date')
                ? Carbon::parse($financialGoal->contributions()->latest()->value('date'))->diffInDays(now())
                : null,
            'monthly_progress' => $this->getMonthlyProgress($financialGoal),
            'projected_completion' => $this->calculateProjectedCompletion($financialGoal)
        ];

        return $this->successResponse($goalData, 'Objectif financier récupéré avec succès');
    }

    /**
     * Update the specified financial goal
     *
     * @param Request $request
     * @param FinancialGoal $financialGoal
     * @return JsonResponse
     */
    public function update(Request $request, FinancialGoal $financialGoal): JsonResponse
    {
        if (!$this->userOwnsGoal($financialGoal)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cet objectif');
        }

        // Ne pas permettre la modification si l'objectif est complété
        if ($financialGoal->status === 'completed') {
            return $this->errorResponse('Impossible de modifier un objectif déjà complété');
        }

        $data = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_amount' => 'numeric|min:1',
            'target_date' => 'date|after:today',
            'category' => 'nullable|string|max:100',
            'priority' => 'in:low,medium,high',
            'is_public' => 'boolean',
            'auto_contribution' => 'boolean',
            'auto_contribution_amount' => 'nullable|numeric|min:0',
            'auto_contribution_frequency' => 'nullable|in:weekly,monthly',
        ]);

        // Vérifier si le montant cible est inférieur au montant actuel
        if (isset($data['target_amount']) && $data['target_amount'] < $financialGoal->current_amount) {
            return $this->errorResponse('Le montant cible ne peut pas être inférieur au montant actuel');
        }

        try {
            DB::beginTransaction();

            $financialGoal->update($data);

            // Recalculer le statut si nécessaire
            $financialGoal->recalculateCurrentAmount();

            DB::commit();

            return $this->updatedResponse(
                $this->enrichGoalData($financialGoal),
                'Objectif financier mis à jour avec succès'
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Erreur lors de la mise à jour: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified financial goal
     *
     * @param FinancialGoal $financialGoal
     * @return JsonResponse
     */
    public function destroy(FinancialGoal $financialGoal): JsonResponse
    {
        if (!$this->userOwnsGoal($financialGoal)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cet objectif');
        }

        try {
            DB::beginTransaction();

            // Supprimer les contributions associées
            $financialGoal->contributions()->delete();

            $financialGoal->delete();

            DB::commit();

            return $this->deletedResponse('Objectif financier supprimé avec succès');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Erreur lors de la suppression: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Add a contribution to a financial goal
     *
     * @param Request $request
     * @param FinancialGoal $financialGoal
     * @return JsonResponse
     */
    public function addContribution(Request $request, FinancialGoal $financialGoal): JsonResponse
    {
        if (!$this->userOwnsGoal($financialGoal)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cet objectif');
        }

        if ($financialGoal->status === 'completed') {
            return $this->errorResponse('Impossible d\'ajouter une contribution à un objectif déjà complété');
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'transaction_id' => 'nullable|exists:transactions,id'
        ]);

        $data['date'] = $data['date'] ?? now();

        try {
            $contribution = $this->budgetService->createGoalContribution(
                Auth::user(),
                $financialGoal,
                $data
            );

            return $this->createdResponse($contribution, 'Contribution ajoutée avec succès');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Erreur lors de l\'ajout de la contribution: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get goals statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = Auth::user();

        $stats = [
            'total_goals' => $user->financialGoals()->count(),
            'active_goals' => $user->financialGoals()->where('status', 'active')->count(),
            'completed_goals' => $user->financialGoals()->where('status', 'completed')->count(),
            'paused_goals' => $user->financialGoals()->where('status', 'paused')->count(),
            'total_target_amount' => $user->financialGoals()->sum('target_amount'),
            'total_saved_amount' => $user->financialGoals()->sum('current_amount'),
            'completion_rate' => $this->calculateCompletionRate($user),
            'goals_by_priority' => $user->financialGoals()
                ->selectRaw('priority, COUNT(*) as count, SUM(current_amount) as saved, SUM(target_amount) as target')
                ->groupBy('priority')
                ->get(),
            'goals_by_category' => $user->financialGoals()
                ->selectRaw('category, COUNT(*) as count, SUM(current_amount) as saved, SUM(target_amount) as target')
                ->whereNotNull('category')
                ->groupBy('category')
                ->get(),
            'monthly_contributions' => $user->financialGoals()
                ->with(['contributions' => function ($query) {
                    $query->selectRaw('financial_goal_id, YEAR(date) as year, MONTH(date) as month, SUM(amount) as total')
                        ->groupBy('financial_goal_id', 'year', 'month')
                        ->orderBy('year', 'desc')
                        ->orderBy('month', 'desc')
                        ->limit(12);
                }])
                ->get()
                ->pluck('contributions')
                ->flatten()
                ->groupBy(function ($item) {
                    return $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                })
                ->map(function ($contributions) {
                    return $contributions->sum('total');
                })
        ];

        return $this->successResponse($stats, 'Statistiques des objectifs récupérées avec succès');
    }

    /**
     * Pause or resume a goal
     *
     * @param FinancialGoal $financialGoal
     * @return JsonResponse
     */
    public function toggleStatus(FinancialGoal $financialGoal): JsonResponse
    {
        if (!$this->userOwnsGoal($financialGoal)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cet objectif');
        }

        if ($financialGoal->status === 'completed') {
            return $this->errorResponse('Impossible de modifier le statut d\'un objectif complété');
        }

        $newStatus = $financialGoal->status === 'active' ? 'paused' : 'active';
        $financialGoal->update(['status' => $newStatus]);

        $message = $newStatus === 'active' ? 'Objectif reactivé' : 'Objectif mis en pause';

        return $this->updatedResponse($financialGoal, $message);
    }

    /**
     * Apply goal-specific filters
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     */
    private function applyGoalFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('target_date_from')) {
            $query->whereDate('target_date', '>=', $request->target_date_from);
        }

        if ($request->filled('target_date_to')) {
            $query->whereDate('target_date', '<=', $request->target_date_to);
        }

        if ($request->filled('min_target_amount')) {
            $query->where('target_amount', '>=', $request->min_target_amount);
        }

        if ($request->filled('max_target_amount')) {
            $query->where('target_amount', '<=', $request->max_target_amount);
        }

        if ($request->filled('completion_status')) {
            switch ($request->completion_status) {
                case 'not_started':
                    $query->where('current_amount', 0);
                    break;
                case 'in_progress':
                    $query->where('current_amount', '>', 0)
                        ->whereColumn('current_amount', '<', 'target_amount');
                    break;
                case 'completed':
                    $query->whereColumn('current_amount', '>=', 'target_amount');
                    break;
            }
        }
    }

    /**
     * Enrich goal data with calculated fields
     *
     * @param FinancialGoal $goal
     * @return array
     */
    private function enrichGoalData(FinancialGoal $goal): array
    {
        $goalArray = $goal->toArray();

        $goalArray['progress_percentage'] = $goal->getProgressPercentage();
        $goalArray['remaining_amount'] = $goal->target_amount - $goal->current_amount;
        $goalArray['days_remaining'] = Carbon::parse($goal->target_date)->diffInDays(now(), false);
        $goalArray['is_overdue'] = Carbon::parse($goal->target_date)->isPast();
        $goalArray['estimated_monthly_saving_needed'] = $this->calculateMonthlySavingNeeded($goal);
        $goalArray['is_on_track'] = $this->isGoalOnTrack($goal);

        return $goalArray;
    }

    /**
     * Get summary statistics for goals
     *
     * @param Request $request
     * @return array
     */
    private function getGoalsSummary(Request $request): array
    {
        $user = Auth::user();
        $query = $user->financialGoals();

        $this->applyGoalFilters($query, $request);

        return [
            'total_count' => $query->count(),
            'total_target_amount' => $query->sum('target_amount'),
            'total_current_amount' => $query->sum('current_amount'),
            'average_progress' => round($query->get()->avg(function ($goal) {
                return $goal->getProgressPercentage();
            }), 2),
            'goals_by_status' => $query->groupBy('status')
                ->selectRaw('status, COUNT(*) as count')
                ->pluck('count', 'status'),
        ];
    }

    /**
     * Calculate monthly saving needed to reach goal
     *
     * @param FinancialGoal $goal
     * @return float
     */
    private function calculateMonthlySavingNeeded(FinancialGoal $goal): float
    {
        $remaining = $goal->target_amount - $goal->current_amount;
        $monthsRemaining = max(1, Carbon::parse($goal->target_date)->diffInMonths(now()));

        return round($remaining / $monthsRemaining, 2);
    }

    /**
     * Check if goal is on track
     *
     * @param FinancialGoal $goal
     * @return bool
     */
    private function isGoalOnTrack(FinancialGoal $goal): bool
    {
        $totalDays = Carbon::parse($goal->created_at)->diffInDays(Carbon::parse($goal->target_date));
        $daysPassed = Carbon::parse($goal->created_at)->diffInDays(now());

        if ($totalDays <= 0) return true;

        $expectedProgress = ($daysPassed / $totalDays) * 100;
        $actualProgress = $goal->getProgressPercentage();

        return $actualProgress >= $expectedProgress * 0.9; // 10% de tolérance
    }

    /**
     * Get monthly progress for a goal
     *
     * @param FinancialGoal $goal
     * @return \Illuminate\Support\Collection
     */
    private function getMonthlyProgress(FinancialGoal $goal)
    {
        return $goal->contributions()
            ->selectRaw('YEAR(date) as year, MONTH(date) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();
    }

    /**
     * Calculate projected completion date
     *
     * @param FinancialGoal $goal
     * @return array|null
     */
    private function calculateProjectedCompletion(FinancialGoal $goal): ?array
    {
        $recentContributions = $goal->contributions()
            ->where('date', '>=', now()->subMonths(3))
            ->avg('amount');

        if (!$recentContributions || $recentContributions <= 0) {
            return null;
        }

        $remaining = $goal->target_amount - $goal->current_amount;
        $monthsNeeded = ceil($remaining / $recentContributions);

        return [
            'estimated_date' => now()->addMonths($monthsNeeded)->format('Y-m-d'),
            'months_needed' => $monthsNeeded,
            'based_on_average' => round($recentContributions, 2)
        ];
    }

    /**
     * Calculate overall completion rate for user
     *
     * @param \App\Models\User $user
     * @return float
     */
    private function calculateCompletionRate($user): float
    {
        $totalGoals = $user->financialGoals()->count();

        if ($totalGoals === 0) return 0;

        $completedGoals = $user->financialGoals()->where('status', 'completed')->count();

        return round(($completedGoals / $totalGoals) * 100, 2);
    }

    /**
     * Check if the authenticated user owns the goal
     *
     * @param FinancialGoal $goal
     * @return bool
     */
    private function userOwnsGoal(FinancialGoal $goal): bool
    {
        return $goal->user_id === Auth::id();
    }
}
