<?php

namespace Tests\Unit;

use App\Models\WnbaGame;
use App\Services\WNBA\Data\GameScheduleService;
use App\Services\WNBA\Data\Providers\EspnWnbaProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_game_without_teams_is_enriched_from_espn_schedule(): void
    {
        $game = WnbaGame::create([
            'game_id' => '401857218',
            'season' => 2026,
            'season_type' => 2,
            'game_date' => '2026-09-25',
            'game_date_time' => '2026-09-25T02:00:00Z',
            'status_name' => 'STATUS_SCHEDULED',
        ]);

        $this->mock(EspnWnbaProvider::class, function ($mock) {
            $mock->shouldReceive('fetchSchedule')
                ->once()
                ->with(2026)
                ->andReturn([
                    [
                        'game_id' => '401857218',
                        'season' => 2026,
                        'season_type' => 'Regular Season',
                        'game_date' => '2026-09-25',
                        'game_date_time' => '2026-09-25T02:00Z',
                        'home_team_id' => '6',
                        'home_team_abbreviation' => 'LA',
                        'home_team_display_name' => 'Los Angeles Sparks',
                        'home_team_logo' => 'https://example.com/la.png',
                        'away_team_id' => '129689',
                        'away_team_abbreviation' => 'GS',
                        'away_team_display_name' => 'Golden State Valkyries',
                        'away_team_logo' => 'https://example.com/gs.png',
                        'venue_name' => 'crypto.com Arena',
                        'venue_city' => 'Los Angeles',
                        'venue_state' => 'CA',
                        'status_name' => 'STATUS_SCHEDULED',
                    ],
                ]);
        });

        $results = app(GameScheduleService::class)->list(2026, true);

        $this->assertCount(1, $results);
        $this->assertSame($game->id, $results[0]['id']);
        $this->assertSame('LA', $results[0]['home_team']['abbreviation']);
        $this->assertSame('GS', $results[0]['away_team']['abbreviation']);
        $this->assertSame('crypto.com Arena', $results[0]['venue_name']);
    }

    public function test_live_espn_score_overrides_stale_db_score(): void
    {
        $home = \App\Models\WnbaTeam::create([
            'team_id' => '6',
            'team_name' => 'Sparks',
            'team_location' => 'Los Angeles',
            'team_abbreviation' => 'LA',
            'team_display_name' => 'Los Angeles Sparks',
        ]);
        $away = \App\Models\WnbaTeam::create([
            'team_id' => '129689',
            'team_name' => 'Valkyries',
            'team_location' => 'Golden State',
            'team_abbreviation' => 'GS',
            'team_display_name' => 'Golden State Valkyries',
        ]);

        $game = WnbaGame::create([
            'game_id' => '401857219',
            'season' => 2026,
            'season_type' => 2,
            'game_date' => '2026-09-25',
            'game_date_time' => '2026-09-25T02:00:00Z',
            'status_name' => 'STATUS_IN_PROGRESS',
        ]);

        \App\Models\WnbaGameTeam::create([
            'game_id' => $game->id,
            'team_id' => $home->team_id,
            'opponent_team_id' => $away->team_id,
            'home_away' => 'home',
            'team_winner' => false,
            'team_score' => 12,
            'opponent_team_score' => 8,
            'field_goals_made' => 0, 'field_goals_attempted' => 0,
            'three_point_field_goals_made' => 0, 'three_point_field_goals_attempted' => 0,
            'free_throws_made' => 0, 'free_throws_attempted' => 0,
            'offensive_rebounds' => 0, 'defensive_rebounds' => 0, 'rebounds' => 0,
            'assists' => 0, 'steals' => 0, 'blocks' => 0, 'turnovers' => 0, 'fouls' => 0,
        ]);
        \App\Models\WnbaGameTeam::create([
            'game_id' => $game->id,
            'team_id' => $away->team_id,
            'opponent_team_id' => $home->team_id,
            'home_away' => 'away',
            'team_winner' => false,
            'team_score' => 8,
            'opponent_team_score' => 12,
            'field_goals_made' => 0, 'field_goals_attempted' => 0,
            'three_point_field_goals_made' => 0, 'three_point_field_goals_attempted' => 0,
            'free_throws_made' => 0, 'free_throws_attempted' => 0,
            'offensive_rebounds' => 0, 'defensive_rebounds' => 0, 'rebounds' => 0,
            'assists' => 0, 'steals' => 0, 'blocks' => 0, 'turnovers' => 0, 'fouls' => 0,
        ]);

        $this->mock(EspnWnbaProvider::class, function ($mock) {
            $mock->shouldReceive('fetchSchedule')
                ->once()
                ->with(2026)
                ->andReturn([
                    [
                        'game_id' => '401857219',
                        'season' => 2026,
                        'season_type' => 'Regular Season',
                        'game_date' => '2026-09-25',
                        'game_date_time' => '2026-09-25T02:00Z',
                        'home_team_id' => '6',
                        'home_team_abbreviation' => 'LA',
                        'home_team_display_name' => 'Los Angeles Sparks',
                        'home_team_score' => 48,
                        'away_team_id' => '129689',
                        'away_team_abbreviation' => 'GS',
                        'away_team_display_name' => 'Golden State Valkyries',
                        'away_team_score' => 41,
                        'status_name' => 'STATUS_IN_PROGRESS',
                    ],
                ]);
        });

        $results = app(GameScheduleService::class)->list(2026, true);
        $this->assertSame(48, $results[0]['home_team']['score']);
        $this->assertSame(41, $results[0]['away_team']['score']);
        $this->assertFalse($results[0]['final_score']['final']);
    }
}
