<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

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
}
