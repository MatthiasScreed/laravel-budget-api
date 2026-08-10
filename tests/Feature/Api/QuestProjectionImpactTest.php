<?php

use App\Models\DailyAction;
use App\Models\Quest;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('calculates the days saved when simulating a save contribution', function () {
    $user = User::factory()->create();
    $quest = Quest::factory()->create([
        'user_id' => $user->id,
        'target_amount' => 1000,
        'current_amount' => 200,
    ]);

    DailyAction::factory()->count(3)->create([
        'user_id' => $user->id,
        'quest_id' => $quest->id,
        'type' => 'save',
        'amount' => 50,
    ]);

    $response = actingAs($user)
        ->getJson("/api/quests/{$quest->id}/projection-impact?amount=200&type=save");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'amount',
                'type',
                'current_projected_date',
                'simulated_projected_date',
                'days_saved',
            ],
        ]);

    expect($response->json('data.days_saved'))->toBeGreaterThanOrEqual(0);
});

it('rejects a projection impact request for a quest owned by another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $quest = Quest::factory()->create(['user_id' => $owner->id]);

    $response = actingAs($intruder)
        ->getJson("/api/quests/{$quest->id}/projection-impact?amount=50&type=save");

    $response->assertStatus(403);
});

it('validates the projection impact request payload', function () {
    $user = User::factory()->create();
    $quest = Quest::factory()->create(['user_id' => $user->id]);

    $response = actingAs($user)
        ->getJson("/api/quests/{$quest->id}/projection-impact?amount=-5&type=invalid");

    $response->assertStatus(422);
});
