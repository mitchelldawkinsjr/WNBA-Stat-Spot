<?php

namespace Tests\Feature\Api;

use App\Services\WNBA\Data\PlayerGamelogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_analytics_uses_live_gamelog_when_db_empty(): void
    {
        $this->mock(PlayerGamelogService::class, function ($mock) {
            $mock->shouldReceive('fetch')
                ->once()
                ->with('3149391', 2026, 50)
                ->andReturn([
                    'provider' => 'espn',
                    'season' => 2026,
                    'player_id' => '3149391',
                    'canonical_player_id' => '3149391',
                    'games' => [
                        [
                            'game_id' => '401857001',
                            'game_date' => '2026-06-20',
                            'home_away' => 'home',
                            'opponent_team_abbreviation' => 'PHX',
                            'minutes' => 32.5,
                            'points' => 24,
                            'rebounds' => 6,
                            'assists' => 9,
                            'steals' => 2,
                            'blocks' => 0,
                            'turnovers' => 3,
                            'field_goals_made' => 8,
                            'field_goals_attempted' => 16,
                            'three_point_field_goals_made' => 3,
                            'three_point_field_goals_attempted' => 7,
                            'free_throws_made' => 5,
                            'free_throws_attempted' => 6,
                        ],
                        [
                            'game_id' => '401857002',
                            'game_date' => '2026-06-18',
                            'home_away' => 'away',
                            'opponent_team_abbreviation' => 'SEA',
                            'minutes' => 30,
                            'points' => 18,
                            'rebounds' => 4,
                            'assists' => 11,
                            'steals' => 1,
                            'blocks' => 1,
                            'turnovers' => 4,
                            'field_goals_made' => 6,
                            'field_goals_attempted' => 14,
                            'three_point_field_goals_made' => 2,
                            'three_point_field_goals_attempted' => 5,
                            'free_throws_made' => 4,
                            'free_throws_attempted' => 4,
                        ],
                    ],
                ]);
        });

        $response = $this->getJson('/api/wnba/analytics/player/3149391?season=2026');

        $response->assertOk()
            ->assertJsonPath('data.season', 2026)
            ->assertJsonPath('data.source', 'espn')
            ->assertJsonPath('data.recent_form.games_analyzed', 2)
            ->assertJsonPath('data.summary.total_games', 2)
            ->assertJsonPath('data.recent_form.game_log.0.points', 24)
            ->assertJsonPath('data.shooting_efficiency.field_goal_percentage', 46.7);
    }
}
