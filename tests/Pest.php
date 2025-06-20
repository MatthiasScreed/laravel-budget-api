<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use App\Models\User;
use Tests\TestCase;

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// ✅ FUNCTIONS HELPER POUR LES TESTS
function createTestUser(array $attributes = []): \App\Models\User
{
    return \App\Models\User::factory()->create($attributes);
}

function createTestStreak(\App\Models\User $user, string $type, array $attributes = []): \App\Models\Streak
{
    return \App\Models\Streak::factory()->create(array_merge([
        'user_id' => $user->id,
        'type' => $type,
    ], $attributes));
}


function actingAsTestUser(?array $attributes = null): \App\Models\User
{
    $user = createTestUser($attributes ?? []);
    \Laravel\Sanctum\Sanctum::actingAs($user);
    return $user;
}

function createAuthenticatedUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    \Laravel\Sanctum\Sanctum::actingAs($user);
    return $user;
}

function createStreakForUser(User $user, string $type, array $attributes = []): \App\Models\Streak
{
    return \App\Models\Streak::factory()->create(array_merge([
        'user_id' => $user->id,
        'type' => $type,
    ], $attributes));
}
