<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense'
        ]);
    }

    /** @test */
    public function user_can_create_transaction()
    {
        $transactionData = [
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 50.00,
            'description' => 'Test transaction',
            'transaction_date' => now()->toDateString()
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/transactions', $transactionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'amount', 'description', 'type',
                    'category' => ['name', 'type']
                ]
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'amount' => 50.00,
            'description' => 'Test transaction'
        ]);
    }

    /** @test */
    public function user_can_list_transactions_with_pagination()
    {
        Transaction::factory(25)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/transactions?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'amount', 'description', 'category']
                ],
                'pagination' => [
                    'current_page', 'per_page', 'total', 'last_page'
                ]
            ]);

        $this->assertEquals(10, count($response->json('data')));
    }

    /** @test */
    public function user_can_filter_transactions()
    {
        // Créer des transactions avec différents types
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'income',
            'amount' => 1000
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'amount' => 50
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/transactions?type=income');

        $response->assertStatus(200);

        $transactions = $response->json('data');
        foreach ($transactions as $transaction) {
            $this->assertEquals('income', $transaction['type']);
        }
    }

    /** @test */
    public function user_cannot_access_other_users_transactions()
    {
        $otherUser = User::factory()->create();
        $otherTransaction = Transaction::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/transactions/{$otherTransaction->id}");

        $response->assertStatus(403);
    }
}
