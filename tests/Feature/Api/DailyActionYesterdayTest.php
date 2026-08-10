<?php

use App\Models\DailyAction;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('returns only yesterday\'s actions with a summary', function () {
    $user = User::factory()->create();

    DailyAction::factory()->create([
        'user_id' => $user->id,
        'type' => 'save',
        'amount' => 30,
        'action_date' => today(),
    ]);

    DailyAction::factory()->count(2)->yesterday()->create([
        'user_id' => $user->id,
        'type' => 'save',
        'amount' => 20,
    ]);

    DailyAction::factory()->yesterday()->create([
        'user_id' => $user->id,
        'type' => 'spend',
        'amount' => 15,
    ]);

    $response = actingAs($user)->getJson('/api/daily-actions/yesterday');

    $response->assertStatus(200)
        ->assertJsonPath('data.summary.total_saved', 40)
        ->assertJsonPath('data.summary.total_spent', 15)
        ->assertJsonPath('data.summary.actions_count', 3)
        ->assertJsonPath('data.summary.has_acted', true);

    expect($response->json('data.actions'))->toHaveCount(3);
});

it('requires authentication to fetch yesterday\'s actions', function () {
    $response = $this->getJson('/api/daily-actions/yesterday');

    $response->assertStatus(401);
});
