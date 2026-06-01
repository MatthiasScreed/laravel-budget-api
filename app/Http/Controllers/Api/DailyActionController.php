<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyAction;
use App\Models\Quest;
use App\Services\GamingService;
use App\Services\StreakService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DailyActionController — Cœur du MVP.
 * Enregistre une économie ou dépense en < 30 secondes.
 * Déclenche : XP + streak + mise à jour quête.
 */
class DailyActionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private GamingService $gamingService,
        private StreakService  $streakService,
    ) {}

    // ==========================================
    // STORE — ACTION PRINCIPALE
    // ==========================================

    /**
     * POST /api/daily-actions
     * Enregistrer une économie ou dépense.
     * Retourne : action + XP gagné + streak mise à jour + état quête.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'          => 'required|in:save,spend',
            'amount'        => 'required|numeric|min:0.01|max:99999',
            'reason'        => 'nullable|string|max:100',
            'reason_preset' => 'nullable|in:cooked,avoided,transport,other_save,food,shopping,subscription,other_spend',
            'quest_id'      => 'nullable|integer|exists:quests,id',
            'action_date'   => 'nullable|date|before_or_equal:today',
        ]);

        try {
            $user      = $request->user();
            $xp        = DailyAction::calculateXp($validated['type']);
            $questId   = $validated['quest_id'] ?? $this->getMainQuestId($user->id);
            $actionDate = $validated['action_date'] ?? today()->toDateString();

            DB::beginTransaction();

            // 1. Créer l'action
            $action = DailyAction::create([
                'user_id'       => $user->id,
                'quest_id'      => $questId,
                'type'          => $validated['type'],
                'amount'        => $validated['amount'],
                'reason'        => $validated['reason'] ?? null,
                'reason_preset' => $validated['reason_preset'] ?? null,
                'xp_earned'     => $xp,
                'action_date'   => $actionDate,
            ]);

            // 2. Mettre à jour le montant de la quête
            $quest = $this->updateQuestAmount($questId, $validated['type'], $validated['amount']);

            // 3. Ajouter l'XP via GamingService (déclenche checkAchievements)
            $xpResult = $this->gamingService->addExperience($user, $xp, 'daily_action');

            // 4. Mettre à jour la streak quotidienne
            $streakResult = $this->streakService->triggerStreak($user, 'daily');

            DB::commit();

            return $this->createdResponse(
                $this->buildActionResponse($action, $xpResult, $streakResult, $quest),
                $this->buildSuccessMessage($validated['type'], $xp, $streakResult)
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleError($e, 'Erreur enregistrement action');
        }
    }

    // ==========================================
    // LECTURE
    // ==========================================

    /**
     * GET /api/daily-actions
     * Historique des actions (30 derniers jours par défaut)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $days    = min($request->integer('days', 30), 365);
            $actions = DailyAction::where('user_id', $request->user()->id)
                ->where('action_date', '>=', now()->subDays($days)->toDateString())
                ->orderByDesc('action_date')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($a) => $a->toApiArray());

            return $this->successResponse($actions);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur récupération actions');
        }
    }

    /**
     * GET /api/daily-actions/today
     * Actions du jour + résumé
     */
    public function today(Request $request): JsonResponse
    {
        try {
            $userId  = $request->user()->id;
            $actions = DailyAction::where('user_id', $userId)
                ->today()
                ->orderByDesc('created_at')
                ->get();

            $summary = [
                'total_saved'  => $actions->where('type', 'save')->sum('amount'),
                'total_spent'  => $actions->where('type', 'spend')->sum('amount'),
                'total_xp'     => $actions->sum('xp_earned'),
                'actions_count'=> $actions->count(),
                'has_acted'    => $actions->isNotEmpty(),
            ];

            return $this->successResponse([
                'actions' => $actions->map(fn($a) => $a->toApiArray()),
                'summary' => $summary,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur récupération actions du jour');
        }
    }

    /**
     * GET /api/daily-actions/stats
     * Stats pour le dashboard (7 derniers jours)
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $weekActions = DailyAction::where('user_id', $userId)
                ->where('action_date', '>=', now()->subDays(7)->toDateString())
                ->get();

            $monthActions = DailyAction::where('user_id', $userId)
                ->thisMonth()
                ->get();

            return $this->successResponse([
                'week' => [
                    'saved'        => round($weekActions->where('type', 'save')->sum('amount'), 2),
                    'spent'        => round($weekActions->where('type', 'spend')->sum('amount'), 2),
                    'xp_earned'    => $weekActions->sum('xp_earned'),
                    'actions_count'=> $weekActions->count(),
                ],
                'month' => [
                    'saved'        => round($monthActions->where('type', 'save')->sum('amount'), 2),
                    'spent'        => round($monthActions->where('type', 'spend')->sum('amount'), 2),
                    'xp_earned'    => $monthActions->sum('xp_earned'),
                    'actions_count'=> $monthActions->count(),
                    'save_days'    => $monthActions->where('type', 'save')
                        ->unique('action_date')->count(),
                ],
                'has_acted_today' => DailyAction::hasActedToday($userId),
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur stats actions');
        }
    }

    /**
     * DELETE /api/daily-actions/{action}
     * Supprimer une action (annulation rapide)
     */
    public function destroy(Request $request, DailyAction $dailyAction): JsonResponse
    {
        if ($dailyAction->user_id !== $request->user()->id) {
            return $this->unauthorizedResponse();
        }

        try {
            DB::beginTransaction();

            // Reverser le montant sur la quête
            if ($dailyAction->quest_id) {
                $reverseType = $dailyAction->type === 'save' ? 'spend' : 'save';
                $this->updateQuestAmount($dailyAction->quest_id, $reverseType, $dailyAction->amount);
            }

            $dailyAction->delete();
            DB::commit();

            return $this->deletedResponse('Action supprimée');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleError($e, 'Erreur suppression action');
        }
    }

    // ==========================================
    // MÉTHODES PRIVÉES
    // ==========================================

    /**
     * Récupérer l'ID de la quête principale de l'utilisateur
     */
    private function getMainQuestId(int $userId): ?int
    {
        return Quest::where('user_id', $userId)
            ->active()
            ->main()
            ->value('id');
    }

    /**
     * Mettre à jour le montant de la quête selon le type d'action
     */
    private function updateQuestAmount(?int $questId, string $type, float $amount): ?Quest
    {
        if (!$questId) {
            return null;
        }

        $quest = Quest::find($questId);
        if (!$quest) {
            return null;
        }

        if ($type === 'save') {
            $quest->addAmount($amount);
        } else {
            $quest->subtractAmount($amount);
        }

        return $quest->fresh();
    }

    /**
     * Construire la réponse complète après une action
     */
    private function buildActionResponse(
        DailyAction $action,
        array $xpResult,
        array $streakResult,
        ?Quest $quest
    ): array {
        return [
            'action'  => $action->toApiArray(),
            'gaming'  => [
                'xp_earned'   => $action->xp_earned,
                'total_xp'    => $xpResult['total_xp'] ?? 0,
                'level'       => $xpResult['level'] ?? 1,
                'leveled_up'  => $xpResult['leveled_up'] ?? false,
                'new_level'   => $xpResult['new_level'] ?? null,
            ],
            'streak'  => [
                'current'      => $streakResult['streak']['current_count'] ?? 0,
                'best'         => $streakResult['streak']['best_count'] ?? 0,
                'bonus_xp'     => $streakResult['bonus_xp'] ?? 0,
                'is_milestone' => $streakResult['is_milestone'] ?? false,
            ],
            'quest'   => $quest?->toApiArray(),
        ];
    }

    /**
     * Message de succès contextuel
     */
    private function buildSuccessMessage(string $type, int $xp, array $streakResult): string
    {
        $base    = $type === 'save' ? 'Économie enregistrée' : 'Dépense enregistrée';
        $xpMsg   = "+{$xp} XP";
        $streak  = $streakResult['streak']['current_count'] ?? 0;
        $streakMsg = $streak > 1 ? " 🔥 {$streak} jours de série" : '';

        return "{$base} ! {$xpMsg}{$streakMsg}";
    }

    private function handleError(\Exception $e, string $message): JsonResponse
    {
        Log::error($message, [
            'error'   => $e->getMessage(),
            'user_id' => auth()->id(),
        ]);

        return $this->errorResponse(
            $message,
            500,
            config('app.debug') ? ['exception' => $e->getMessage()] : null
        );
    }
}
