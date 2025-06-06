<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'Premier pas',
                'slug' => 'first-transaction',
                'description' => 'Enregistrer votre premier transaction',
                'icon' => 'play-circle',
                'color' => '#10B981',
                'type' => 'transaction',
                'criteria' => ['min_transaction' => 1],
                'points' => 10,
                'rarity' => 'common'
            ],
            [
                'name' => 'Actif régulier',
                'slug' => 'active-user',
                'description' => 'Enregistrer 10 transactions',
                'icon' => 'activity',
                'color' => '#3B82F6',
                'type' => 'transaction',
                'criteria' => ['min_transactions' => 10],
                'points' => 25,
                'rarity' => 'common'
            ],
            [
                'name' => 'Expert des finances',
                'slug' => 'finance-expert',
                'description' => 'Enregistrer 100 transactions',
                'icon' => 'trending-up',
                'color' => '#8B5CF6',
                'type' => 'transaction',
                'criteria' => ['min_transactions' => 100],
                'points' => 100,
                'rarity' => 'epic'
            ],
            // Succès d'objectifs
            [
                'name' => 'Planificateur',
                'slug' => 'planner',
                'description' => 'Créer votre premier objectif financier',
                'icon' => 'target',
                'color' => '#F59E0B',
                'type' => 'goal',
                'criteria' => ['min_goals_created' => 1],
                'points' => 15,
                'rarity' => 'common'
            ],
            [
                'name' => 'Réalisateur',
                'slug' => 'achiever',
                'description' => 'Atteindre votre premier objectif',
                'icon' => 'check-circle',
                'color' => '#059669',
                'type' => 'goal',
                'criteria' => ['min_goals_completed' => 1],
                'points' => 50,
                'rarity' => 'rare'
            ],
            [
                'name' => 'Maître des objectifs',
                'slug' => 'goal-master',
                'description' => 'Atteindre 5 objectifs financiers',
                'icon' => 'crown',
                'color' => '#DC2626',
                'type' => 'goal',
                'criteria' => ['min_goals_completed' => 5],
                'points' => 200,
                'rarity' => 'legendary'
            ],

            // Succès de séries
            [
                'name' => 'Début de série',
                'slug' => 'streak-starter',
                'description' => 'Maintenir une série de 3 jours',
                'icon' => 'flame',
                'color' => '#F97316',
                'type' => 'streak',
                'criteria' => ['streak_type' => 'daily_transaction', 'min_count' => 3],
                'points' => 20,
                'rarity' => 'common'
            ],
            [
                'name' => 'Régularité',
                'slug' => 'consistency',
                'description' => 'Maintenir une série de 7 jours',
                'icon' => 'calendar',
                'color' => '#7C3AED',
                'type' => 'streak',
                'criteria' => ['streak_type' => 'daily_transaction', 'min_count' => 7],
                'points' => 50,
                'rarity' => 'rare'
            ],
            [
                'name' => 'Champion de la régularité',
                'slug' => 'streak-champion',
                'description' => 'Maintenir une série de 30 jours',
                'icon' => 'award',
                'color' => '#DC2626',
                'type' => 'streak',
                'criteria' => ['streak_type' => 'daily_transaction', 'min_count' => 30],
                'points' => 200,
                'rarity' => 'epic'
            ],

            // Succès d'étapes
            [
                'name' => 'Épargnant débutant',
                'slug' => 'beginner-saver',
                'description' => 'Épargner 1000€ au total',
                'icon' => 'piggy-bank',
                'color' => '#10B981',
                'type' => 'milestone',
                'criteria' => ['min_savings_amount' => 1000],
                'points' => 30,
                'rarity' => 'common'
            ],
            [
                'name' => 'Épargnant sérieux',
                'slug' => 'serious-saver',
                'description' => 'Épargner 5000€ au total',
                'icon' => 'dollar-sign',
                'color' => '#3B82F6',
                'type' => 'milestone',
                'criteria' => ['min_savings_amount' => 5000],
                'points' => 75,
                'rarity' => 'rare'
            ],
            [
                'name' => 'Maître épargnant',
                'slug' => 'master-saver',
                'description' => 'Épargner 20000€ au total',
                'icon' => 'trending-up',
                'color' => '#8B5CF6',
                'type' => 'milestone',
                'criteria' => ['min_savings_amount' => 20000],
                'points' => 300,
                'rarity' => 'legendary'
            ]

        ];

        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(
                ['slug' => $achievement['slug']],
                $achievement
            );
        }

        $this->command->info('Succès créés avec succès !');
    }
}
