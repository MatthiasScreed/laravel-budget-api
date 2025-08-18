<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Achievement;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamingApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
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
                    'activity_summary'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true
            ]);
    }

    /** @test */
    public function user_can_get_level_information()
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
                        'xp_to_next_level'
                    ],
                    'next_levels',
                    'level_history'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true
            ]);

        // Vérifier les données de base
        $data = $response->json('data.level_info');
        $this->assertEquals(1, $data['current_level']);
        $this->assertEquals(0, $data['total_xp']);
        $this->assertIsNumeric($data['progress_percentage']);
    }

    /** @test */
    public function user_can_get_achievements_list()
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
                        'completion_percentage'
                    ]
                ],
                'message'
            ])
            ->assertJson([
                'success' => true
            ]);

        // Vérifier qu'on a des achievements (créés par TestCase)
        $achievements = $response->json('data.achievements');
        $this->assertIsArray($achievements);
        $this->assertGreaterThanOrEqual(2, count($achievements));

        // Vérifier la structure d'un achievement
        if (count($achievements) > 0) {
            $achievement = $achievements[0];
            $this->assertArrayHasKey('id', $achievement);
            $this->assertArrayHasKey('name', $achievement);
            $this->assertArrayHasKey('description', $achievement);
            $this->assertArrayHasKey('is_unlocked', $achievement);
            $this->assertIsBool($achievement['is_unlocked']);
        }
    }

    /** @test */
    public function achievements_are_marked_correctly_as_unlocked()
    {
        // Récupérer un achievement existant (créé par TestCase)
        $achievement = Achievement::first();
        $this->assertNotNull($achievement, 'Aucun achievement trouvé - TestCase.createTestAchievements() devrait en créer');

        // Débloquer l'achievement pour l'utilisateur
        $this->user->achievements()->attach($achievement->id, [
            'unlocked_at' => now()
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/gaming/achievements');

        $response->assertStatus(200);

        $achievements = $response->json('data.achievements');
        $unlockedAchievement = collect($achievements)->first(function ($item) use ($achievement) {
            return $item['id'] == $achievement->id;
        });

        $this->assertNotNull($unlockedAchievement, 'Achievement débloqué non trouvé dans la réponse');
        $this->assertTrue($unlockedAchievement['is_unlocked']);
        $this->assertNotNull($unlockedAchievement['unlocked_at']);
    }

    /** @test */
    public function user_can_check_achievements_manually()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/gaming/check-achievements');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'unlocked_achievements',
                    'count',
                    'xp_gained'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true
            ]);

        // Pas d'achievements débloqués initialement
        $data = $response->json('data');
        $this->assertIsArray($data['unlocked_achievements']);
        $this->assertEquals(0, $data['count']);
        $this->assertEquals(0, $data['xp_gained']);
    }

    /** @test */
    public function gaming_dashboard_handles_missing_user_level()
    {
        // Supprimer le UserLevel pour tester la création automatique
        $this->user->level()?->delete();

        // Vérifier que le UserLevel est bien supprimé
        $this->user->refresh();
        $this->assertNull($this->user->level);

        // Appeler le dashboard (devrait recréer automatiquement le UserLevel)
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/gaming/dashboard');

        // Vérifier que la requête réussit
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stats',
                    'recent_achievements',
                    'next_achievements',
                    'activity_summary'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true
            ]);

        // Vérifier les données de base
        $stats = $response->json('data.stats');
        $this->assertArrayHasKey('level_info', $stats);
        $this->assertEquals(1, $stats['level_info']['current_level']);
        $this->assertEquals(0, $stats['level_info']['total_xp']);
    }

    /** @test */
    public function user_can_get_gaming_stats()
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
                        'title'
                    ],
                    'achievements_count',
                    'recent_achievements'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'level_info' => [
                        'current_level' => 1,
                        'total_xp' => 0
                    ],
                    'achievements_count' => 0
                ]
            ]);
    }

    /** @test */
    public function user_can_add_xp_manually()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/gaming/actions/add-xp', [
                'xp' => 100,
                'reason' => 'Test manual XP'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'xp_added',
                    'leveled_up',
                    'new_stats'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'xp_added' => 100
                ]
            ]);

        // Vérifier que l'XP a bien été ajouté
        $this->user->refresh();
        $this->assertEquals(100, $this->user->getTotalXp());
    }
}
