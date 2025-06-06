<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Configuration par défaut pour tous les tests
    config(['app.debug' => false]); // Pour tester les messages d'erreur en production
});

// =============================================================================
// REGISTRATION TESTS - AVEC VALIDATIONS EXACTES
// =============================================================================

describe('Registration', function () {
    test('user can register successfully with all required fields', function () {
        $userData = [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'password' => 'SecurePassword123!@',
            'password_confirmation' => 'SecurePassword123!@',
            'terms_accepted' => true
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at'],
                    'token',
                    'token_type',
                    'expires_at'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Jean Dupont',
                        'email' => 'jean@example.com'
                    ],
                    'token_type' => 'Bearer'
                ]
            ]);

        // Vérifier en base de données
        $this->assertDatabaseHas('users', [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'currency' => 'EUR', // Valeur par défaut
            'timezone' => 'Europe/Paris',
            'language' => 'fr',
            'is_active' => true
        ]);

        $user = User::where('email', 'jean@example.com')->first();

        // Vérifier le mot de passe hashé
        expect(Hash::check('SecurePassword123!@', $user->password))->toBeTrue();

        // Vérifier email vérifié automatiquement
        expect($user->email_verified_at)->not->toBeNull();

        // Vérifier qu'un token a été créé avec expiration
        $token = $user->tokens()->first();
        expect($token)->not->toBeNull();
        expect($token->expires_at)->toBeGreaterThan(now()->addDays(25));

        // Vérifier que le niveau utilisateur a été créé automatiquement
        expect($user->level)->not->toBeNull();
        expect($user->level->level)->toBe(1);
        expect($user->level->total_xp)->toBe(0);
    });

    test('registration fails without terms acceptance', function () {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'password' => 'SecurePassword123!@',
            'password_confirmation' => 'SecurePassword123!@',
            'terms_accepted' => false
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted'])
            ->assertJson([
                'errors' => [
                    'terms_accepted' => ['Vous devez accepter les conditions d\'utilisation.']
                ]
            ]);
    });

});
