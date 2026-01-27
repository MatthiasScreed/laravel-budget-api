<?php

namespace Database\Seeders;

use App\Models\GamingReward;
use Illuminate\Database\Seeder;

class GamingRewardsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rewards = [
            [
                'name' => 'Badge Débutant',
                'type' => 'badge',
                'description' => 'Premier badge obtenu',
                'icon' => 'shield',
                'color' => '#6B7280',
                'rarity' => 'common',
                'criteria' => ['level' => 1],
                'is_active' => true,
                'is_repeatable' => false,
            ],
            [
                'name' => 'Maître de l\'Épargne',
                'type' => 'title',
                'description' => 'Titre prestigieux pour les experts',
                'icon' => 'crown',
                'color' => '#F59E0B',
                'rarity' => 'legendary',
                'criteria' => ['level' => 50, 'goals_completed' => 10],
                'is_active' => true,
                'is_repeatable' => false,
            ],
            [
                'name' => 'Bonus XP x2',
                'type' => 'bonus_xp',
                'description' => 'Double XP pendant 24h',
                'icon' => 'zap',
                'color' => '#8B5CF6',
                'rarity' => 'epic',
                'criteria' => ['streak_days' => 30],
                'reward_data' => ['multiplier' => 2, 'duration_hours' => 24],
                'is_active' => true,
                'is_repeatable' => true,
            ],
        ];

        foreach ($rewards as $reward) {
            GamingReward::create($reward);
        }
    }
}
