<?php

namespace Modules\Core\Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_liveness_check_returns_ok(): void
    {
        $this->getJson('/api/v1/core/health')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok', 'module' => 'core']);
    }

    public function test_readiness_check_reports_database_redis_and_cache(): void
    {
        $response = $this->getJson('/api/v1/core/health/ready');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'checks' => ['database' => ['ok'], 'redis' => ['ok'], 'cache' => ['ok']],
            ])
            ->assertJson(['status' => 'ok']);
    }
}
