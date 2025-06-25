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


/**
 * Helper function pour créer un utilisateur avec niveau gaming
 */
function createUserWithLevel(array $attributes = []): \App\Models\User
{
    $user = \App\Models\User::factory()->create($attributes);

    if (!$user->level) {
        $user->level()->create([
            'level' => 1,
            'total_xp' => 0,
            'current_level_xp' => 0,
            'next_level_xp' => 100
        ]);
    }

    return $user->fresh('level');
}

/**
 * Helper function pour vérifier les structures JSON gaming
 */
function assertGamingStructure(\Illuminate\Testing\TestResponse $response): void
{
    $response->assertJsonStructure([
        'success',
        'data' => [
            'level_info' => [
                'current_level',
                'total_xp',
                'progress_percentage'
            ]
        ]
    ]);
}

/**
 * Helper function pour débugger les réponses de test
 */
function debugResponse(\Illuminate\Testing\TestResponse $response): void
{
    dump([
        'status' => $response->status(),
        'headers' => $response->headers->all(),
        'content' => $response->getContent()
    ]);
}
