<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_health_endpoint_returns_success(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'version',
                'environment',
            ])
            ->assertJson([
                'status' => 'ok',
            ]);
    }

    public function test_detailed_health_check_returns_comprehensive_status(): void
    {
        $response = $this->get('/api/health/detailed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database',
                    'redis',
                    'queue',
                    'storage',
                ],
                'timestamp',
            ]);
    }

    public function test_database_health_check(): void
    {
        DB::connection()->getPdo();

        $response = $this->get('/api/health/detailed');

        $response->assertStatus(200)
            ->assertJsonPath('checks.database.status', 'ok');

        $this->get('/api/health/database')
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'details', 'timestamp']);
    }

    public function test_cache_health_check(): void
    {
        Cache::put('health-test', 'working', 10);

        $response = $this->get('/api/health/detailed');

        $response->assertStatus(200)
            ->assertJsonPath('checks.redis.status', 'ok');

        $this->get('/api/health/cache')
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'details', 'timestamp']);

        Cache::forget('health-test');
    }

    public function test_health_check_includes_performance_metrics(): void
    {
        $startTime = microtime(true);

        $response = $this->get('/api/health/detailed');

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(1000, $responseTime, 'Health check took too long to respond');
    }

    public function test_health_check_with_database_failure(): void
    {
        config(['database.connections.sqlite.database' => '/tmp/nonexistent-db.sqlite']);

        $response = $this->get('/api/health/detailed');

        $this->assertContains($response->status(), [200, 503]);
    }

    public function test_health_check_returns_json_content_type(): void
    {
        $response = $this->get('/api/health');

        $response->assertHeader('Content-Type', 'application/json');
    }
}
