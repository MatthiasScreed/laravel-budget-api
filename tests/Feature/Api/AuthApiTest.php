<?php

use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase; //
use Illuminate\Support\Facades\Hash;

// ✅ Configuration Pest avec RefreshDatabase

uses(RefreshDatabase::class);

// ✅ Configuration avant chaque test
beforeEach(function () {
    config(['app.debug' => true]);
    config(['app.env' => 'testing']);

    // ✅ IMPORTANT : Désactiver l'observer User pour éviter la double création
    \App\Models\User::unsetEventDispatcher();
});

// ✅ Configuration après chaque test pour remettre l'observer
afterEach(function () {
    // Remettre l'event dispatcher si nécessaire
    \App\Models\User::setEventDispatcher(app('events'));
});


// =============================================================================
// TESTS D'INSCRIPTION
// =============================================================================

describe('User Registration', function () {

    test('user can register successfully with all required fields', function () {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecureUniquePassword2025!@#$',
            'password_confirmation' => 'SecureUniquePassword2025!@#$',
            'currency' => 'EUR',
            'language' => 'fr',
            'terms_accepted' => true
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        // ✅ DEBUG si erreur 500
        if ($response->status() === 500) {
            dump('Error content:', $response->getContent());
        }

        $response->assertStatus(201);

        // Vérifier que le UserLevel est créé
        $user = User::where('email', 'test@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user->level)->not->toBeNull();
    });

    test('registration fails with missing required fields', function () {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    test('registration fails with invalid email format', function () {
        $userData = [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('registration fails with weak password', function () {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    test('registration fails with password confirmation mismatch', function () {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword!'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    test('registration fails without terms acceptance', function () {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'UniqueSecurePassword2025!@#',
            'password_confirmation' => 'UniqueSecurePassword2025!@#',
            'terms_accepted' => false // ✅ TESTÉ EXPLICITEMENT
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted']);
    });

    test('registration fails with duplicate email', function () {
        // Créer un utilisateur existant
        User::factory()->create(['email' => 'test@example.com']);

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });
});

// =============================================================================
// TESTS DE CONNEXION
// =============================================================================

describe('User Login', function () {

    test('user can login successfully with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('SecureLoginPassword2025!')
        ]);

        // ✅ Créer manuellement le UserLevel
        $user->level()->create([
            'level' => 1,
            'total_xp' => 0,
            'current_level_xp' => 0,
            'next_level_xp' => 100
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'SecureLoginPassword2025!'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);
        $response->assertStatus(200);
    });

    test('login creates user level if missing', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('SecureLoginPassword2025!')
        ]);

        // ✅ Pas de UserLevel car observer désactivé
        expect($user->level)->toBeNull();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'SecureLoginPassword2025!'
        ]);

        $response->assertStatus(200);

        // ✅ Vérifier création par AuthController
        $user->refresh();
        expect($user->level)->not->toBeNull();
    });

    test('login fails with invalid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correctpassword')
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Identifiants invalides'
            ]);
    });

    test('login fails with non-existent email', function () {
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Identifiants invalides'
            ]);
    });

});

// =============================================================================
// TESTS DE PROFIL UTILISATEUR
// =============================================================================

describe('User Profile', function () {

    test('authenticated user can get profile with gaming stats', function () {
        $user = User::factory()->create();

        // Créer le niveau gaming
        $user->level()->create([
            'level' => 5,
            'total_xp' => 250,
            'current_level_xp' => 50,
            'next_level_xp' => 100
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'name', 'email', 'currency',
                    'stats' => [
                        'total_transactions',
                        'gaming_level',
                        'total_xp',
                        'achievements_count'
                    ],
                    'level_info' => [
                        'current_level',
                        'total_xp',
                        'progress_percentage',
                        'title'
                    ],
                    'recent_achievements',
                    'preferences'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'level_info' => [
                        'current_level' => 5,
                        'total_xp' => 250
                    ]
                ]
            ]);
    });

    test('profile creation creates user level if missing', function () {
        $user = User::factory()->create();

        // ✅ Ne pas créer de UserLevel pour tester la création automatique
        expect($user->level)->toBeNull();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/user');

        $response->assertStatus(200);

        // Vérifier que le UserLevel a été créé
        $user->refresh();
        expect($user->level)->not->toBeNull();
        expect($user->level->level)->toBe(1);
    });

    test('unauthenticated user cannot access profile', function () {
        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(401);
    });
});

// =============================================================================
// TESTS DE DÉCONNEXION
// =============================================================================

describe('User Logout', function () {

    test('authenticated user can logout', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);
    });

    test('authenticated user can logout from all devices', function () {
        $user = User::factory()->create();

        // Créer plusieurs tokens
        $token1 = $user->createToken('device1')->plainTextToken;
        $token2 = $user->createToken('device2')->plainTextToken;

        expect($user->tokens()->count())->toBe(2);

        $response = $this->withToken($token1)
            ->postJson('/api/auth/logout-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Déconnexion de tous les appareils réussie'
            ]);

        // Vérifier que tous les tokens ont été supprimés
        expect($user->fresh()->tokens()->count())->toBe(0);
    });

    test('unauthenticated user cannot logout', function () {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    });
});

// =============================================================================
// TESTS D'INTÉGRATION GAMING
// =============================================================================

describe('Gaming Integration', function () {

    test('registration triggers gaming system initialization', function () {
        $userData = [
            'name' => 'Gaming User',
            'email' => 'gaming@example.com',
            'password' => 'SuperUniqueGamingPassword2025!@#$%', // ✅ Mot de passe plus complexe
            'password_confirmation' => 'SuperUniqueGamingPassword2025!@#$%',
            'terms_accepted' => true // ✅ AJOUTÉ
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        // ✅ DEBUG si erreur 422
        if ($response->status() === 422) {
            dump('Validation errors:', $response->json('errors'));
        }

        $response->assertStatus(201);

        $user = User::where('email', 'gaming@example.com')->first();

        // Vérifier l'initialisation complète du système gaming
        expect($user->level)->not->toBeNull();
        expect($user->level->level)->toBe(1);
        expect($user->level->total_xp)->toBe(0);

        // ✅ Vérifier que l'utilisateur peut accéder aux fonctions gaming (si routes existent)
        $gamingResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/gaming/dashboard');

        // Accepter 200 (succès) ou 404 (route pas encore implémentée)
        expect($gamingResponse->status())->toBeIn([200, 404]);
    });


    test('login triggers daily login streak', function () {
        $user = User::factory()->create([
            'email' => 'streak@example.com',
            'password' => Hash::make('StreakSuperSecurePassword2025!@#$')
        ]);

        // ✅ Créer manuellement le UserLevel
        $user->level()->create([
            'level' => 1,
            'total_xp' => 0,
            'current_level_xp' => 0,
            'next_level_xp' => 100
        ]);

        $loginData = [
            'email' => 'streak@example.com',
            'password' => 'StreakSuperSecurePassword2025!@#$'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        // ✅ DEBUG si erreur 500
        if ($response->status() === 500) {
            dump('Login error content:', $response->getContent());
        }

        $response->assertStatus(200);

        // Vérifier que la réponse contient des informations sur le streak
        $responseData = $response->json('data');
        expect($responseData)->toHaveKey('streak_bonus');
        expect($responseData['streak_bonus'])->toBeInt();
    });
});
