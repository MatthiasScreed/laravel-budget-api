<?php

namespace Tests;

use App\Models\Achievement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    /**
     * Indicates whether the default seeder should run before each test.
     */
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Configuration SQLite ultra-stable
        $this->setupTestDatabase();

        // ✅ Configuration des services de test
        $this->setupTestServices();

        // ✅ Désactiver les observers problématiques
        $this->disableObservers();

        $this->createTestAchievements();

        // ✅ DÉSACTIVER la vérification des mots de passe compromis pour les tests
        config([
            'app.env' => 'testing',
            'auth.password_timeout' => 10800,
            // ✅ Si vous utilisez un package de validation de mots de passe, le désactiver
            'password-rules.compromised' => false,
        ]);

        if (class_exists(\Illuminate\Validation\Rules\Password::class)) {
            \Illuminate\Validation\Rules\Password::defaults(function () {
                return \Illuminate\Validation\Rules\Password::min(8);
            });
        }
    }

    /**
     * Configuration de la base de données de test
     */
    protected function setupTestDatabase(): void
    {
        config([
            'database.default' => 'testing',
            'database.connections.testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false, // ✅ Désactiver pour éviter les conflits
                'options' => [
                    \PDO::ATTR_EMULATE_PREPARES => true,
                ],
            ],
        ]);
    }

    /**
     * Configuration des services de test
     */
    protected function setupTestServices(): void
    {
        config([
            'app.env' => 'testing',
            'app.debug' => true,
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'mail.default' => 'array',
            'broadcasting.default' => 'null',
            'sanctum.stateful' => [],
            'sanctum.guard' => ['web'],
            'sanctum.expiration' => null,
            // ✅ Variable pour contrôler la création de UserLevel
            'testing.create_user_level' => false,
        ]);
    }

    /**
     * Désactiver les observers problématiques
     */
    protected function disableObservers(): void
    {
        // Désactiver l'observer User qui crée automatiquement UserLevel
        \App\Models\User::unsetEventDispatcher();
    }

    protected function tearDown(): void
    {
        // Remettre l'event dispatcher
        if (class_exists(\App\Models\User::class)) {
            \App\Models\User::setEventDispatcher(app('events'));
        }

        parent::tearDown();
    }

    protected function createTestAchievements(): void
    {
        Achievement::create([
            'name' => 'Premier pas',
            'slug' => 'first-transaction',
            'description' => 'Créer sa première transaction',
            'icon' => '🎯',
            'color' => '#3B82F6',
            'points' => 10,
            'type' => 'transaction',
            'rarity' => 'common', // ✅ Valeur correcte
            'criteria' => ['min_transactions' => 1],
            'is_active' => true,
        ]);

        Achievement::create([
            'name' => 'Organisé',
            'slug' => 'organized',
            'description' => 'Créer 3 catégories',
            'icon' => '📁',
            'color' => '#10B981',
            'points' => 25,
            'type' => 'milestone',
            'rarity' => 'rare', // 🔧 CHANGER uncommon en rare
            'criteria' => ['min_categories' => 3],
            'is_active' => true,
        ]);
    }
}
