<?php

namespace Database\Factories;

use App\Models\DailyAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DailyAction>
 */
class DailyActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['save', 'spend']);

        return [
            'user_id' => User::factory(),
            'quest_id' => null,
            'type' => $type,
            'amount' => $this->faker->randomFloat(2, 1, 100),
            'reason' => $this->faker->sentence(3),
            'reason_preset' => null,
            'xp_earned' => DailyAction::calculateXp($type),
            'action_date' => today(),
        ];
    }

    /**
     * State pour une action de la veille
     */
    public function yesterday(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_date' => today()->subDay(),
        ]);
    }
}
