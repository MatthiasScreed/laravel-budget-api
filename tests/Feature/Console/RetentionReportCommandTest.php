<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('reports day-1 retention for a returning user', function () {
    User::factory()->create([
        'created_at' => Carbon::today()->subDay()->setTime(10, 0),
        'last_activity_at' => Carbon::today(),
    ]);

    $this->artisan('retention:report')
        ->expectsOutputToContain('Day-1 retention : 1/1 (100%)')
        ->assertSuccessful();
});

it('reports day-1 retention for a user who did not come back', function () {
    User::factory()->create([
        'created_at' => Carbon::today()->subDay()->setTime(10, 0),
        'last_activity_at' => null,
    ]);

    $this->artisan('retention:report')
        ->expectsOutputToContain('Day-1 retention : 0/1 (0%)')
        ->assertSuccessful();
});

it('does not count activity on the signup day itself as a return', function () {
    User::factory()->create([
        'created_at' => Carbon::today()->subDay()->setTime(10, 0),
        'last_activity_at' => Carbon::today()->subDay()->setTime(18, 0),
    ]);

    $this->artisan('retention:report')
        ->expectsOutputToContain('Day-1 retention : 0/1 (0%)')
        ->assertSuccessful();
});

it('ignores users outside the day-1 and day-7 cohorts', function () {
    User::factory()->create([
        'created_at' => Carbon::today()->subDays(3),
        'last_activity_at' => Carbon::today(),
    ]);

    $this->artisan('retention:report')
        ->expectsOutputToContain('Day-1 retention : 0/0 (0%)')
        ->expectsOutputToContain('Day-7 retention : 0/0 (0%)')
        ->assertSuccessful();
});

it('reports day-7 retention for a returning user', function () {
    User::factory()->create([
        'created_at' => Carbon::today()->subDays(7)->setTime(9, 0),
        'last_activity_at' => Carbon::today(),
    ]);

    $this->artisan('retention:report')
        ->expectsOutputToContain('Day-7 retention : 1/1 (100%)')
        ->assertSuccessful();
});
