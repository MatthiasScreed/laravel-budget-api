<?php

namespace Database\Seeders;

use App\Models\DailyChallenge;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DailyChallengesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $today = Carbon::today();

        $challenges = [
            [
                'challenge_date' => $today,
                'type' => 'transaction',
                'title' => 'Enregistrer 3 transactions',
                'description' => 'Ajoutez 3 transactions à votre budget aujourd\'hui',
                'criteria' => ['transaction_count' => 3],
                'reward_xp' => 50,
                'difficulty' => 'easy',
                'is_global' => true,
                'is_active' => true,
            ],
            [
                'challenge_date' => $today,
                'type' => 'goal',
                'title' => 'Contribuer à un objectif',
                'description' => 'Ajoutez de l\'argent à l\'un de vos objectifs',
                'criteria' => ['goal_contribution' => true],
                'reward_xp' => 75,
                'difficulty' => 'medium',
                'is_global' => true,
                'is_active' => true,
            ],
            [
                'challenge_date' => $today,
                'type' => 'streak',
                'title' => 'Maintenir une série',
                'description' => 'Gardez votre série de connexion active',
                'criteria' => ['maintain_streak' => true],
                'reward_xp' => 25,
                'difficulty' => 'easy',
                'is_global' => true,
                'is_active' => true,
            ]
        ];

        foreach ($challenges as $challenge) {
            DailyChallenge::create($challenge);
        }

    }
}
