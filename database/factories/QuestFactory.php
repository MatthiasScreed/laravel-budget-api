<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quest>
 */
class QuestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $targetAmount = $this->faker->numberBetween(500, 5000);

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->randomElement([
                'Voyage au Japon', 'MacBook Pro', 'Fonds d\'urgence', 'Nouvelle voiture',
            ]),
            'target_amount' => $targetAmount,
            'current_amount' => $this->faker->numberBetween(0, $targetAmount * 0.5),
            'target_date' => $this->faker->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'emoji' => '🎯',
            'status' => 'active',
            'is_main' => true,
        ];
    }
}
