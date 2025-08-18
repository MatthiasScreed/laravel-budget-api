<?php

namespace Database\Factories;

use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->sentence(),
            'icon' => $this->faker->randomElement(['🎯', '🏆', '⭐', '🎉', '💪', '🔥', '⚡', '🌟']),
            'color' => $this->faker->randomElement(['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6']),
            'type' => $this->faker->randomElement(['transaction', 'goal', 'milestone', 'streak']),
            'criteria' => ['min_transactions' => $this->faker->numberBetween(1, 10)],
            'points' => $this->faker->numberBetween(5, 100),
            'rarity' => $this->faker->randomElement(['common', 'rare', 'epic', 'legendary']),
            'is_active' => true,
        ];
    }

    /**
     * Achievement simple pour les tests
     */
    public function simple(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'transaction',
            'criteria' => ['min_transactions' => 1],
            'points' => 10,
            'rarity' => 'common',
            'color' => '#3B82F6'
        ]);
    }

    /**
     * Achievement difficile
     */
    public function rare(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'milestone',
            'criteria' => ['min_transactions' => 100],
            'points' => 500,
            'rarity' => 'rare',
            'color' => '#8B5CF6'
        ]);
    }

    /**
     * Achievement de transaction
     */
    public function transaction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'transaction',
            'criteria' => ['min_transactions' => $this->faker->numberBetween(1, 5)],
            'points' => $this->faker->numberBetween(10, 50),
            'rarity' => 'common'
        ]);
    }
}
