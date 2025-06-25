<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\FinancialGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialGoalApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function user_can_create_financial_goal()
    {
        $goalData = [
            'name' => 'Vacances d\'été',
            'description' => 'Économiser pour les vacances',
            'target_amount' => 2000.00,
            'target_date' => now()->addMonths(6)->toDateString(),
            'priority' => 'high'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/financial-goals', $goalData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'name', 'target_amount', 'current_amount',
                    'progress_percentage', 'days_remaining'
                ]
            ]);

        $this->assertDatabaseHas('financial_goals', [
            'user_id' => $this->user->id,
            'name' => 'Vacances d\'été',
            'target_amount' => 2000.00
        ]);
    }

    /** @test */
    public function user_can_add_contribution_to_goal()
    {
        $goal = FinancialGoal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 1000,
            'current_amount' => 0
        ]);

        $contributionData = [
            'amount' => 100.00,
            'description' => 'Première contribution'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/financial-goals/{$goal->id}/contributions", $contributionData);

        $response->assertStatus(201);

        // Vérifier que l'objectif a été mis à jour
        $goal->refresh();
        $this->assertEquals(100.00, $goal->current_amount);
    }

    /** @test */
    public function user_can_get_goals_statistics()
    {
        FinancialGoal::factory(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/financial-goals/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_goals',
                    'active_goals',
                    'completed_goals',
                    'total_target_amount',
                    'completion_rate'
                ]
            ]);
    }
}
