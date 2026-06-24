<?php

namespace Tests\Unit\Tank01;

use App\Services\WNBA\Data\WnbaProviderResolver;
use Tests\TestCase;

class WnbaProviderResolverTest extends TestCase
{
    public function test_detects_tank01_player_id_for_gamelog(): void
    {
        $resolver = app(WnbaProviderResolver::class);

        $this->assertSame('tank01', $resolver->resolveGamelogProviderName('1004'));
    }

    public function test_detects_espn_player_id_for_gamelog(): void
    {
        $resolver = app(WnbaProviderResolver::class);

        $this->assertSame('espn', $resolver->resolveGamelogProviderName('4433402'));
    }

    public function test_provider_override_wins_for_gamelog(): void
    {
        $resolver = app(WnbaProviderResolver::class);

        $this->assertSame('espn', $resolver->resolveGamelogProviderName('1004', 'espn'));
    }
}
