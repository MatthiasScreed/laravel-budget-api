<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialInsight;
use App\Services\FinancialInsightService;
use App\Services\GamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller API pour les insights financiers
 */
class FinancialInsightController extends Controller
{
    private GamingService $gamingService;

    // ❌ ANCIEN - Ne fonctionne pas :
    // public function __construct(
    //     FinancialInsightService $insightService,
    //     GamingService $gamingService
    // ) {
    //     $this->insightService = $insightService;
    //     $this->gamingService = $gamingService;
    // }

    // ✅ NOUVEAU - Fonctionne :
    public function __construct(GamingService $gamingService)
    {
        $this->gamingService = $gamingService;
        // On n'injecte plus FinancialInsightService ici
        // On le créera manuellement dans generate()
    }

    /**
     * Liste des insights de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $query = $user->financialInsights()
                ->active();

            $query = $this->applyFilters($query, $request);

            $insights = $query
                ->byPriority()
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $insights->items(),
                'meta' => $this->buildPaginationMeta($insights),
            ]);

        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'Erreur récupération des insights'
            );
        }
    }

    /**
     * Résumé des insights (compteurs)
     */
    public function summary(): JsonResponse
    {
        try {
            $user = auth()->user();

            $summary = [
                'total_active' => $user
                    ->financialInsights()
                    ->active()
                    ->count(),
                'unread' => $user
                    ->financialInsights()
                    ->active()
                    ->unread()
                    ->count(),
                'by_type' => $this->countByType($user),
                'by_priority' => $this->countByPriority($user),
                'total_potential_saving' => $user
                    ->financialInsights()
                    ->active()
                    ->sum('potential_saving'),
                'acted_count' => $user
                    ->financialInsights()
                    ->whereNotNull('acted_at')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);

        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'Erreur récupération du résumé'
            );
        }
    }

    /**
     * Détail d'un insight
     */
    public function show(int $id): JsonResponse
    {
        try {
            $insight = $this->findUserInsight($id);

            if (!$insight) {
                return $this->notFoundResponse('Insight non trouvé');
            }

            return response()->json([
                'success' => true,
                'data' => $insight,
            ]);

        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'Erreur récupération de l\'insight'
            );
        }
    }

    /**
     * Générer de nouveaux insights via le service
     * ⚠️ SEULE MÉTHODE MODIFIÉE
     */
    public function generate(): JsonResponse
    {
        try {
            $user = auth()->user();

            // ✅ Instanciation manuelle du service
            $insightService = new FinancialInsightService($user);
            $insights = $insightService->generateInsights();

            // XP pour consultation des insights
            $this->gamingService->awardXP(
                $user,
                'insight_generated',
                5
            );

            return response()->json([
                'success' => true,
                'data' => $insights,
                'message' => count($insights) . ' insight(s) généré(s)',
            ]);

        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'Erreur génération des insights'
            );
        }
    }

    /**
     * Marquer un insight comme lu
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $insight = $this->findUserInsight($id);

            if (!$insight) {
                return $this->notFoundResponse('Insight non trouvé');
            }

            $insight->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Insight marqué comme lu',
                'data' => $insight->fresh(),
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur marquage lecture');
        }
    }

    /**
     * Marquer l'action comme effectuée
     */
    public function markAsActed(int $id): JsonResponse
    {
        try {
            $insight = $this->findUserInsight($id);

            if (!$insight) {
                return $this->notFoundResponse('Insight non trouvé');
            }

            $insight->markAsActed();

            // XP bonus pour action sur insight
            $xpAwarded = $this->gamingService->awardXP(
                auth()->user(),
                'insight_acted',
                15
            );

            return response()->json([
                'success' => true,
                'message' => 'Action enregistrée',
                'data' => $insight->fresh(),
                'gaming' => [
                    'xp_earned' => $xpAwarded,
                    'action' => 'insight_acted',
                ],
            ]);

        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'Erreur enregistrement action'
            );
        }
    }

    /**
     * Rejeter un insight
     */
    public function dismiss(int $id): JsonResponse
    {
        try {
            $insight = $this->findUserInsight($id);

            if (!$insight) {
                return $this->notFoundResponse('Insight non trouvé');
            }

            $insight->dismiss();

            return response()->json([
                'success' => true,
                'message' => 'Insight rejeté',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur rejet de l\'insight');
        }
    }

    /**
     * Marquer tous les insights comme lus
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = auth()->user();

            $count = $user->financialInsights()
                ->active()
                ->unread()
                ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => "$count insight(s) marqué(s)",
                'data' => ['updated_count' => $count],
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur marquage global');
        }
    }

    /**
     * Supprimer un insight
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $insight = $this->findUserInsight($id);

            if (!$insight) {
                return $this->notFoundResponse('Insight non trouvé');
            }

            $insight->delete();

            return response()->json([
                'success' => true,
                'message' => 'Insight supprimé',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur suppression insight');
        }
    }

    // ========================================
    // MÉTHODES PRIVÉES
    // ========================================

    private function findUserInsight(int $id): ?FinancialInsight
    {
        return auth()->user()
            ->financialInsights()
            ->find($id);
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->get('unread_only')) {
            $query->unread();
        }

        return $query;
    }

    private function countByType($user): array
    {
        return $user->financialInsights()
            ->active()
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }

    private function countByPriority($user): array
    {
        return $user->financialInsights()
            ->active()
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();
    }

    private function buildPaginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function notFoundResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 404);
    }

    private function handleError(
        \Exception $e,
        string $context
    ): JsonResponse {
        Log::error("$context: {$e->getMessage()}", [
            'user_id' => auth()->id(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $context,
        ], 500);
    }
}
