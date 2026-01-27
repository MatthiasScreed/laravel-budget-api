<?php

namespace Database\Factories;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StreakFactory extends Factory
{
    protected $model = Streak::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement([
                Streak::TYPE_DAILY_LOGIN,
                Streak::TYPE_DAILY_TRANSACTION,
                Streak::TYPE_WEEKLY_BUDGET,
                Streak::TYPE_MONTHLY_SAVING,
            ]),
            'current_count' => $this->faker->numberBetween(0, 30),
            'best_count' => $this->faker->numberBetween(0, 100),
            'last_activity_date' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'is_active' => $this->faker->boolean(80),
            'bonus_claimed_at' => $this->faker->optional(0.3)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'last_activity_date' => now(),
        ]);
    }

    public function dailyLogin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Streak::TYPE_DAILY_LOGIN,
        ]);
    }

    public function dailyTransaction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Streak::TYPE_DAILY_TRANSACTION,
        ]);
    }

    public function withBonusAvailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_count' => 7, // Multiple de 7 pour le bonus
            'best_count' => 7,
            'bonus_claimed_at' => null,
            'is_active' => true,
        ]);
    }

    public function longStreak(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_count' => $this->faker->numberBetween(30, 100),
            'best_count' => $this->faker->numberBetween(50, 365),
            'is_active' => true,
        ]);
    }
}
