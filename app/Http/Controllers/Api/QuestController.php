<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Services\GamingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * QuestController — Gestion des quêtes MVP.
 * Une quête principale par utilisateur sur le dashboard.
 * Opérations : créer, lire, modifier, supprimer, changer la quête principale.
 */
class QuestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private GamingService $gamingService) {}

    // ==========================================
    // LISTE + QUÊTE PRINCIPALE
    // ==========================================

    /**
     * GET /api/quests
     * Retourne toutes les quêtes de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $quests = Quest::where('user_id', $request->user()->id)
                ->orderByDesc('is_main')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($q) => $q->toApiArray());

            return $this->successResponse($quests, 'Quêtes récupérées');

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur récupération quêtes');
        }
    }

    /**
     * GET /api/quests/main
     * Retourne la quête principale (dashboard)
     */
    public function main(Request $request): JsonResponse
    {
        try {
            $quest = Quest::where('user_id', $request->user()->id)
                ->active()
                ->main()
                ->first();

            if (!$quest) {
                return $this->successResponse(null, 'Aucune quête active');
            }

            return $this->successResponse($quest->toApiArray());

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur récupération quête principale');
        }
    }

    // ==========================================
    // CRUD
    // ==========================================

    /**
     * POST /api/quests
     * Créer une nouvelle quête
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'target_amount' => 'required|numeric|min:1|max:999999',
            'target_date'   => 'nullable|date|after:today',
            'emoji'         => 'nullable|string|max:10',
        ]);

        try {
            $user = $request->user();

            // Si c'est la première quête, elle devient automatiquement principale
            $isFirst = !Quest::where('user_id', $user->id)->exists();

            $quest = Quest::create([
                'user_id'        => $user->id,
                'name'           => $validated['name'],
                'target_amount'  => $validated['target_amount'],
                'target_date'    => $validated['target_date'] ?? null,
                'emoji'          => $validated['emoji'] ?? '🎯',
                'current_amount' => 0,
                'status'         => 'active',
                'is_main'        => $isFirst,
            ]);

            // XP pour création de quête
            $this->gamingService->addExperience($user, 15, 'quest_created');

            Log::info("Quest created", ['user_id' => $user->id, 'quest_id' => $quest->id]);

            return $this->createdResponse($quest->toApiArray(), 'Quête créée ! +15 XP');

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur création quête');
        }
    }

    /**
     * GET /api/quests/{quest}
     */
    public function show(Request $request, Quest $quest): JsonResponse
    {
        if ($quest->user_id !== $request->user()->id) {
            return $this->unauthorizedResponse();
        }

        return $this->successResponse($quest->toApiArray());
    }

    /**
     * PUT /api/quests/{quest}
     * Modifier une quête
     */
    public function update(Request $request, Quest $quest): JsonResponse
    {
        if ($quest->user_id !== $request->user()->id) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:100',
            'target_amount' => 'sometimes|numeric|min:1|max:999999',
            'target_date'   => 'nullable|date',
            'emoji'         => 'nullable|string|max:10',
        ]);

        try {
            $quest->update($validated);

            return $this->updatedResponse($quest->fresh()->toApiArray(), 'Quête mise à jour');

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur mise à jour quête');
        }
    }

    /**
     * DELETE /api/quests/{quest}
     */
    public function destroy(Request $request, Quest $quest): JsonResponse
    {
        if ($quest->user_id !== $request->user()->id) {
            return $this->unauthorizedResponse();
        }

        try {
            $quest->delete();

            return $this->deletedResponse('Quête supprimée');

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur suppression quête');
        }
    }

    // ==========================================
    // ACTIONS SPÉCIALES
    // ==========================================

    /**
     * POST /api/quests/{quest}/set-main
     * Définir comme quête principale du dashboard
     */
    public function setMain(Request $request, Quest $quest): JsonResponse
    {
        if ($quest->user_id !== $request->user()->id) {
            return $this->unauthorizedResponse();
        }

        try {
            // Retirer is_main de toutes les autres quêtes de l'utilisateur
            Quest::where('user_id', $request->user()->id)
                ->where('id', '!=', $quest->id)
                ->update(['is_main' => false]);

            $quest->update(['is_main' => true]);

            return $this->successResponse($quest->fresh()->toApiArray(), 'Quête principale mise à jour');

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur changement quête principale');
        }
    }

    // ==========================================
    // HELPER
    // ==========================================

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
