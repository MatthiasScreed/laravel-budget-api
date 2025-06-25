<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Transaction;
use App\Models\FinancialGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function user_can_get_dashboard_stats()
    {
        // Créer quelques données de test
        Transaction::factory(5)->create(['user_id' => $this->user->id]);
        FinancialGoal::factory(2)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'financial' => [
                        'balance', 'monthly_income', 'monthly_expenses'
                    ],
                    'goals' => [
                        'total_goals', 'active_goals', 'total_saved'
                    ],
                    'gaming' => [
                        'level', 'total_xp', 'achievements_count'
                    ],
                    'recent_activity'
                ]
            ]);
    }
}
