<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserGamingProfile;
use App\Models\Milestone;
use App\Models\UserMilestone;
use App\Models\GamingEngagementEvent;
use App\Services\ProgressiveGamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProgressiveGamingController extends Controller
{
    public function __construct(
        private ProgressiveGamingService $gamingService
    ) {}

    // ==========================================
    // CONFIGURATION & PROFIL
    // ==========================================

    /**
     * Récupère la configuration gaming de l'utilisateur
     *
     * GET /api/gaming/config
     */
    public function getConfig(Request $request): JsonResponse
    {
        $user = $request->user();
        $config = $this->gamingService->getGamingConfig($user);

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Récupère le profil gaming de l'utilisateur
     *
     * GET /api/gaming/profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = UserGamingProfile::getOrCreate($user);

        return response()->json([
            'success' => true,
            'data' => [
                'engagement_level' => $profile->effective_engagement_level,
                'engagement_label' => $profile->engagement_label,
                'gaming_preference' => $profile->gaming_preference,
                'gaming_affinity_score' => $profile->gaming_affinity_score,
                'unlocked_features' => $profile->unlocked_features,
                'settings' => [
                    'show_xp' => $profile->shouldShowXP(),
                    'show_badges' => $profile->shouldShowLevelBadges(),
                    'show_leaderboard' => $profile->shouldShowLeaderboard(),
                    'show_challenges' => $profile->shouldShowChallenges(),
                ],
                'stats' => [
                    'total_interactions' => $profile->total_interactions,
                    'gaming_engagement_ratio' => $profile->gaming_engagement_ratio,
                ],
            ],
        ]);
    }

    /**
     * Met à jour les préférences gaming
     *
     * PUT /api/gaming/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gaming_preference' => 'sometimes|in:auto,minimal,moderate,full',
            'show_xp_notifications' => 'sometimes|boolean',
            'show_level_badges' => 'sometimes|boolean',
            'show_leaderboard' => 'sometimes|boolean',
            'show_challenges' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $profile = UserGamingProfile::getOrCreate($user);

        $profile->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'gaming_preference' => $profile->gaming_preference,
                'effective_level' => $profile->effective_engagement_level,
            ],
            'message' => 'Préférences mises à jour',
        ]);
    }

    // ==========================================
    // DASHBOARD & DONNÉES
    // ==========================================

    /**
     * Récupère les données du dashboard gaming
     *
     * GET /api/gaming/dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->gamingService->getDashboardData($user);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Récupère l'encouragement du jour
     *
     * GET /api/gaming/encouragement
     */
    public function getDailyEncouragement(Request $request): JsonResponse
    {
        $user = $request->user();
        $encouragement = $this->gamingService->getDailyEncouragement($user);

        return response()->json([
            'success' => true,
            'data' => $encouragement,
        ]);
    }

    // ==========================================
    // MILESTONES
    // ==========================================

    /**
     * Liste les milestones de l'utilisateur
     *
     * GET /api/gaming/milestones
     */
    public function getMilestones(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = UserGamingProfile::getOrCreate($user);
        $level = $profile->effective_engagement_level;

        // Filtres optionnels
        $category = $request->query('category');
        $status = $request->query('status'); // all, completed, pending

        $query = Milestone::active()
            ->forEngagementLevel($level)
            ->ordered();

        if ($category) {
            $query->byCategory($category);
        }

        $milestones = $query->get()->map(function ($milestone) use ($user, $status) {
            $userMilestone = UserMilestone::where('user_id', $user->id)
                ->where('milestone_id', $milestone->id)
                ->first();

            $evaluation = $milestone->evaluateProgress($user);
            $isCompleted = $userMilestone?->is_completed ?? false;

            // Filtrer selon le statut demandé
            if ($status === 'completed' && !$isCompleted) {
                return null;
            }
            if ($status === 'pending' && $isCompleted) {
                return null;
            }

            return [
                'id' => $milestone->id,
                'code' => $milestone->code,
                'title' => $milestone->title,
                'description' => $milestone->description,
                'icon' => $milestone->icon,
                'category' => $milestone->category_info,
                'progress' => [
                    'current' => $evaluation['current_value'],
                    'target' => $evaluation['target_value'],
                    'percentage' => $evaluation['progress_percentage'],
                ],
                'is_completed' => $isCompleted,
                'completed_at' => $userMilestone?->completed_at,
                'reward' => $milestone->points_reward > 0
                    ? "+{$milestone->points_reward} points"
                    : null,
                'reward_claimed' => $userMilestone?->reward_claimed ?? false,
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => [
                'milestones' => $milestones,
                'stats' => [
                    'total' => $milestones->count(),
                    'completed' => $milestones->where('is_completed', true)->count(),
                    'pending' => $milestones->where('is_completed', false)->count(),
                ],
            ],
        ]);
    }

    /**
     * Réclame la récompense d'un milestone
     *
     * POST /api/gaming/milestones/{id}/claim
     */
    public function claimMilestoneReward(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $userMilestone = UserMilestone::where('user_id', $user->id)
            ->where('milestone_id', $id)
            ->first();

        if (!$userMilestone) {
            return response()->json([
                'success' => false,
                'message' => 'Milestone non trouvé',
            ], 404);
        }

        $result = $userMilestone->claimReward();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'rewards' => $result['rewards'],
            ],
            'message' => 'Récompense réclamée !',
        ]);
    }

    // ==========================================
    // ÉVÉNEMENTS & TRACKING
    // ==========================================

    /**
     * Traite un événement gaming
     *
     * POST /api/gaming/event
     */
    public function processEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string|max:50',
            'context' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $eventType = $request->input('event_type');
        $context = $request->input('context', []);

        $result = $this->gamingService->processEvent($user, $eventType, $context);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Enregistre une interaction avec un élément gaming
     *
     * POST /api/gaming/interaction
     */
    public function recordInteraction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string|max:50',
            'element_type' => 'nullable|string|max:30',
            'element_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        GamingEngagementEvent::record(
            $user,
            $request->input('event_type'),
            $request->input('element_type'),
            $request->input('element_id'),
            $request->input('metadata', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'Interaction enregistrée',
        ]);
    }

    /**
     * Enregistre un feedback dismissé
     *
     * POST /api/gaming/feedback/{id}/dismiss
     */
    public function dismissFeedback(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $log = $user->feedbackLogs()->find($id);

        if ($log) {
            $log->recordReaction('dismissed');
        }

        // Enregistrer aussi comme événement d'engagement
        GamingEngagementEvent::record(
            $user,
            GamingEngagementEvent::TYPE_DISMISSED_XP_POPUP,
            'feedback',
            $id
        );

        return response()->json([
            'success' => true,
            'message' => 'Feedback fermé',
        ]);
    }

    // ==========================================
    // PROGRESSION
    // ==========================================

    /**
     * Récupère la progression détaillée
     *
     * GET /api/gaming/progress
     */
    public function getProgress(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = UserGamingProfile::getOrCreate($user);
        $userLevel = $user->level;

        // Ne pas montrer les détails si niveau d'engagement trop bas
        if ($profile->effective_engagement_level < UserGamingProfile::LEVEL_REWARDS) {
            return response()->json([
                'success' => true,
                'data' => [
                    'limited' => true,
                    'message' => 'Continuez à utiliser l\'app pour débloquer plus de fonctionnalités',
                ],
            ]);
        }

        $terminology = $profile->effective_engagement_level >= UserGamingProfile::LEVEL_GAMING
            ? ['points' => 'XP', 'tier' => 'Niveau']
            : ['points' => 'Points', 'tier' => 'Palier'];

        return response()->json([
            'success' => true,
            'data' => [
                'terminology' => $terminology,
                'current_tier' => $userLevel?->level ?? 1,
                'tier_name' => $this->getTierName($userLevel?->level ?? 1),
                'total_points' => $userLevel?->total_xp ?? 0,
                'points_in_tier' => $userLevel?->current_level_xp ?? 0,
                'points_for_next' => $userLevel?->next_level_xp ?? 100,
                'progress_percentage' => $userLevel?->getProgressPercentage() ?? 0,
            ],
        ]);
    }

    // ==========================================
    // ADMIN / DEBUG (optionnel)
    // ==========================================

    /**
     * Recalcule le profil gaming (admin)
     *
     * POST /api/gaming/recalculate
     */
    public function recalculateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = UserGamingProfile::getOrCreate($user);

        $oldScore = $profile->gaming_affinity_score;
        $oldLevel = $profile->engagement_level;

        $newScore = $profile->recalculateAffinityScore();
        $newLevel = $profile->updateEngagementLevel();

        return response()->json([
            'success' => true,
            'data' => [
                'affinity_score' => [
                    'old' => $oldScore,
                    'new' => $newScore,
                ],
                'engagement_level' => [
                    'old' => $oldLevel,
                    'new' => $newLevel,
                ],
            ],
            'message' => 'Profil recalculé',
        ]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function getTierName(int $tier): string
    {
        return match (true) {
            $tier >= 20 => 'Expert financier',
            $tier >= 15 => 'Gestionnaire confirmé',
            $tier >= 10 => 'Épargnant régulier',
            $tier >= 5 => 'En progression',
            $tier >= 2 => 'Apprenti',
            default => 'Débutant',
        };
    }
}
