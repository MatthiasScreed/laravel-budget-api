<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('can get gaming summary', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->getJson('/api/gaming/summary');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'level',
                'xp',
                'xp_for_next_level',
                'progress_percent',
                'points',
                'rank',
                'active_streaks_count',
                'achievements_unlocked_count',
                'recent_xp_gained',
            ],
            'message',
        ]);
});

it('requires authentication to get gaming summary', function () {
    $response = $this->getJson('/api/gaming/summary');

    $response->assertStatus(401);
});
