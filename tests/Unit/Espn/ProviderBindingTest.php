<?php

namespace Tests\Unit\Espn;

use App\Contracts\WnbaStatsProvider;
use App\Services\WNBA\Data\Providers\EspnWnbaProvider;
use App\Services\WNBA\Data\WnbaProviderResolver;
use Tests\TestCase;

class ProviderBindingTest extends TestCase
{
    public function test_espn_provider_is_bound_when_configured(): void
    {
        config(['wnba.data_source.provider' => 'espn']);

        app()->forgetInstance(WnbaStatsProvider::class);

        $this->assertInstanceOf(EspnWnbaProvider::class, app(WnbaStatsProvider::class));
    }

    public function test_resolver_selects_incremental_provider(): void
    {
        config([
            'wnba.data_source.provider' => 'tank01',
            'wnba.data_source.routing.incremental' => 'espn',
        ]);

        $resolver = app(WnbaProviderResolver::class);

        $this->assertSame('espn', $resolver->resolveName('incremental'));
        $this->assertInstanceOf(EspnWnbaProvider::class, $resolver->resolve('incremental'));
    }

    public function test_resolver_selects_pbp_provider(): void
    {
        config([
            'wnba.data_source.routing.play_by_play' => 'sportsdataverse',
        ]);

        $this->assertSame('sportsdataverse', app(WnbaProviderResolver::class)->resolveName('play_by_play'));
    }
}
