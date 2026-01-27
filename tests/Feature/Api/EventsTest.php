<?php

namespace Tests\Feature\Api;

use App\Events\GoalCreated;
use App\Events\TransactionCreated;
use App\Models\Category;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // ✅ Fake les events pour les tester sans déclencher la logique gaming
        Event::fake();
    }

    /** @test */
    public function transaction_creation_triggers_event()
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $transactionData = [
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 50.00,
            'description' => 'Test transaction',
            'transaction_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/transactions', $transactionData);

        // ✅ Le TransactionController utilise maintenant BudgetService qui déclenche l'event
        $response->assertStatus(201);

        Event::assertDispatched(TransactionCreated::class, function ($event) {
            return $event->user->id === $this->user->id &&
                $event->transaction instanceof Transaction;
        });
    }

    /** @test */
    public function goal_creation_triggers_event()
    {
        $goalData = [
            'name' => 'Test Goal',
            'target_amount' => 1000.00,
            'target_date' => now()->addMonths(3)->toDateString(),
            'priority' => 3, // ✅ CORRECTION : utiliser un entier (1-5) au lieu de 'medium'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/financial-goals', $goalData);

        $response->assertStatus(201);

        Event::assertDispatched(GoalCreated::class, function ($event) {
            return $event->user->id === $this->user->id &&
                $event->goal instanceof FinancialGoal;
        });
    }

    /** @test */
    public function transaction_event_contains_correct_data()
    {
        // ✅ Ne pas fake les events pour ce test spécifique
        Event::fake([TransactionCreated::class]);

        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $transactionData = [
            'category_id' => $category->id,
            'type' => 'income',
            'amount' => 150.00,
            'description' => 'Test income transaction',
            'transaction_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/transactions', $transactionData);

        $response->assertStatus(201);

        Event::assertDispatched(TransactionCreated::class, function ($event) use ($transactionData) {
            return $event->user->id === $this->user->id &&
                $event->transaction->amount == $transactionData['amount'] &&
                $event->transaction->type === $transactionData['type'] &&
                $event->transaction->description === $transactionData['description'];
        });
    }

    /** @test */
    public function goal_event_contains_correct_data()
    {
        Event::fake([GoalCreated::class]);

        $goalData = [
            'name' => 'Emergency Fund',
            'target_amount' => 5000.00,
            'target_date' => now()->addYear()->toDateString(),
            'priority' => 5, // ✅ CORRECTION : utiliser un entier (1-5) au lieu de 'high'
            'description' => 'Build emergency fund for unexpected expenses',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/financial-goals', $goalData);

        $response->assertStatus(201);

        Event::assertDispatched(GoalCreated::class, function ($event) use ($goalData) {
            return $event->user->id === $this->user->id &&
                $event->goal->name === $goalData['name'] &&
                $event->goal->target_amount == $goalData['target_amount'] &&
                $event->goal->priority === $goalData['priority'];
        });
    }

    /** @test */
    public function events_are_not_triggered_on_validation_failure()
    {
        // Transaction avec données invalides
        $invalidTransactionData = [
            'category_id' => 99999, // ID inexistant
            'type' => 'invalid_type',
            'amount' => -50.00, // Montant négatif
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/transactions', $invalidTransactionData);

        $response->assertStatus(422); // Erreur de validation

        Event::assertNotDispatched(TransactionCreated::class);

        // Goal avec données invalides
        $invalidGoalData = [
            'name' => '', // Nom vide
            'target_amount' => -1000.00, // Montant négatif
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/financial-goals', $invalidGoalData);

        $response->assertStatus(422); // Erreur de validation

        Event::assertNotDispatched(GoalCreated::class);
    }

    /** @test */
    public function multiple_transactions_trigger_multiple_events()
    {
        Event::fake([TransactionCreated::class]);

        $category = Category::factory()->create(['user_id' => $this->user->id]);

        // Créer 3 transactions
        for ($i = 1; $i <= 3; $i++) {
            $transactionData = [
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 10 * $i,
                'description' => "Test transaction {$i}",
                'transaction_date' => now()->toDateString(),
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/transactions', $transactionData);

            $response->assertStatus(201);
        }

        // Vérifier que 3 events ont été déclenchés
        Event::assertDispatchedTimes(TransactionCreated::class, 3);
    }
}
