<?php

namespace Database\Factories;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialGoal>
 */
class FinancialGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $targetAmount = $this->faker->numberBetween(500, 50000);
        $currentAmount = $this->faker->numberBetween(0, $targetAmount * 0.8);

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->randomElement([
                'Vacances d\'été',
                'Nouvelle voiture',
                'Fonds d\'urgence',
                'Appartement',
                'Mariage',
                'Formation',
                'Voyage au Japon',
                'Équipement informatique',
                'Rénovation cuisine'
            ]),
            'description' => $this->faker->sentence(10),
            'target_amount' => $targetAmount,
            'current_amount' => $currentAmount,
            'target_date' => $this->faker->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'start_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['active', 'completed', 'paused']),
            'type' => $this->faker->randomElement([
                'savings', 'debt_payoff', 'investment', 'purchase', 'emergency_fund', 'other'
            ]),
            'priority' => $this->faker->numberBetween(1, 5),
            'color' => $this->faker->randomElement([
                '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'
            ]),
            'icon' => $this->faker->randomElement([
                'piggy-bank', 'car', 'home', 'plane', 'heart', 'graduation-cap', 'laptop'
            ]),
            'monthly_target' => $this->faker->optional(0.7)->randomFloat(2, 50, 1000),
            'is_automatic' => $this->faker->boolean(30),
            'automatic_amount' => $this->faker->optional(0.3)->randomFloat(2, 25, 500),
            'automatic_frequency' => $this->faker->optional(0.3)->randomElement(['weekly', 'monthly', 'quarterly']),
            'notes' => $this->faker->optional(0.6)->paragraph(),
            'is_shared' => $this->faker->boolean(10),
            'tags' => $this->faker->optional(0.5)->randomElements([
                'urgent', 'long-terme', 'famille', 'personnel', 'vacances', 'investissement'
            ], $this->faker->numberBetween(1, 3)),
            'completed_at' => null, // Sera défini si status = 'completed'
        ];
    }

    /**
     * State pour un objectif actif
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'completed_at' => null,
        ]);
    }

    /**
     * State pour un objectif terminé
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'current_amount' => $attributes['target_amount'],
                'completed_at' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            ];
        });
    }

    /**
     * State pour un objectif d'urgence
     */
    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Fonds d\'urgence',
            'type' => 'emergency_fund',
            'priority' => 1,
            'color' => '#EF4444',
            'icon' => 'shield',
        ]);
    }

    /**
     * State pour un objectif de voyage
     */
    public function travel(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Voyage ' . $this->faker->country(),
            'type' => 'savings',
            'icon' => 'plane',
            'color' => '#06B6D4',
            'tags' => ['voyage', 'vacances'],
        ]);
    }

    /**
     * State pour un objectif avec contributions automatiques
     */
    public function withAutoContributions(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_automatic' => true,
            'automatic_amount' => $this->faker->randomFloat(2, 50, 300),
            'automatic_frequency' => 'monthly',
            'next_automatic_date' => now()->addMonth()->format('Y-m-d'),
        ]);
    }
}
