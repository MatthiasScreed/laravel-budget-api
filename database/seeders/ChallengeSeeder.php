<?php

namespace Database\Seeders;

use App\Models\Challenge;
use Illuminate\Database\Seeder;

class ChallengeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $challenges = [
            [
                'name' => 'Défi épargne de janvier',
                'description' => 'Économisez 500€ en janvier',
                'icon' => 'calendar',
                'type' => 'seasonal',
                'difficulty' => 'medium',
                'criteria' => [
                    'target_amount' => 500,
                    'period' => 'month',
                    'type' => 'savings',
                ],
                'reward_xp' => 150,
                'start_date' => now()->startOfMonth(),
                'end_date' => now()->endOfMonth(),
            ],
            [
                'name' => 'Défi transactions quotidiennes',
                'description' => 'Enregistrez au moins une transaction par jour pendant 7 jours',
                'icon' => 'activity',
                'type' => 'personal',
                'difficulty' => 'easy',
                'criteria' => [
                    'streak_type' => 'daily_transaction',
                    'target_count' => 7,
                ],
                'reward_xp' => 100,
                'start_date' => now(),
                'end_date' => now()->addDays(7),
            ],
            [
                'name' => 'Défi budget équilibré',
                'description' => 'Maintenez un budget équilibré (revenus ≥ dépenses) pendant un mois',
                'icon' => 'balance-scale',
                'type' => 'community',
                'difficulty' => 'hard',
                'criteria' => [
                    'balanced_budget' => true,
                    'period' => 'month',
                ],
                'reward_xp' => 300,
                'start_date' => now()->startOfMonth(),
                'end_date' => now()->endOfMonth(),
            ],
        ];

        foreach ($challenges as $challengeData) {
            Challenge::create($challengeData);
        }

        $this->command->info('Défis créés avec succès !');
    }
}
