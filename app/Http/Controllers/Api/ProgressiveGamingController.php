<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Controller Gaming Progressif - Version autonome
 * Ne dépend PAS de UserGamingProfile (modèle manquant)
 */
class ProgressiveGamingController extends Controller
{
    /**
     * Récupère la configuration gaming de l'utilisateur
     * GET /api/gaming/config
     */
    public function getConfig(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $level = $user->level?->level ?? 1;
            $engagementLevel = $this->calculateEngagementLevel($level);

            return response()->json([
                'success' => true,
                'data' => [
                    'engagement_level' => $engagementLevel,
                    'engagement_label' => $this->getEngagementLabel($engagementLevel),
                    'terminology' => $this->getTerminology($engagementLevel),
                    'features' => $this->getFeatures($engagementLevel),
                    'ui_config' => $this->getUIConfig($engagementLevel),
                    'notifications' => $this->getNotificationConfig($engagementLevel),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Gaming config error', [
                'userId' => $request->user()?->id,
                'exception' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur de configuration gaming',
                'error_code' => 'GAMING_CONFIG_ERROR'
            ], 500);
        }
    }

    /**
     * Récupère le profil gaming de l'utilisateur
     * GET /api/gaming/profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $level = $user->level?->level ?? 1;
        $engagementLevel = $this->calculateEngagementLevel($level);

        return response()->json([
            'success' => true,
            'data' => [
                'engagement_level' => $engagementLevel,
                'engagement_label' => $this->getEngagementLabel($engagementLevel),
                'gaming_preference' => 'auto',
                'gaming_affinity_score' => min(100, $level * 10),
                'unlocked_features' => $this->getFeatures($engagementLevel),
                'settings' => [
                    'show_xp' => $engagementLevel >= 2,
                    'show_badges' => $engagementLevel >= 2,
                    'show_leaderboard' => $engagementLevel >= 4,
                    'show_challenges' => $engagementLevel >= 4,
                ],
                'stats' => [
                    'total_interactions' => $user->transactions()->count(),
                    'gaming_engagement_ratio' => 0.5,
                ],
            ],
        ]);
    }

    /**
     * Met à jour les préférences gaming
     * PUT /api/gaming/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        // Stockage simple dans les préférences utilisateur
        $user = $request->user();
        $prefs = $user->preferences ?? [];
        $prefs['gaming'] = array_merge($prefs['gaming'] ?? [], $request->only([
            'gaming_preference',
            'show_xp_notifications',
            'show_level_badges',
            'show_leaderboard',
            'show_challenges'
        ]));
        $user->update(['preferences' => $prefs]);

        return response()->json([
            'success' => true,
            'data' => [
                'gaming_preference' => $prefs['gaming']['gaming_preference'] ?? 'auto',
                'effective_level' => $this->calculateEngagementLevel($user->level?->level ?? 1),
            ],
            'message' => 'Préférences mises à jour',
        ]);
    }

    /**
     * Récupère les données du dashboard gaming
     * GET /api/gaming/dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $level = $user->level?->level ?? 1;
        $engagementLevel = $this->calculateEngagementLevel($level);

        $data = [
            'engagement_level' => $engagementLevel,
            'milestones' => [],
            'encouragement' => $this->getDailyEncouragementData($user),
        ];

        if ($engagementLevel >= 2) {
            $data['progress'] = [
                'current_tier' => $level,
                'tier_name' => $this->getTierName($level),
                'progress_percentage' => $user->level?->getProgressPercentage() ?? 0,
            ];
            $data['recent_achievements'] = $this->getRecentAchievements($user);
        }

        if ($engagementLevel >= 3) {
            $data['streak'] = $this->getStreakData($user);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Récupère l'encouragement du jour
     * GET /api/gaming/encouragement
     */
    public function getDailyEncouragement(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->getDailyEncouragementData($user),
        ]);
    }

    /**
     * Liste les milestones de l'utilisateur
     * GET /api/gaming/milestones
     */
    public function getMilestones(Request $request): JsonResponse
    {
        // Version simplifiée sans le modèle Milestone
        return response()->json([
            'success' => true,
            'data' => [
                'milestones' => [],
                'stats' => [
                    'total' => 0,
                    'completed' => 0,
                    'pending' => 0,
                ],
            ],
        ]);
    }

    /**
     * Réclame la récompense d'un milestone
     * POST /api/gaming/milestones/{id}/claim
     */
    public function claimMilestoneReward(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Fonctionnalité en cours de développement',
        ], 501);
    }

    /**
     * Traite un événement gaming
     * POST /api/gaming/event
     */
    public function processEvent(Request $request): JsonResponse
    {
        $user = $request->user();
        $eventType = $request->input('event_type');
        $context = $request->input('context', []);

        // Traitement simplifié
        $points = $this->calculatePoints($eventType, $context);

        if ($points > 0 && $user->level) {
            $user->level->addXp($points);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'feedback' => null,
                'milestones' => ['newly_completed' => [], 'progress_updated' => []],
                'points' => $points,
                'show_points' => true,
            ],
        ]);
    }

    /**
     * Enregistre une interaction avec un élément gaming
     * POST /api/gaming/interaction
     */
    public function recordInteraction(Request $request): JsonResponse
    {
        // Log simple sans le modèle GamingEngagementEvent
        Log::info('Gaming interaction', [
            'user_id' => $request->user()->id,
            'event_type' => $request->input('event_type'),
            'element_type' => $request->input('element_type'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Interaction enregistrée',
        ]);
    }

    /**
     * Enregistre un feedback dismissé
     * POST /api/gaming/feedback/{id}/dismiss
     */
    public function dismissFeedback(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Feedback fermé',
        ]);
    }

    /**
     * Récupère la progression détaillée
     * GET /api/gaming/progress
     */
    public function getProgress(Request $request): JsonResponse
    {
        $user = $request->user();
        $userLevel = $user->level;
        $level = $userLevel?->level ?? 1;
        $engagementLevel = $this->calculateEngagementLevel($level);

        if ($engagementLevel < 2) {
            return response()->json([
                'success' => true,
                'data' => [
                    'limited' => true,
                    'message' => 'Continuez à utiliser l\'app pour débloquer plus de fonctionnalités',
                ],
            ]);
        }

        $terminology = $engagementLevel >= 4
            ? ['points' => 'XP', 'tier' => 'Niveau']
            : ['points' => 'Points', 'tier' => 'Palier'];

        return response()->json([
            'success' => true,
            'data' => [
                'terminology' => $terminology,
                'current_tier' => $level,
                'tier_name' => $this->getTierName($level),
                'total_points' => $userLevel?->total_xp ?? 0,
                'points_in_tier' => $userLevel?->current_level_xp ?? 0,
                'points_for_next' => $userLevel?->next_level_xp ?? 100,
                'progress_percentage' => $userLevel?->getProgressPercentage() ?? 0,
            ],
        ]);
    }

    /**
     * Recalcule le profil gaming
     * POST /api/gaming/recalculate
     */
    public function recalculateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $level = $user->level?->level ?? 1;

        return response()->json([
            'success' => true,
            'data' => [
                'affinity_score' => ['old' => 0, 'new' => min(100, $level * 10)],
                'engagement_level' => [
                    'old' => 1,
                    'new' => $this->calculateEngagementLevel($level)
                ],
            ],
            'message' => 'Profil recalculé',
        ]);
    }

    // ==========================================
    // HELPERS PRIVÉS
    // ==========================================

    private function calculateEngagementLevel(int $level): int
    {
        if ($level >= 15) return 4;
        if ($level >= 8) return 3;
        if ($level >= 3) return 2;
        return 1;
    }

    private function getEngagementLabel(int $level): string
    {
        return match ($level) {
            4 => 'Gamer confirmé',
            3 => 'Joueur social',
            2 => 'Apprenti motivé',
            default => 'Débutant prudent',
        };
    }

    private function getTerminology(int $level): array
    {
        if ($level >= 4) {
            return ['points' => 'XP', 'tier' => 'Niveau', 'achievement' => 'Succès', 'streak' => 'Streak'];
        }
        return ['points' => 'Points', 'tier' => 'Palier', 'achievement' => 'Objectif atteint', 'streak' => 'Série'];
    }

    private function getFeatures(int $level): array
    {
        $features = ['basic_feedback', 'milestones'];
        if ($level >= 2) $features = array_merge($features, ['points_display', 'tier_progress', 'achievements_page']);
        if ($level >= 3) $features = array_merge($features, ['streak_tracking', 'anonymous_comparison']);
        if ($level >= 4) $features = array_merge($features, ['leaderboard', 'challenges', 'full_gaming']);
        return $features;
    }

    private function getUIConfig(int $level): array
    {
        return [
            'show_xp_bar' => $level >= 2,
            'show_level_badge' => $level >= 2,
            'show_achievements_count' => $level >= 2,
            'show_streak_counter' => $level >= 3,
            'show_comparison_widget' => $level >= 3,
            'show_leaderboard_link' => $level >= 4,
            'show_challenges_link' => $level >= 4,
            'animation_intensity' => match ($level) { 1 => 'subtle', 2 => 'moderate', 3 => 'engaging', default => 'full' },
        ];
    }

    private function getNotificationConfig(int $level): array
    {
        return [
            'feedback_enabled' => true,
            'milestone_alerts' => true,
            'points_notifications' => $level >= 2,
            'level_up_celebrations' => $level >= 2,
            'achievement_popups' => $level >= 2,
            'streak_reminders' => $level >= 3,
            'challenge_alerts' => $level >= 4,
            'frequency' => match ($level) { 1 => 'minimal', 2 => 'moderate', 3 => 'regular', default => 'frequent' },
            'sound_enabled' => $level >= 2,
            'vibration_enabled' => $level >= 3,
        ];
    }

    private function calculatePoints(string $eventType, array $context): int
    {
        $basePoints = match ($eventType) {
            'transaction_created' => 2, 'goal_created' => 10, 'goal_completed' => 50, 'daily_login' => 1, default => 0,
        };
        return (int) round($basePoints * (isset($context['is_first']) ? 1.5 : 1.0));
    }

    private function getTierName(int $tier): string
    {
        return match (true) {
            $tier >= 20 => 'Expert financier', $tier >= 15 => 'Gestionnaire confirmé',
            $tier >= 10 => 'Épargnant régulier', $tier >= 5 => 'En progression',
            $tier >= 2 => 'Apprenti', default => 'Débutant',
        };
    }

    private function getDailyEncouragementData(User $user): array
    {
        $messages = [
            "Chaque euro compte, continuez ! 🌱",
            "Vous progressez, c'est l'essentiel",
            "Un nouveau mois, de nouvelles opportunités 🌟",
            "Belle régularité dans le suivi !",
        ];
        return ['message' => $messages[array_rand($messages)], 'stats_highlight' => null];
    }

    private function getRecentAchievements(User $user): array
    {
        return $user->achievements()
            ->wherePivot('unlocked_at', '>=', now()->subMonth())
            ->orderByPivot('unlocked_at', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($a) => ['name' => $a->name, 'icon' => $a->icon, 'unlocked_at' => $a->pivot->unlocked_at])
            ->toArray();
    }

    private function getStreakData(User $user): array
    {
        $streak = $user->streaks()->where('is_active', true)->first();
        return ['current' => $streak?->current_count ?? 0, 'best' => $streak?->best_count ?? 0, 'label' => 'jours d\'activité'];
    }
}
