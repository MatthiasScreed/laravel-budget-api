<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // ✅ Champs obligatoires ajoutés
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'icon' => $this->faker->randomElement([
                'star', 'trophy', 'medal', 'crown', 'fire', 'heart',
                'target', 'check-circle', 'trending-up', 'zap'
            ]),
            'color' => $this->faker->randomElement([
                '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6'
            ]),
            'type' => $this->faker->randomElement([
                'transaction', 'goal', 'streak', 'milestone', 'social'
            ]),
            'criteria' => [
                'min_transactions' => $this->faker->numberBetween(1, 100)
            ],
            'points' => $this->faker->numberBetween(10, 500),
            'rarity' => $this->faker->randomElement([
                'common', 'rare', 'epic', 'legendary'
            ]),
            'is_active' => true
        ];
    }

    /**
     * State pour un achievement simple
     */
    public function simple(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Test Achievement',
            'description' => 'A simple test achievement',
            'type' => 'transaction',
            'criteria' => ['min_transactions' => 1],
            'points' => 10,
            'rarity' => 'common'
        ]);
    }

    /**
     * State pour un achievement de transaction
     */
    public function transaction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'transaction',
            'criteria' => ['min_transactions' => $this->faker->numberBetween(1, 50)]
        ]);
    }

    /**
     * State pour un achievement d'objectif
     */
    public function goal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'goal',
            'criteria' => ['min_goals_completed' => $this->faker->numberBetween(1, 10)]
        ]);
    }

    /**
     * State pour un achievement rare
     */
    public function rare(): static
    {
        return $this->state(fn (array $attributes) => [
            'rarity' => 'rare',
            'points' => $this->faker->numberBetween(50, 200)
        ]);
    }

}
