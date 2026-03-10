<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use App\Services\BudgetService;
use App\Services\EngagementService;
use App\Services\GamingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FinancialGoalController extends Controller
{
    protected BudgetService $budgetService;
    protected EngagementService $engagementService;

    protected GamingService $gamingService;

    public function __construct(
        BudgetService $budgetService,
        EngagementService $engagementService,
        GamingService $gamingService
    ) {
        $this->budgetService    = $budgetService;
        $this->engagementService = $engagementService;
        $this->gamingService    = $gamingService;
    }

    // ==========================================
    // CRUD PRINCIPAL
    // ==========================================

    /**
     * Lister tous les objectifs de l'utilisateur connecté.
     * FIX: cast des montants en float pour éviter NaN côté frontend.
     */
    public function index(): JsonResponse
    {
        try {
            $goals = Auth::user()
                ->financialGoals()
                ->with('contributions', 'projections')
                ->orderBy('created_at', 'desc')
                ->get();

            $goalsWithProgress = $goals->map(fn ($goal) => $this->formatGoal($goal));

            return response()->json([
                'success' => true,
                'data'    => $goalsWithProgress,
                'meta'    => [
                    'total'     => $goals->count(),
                    'active'    => $goals->where('status', 'active')->count(),
                    'completed' => $goals->where('status', 'completed')->count(),
                    'paused'    => $goals->where('status', 'paused')->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur index goals', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des objectifs',
            ], 500);
        }
    }

    /**
     * Créer un nouvel objectif.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'target_amount'        => 'required|numeric|min:0',
            'target_date'          => 'nullable|date',
            'priority'             => 'nullable',
            'description'          => 'nullable|string|max:1000',
            'type'                 => 'nullable|in:savings,debt_payoff,investment,purchase,emergency_fund,other',
            'color'                => 'nullable|string|max:7',
            'icon'                 => 'nullable|string|max:50',
            'monthly_target'       => 'nullable|numeric|min:0',
            'is_automatic'         => 'nullable|boolean',
            'automatic_amount'     => 'nullable|numeric|min:0',
            'automatic_frequency'  => 'nullable|in:weekly,monthly,quarterly',
            'notes'                => 'nullable|string|max:2000',
            'tags'                 => 'nullable|array',
            'tags.*'               => 'string|max:50',
            'current_amount'       => 'nullable|numeric|min:0',
        ]);

        $goal = $this->budgetService->createGoal(Auth::user(), $data);

        try {
            $this->gamingService->addExperience(
                Auth::user(),
                25,
                'goal_created'
            );
        } catch (\Exception $e) {
            Log::warning('Gaming XP failed (non-bloquant)', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Objectif créé avec succès',
            'data'    => $this->formatGoal(
                $goal->load('contributions', 'projections')
            ),
        ], 201);
    }

    /**
     * Afficher un objectif spécifique.
     */
    public function show(FinancialGoal $financialGoal): JsonResponse
    {
        $this->authorizeAccess($financialGoal);

        return response()->json([
            'success' => true,
            'data'    => $this->formatGoal($financialGoal->load('contributions', 'projections')),
        ]);
    }

    /**
     * Mettre à jour un objectif.
     */
    public function update(
        Request $request,
        FinancialGoal $financialGoal
    ): JsonResponse {
        $this->authorizeAccess($financialGoal);

        $data = $request->validate([
            'name'                 => 'string|max:255',
            'target_amount'        => 'numeric|min:0',
            'target_date'          => 'nullable|date',
            'priority'             => 'integer|min:1|max:5',
            'description'          => 'nullable|string|max:1000',
            'type'                 => 'nullable|in:savings,debt_payoff,investment,purchase,emergency_fund,other',
            'color'                => 'nullable|string|max:7',
            'icon'                 => 'nullable|string|max:50',
            'monthly_target'       => 'nullable|numeric|min:0',
            'is_automatic'         => 'nullable|boolean',
            'automatic_amount'     => 'nullable|numeric|min:0',
            'automatic_frequency'  => 'nullable|in:weekly,monthly,quarterly',
            'notes'                => 'nullable|string|max:2000',
            'tags'                 => 'nullable|array',
            'tags.*'               => 'string|max:50',
            'status'               => 'nullable|in:active,completed,paused,cancelled',
        ]);

        $financialGoal->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Objectif mis à jour avec succès',
            'data'    => $this->formatGoal(
                $financialGoal->fresh()->load('contributions', 'projections')
            ),
        ]);
    }

    /**
     * Supprimer un objectif.
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

    // ==========================================
    // CONTRIBUTIONS
    // ==========================================

    /**
     * Ajouter une contribution à un objectif.
     * ✅ MODIFIÉ: Ajout Gaming XP + check achievements
     */
    public function contribute(
        Request $request,
        FinancialGoal $financialGoal
    ): JsonResponse {
        $this->authorizeAccess($financialGoal);

        $data = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'description'    => 'nullable|string|max:255',
            'transaction_id' => 'nullable|exists:transactions,id',
        ]);

        $user = $request->user();

        // Créer la contribution via BudgetService
        $contribution = $this->budgetService->contributeToGoal(
            $financialGoal,
            $data,
            $user
        );

        // Recharger l'objectif après recalcul
        $freshGoal    = $financialGoal->fresh();
        $goalCompleted = (float) $freshGoal->current_amount
            >= (float) $freshGoal->target_amount;

        // ✅ Gaming: XP via GamingService (déclenche checkAchievements)
        $gamingXp = $goalCompleted ? 200 : 15;
        $gamingSource = $goalCompleted ? 'goal_completed' : 'goal_contribution';

        try {
            $gamingResult = $this->gamingService->addExperience(
                $user,
                $gamingXp,
                $gamingSource
            );

            // Mettre à jour le streak
            $this->gamingService->updateStreak($user, 'daily_activity');
        } catch (\Exception $e) {
            Log::warning('Gaming failed (non-bloquant)', [
                'error' => $e->getMessage(),
            ]);
            $gamingResult = ['leveled_up' => false];
        }

        // Tracker l'engagement (séparé du gaming)
        $engagementResult = $this->safeTrackEngagement(
            $user,
            'goal_contribute',
            'goal_details_page',
            [
                'goal_id'             => $financialGoal->id,
                'contribution_amount' => $contribution->amount,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $goalCompleted
                ? '🎉 Objectif atteint ! Félicitations !'
                : 'Contribution ajoutée (+' . $gamingXp . ' XP)',
            'data'    => [
                'contribution'   => $contribution,
                'goal'           => $this->formatGoal(
                    $freshGoal->load('contributions', 'projections')
                ),
                'goal_completed' => $goalCompleted,
                'engagement'     => [
                    'xp_gained'    => $gamingXp,
                    'leveled_up'   => $gamingResult['leveled_up'] ?? false,
                ],
            ],
        ], 201);
    }

    // ==========================================
    // ROUTES SPÉCIALES
    // ==========================================

    /**
     * Retourner uniquement les objectifs actifs.
     */
    public function active(): JsonResponse
    {
        try {
            $goals = Auth::user()
                ->financialGoals()
                ->where('status', 'active')
                ->with('contributions')
                ->orderByDesc('priority')
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $goals->map(
                    fn ($goal) => $this->formatGoal($goal)
                ),
                'count'   => $goals->count(),
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
     * Supprimer les doublons d'objectifs.
     */
    public function destroyDuplicates(): JsonResponse
    {
        $user  = Auth::user();
        $goals = $user->financialGoals()
            ->orderBy('created_at', 'asc')
            ->get(['id', 'name', 'target_amount', 'created_at']);

        $seen     = [];
        $toDelete = [];

        foreach ($goals as $goal) {
            $key = strtolower(trim($goal->name))
                . '|' . (float) $goal->target_amount;

            if (isset($seen[$key])) {
                $toDelete[] = $goal->id;
            } else {
                $seen[$key] = $goal->id;
            }
        }

        $deletedCount = 0;
        if (! empty($toDelete)) {
            $deletedCount = $user->financialGoals()
                ->whereIn('id', $toDelete)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $deletedCount > 0
                ? "{$deletedCount} doublon(s) supprimé(s)."
                : 'Aucun doublon trouvé.',
            'data'    => [
                'deleted_count' => $deletedCount,
                'deleted_ids'   => $toDelete,
            ],
        ]);
    }

    // ==========================================
    // MÉTHODES PRIVÉES
    // ==========================================

    /**
     * Formater un objectif avec données calculées.
     */
    private function formatGoal(FinancialGoal $goal): array
    {
        $currentAmount = (float) ($goal->current_amount ?? 0);
        $targetAmount  = (float) ($goal->target_amount  ?? 0);

        return [
            'id'                    => $goal->id,
            'name'                  => $goal->name,
            'description'           => $goal->description,
            'target_amount'         => $targetAmount,
            'current_amount'        => $currentAmount,
            'target_date'           => $goal->target_date,
            'priority'              => $goal->priority,
            'type'                  => $goal->type,
            'color'                 => $goal->color,
            'icon'                  => $goal->icon ?? '🎯',
            'status'                => $goal->status,
            'monthly_target'        => (float) ($goal->monthly_target ?? 0),
            'is_automatic'          => (bool) $goal->is_automatic,
            'automatic_amount'      => (float) ($goal->automatic_amount ?? 0),
            'automatic_frequency'   => $goal->automatic_frequency,
            'notes'                 => $goal->notes,
            'tags'                  => $goal->tags ?? [],
            'progress_percentage'   => $this->calculateProgress($goal),
            'is_reached'            => $currentAmount >= $targetAmount,
            'remaining_amount'      => max(0.0, $targetAmount - $currentAmount),
            'days_remaining'        => $this->calculateDaysRemaining($goal),
            'on_track'              => $this->isOnTrack($goal),
            'contributions_count'   => $goal->contributions?->count() ?? 0,
            'total_contributed'     => (float) ($goal->contributions?->sum('amount') ?? 0),
            'last_contribution_date' => $goal->contributions
                ?->sortByDesc('created_at')
                ->first()?->created_at,
            'contributions'         => $goal->relationLoaded('contributions')
                ? $goal->contributions : null,
            'projections'           => $goal->relationLoaded('projections')
                ? $goal->projections : null,
            'created_at'            => $goal->created_at,
            'updated_at'            => $goal->updated_at,
        ];
    }

    /**
     * Calculer le pourcentage de progression (0-100).
     */
    private function calculateProgress(FinancialGoal $goal): int
    {
        $target = (float) $goal->target_amount;
        if ($target <= 0) {
            return 0;
        }

        return min(
            100,
            (int) round(((float) $goal->current_amount / $target) * 100)
        );
    }

    /**
     * Calculer les jours restants avant la date cible.
     */
    private function calculateDaysRemaining(FinancialGoal $goal): ?int
    {
        if (! $goal->target_date) {
            return null;
        }

        return max(
            0,
            (int) Carbon::now()->diffInDays(
                Carbon::parse($goal->target_date),
                false
            )
        );
    }


    /**
     * Vérifier si l'objectif est "on track".
     */
    private function isOnTrack(FinancialGoal $goal): bool
    {
        if (! $goal->target_date
            || (float) $goal->target_amount <= 0) {
            return true;
        }

        $createdAt  = Carbon::parse($goal->created_at);
        $targetDate = Carbon::parse($goal->target_date);
        $now        = Carbon::now();

        $totalDays   = $createdAt->diffInDays($targetDate);
        $elapsedDays = $createdAt->diffInDays($now);

        if ($totalDays <= 0) {
            return (float) $goal->current_amount
                >= (float) $goal->target_amount;
        }

        $expectedProgress = ($elapsedDays / $totalDays) * 100;

        return $this->calculateProgress($goal)
            >= ($expectedProgress - 10);
    }

    /**
     * Tracker l'engagement sans bloquer la réponse en cas d'erreur.
     */
    private function safeTrackEngagement(
        $user,
        string $actionType,
        string $context,
        array $metadata = []
    ): array {
        try {
            return $this->engagementService->trackUserAction(
                $user, $actionType, $context, $metadata
            );
        } catch (\Exception $e) {
            Log::warning('Engagement tracking failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'xp_gained' => 0,
                'total_xp' => 0,
                'current_level' => 1,
                'achievements_unlocked' => [],
            ];
        }
    }

    /**
     * Vérifier que l'objectif appartient à l'utilisateur connecté.
     */
    private function authorizeAccess(FinancialGoal $goal): void
    {
        if ($goal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
    }
}
