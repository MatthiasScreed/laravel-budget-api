<?php

namespace Database\Seeders;

use App\Models\Milestone;
use App\Models\FeedbackTemplate;
use Illuminate\Database\Seeder;

class ProgressiveGamingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMilestones();
        $this->seedFeedbackTemplates();
    }

    // ==========================================
    // MILESTONES - Paliers financiers
    // ==========================================

    private function seedMilestones(): void
    {
        $milestones = [
            // ========== FINANCIAL - Niveau 1 (Doux) ==========
            [
                'code' => 'first_transaction',
                'category' => 'financial',
                'title' => 'Premier pas',
                'description' => 'Enregistrez votre première transaction',
                'icon' => '✓',
                'conditions' => ['type' => 'transaction_count', 'value' => 1],
                'points_reward' => 10,
                'min_engagement_level' => 1,
                'sort_order' => 1,
            ],
            [
                'code' => 'ten_transactions',
                'category' => 'financial',
                'title' => 'Bonne habitude',
                'description' => 'Enregistrez 10 transactions',
                'icon' => '📊',
                'conditions' => ['type' => 'transaction_count', 'value' => 10],
                'points_reward' => 25,
                'min_engagement_level' => 1,
                'sort_order' => 2,
            ],
            [
                'code' => 'first_income',
                'category' => 'financial',
                'title' => 'Revenu enregistré',
                'description' => 'Enregistrez votre premier revenu',
                'icon' => '💵',
                'conditions' => ['type' => 'income_recorded', 'value' => 1],
                'points_reward' => 15,
                'min_engagement_level' => 1,
                'sort_order' => 3,
            ],

            // ========== SAVINGS - Niveau 1-2 ==========
            [
                'code' => 'savings_100',
                'category' => 'financial',
                'title' => 'Premiers 100€',
                'description' => 'Économisez 100€ ce mois',
                'icon' => '🌱',
                'conditions' => ['type' => 'savings_amount', 'value' => 100, 'period' => 'month'],
                'points_reward' => 50,
                'min_engagement_level' => 1,
                'sort_order' => 10,
            ],
            [
                'code' => 'savings_500',
                'category' => 'financial',
                'title' => 'Épargnant en herbe',
                'description' => 'Économisez 500€ ce mois',
                'icon' => '💰',
                'conditions' => ['type' => 'savings_amount', 'value' => 500, 'period' => 'month'],
                'points_reward' => 100,
                'min_engagement_level' => 2,
                'sort_order' => 11,
            ],
            [
                'code' => 'savings_1000',
                'category' => 'financial',
                'title' => 'Belle économie',
                'description' => 'Économisez 1000€ ce mois',
                'icon' => '🏆',
                'conditions' => ['type' => 'savings_amount', 'value' => 1000, 'period' => 'month'],
                'points_reward' => 150,
                'min_engagement_level' => 2,
                'sort_order' => 12,
            ],
            [
                'code' => 'savings_rate_10',
                'category' => 'financial',
                'title' => 'Taux d\'épargne sain',
                'description' => 'Atteignez un taux d\'épargne de 10%',
                'icon' => '📈',
                'conditions' => ['type' => 'savings_rate', 'value' => 10, 'period' => 'month'],
                'points_reward' => 75,
                'min_engagement_level' => 2,
                'sort_order' => 15,
            ],
            [
                'code' => 'savings_rate_20',
                'category' => 'financial',
                'title' => 'Excellent épargnant',
                'description' => 'Atteignez un taux d\'épargne de 20%',
                'icon' => '🌟',
                'conditions' => ['type' => 'savings_rate', 'value' => 20, 'period' => 'month'],
                'points_reward' => 125,
                'min_engagement_level' => 2,
                'sort_order' => 16,
            ],

            // ========== GOALS - Niveau 1-2 ==========
            [
                'code' => 'first_goal',
                'category' => 'financial',
                'title' => 'Vision claire',
                'description' => 'Créez votre premier objectif financier',
                'icon' => '🎯',
                'conditions' => ['type' => 'goal_created', 'value' => 1],
                'points_reward' => 20,
                'feature_unlock' => 'goal_templates',
                'min_engagement_level' => 1,
                'sort_order' => 20,
            ],
            [
                'code' => 'first_goal_completed',
                'category' => 'financial',
                'title' => 'Objectif atteint !',
                'description' => 'Complétez votre premier objectif',
                'icon' => '🎉',
                'conditions' => ['type' => 'goal_completed', 'value' => 1],
                'points_reward' => 100,
                'min_engagement_level' => 1,
                'sort_order' => 21,
            ],
            [
                'code' => 'three_goals_completed',
                'category' => 'financial',
                'title' => 'Objectifs en série',
                'description' => 'Complétez 3 objectifs',
                'icon' => '🏅',
                'conditions' => ['type' => 'goal_completed', 'value' => 3],
                'points_reward' => 200,
                'min_engagement_level' => 2,
                'sort_order' => 22,
            ],

            // ========== ENGAGEMENT - Niveau 2-3 ==========
            [
                'code' => 'fifty_transactions',
                'category' => 'engagement',
                'title' => 'Utilisateur régulier',
                'description' => 'Enregistrez 50 transactions',
                'icon' => '⭐',
                'conditions' => ['type' => 'transaction_count', 'value' => 50],
                'points_reward' => 75,
                'min_engagement_level' => 2,
                'sort_order' => 30,
            ],
            [
                'code' => 'hundred_transactions',
                'category' => 'engagement',
                'title' => 'Expert du suivi',
                'description' => 'Enregistrez 100 transactions',
                'icon' => '🏆',
                'conditions' => ['type' => 'transaction_count', 'value' => 100],
                'points_reward' => 150,
                'feature_unlock' => 'advanced_analytics',
                'min_engagement_level' => 2,
                'sort_order' => 31,
            ],

            // ========== STREAK - Niveau 3 ==========
            [
                'code' => 'streak_7',
                'category' => 'streak',
                'title' => 'Une semaine !',
                'description' => '7 jours d\'activité consécutifs',
                'icon' => '🔥',
                'conditions' => ['type' => 'consecutive_days', 'value' => 7],
                'points_reward' => 50,
                'min_engagement_level' => 3,
                'sort_order' => 40,
            ],
            [
                'code' => 'streak_30',
                'category' => 'streak',
                'title' => 'Un mois complet !',
                'description' => '30 jours d\'activité consécutifs',
                'icon' => '💪',
                'conditions' => ['type' => 'consecutive_days', 'value' => 30],
                'points_reward' => 200,
                'min_engagement_level' => 3,
                'sort_order' => 41,
            ],

            // ========== DISCOVERY - Niveau 1 ==========
            [
                'code' => 'profile_complete',
                'category' => 'discovery',
                'title' => 'Profil complet',
                'description' => 'Complétez votre profil utilisateur',
                'icon' => '👤',
                'conditions' => ['type' => 'feature_used', 'value' => 'profile_complete'],
                'points_reward' => 15,
                'min_engagement_level' => 1,
                'sort_order' => 50,
            ],
            [
                'code' => 'bank_connected',
                'category' => 'discovery',
                'title' => 'Compte connecté',
                'description' => 'Connectez votre compte bancaire',
                'icon' => '🏦',
                'conditions' => ['type' => 'feature_used', 'value' => 'bank_connected'],
                'points_reward' => 30,
                'min_engagement_level' => 1,
                'sort_order' => 51,
            ],
        ];

        foreach ($milestones as $milestone) {
            Milestone::updateOrCreate(
                ['code' => $milestone['code']],
                $milestone
            );
        }

        $this->command->info('✅ ' . count($milestones) . ' milestones créés');
    }

    // ==========================================
    // FEEDBACK TEMPLATES - Messages contextuels
    // ==========================================

    private function seedFeedbackTemplates(): void
    {
        $templates = [
            // ========== TRANSACTION CREATED - Niveau 1 ==========
            [
                'trigger_event' => 'transaction_created',
                'category' => 'encouragement',
                'title' => 'Enregistré',
                'message' => 'Transaction ajoutée avec succès',
                'icon' => '✓',
                'engagement_level' => 1,
                'tone' => 'neutral',
                'priority' => 1,
            ],
            [
                'trigger_event' => 'transaction_created',
                'category' => 'encouragement',
                'title' => 'Bien joué !',
                'message' => 'Chaque transaction comptée, c\'est un pas vers le contrôle',
                'icon' => '👍',
                'conditions' => ['min_count' => 5],
                'engagement_level' => 1,
                'tone' => 'encouraging',
                'priority' => 2,
            ],

            // ========== TRANSACTION INCOME - Niveau 1-2 ==========
            [
                'trigger_event' => 'transaction_income',
                'category' => 'celebration',
                'title' => 'Revenu enregistré',
                'message' => '{{ amount }}€ de revenu ajouté',
                'icon' => '💵',
                'engagement_level' => 1,
                'tone' => 'neutral',
                'priority' => 1,
            ],
            [
                'trigger_event' => 'transaction_income',
                'category' => 'celebration',
                'title' => 'Belle rentrée !',
                'message' => '{{ amount }}€ ajoutés à votre budget',
                'icon' => '🎉',
                'conditions' => ['min_amount' => 500],
                'engagement_level' => 2,
                'tone' => 'celebratory',
                'priority' => 3,
            ],

            // ========== GOAL PROGRESS - Niveau 1-2 ==========
            [
                'trigger_event' => 'goal_progress',
                'category' => 'encouragement',
                'title' => 'En bonne voie',
                'message' => 'Votre objectif avance : {{ progress }}% atteint',
                'icon' => '📈',
                'engagement_level' => 1,
                'tone' => 'encouraging',
                'priority' => 2,
            ],
            [
                'trigger_event' => 'goal_progress',
                'category' => 'celebration',
                'title' => 'Mi-parcours !',
                'message' => 'Déjà 50% de votre objectif atteint, continuez !',
                'icon' => '🎯',
                'conditions' => ['min_progress' => 50, 'max_progress' => 60],
                'engagement_level' => 2,
                'tone' => 'celebratory',
                'priority' => 5,
            ],
            [
                'trigger_event' => 'goal_progress',
                'category' => 'celebration',
                'title' => 'Presque là !',
                'message' => '90% atteint, plus que quelques efforts !',
                'icon' => '🔥',
                'conditions' => ['min_progress' => 90],
                'engagement_level' => 2,
                'tone' => 'celebratory',
                'priority' => 6,
            ],

            // ========== GOAL COMPLETED - Niveau 1-2 ==========
            [
                'trigger_event' => 'goal_completed',
                'category' => 'celebration',
                'title' => 'Objectif atteint !',
                'message' => 'Félicitations ! Vous avez atteint votre objectif "{{ goal_name }}"',
                'icon' => '🎉',
                'engagement_level' => 1,
                'tone' => 'celebratory',
                'priority' => 10,
            ],

            // ========== SAVINGS POSITIVE - Niveau 1-2 ==========
            [
                'trigger_event' => 'savings_positive',
                'category' => 'celebration',
                'title' => 'Économies ce mois',
                'message' => 'Vous avez économisé {{ amount }}€ ce mois',
                'icon' => '💰',
                'engagement_level' => 1,
                'tone' => 'celebratory',
                'priority' => 3,
            ],
            [
                'trigger_event' => 'savings_positive',
                'category' => 'celebration',
                'title' => 'Excellent mois !',
                'message' => '{{ amount }}€ économisés, vous êtes sur la bonne voie !',
                'icon' => '🌟',
                'conditions' => ['min_amount' => 200],
                'engagement_level' => 2,
                'tone' => 'celebratory',
                'priority' => 5,
            ],

            // ========== STREAK - Niveau 3 ==========
            [
                'trigger_event' => 'streak_continued',
                'category' => 'encouragement',
                'title' => 'Série en cours',
                'message' => '{{ days }} jours de suite, continuez !',
                'icon' => '🔥',
                'engagement_level' => 3,
                'tone' => 'encouraging',
                'priority' => 3,
            ],
            [
                'trigger_event' => 'streak_continued',
                'category' => 'celebration',
                'title' => 'Série impressionnante !',
                'message' => '{{ days }} jours consécutifs ! Vous êtes régulier.',
                'icon' => '💪',
                'conditions' => ['min_days' => 7],
                'engagement_level' => 3,
                'tone' => 'celebratory',
                'priority' => 5,
            ],

            // ========== MILESTONE REACHED - Niveau 1-2 ==========
            [
                'trigger_event' => 'milestone_reached',
                'category' => 'celebration',
                'title' => 'Palier atteint !',
                'message' => '{{ milestone_title }} - Bravo !',
                'icon' => '🏆',
                'engagement_level' => 1,
                'tone' => 'celebratory',
                'priority' => 8,
            ],

            // ========== WEEKLY SUMMARY - Niveau 2 ==========
            [
                'trigger_event' => 'weekly_summary',
                'category' => 'insight',
                'title' => 'Bilan de la semaine',
                'message' => 'Cette semaine : {{ income }}€ de revenus, {{ expenses }}€ de dépenses',
                'icon' => '📊',
                'engagement_level' => 2,
                'tone' => 'informative',
                'priority' => 4,
            ],

            // ========== TIPS - Niveau 1 ==========
            [
                'trigger_event' => 'daily_tip',
                'category' => 'tip',
                'title' => 'Astuce',
                'message' => 'Catégorisez vos dépenses pour mieux les comprendre',
                'icon' => '💡',
                'engagement_level' => 1,
                'tone' => 'informative',
                'priority' => 1,
            ],
            [
                'trigger_event' => 'daily_tip',
                'category' => 'tip',
                'title' => 'Le saviez-vous ?',
                'message' => 'Définir des objectifs augmente vos chances d\'épargner',
                'icon' => '💡',
                'engagement_level' => 1,
                'tone' => 'informative',
                'priority' => 1,
            ],

            // ========== RETURN AFTER ABSENCE - Niveau 1 ==========
            [
                'trigger_event' => 'return_after_absence',
                'category' => 'encouragement',
                'title' => 'Content de vous revoir !',
                'message' => 'Prêt à reprendre le suivi de vos finances ?',
                'icon' => '👋',
                'engagement_level' => 1,
                'tone' => 'encouraging',
                'priority' => 5,
            ],
        ];

        foreach ($templates as $template) {
            FeedbackTemplate::updateOrCreate(
                [
                    'trigger_event' => $template['trigger_event'],
                    'title' => $template['title'],
                ],
                $template
            );
        }

        $this->command->info('✅ ' . count($templates) . ' feedback templates créés');
    }
}
