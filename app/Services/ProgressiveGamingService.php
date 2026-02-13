<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserGamingProfile;
use App\Models\Milestone;
use App\Models\UserMilestone;
use App\Models\FeedbackTemplate;
use App\Models\UserFeedbackLog;
use App\Models\GamingEngagementEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProgressiveGamingService
{
    // ==========================================
    // CONFIGURATION
    // ==========================================

    /**
     * Mapping des termes gaming vers termes accessibles
     */
    private const TERMINOLOGY = [
        'xp' => 'points',
        'level' => 'palier',
        'achievement' => 'objectif atteint',
        'streak' => 'série',
        'leaderboard' => 'classement',
        'challenge' => 'défi',
        'quest' => 'mission',
        'badge' => 'badge',
        'reward' => 'récompense',
    ];

    /**
     * Seuils pour les messages de félicitations
     */
    private const CELEBRATION_THRESHOLDS = [
        'savings' => [100, 500, 1000, 2500, 5000, 10000],
        'transactions' => [10, 50, 100, 250, 500],
        'goals_completed' => [1, 3, 5, 10],
        'streak_days' => [3, 7, 14, 30, 60, 100],
    ];

    // ==========================================
    // MÉTHODES PRINCIPALES
    // ==========================================

    /**
     * Obtient la configuration gaming adaptée à l'utilisateur
     */
    public function getGamingConfig(User $user): array
    {
        $profile = UserGamingProfile::getOrCreate($user);
        $level = $profile->effective_engagement_level;

        return [
            'engagement_level' => $level,
            'engagement_label' => $profile->engagement_label,
            'terminology' => $this->getTerminologyForLevel($level),
            'features' => $this->getFeaturesForLevel($level, $profile),
            'ui_config' => $this->getUIConfigForLevel($level),
            'notifications' => $this->getNotificationConfig($profile),
        ];
    }

    /**
     * Traite un événement et génère le feedback approprié
     */
    public function processEvent(
        User $user,
        string $eventType,
        array $context = []
    ): array {
        $profile = UserGamingProfile::getOrCreate($user);
        $level = $profile->effective_engagement_level;

        // 1. Vérifier les milestones
        $milestoneResults = $this->checkMilestones($user, $eventType, $context);

        // 2. Générer le feedback contextuel
        $feedback = $this->generateFeedback($user, $eventType, $context, $level);

        // 3. Calculer les points (invisibles au niveau 1)
        $points = $this->calculatePoints($eventType, $context);

        // 4. Mettre à jour les stats internes
        $this->updateInternalStats($user, $eventType, $points);

        // 5. Préparer la réponse selon le niveau d'engagement
        return $this->formatResponse($level, [
            'feedback' => $feedback,
            'milestones' => $milestoneResults,
            'points' => $points,
            'show_points' => $profile->shouldShowXP(),
        ]);
    }

    /**
     * Obtient le dashboard gaming adapté
     */
    public function getDashboardData(User $user): array
    {
        $profile = UserGamingProfile::getOrCreate($user);
        $level = $profile->effective_engagement_level;

        $baseData = [
            'engagement_level' => $level,
            'milestones' => $this->getUserMilestones($user, $level),
            'encouragement' => $this->getDailyEncouragement($user),
        ];

        // Niveau 2+ : Ajouter les points/paliers
        if ($level >= UserGamingProfile::LEVEL_REWARDS) {
            $baseData['progress'] = $this->getProgressData($user);
            $baseData['recent_achievements'] = $this->getRecentAchievements($user, 3);
        }

        // Niveau 3+ : Ajouter les comparaisons sociales
        if ($level >= UserGamingProfile::LEVEL_SOCIAL) {
            $baseData['comparison'] = $this->getAnonymousComparison($user);
            $baseData['streak'] = $this->getStreakData($user);
        }

        // Niveau 4 : Full gaming
        if ($level >= UserGamingProfile::LEVEL_GAMING) {
            $baseData['leaderboard_preview'] = $this->getLeaderboardPreview($user);
            $baseData['active_challenges'] = $this->getActiveChallenges($user);
        }

        return $baseData;
    }

    // ==========================================
    // FEEDBACK & MESSAGING
    // ==========================================

    /**
     * Génère un feedback contextuel
     */
    private function generateFeedback(
        User $user,
        string $eventType,
        array $context,
        int $level
    ): ?array {
        // Vérifier si on n'a pas déjà envoyé ce feedback récemment
        if ($this->hasSentRecentFeedback($user, $eventType)) {
            return null;
        }

        // Trouver le meilleur template
        $template = FeedbackTemplate::findBestMatch($eventType, $level, $context);

        if (!$template) {
            return $this->generateDefaultFeedback($eventType, $context, $level);
        }

        // Logger le feedback
        UserFeedbackLog::create([
            'user_id' => $user->id,
            'feedback_template_id' => $template->id,
            'trigger_event' => $eventType,
            'context' => $context,
        ]);

        return $template->generateFeedback($context);
    }

    /**
     * Génère un feedback par défaut si pas de template
     */
    private function generateDefaultFeedback(
        string $eventType,
        array $context,
        int $level
    ): ?array {
        $defaults = [
            'transaction_created' => [
                1 => ['icon' => '✓', 'title' => 'Enregistré', 'message' => 'Transaction ajoutée'],
                2 => ['icon' => '✓', 'title' => 'Bien joué !', 'message' => 'Transaction enregistrée'],
            ],
            'goal_progress' => [
                1 => ['icon' => '📈', 'title' => 'Progression', 'message' => 'Vous avancez bien'],
                2 => ['icon' => '🎯', 'title' => 'En route !', 'message' => 'Objectif en bonne voie'],
            ],
            'savings_positive' => [
                1 => ['icon' => '💰', 'title' => 'Économies', 'message' => 'Votre solde est positif'],
                2 => ['icon' => '🌟', 'title' => 'Bravo !', 'message' => 'Vous économisez ce mois'],
            ],
        ];

        $levelDefaults = $defaults[$eventType] ?? null;

        if (!$levelDefaults) {
            return null;
        }

        return $levelDefaults[$level] ?? $levelDefaults[1] ?? null;
    }

    /**
     * Obtient l'encouragement quotidien
     */
    public function getDailyEncouragement(User $user): array
    {
        $cacheKey = "daily_encouragement_{$user->id}_" . date('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $profile = UserGamingProfile::getOrCreate($user);
            $stats = $this->getUserQuickStats($user);

            // Messages selon la situation financière
            $messages = $this->selectEncouragementMessages($stats, $profile);

            return [
                'message' => $messages[array_rand($messages)],
                'stats_highlight' => $this->getStatsHighlight($stats),
            ];
        });
    }

    /**
     * Sélectionne les messages d'encouragement appropriés
     */
    private function selectEncouragementMessages(array $stats, UserGamingProfile $profile): array
    {
        $messages = [];

        // Selon le taux d'épargne
        if (($stats['savings_rate'] ?? 0) > 20) {
            $messages[] = "Excellent ! Vous êtes sur la bonne voie 💪";
            $messages[] = "Continuez comme ça, vos finances sont saines !";
        } elseif (($stats['savings_rate'] ?? 0) > 0) {
            $messages[] = "Chaque euro compte, continuez ! 🌱";
            $messages[] = "Vous progressez, c'est l'essentiel";
        } else {
            $messages[] = "Un nouveau mois, de nouvelles opportunités 🌟";
            $messages[] = "Analysons ensemble vos dépenses";
        }

        // Selon l'activité récente
        if (($stats['transactions_this_week'] ?? 0) > 5) {
            $messages[] = "Belle régularité dans le suivi !";
        }

        // Messages génériques si rien de spécial
        if (empty($messages)) {
            $messages = [
                "Bienvenue ! Prêt à prendre le contrôle ?",
                "Une bonne gestion commence par le suivi",
            ];
        }

        return $messages;
    }

    // ==========================================
    // MILESTONES
    // ==========================================

    /**
     * Vérifie et met à jour les milestones
     */
    private function checkMilestones(User $user, string $eventType, array $context): array
    {
        $profile = UserGamingProfile::getOrCreate($user);
        $level = $profile->effective_engagement_level;

        // Récupérer les milestones pertinents
        $milestones = Milestone::active()
            ->forEngagementLevel($level)
            ->ordered()
            ->get();

        $results = [
            'newly_completed' => [],
            'progress_updated' => [],
        ];

        foreach ($milestones as $milestone) {
            $result = $this->evaluateMilestone($user, $milestone, $context);

            if ($result['newly_completed']) {
                $results['newly_completed'][] = $this->formatMilestoneForDisplay(
                    $milestone,
                    $level
                );
            } elseif ($result['progress_changed']) {
                $results['progress_updated'][] = [
                    'milestone' => $this->formatMilestoneForDisplay($milestone, $level),
                    'progress' => $result['progress'],
                ];
            }
        }

        return $results;
    }

    /**
     * Évalue un milestone spécifique
     */
    private function evaluateMilestone(User $user, Milestone $milestone, array $context): array
    {
        // Récupérer ou créer le suivi utilisateur
        $userMilestone = UserMilestone::firstOrCreate(
            ['user_id' => $user->id, 'milestone_id' => $milestone->id],
            ['progress' => 0, 'target' => $milestone->target_value]
        );

        // Si déjà complété, skip
        if ($userMilestone->is_completed) {
            return ['newly_completed' => false, 'progress_changed' => false];
        }

        // Évaluer la progression
        $evaluation = $milestone->evaluateProgress($user);
        $oldProgress = $userMilestone->progress;
        $newProgress = $evaluation['current_value'];

        $newlyCompleted = $userMilestone->updateProgress($newProgress, $context);

        return [
            'newly_completed' => $newlyCompleted,
            'progress_changed' => abs($newProgress - $oldProgress) > 0.01,
            'progress' => $userMilestone->progress_percentage,
        ];
    }

    /**
     * Formate un milestone pour l'affichage selon le niveau
     */
    private function formatMilestoneForDisplay(Milestone $milestone, int $level): array
    {
        $formatted = [
            'id' => $milestone->id,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'icon' => $milestone->icon,
            'category' => $milestone->category_info,
        ];

        // Niveau 2+ : Montrer les récompenses
        if ($level >= UserGamingProfile::LEVEL_REWARDS && $milestone->points_reward > 0) {
            $formatted['reward'] = "+{$milestone->points_reward} points";
        }

        // Niveau 3+ : Montrer le déverrouillage
        if ($level >= UserGamingProfile::LEVEL_SOCIAL && $milestone->feature_unlock) {
            $formatted['unlocks'] = $this->getFeatureLabel($milestone->feature_unlock);
        }

        return $formatted;
    }

    /**
     * Récupère les milestones de l'utilisateur
     */
    private function getUserMilestones(User $user, int $level): array
    {
        $milestones = Milestone::active()
            ->forEngagementLevel($level)
            ->ordered()
            ->limit(10)
            ->get();

        return $milestones->map(function ($milestone) use ($user, $level) {
            $userMilestone = UserMilestone::where('user_id', $user->id)
                ->where('milestone_id', $milestone->id)
                ->first();

            $evaluation = $milestone->evaluateProgress($user);

            return [
                'milestone' => $this->formatMilestoneForDisplay($milestone, $level),
                'progress' => $evaluation['progress_percentage'],
                'is_completed' => $userMilestone?->is_completed ?? false,
                'completed_at' => $userMilestone?->completed_at,
            ];
        })->toArray();
    }

    // ==========================================
    // POINTS & PROGRESSION
    // ==========================================

    /**
     * Calcule les points pour un événement
     */
    private function calculatePoints(string $eventType, array $context): int
    {
        $basePoints = match ($eventType) {
            'transaction_created' => 2,
            'transaction_income' => 3,
            'goal_created' => 10,
            'goal_progress' => 5,
            'goal_completed' => 50,
            'category_budget_respected' => 15,
            'daily_login' => 1,
            'weekly_review' => 20,
            default => 0,
        };

        // Bonus contextuels
        $multiplier = 1.0;

        if (isset($context['amount']) && $context['amount'] >= 100) {
            $multiplier += 0.1;
        }

        if (isset($context['is_first']) && $context['is_first']) {
            $multiplier += 0.5;
        }

        return (int) round($basePoints * $multiplier);
    }

    /**
     * Met à jour les stats internes (invisibles)
     */
    private function updateInternalStats(User $user, string $eventType, int $points): void
    {
        // Les points sont stockés mais pas forcément affichés
        if ($points > 0) {
            // Utiliser le système existant d'XP
            $user->level?->addXp($points, $eventType);
        }
    }

    /**
     * Récupère les données de progression
     */
    private function getProgressData(User $user): array
    {
        $level = $user->level;

        if (!$level) {
            return [
                'current_tier' => 1,
                'tier_name' => 'Débutant',
                'progress_percentage' => 0,
            ];
        }

        return [
            'current_tier' => $level->level,
            'tier_name' => $this->getTierName($level->level),
            'progress_percentage' => $level->getProgressPercentage(),
            'points_in_tier' => $level->current_level_xp,
            'points_for_next' => $level->next_level_xp,
        ];
    }

    /**
     * Nom du palier (terminologie accessible)
     */
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

    // ==========================================
    // SOCIAL & COMPARAISONS
    // ==========================================

    /**
     * Comparaison anonyme avec les autres utilisateurs
     */
    private function getAnonymousComparison(User $user): array
    {
        $userStats = $this->getUserQuickStats($user);

        // Statistiques moyennes (cachées)
        $avgStats = Cache::remember('avg_user_stats', 3600, function () {
            return $this->calculateAverageStats();
        });

        $savingsRate = $userStats['savings_rate'] ?? 0;
        $avgSavingsRate = $avgStats['savings_rate'] ?? 10;

        $percentile = $this->calculatePercentile($savingsRate, $avgSavingsRate);

        return [
            'savings_comparison' => [
                'user_rate' => $savingsRate,
                'percentile' => $percentile,
                'message' => $this->getComparisonMessage($percentile),
            ],
            'anonymized' => true, // Toujours anonyme
        ];
    }

    /**
     * Message de comparaison (positif uniquement)
     */
    private function getComparisonMessage(int $percentile): string
    {
        return match (true) {
            $percentile >= 80 => "Vous êtes dans le top 20% des épargnants !",
            $percentile >= 60 => "Vous faites mieux que la majorité",
            $percentile >= 40 => "Vous êtes dans la moyenne, continuez !",
            default => "Chaque effort compte pour progresser",
        };
    }

    // ==========================================
    // CONFIGURATION UI
    // ==========================================

    /**
     * Configuration UI selon le niveau
     */
    private function getUIConfigForLevel(int $level): array
    {
        return [
            'show_xp_bar' => $level >= UserGamingProfile::LEVEL_REWARDS,
            'show_level_badge' => $level >= UserGamingProfile::LEVEL_REWARDS,
            'show_achievements_count' => $level >= UserGamingProfile::LEVEL_REWARDS,
            'show_streak_counter' => $level >= UserGamingProfile::LEVEL_SOCIAL,
            'show_comparison_widget' => $level >= UserGamingProfile::LEVEL_SOCIAL,
            'show_leaderboard_link' => $level >= UserGamingProfile::LEVEL_GAMING,
            'show_challenges_link' => $level >= UserGamingProfile::LEVEL_GAMING,
            'animation_intensity' => match ($level) {
                1 => 'subtle',
                2 => 'moderate',
                3 => 'engaging',
                4 => 'full',
                default => 'subtle',
            },
        ];
    }

    /**
     * Terminologie selon le niveau
     */
    private function getTerminologyForLevel(int $level): array
    {
        if ($level >= UserGamingProfile::LEVEL_GAMING) {
            // Full gaming terminology
            return [
                'points' => 'XP',
                'tier' => 'Niveau',
                'achievement' => 'Succès',
                'streak' => 'Streak',
            ];
        }

        // Terminologie accessible
        return [
            'points' => 'Points',
            'tier' => 'Palier',
            'achievement' => 'Objectif atteint',
            'streak' => 'Série',
        ];
    }

    /**
     * Fonctionnalités disponibles selon le niveau
     */
    private function getFeaturesForLevel(int $level, UserGamingProfile $profile): array
    {
        $features = ['basic_feedback', 'milestones'];

        if ($level >= UserGamingProfile::LEVEL_REWARDS) {
            $features[] = 'points_display';
            $features[] = 'tier_progress';
            $features[] = 'achievements_page';
        }

        if ($level >= UserGamingProfile::LEVEL_SOCIAL) {
            $features[] = 'streak_tracking';
            $features[] = 'anonymous_comparison';
        }

        if ($level >= UserGamingProfile::LEVEL_GAMING) {
            $features[] = 'leaderboard';
            $features[] = 'challenges';
            $features[] = 'full_gaming';
        }

        // Ajouter les fonctionnalités débloquées manuellement
        $unlocked = $profile->unlocked_features ?? [];

        return array_unique(array_merge($features, $unlocked));
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function hasSentRecentFeedback(User $user, string $event): bool
    {
        return UserFeedbackLog::where('user_id', $user->id)
            ->where('trigger_event', $event)
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();
    }

    private function getUserQuickStats(User $user): array
    {
        $cacheKey = "user_quick_stats_{$user->id}";

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $monthStart = now()->startOfMonth();

            $income = $user->transactions()
                ->where('type', 'income')
                ->where('created_at', '>=', $monthStart)
                ->sum('amount');

            $expenses = $user->transactions()
                ->where('type', 'expense')
                ->where('created_at', '>=', $monthStart)
                ->sum('amount');

            $savingsRate = $income > 0 ? (($income - $expenses) / $income) * 100 : 0;

            return [
                'income_this_month' => $income,
                'expenses_this_month' => $expenses,
                'savings_this_month' => $income - $expenses,
                'savings_rate' => round($savingsRate, 1),
                'transactions_this_week' => $user->transactions()
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count(),
            ];
        });
    }

    private function formatResponse(int $level, array $data): array
    {
        // Filtrer les données selon le niveau
        if ($level < UserGamingProfile::LEVEL_REWARDS) {
            unset($data['points']);
        }

        return $data;
    }

    private function getFeatureLabel(string $feature): string
    {
        return match ($feature) {
            'advanced_analytics' => 'Analyses avancées',
            'custom_categories' => 'Catégories personnalisées',
            'export_data' => 'Export des données',
            'ai_suggestions' => 'Suggestions intelligentes',
            default => ucfirst(str_replace('_', ' ', $feature)),
        };
    }

    private function calculatePercentile(float $value, float $average): int
    {
        // Approximation simple
        $ratio = $average > 0 ? $value / $average : 1;
        return min(99, max(1, (int) ($ratio * 50)));
    }

    private function calculateAverageStats(): array
    {
        // À implémenter avec de vraies stats agrégées
        return ['savings_rate' => 12];
    }

    private function getStatsHighlight(array $stats): ?array
    {
        if (($stats['savings_this_month'] ?? 0) > 0) {
            return [
                'type' => 'savings',
                'value' => $stats['savings_this_month'],
                'label' => 'économisés ce mois',
            ];
        }
        return null;
    }

    private function getRecentAchievements(User $user, int $limit): array
    {
        return $user->achievements()
            ->wherePivot('unlocked_at', '>=', now()->subMonth())
            ->orderByPivot('unlocked_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($a) => [
                'name' => $a->name,
                'icon' => $a->icon,
                'unlocked_at' => $a->pivot->unlocked_at,
            ])
            ->toArray();
    }

    private function getStreakData(User $user): array
    {
        $streak = $user->streaks()
            ->where('type', 'daily_activity')
            ->where('is_active', true)
            ->first();

        return [
            'current' => $streak?->current_count ?? 0,
            'best' => $streak?->best_count ?? 0,
            'label' => 'jours d\'activité',
        ];
    }

    private function getLeaderboardPreview(User $user): array
    {
        return ['position' => 42, 'total_users' => 150]; // Placeholder
    }

    private function getActiveChallenges(User $user): array
    {
        return []; // Placeholder
    }
}
