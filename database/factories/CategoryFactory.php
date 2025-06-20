<?php

namespace Database\Factories;

use App\Models\Category;  // ✅ AJOUT DE L'IMPORT MANQUANT
use App\Models\User;      // ✅ AJOUT DE L'IMPORT MANQUANT
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->randomElement([
                'Alimentation',
                'Transport',
                'Loisirs',
                'Santé',
                'Logement',
                'Vêtements',
                'Education',
                'Restaurants',
                'Sport',
                'Technologie'
            ]),
            'description' => $this->faker->optional()->sentence(),
            'type' => $this->faker->randomElement(['income', 'expense']),
            'color' => $this->faker->hexColor(),
            'icon' => $this->faker->randomElement([
                'shopping-cart',
                'car',
                'home',
                'heart',
                'gamepad-2',
                'utensils',
                'shirt',
                'book',
                'dumbbell',
                'laptop'
            ]),
            'is_active' => $this->faker->boolean(90), // 90% actives
            'is_system' => false,
            'sort_order' => $this->faker->numberBetween(0, 100)
        ];
    }

    /**
     * Indicate that the category is an income category
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
            'name' => $this->faker->randomElement([
                'Salaire',
                'Freelance',
                'Investissements',
                'Vente',
                'Prime',
                'Allocation',
                'Pension',
                'Loyer perçu'
            ])
        ]);
    }

    /**
     * Indicate that the category is an expense category
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
            'name' => $this->faker->randomElement([
                'Alimentation',
                'Transport',
                'Loisirs',
                'Santé',
                'Logement',
                'Vêtements',
                'Education',
                'Restaurants'
            ])
        ]);
    }

    /**
     * Indicate that the category is a system category
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
            'user_id' => null, // System categories are global
        ]);
    }

    /**
     * Indicate that the category is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
