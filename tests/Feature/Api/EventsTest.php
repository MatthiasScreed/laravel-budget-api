<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\FinancialGoal;
use App\Events\TransactionCreated;
use App\Events\GoalCreated;
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
            'transaction_date' => now()->toDateString()
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/transactions', $transactionData);

        Event::assertDispatched(TransactionCreated::class);
    }

    /** @test */
    public function goal_creation_triggers_event()
    {
        $goalData = [
            'name' => 'Test Goal',
            'target_amount' => 1000.00,
            'target_date' => now()->addMonths(3)->toDateString(),
            'priority' => 'medium'
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/financial-goals', $goalData);

        Event::assertDispatched(GoalCreated::class);
    }
}
