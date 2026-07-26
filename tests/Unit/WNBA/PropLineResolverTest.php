<?php

namespace Tests\Unit\WNBA;

use App\Services\WNBA\Predictions\PropLineResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PropLineResolverTest extends TestCase
{
    private PropLineResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PropLineResolver;
    }

    #[Test]
    public function it_prefers_live_book_line_over_player_averages(): void
    {
        $result = $this->resolver->resolve(
            ['available' => true, 'line' => 19.5],
            ['suggested_line' => 22.5, 'recent_average' => 22.0, 'season_average' => 21.0]
        );

        $this->assertSame(19.5, $result['line']);
        $this->assertSame('odds_api', $result['source']);
    }

    #[Test]
    public function it_falls_back_to_player_average_suggested_line(): void
    {
        $result = $this->resolver->resolve(
            ['available' => false, 'line' => null],
            ['suggested_line' => 11.5, 'recent_average' => 12.0, 'season_average' => 11.0]
        );

        $this->assertSame(11.5, $result['line']);
        $this->assertSame('player_average', $result['source']);
    }

    #[Test]
    public function it_blends_averages_when_suggested_line_missing(): void
    {
        $result = $this->resolver->resolve(
            ['available' => false],
            ['recent_average' => 8.2, 'season_average' => 7.8]
        );

        $this->assertSame(8.0, $result['line']);
        $this->assertSame('player_average', $result['source']);
    }

    #[Test]
    public function it_ignores_zero_or_missing_book_lines(): void
    {
        $result = $this->resolver->resolve(
            ['available' => true, 'line' => 0],
            ['suggested_line' => 6.5]
        );

        $this->assertSame(6.5, $result['line']);
        $this->assertSame('player_average', $result['source']);
    }

    #[Test]
    public function it_returns_unavailable_without_stats_or_odds(): void
    {
        $result = $this->resolver->resolve(['available' => false], []);

        $this->assertNull($result['line']);
        $this->assertSame('unavailable', $result['source']);
    }
}
