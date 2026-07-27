<?php

namespace Tests\Unit\Services\WNBA\Agents;

use App\Services\WNBA\Agents\AgentResponseCache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AgentResponseCacheTest extends TestCase
{
    public function test_clear_flushes_application_cache(): void
    {
        Cache::put('agent_response_cache_probe', 'stale', 3600);

        $ok = AgentResponseCache::clear('unit_test');

        $this->assertTrue($ok);
        $this->assertFalse(Cache::has('agent_response_cache_probe'));
    }

    public function test_clear_returns_false_when_artisan_throws(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('cache:clear')
            ->andThrow(new \RuntimeException('boom'));

        $this->assertFalse(AgentResponseCache::clear('unit_test_fail'));
    }
}
