<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use App\Services\BudgetService;
use App\Services\EngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialGoalController extends Controller
{
    protected BudgetService $budgetService;

    protected EngagementService $engagementService;

    public function __construct(
        BudgetService $budgetService,
        EngagementService $engagementService
    ) {
        $this->budgetService = $budgetService;
        $this->engagementService = $engagementService;
    }

    /**
     * Display a listing of the resource.
     *
     * ✅ CORRIGÉ : Retourne un format JSON standardisé
     */
    public function index(): JsonResponse
    {
        $goals = Auth::user()
            ->financialGoals()
            ->with('contributions', 'projections')
            ->orderBy('created_at', 'desc')
            ->get();

        // ✅ Calculer les données enrichies pour chaque objectif
        $goalsWithProgress = $goals->map(function ($goal) {
            return [
                'id' => $goal->id,
                'name' => $goal->name,
                'description' => $goal->description,
                'target_amount' => $goal->target_amount,
                'current_amount' => $goal->current_amount,
                'target_date' => $goal->target_date,
                'priority' => $goal->priority,
                'type' => $goal->type,
                'color' => $goal->color,
                'icon' => $goal->icon,
                'status' => $goal->status,
                'monthly_target' => $goal->monthly_target,
                'is_automatic' => $goal->is_automatic,
                'automatic_amount' => $goal->automatic_amount,
                'automatic_frequency' => $goal->automatic_frequency,
                'notes' => $goal->notes,
                'tags' => $goal->tags,

                // ✅ Données calculées
                'progress_percentage' => $this->calculateProgress($goal),
                'is_reached' => $goal->current_amount >= $goal->target_amount,
                'remaining_amount' => max(0, $goal->target_amount - $goal->current_amount),
                'days_remaining' => $this->calculateDaysRemaining($goal),
                'on_track' => $this->isOnTrack($goal),

                // ✅ Relations
                'contributions_count' => $goal->contributions->count(),
                'total_contributed' => $goal->contributions->sum('amount'),
                'last_contribution_date' => $goal->contributions->sortByDesc('created_at')->first()?->created_at,

                'created_at' => $goal->created_at,
                'updated_at' => $goal->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $goalsWithProgress,
            'meta' => [
                'total' => $goals->count(),
                'active' => $goals->where('status', 'active')->count(),
                'completed' => $goals->where('status', 'completed')->count(),
                'paused' => $goals->where('status', 'paused')->count(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:0',
            'target_date' => 'nullable|date',
            'priority' => 'nullable|integer|min:1|max:5',
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|in:savings,debt_payoff,investment,purchase,emergency_fund,other',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'monthly_target' => 'nullable|numeric|min:0',
            'is_automatic' => 'nullable|boolean',
            'automatic_amount' => 'nullable|numeric|min:0',
            'automatic_frequency' => 'nullable|in:weekly,monthly,quarterly',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $user = Auth::user();

        // Créer l'objectif via BudgetService
        $goal = $this->budgetService->createGoal($user, $data);

        // Tracker l'engagement pour création d'objectif
        $engagementResult = $this->engagementService->trackUserAction(
            $user,
            'goal_create',
            'goals_page',
            [
                'goal_id' => $goal->id,
                'target_amount' => $goal->target_amount,
                'type' => $goal->type,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'goal' => $goal->load('contributions', 'projections'),
                'engagement' => [
                    'xp_gained' => $engagementResult['xp_gained'],
                    'total_xp' => $engagementResult['total_xp'],
                    'current_level' => $engagementResult['current_level'],
                    'achievements_unlocked' => $engagementResult['achievements_unlocked'] ?? [],
                ],
            ],
            'message' => 'Objectif créé avec succès ! (+'.$engagementResult['xp_gained'].' XP)',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(FinancialGoal $financialGoal): JsonResponse
    {
        $this->authorizeAccess($financialGoal);

        $goal = $financialGoal->load('contributions', 'projections');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $goal->id,
                'name' => $goal->name,
                'description' => $goal->description,
                'target_amount' => $goal->target_amount,
                'current_amount' => $goal->current_amount,
                'target_date' => $goal->target_date,
                'priority' => $goal->priority,
                'type' => $goal->type,
                'color' => $goal->color,
                'icon' => $goal->icon,
                'status' => $goal->status,
                'monthly_target' => $goal->monthly_target,
                'progress_percentage' => $this->calculateProgress($goal),
                'is_reached' => $goal->current_amount >= $goal->target_amount,
                'remaining_amount' => max(0, $goal->target_amount - $goal->current_amount),
                'days_remaining' => $this->calculateDaysRemaining($goal),
                'on_track' => $this->isOnTrack($goal),
                'contributions' => $goal->contributions,
                'projections' => $goal->projections,
                'created_at' => $goal->created_at,
                'updated_at' => $goal->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FinancialGoal $financialGoal): JsonResponse
    {
        $this->authorizeAccess($financialGoal);

        $data = $request->validate([
            'name' => 'string|max:255',
            'target_amount' => 'numeric|min:0',
            'target_date' => 'nullable|date',
            'priority' => 'integer|min:1|max:5',
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|in:savings,debt_payoff,investment,purchase,emergency_fund,other',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'monthly_target' => 'nullable|numeric|min:0',
            'is_automatic' => 'nullable|boolean',
            'automatic_amount' => 'nullable|numeric|min:0',
            'automatic_frequency' => 'nullable|in:weekly,monthly,quarterly',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'status' => 'nullable|in:active,completed,paused,cancelled',
        ]);

        $financialGoal->update($data);

        return response()->json([
            'success' => true,
            'data' => $financialGoal->fresh()->load('contributions', 'projections'),
            'message' => 'Objectif mis à jour avec succès',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialGoal $financialGoal): JsonResponse
    {
        $this->authorizeAccess($financialGoal);

        $goalName = $financialGoal->name;
        $financialGoal->delete();

        return response()->json([
            'success' => true,
            'message' => "Objectif \"$goalName\" supprimé avec succès",
        ]);
    }

    /**
     * Contribuer à un objectif avec tracking
     */
    public function contribute(Request $request, FinancialGoal $financialGoal): JsonResponse
    {
        $this->authorizeAccess($financialGoal);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'transaction_id' => 'nullable|exists:transactions,id',
        ]);

        $user = $request->user();

        // Créer la contribution
        $contribution = $this->budgetService->contributeToGoal($financialGoal, $data);

        // Tracker l'engagement
        $engagementResult = $this->engagementService->trackUserAction(
            $user,
            'goal_contribute',
            'goal_details_page',
            [
                'goal_id' => $financialGoal->id,
                'contribution_amount' => $contribution->amount,
                'goal_progress' => $financialGoal->fresh()->progress_percentage,
            ]
        );

        // Vérifier si l'objectif est maintenant complété
        $goalCompleted = $financialGoal->fresh()->current_amount >= $financialGoal->target_amount;

        return response()->json([
            'success' => true,
            'data' => [
                'contribution' => $contribution,
                'goal' => $financialGoal->fresh()->load('contributions', 'projections'),
                'goal_completed' => $goalCompleted,
                'engagement' => [
                    'xp_gained' => $engagementResult['xp_gained'],
                    'total_xp' => $engagementResult['total_xp'],
                    'current_level' => $engagementResult['current_level'],
                    'achievements_unlocked' => $engagementResult['achievements_unlocked'] ?? [],
                ],
            ],
            'message' => $goalCompleted
                ? '🎉 Objectif atteint ! Félicitations !'
                : 'Contribution ajoutée (+'.$engagementResult['xp_gained'].' XP)',
        ]);
    }

    // ==========================================
    // MÉTHODES PRIVÉES UTILITAIRES
    // ==========================================

    /**
     * Calculer le pourcentage de progression
     */
    private function calculateProgress(FinancialGoal $goal): int
    {
        if ($goal->target_amount <= 0) {
            return 0;
        }

        $percentage = ($goal->current_amount / $goal->target_amount) * 100;

        return min(100, (int) round($percentage));
    }

    /**
     * Calculer les jours restants
     */
    private function calculateDaysRemaining(FinancialGoal $goal): ?int
    {
        if (! $goal->target_date) {
            return null;
        }

        $targetDate = \Carbon\Carbon::parse($goal->target_date);
        $now = \Carbon\Carbon::now();

        return max(0, $now->diffInDays($targetDate, false));
    }

    /**
     * Vérifier si l'objectif est "on track"
     */
    private function isOnTrack(FinancialGoal $goal): bool
    {
        if (! $goal->target_date || $goal->target_amount <= 0) {
            return true;
        }

        $targetDate = \Carbon\Carbon::parse($goal->target_date);
        $now = \Carbon\Carbon::now();
        $totalDays = \Carbon\Carbon::parse($goal->created_at)->diffInDays($targetDate);
        $elapsedDays = \Carbon\Carbon::parse($goal->created_at)->diffInDays($now);

        if ($totalDays <= 0) {
            return $goal->current_amount >= $goal->target_amount;
        }

        $expectedProgress = ($elapsedDays / $totalDays) * 100;
        $actualProgress = $this->calculateProgress($goal);

        // On track si dans les 10% de marge
        return $actualProgress >= ($expectedProgress - 10);
    }

    /**
     * ✅ NOUVEAU: Retourne uniquement les objectifs actifs
     * Route: GET /api/goals/active
     */
    public function active(Request $request): JsonResponse
    {
        try {
            $goals = Auth::user()
                ->financialGoals()
                ->where('status', 'active')
                ->with('contributions')
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            $goalsFormatted = $goals->map(function ($goal) {
                return [
                    'id' => $goal->id,
                    'name' => $goal->name,
                    'target_amount' => $goal->target_amount,
                    'current_amount' => $goal->current_amount,
                    'deadline' => $goal->target_date,
                    'priority' => $goal->priority,
                    'status' => $goal->status,
                    'category' => $goal->type,
                    'icon' => $goal->icon ?? '🎯',
                    'progress_percentage' => $goal->target_amount > 0 ?
                        round(($goal->current_amount / $goal->target_amount) * 100, 1) : 0,
                    'estimated_completion_date' => $goal->target_date,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $goalsFormatted,
                'count' => $goals->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur goals actifs', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur chargement objectifs actifs',
            ], 500);
        }
    }

    /**
     * Vérifier l'autorisation d'accès à un objectif
     */
    private function authorizeAccess(FinancialGoal $goal): void
    {
        if ($goal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
    }
}
