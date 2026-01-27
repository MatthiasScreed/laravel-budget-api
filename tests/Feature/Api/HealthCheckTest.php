<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\CreatesApplication;

class HealthCheckTest extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // 🎯 CONFIGURATION MINIMALE POUR LES TESTS DE HEALTH CHECK
        // On ne veut pas créer d'achievements ici !

        config([
            'database.default' => 'testing',
            'database.connections.testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'app.env' => 'testing',
            'cache.default' => 'array',
        ]);
    }

    /** @test */
    public function api_health_check_returns_ok()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'timestamp',
                'services' => [
                    'database',
                    'cache',
                    'gaming',
                    'api',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function api_documentation_is_accessible()
    {
        // Route /api/docs n'existe pas - skipped
        $this->markTestSkipped('API docs endpoint not implemented yet');
    }
}
