<?php

namespace Tests\Unit\Services\WNBA\Data;

use App\Services\WNBA\Data\DataAggregatorService;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class DataAggregatorSeasonStatsTest extends TestCase
{
    public function test_calculate_season_stats_handles_string_and_empty_minutes(): void
    {
        $service = app(DataAggregatorService::class);
        $method = new ReflectionMethod(DataAggregatorService::class, 'calculateSeasonStats');
        $method->setAccessible(true);

        $games = new Collection([
            (object) [
                'points' => 11,
                'rebounds' => 4,
                'assists' => 2,
                'steals' => 1,
                'blocks' => 0,
                'turnovers' => 1,
                'minutes' => '14.0',
                'plus_minus' => 14,
                'field_goals_made' => 4,
                'field_goals_attempted' => 8,
                'three_point_field_goals_made' => 1,
                'three_point_field_goals_attempted' => 3,
                'free_throws_made' => 2,
                'free_throws_attempted' => 2,
            ],
            (object) [
                'points' => 0,
                'rebounds' => 0,
                'assists' => 0,
                'steals' => 0,
                'blocks' => 0,
                'turnovers' => 0,
                'minutes' => '',
                'plus_minus' => 0,
                'field_goals_made' => 0,
                'field_goals_attempted' => 0,
                'three_point_field_goals_made' => 0,
                'three_point_field_goals_attempted' => 0,
                'free_throws_made' => 0,
                'free_throws_attempted' => 0,
            ],
            (object) [
                'points' => 14,
                'rebounds' => 5,
                'assists' => 3,
                'steals' => 0,
                'blocks' => 1,
                'turnovers' => 2,
                'minutes' => '17',
                'plus_minus' => -2,
                'field_goals_made' => 5,
                'field_goals_attempted' => 10,
                'three_point_field_goals_made' => 0,
                'three_point_field_goals_attempted' => 1,
                'free_throws_made' => 4,
                'free_throws_attempted' => 4,
            ],
        ]);

        $stats = $method->invoke($service, $games);

        $this->assertSame(3, $stats['games_played']);
        $this->assertEqualsWithDelta(10.3, $stats['averages']['minutes'], 0.1);
        $this->assertEqualsWithDelta(31.0, $stats['totals']['minutes'], 0.1);
        $this->assertEqualsWithDelta(8.3, $stats['averages']['points'], 0.1);
    }
}
