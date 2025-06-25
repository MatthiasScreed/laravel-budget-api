<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /** @test */
    public function api_health_check_returns_ok()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => [
                    'database',
                    'gaming_system'
                ]
            ])
            ->assertJson([
                'status' => 'OK'
            ]);
    }

    /** @test */
    public function api_documentation_is_accessible()
    {
        $response = $this->getJson('/api/docs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'api_version',
                'endpoints',
                'authentication',
                'response_format'
            ]);
    }
}
