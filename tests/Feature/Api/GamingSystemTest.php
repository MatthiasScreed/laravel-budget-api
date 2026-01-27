<?php

namespace Tests\Feature\Api;

use App\Models\Achievement;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // 🎯 PLUS BESOIN DE CRÉER DES ACHIEVEMENTS ICI
        // Le TestCase parent les crée déjà avec createTestAchievements()
    }

    /** @test */
    public function user_has_initial_gaming_stats()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/gaming/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'level_info' => [
                        'current_level',
                        'total_xp',
                        'progress_percentage',
                        'title',
                    ],
                    'achievements_count',
                    'recent_achievements',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'level_info' => [
                        'current_level' => 1,
                        'total_xp' => 0,
                    ],
                    'achievements_count' => 0,
                ],
            ]);
    }

    /** @test */
    public function user_can_get_gaming_dashboard()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/gaming/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stats',
                    'recent_achievements',
                    'next_achievements',
                    'activity_summary',
                ],
            ]);
    }

    /** @test */
    public function creating_transaction_gives_xp_and_unlocks_achievement()
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

        $response->assertStatus(201);

        // Vérifier que l'utilisateur a gagné de l'XP
        $this->user->refresh();
        $this->assertGreaterThan(0, $this->user->getTotalXp());

        // Vérifier les achievements
        $achievementsResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/gaming/achievements/unlocked');

        $achievementsResponse->assertStatus(200);
        $achievements = $achievementsResponse->json('data.achievements');
        $this->assertGreaterThan(0, count($achievements));
    }

    /** @test */
    public function user_can_check_achievements_manually()
    {
        // Créer une transaction d'abord
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/gaming/check-achievements');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'unlocked_achievements',
                    'count',
                    'xp_gained',
                ],
            ]);
    }

    /** @test */
    public function user_can_view_all_achievements()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/gaming/achievements');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'achievements',
                    'stats' => [
                        'total_achievements',
                        'unlocked_count',
                        'completion_percentage',
                    ],
                ],
            ]);
    }

    /** @test */
    public function user_can_view_level_information()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/gaming/level');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'level_info' => [
                        'current_level',
                        'total_xp',
                        'progress_percentage',
                        'title',
                    ],
                ],
            ]);
    }

    /** @test */
    public function adding_xp_manually_works()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/gaming/actions/add-xp', [
                'xp' => 100,
                'reason' => 'Test manual XP',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'xp_added',
                    'leveled_up',
                    'new_stats',
                ],
            ]);

        $this->user->refresh();
        $this->assertEquals(100, $this->user->getTotalXp());
    }

    /** @test */
    public function health_check_shows_gaming_system_status()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'services' => [
                    'database',
                    'gaming_system',
                ],
            ]);

        $services = $response->json('services');
        $this->assertEquals('OK', $services['database']);
        // Le gaming_system peut être 'NO_ACHIEVEMENTS' car on n'a créé qu'un seul achievement
        $this->assertContains($services['gaming_system'], ['OK', 'NO_ACHIEVEMENTS']);
    }
}
