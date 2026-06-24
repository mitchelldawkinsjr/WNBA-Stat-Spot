<?php

namespace Tests\Feature\Api;

use App\Services\WNBA\Data\GameScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_games_index_defaults_to_current_season_with_live_schedule(): void
    {
        $this->mock(GameScheduleService::class, function ($mock) {
            $mock->shouldReceive('list')
                ->once()
                ->with(2026, true)
                ->andReturn([
                    [
                        'id' => 0,
                        'game_id' => '401857014',
                        'season' => '2026',
                        'season_type' => 'Regular Season',
                        'game_date' => '2026-06-23',
                        'game_date_time' => '2026-06-23T00:00:00+00:00',
                        'venue_name' => 'Gainbridge Fieldhouse',
                        'venue_city' => 'Indianapolis',
                        'venue_state' => 'IN',
                        'status_name' => 'STATUS_FINAL',
                        'home_team' => ['abbreviation' => 'IND', 'score' => 86],
                        'away_team' => ['abbreviation' => 'PHX', 'score' => 77],
                        'source' => 'espn',
                    ],
                ]);
        });

        $response = $this->getJson('/api/games');

        $response->assertOk()
            ->assertJsonPath('meta.season', 2026)
            ->assertJsonPath('data.0.season', '2026')
            ->assertJsonPath('data.0.game_id', '401857014');
    }

    public function test_games_index_accepts_season_filter(): void
    {
        $this->mock(GameScheduleService::class, function ($mock) {
            $mock->shouldReceive('list')
                ->once()
                ->with(2025, true)
                ->andReturn([]);
        });

        $this->getJson('/api/games?season=2025')
            ->assertOk()
            ->assertJsonPath('meta.season', 2025);
    }
}
