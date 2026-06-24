<?php

namespace Tests\Unit\Tank01;

use App\Contracts\OddsProvider;
use App\Contracts\WnbaStatsProvider;
use App\Services\Odds\Providers\Tank01OddsProvider;
use App\Services\WNBA\Data\Providers\Tank01WnbaProvider;
use Tests\TestCase;

class ProviderBindingTest extends TestCase
{
    public function test_tank01_providers_are_bound_by_default(): void
    {
        config([
            'wnba.data_source.provider' => 'tank01',
            'odds-api.provider' => 'tank01',
        ]);

        app()->forgetInstance(WnbaStatsProvider::class);
        app()->forgetInstance(OddsProvider::class);

        $this->assertInstanceOf(Tank01WnbaProvider::class, app(WnbaStatsProvider::class));
        $this->assertInstanceOf(Tank01OddsProvider::class, app(OddsProvider::class));
    }
}
