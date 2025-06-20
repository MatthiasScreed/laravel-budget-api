<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'type' => $this->faker->randomElement(['income', 'expense']),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'description' => $this->faker->sentence(),
            'transaction_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled']),
            'payment_method' => $this->faker->randomElement(['cash', 'card', 'transfer', 'check']),
            'is_recurring' => $this->faker->boolean(10),
            'is_reconciled' => $this->faker->boolean(80),
            'is_transfer' => $this->faker->boolean(5),
            'source' => 'manual'
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
            'category_id' => Category::factory()->income()
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
            'category_id' => Category::factory()->expense()
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed'
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending'
        ]);
    }

    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_date' => $this->faker->dateTimeBetween('first day of this month', 'now')
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'recurrence_type' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'yearly']),
            'recurrence_interval' => $this->faker->numberBetween(1, 3)
        ]);
    }
}
