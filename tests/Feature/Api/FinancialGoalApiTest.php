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
            'priority' => 2, // Changé de 'high' vers un nombre (1-5)
            'type' => 'savings',
            'color' => '#3B82F6'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/financial-goals', $goalData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'name', 'target_amount', 'current_amount',
                    'progress_percentage', 'days_remaining'
                ],
                'message'
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
        // ✅ Utiliser la factory complète avec un nom
        $goal = FinancialGoal::factory()->active()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Goal', // ✅ Ajout du nom requis
            'target_amount' => 1000,
            'current_amount' => 0
        ]);

        $contributionData = [
            'amount' => 100.00,
            'description' => 'Première contribution'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/financial-goals/{$goal->id}/contributions", $contributionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'contribution',
                    'goal'
                ],
                'message'
            ]);

        // Vérifier que l'objectif a été mis à jour
        $goal->refresh();
        $this->assertEquals(100.00, $goal->current_amount);
    }

    /** @test */
    public function user_can_get_goals_statistics()
    {
        // ✅ Créer des objectifs avec différents statuts
        FinancialGoal::factory()->active()->count(2)->create([
            'user_id' => $this->user->id
        ]);

        FinancialGoal::factory()->completed()->create([
            'user_id' => $this->user->id
        ]);

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
                ],
                'message'
            ]);

        // Vérifier les valeurs
        $data = $response->json('data');
        $this->assertEquals(3, $data['total_goals']);
        $this->assertEquals(2, $data['active_goals']);
        $this->assertEquals(1, $data['completed_goals']);
    }

    /** @test */
    public function user_can_get_goal_contributions()
    {
        $goal = FinancialGoal::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        // Ajouter une contribution
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/financial-goals/{$goal->id}/contributions", [
                'amount' => 50.00,
                'description' => 'Test contribution'
            ]);

        // Récupérer les contributions
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/financial-goals/{$goal->id}/contributions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'goal',
                    'contributions',
                    'total_contributions',
                    'contributions_count'
                ],
                'message'
            ]);
    }

    /** @test */
    public function user_cannot_access_other_user_goals()
    {
        $otherUser = User::factory()->create();
        $goal = FinancialGoal::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/financial-goals/{$goal->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function goal_marked_as_completed_when_target_reached()
    {
        $goal = FinancialGoal::factory()->active()->create([
            'user_id' => $this->user->id,
            'target_amount' => 100,
            'current_amount' => 80
        ]);

        // Ajouter une contribution qui atteint le target
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/financial-goals/{$goal->id}/contributions", [
                'amount' => 20.00,
                'description' => 'Final contribution'
            ]);

        $response->assertStatus(201);

        // Vérifier que l'objectif est marqué comme terminé
        $goal->refresh();
        $this->assertEquals('completed', $goal->status);
        $this->assertNotNull($goal->completed_at);
    }
}
