<?php

namespace Tests\Unit\Services\WNBA\Data;

use App\Models\WnbaGame;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\WNBA\Data\DataAggregatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataAggregatorSeasonScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_prop_data_defaults_to_current_season_only(): void
    {
        config(['wnba.seasons.current_season' => 2026]);

        $team = WnbaTeam::create([
            'team_id' => '17',
            'team_name' => 'Aces',
            'team_location' => 'Las Vegas',
            'team_abbreviation' => 'LV',
            'team_display_name' => 'Las Vegas Aces',
        ]);
        $player = WnbaPlayer::create([
            'athlete_id' => '3149391',
            'athlete_display_name' => 'A. Wilson',
            'athlete_short_name' => 'A. Wilson',
        ]);

        $this->seedPlayerGame($player->id, $team->team_id, 2025, '401920001', '2025-06-01', 30);
        $this->seedPlayerGame($player->id, $team->team_id, 2026, '401920002', '2026-06-01', 22);
        $this->seedPlayerGame($player->id, $team->team_id, 2026, '401920003', '2026-06-15', 28);

        $data = app(DataAggregatorService::class)->aggregatePropData($player->id, 'points');

        $this->assertSame(2, $data['games_played']);
        $this->assertEqualsWithDelta(25.0, $data['stat_distribution']['mean'], 0.01);
    }

    private function seedPlayerGame(
        int $playerId,
        string $teamId,
        int $season,
        string $gameId,
        string $date,
        int $points,
    ): void {
        $game = WnbaGame::create([
            'game_id' => $gameId,
            'season' => $season,
            'season_type' => 2,
            'game_date' => $date,
            'game_date_time' => $date.' 19:00:00',
        ]);

        WnbaPlayerGame::create([
            'game_id' => $game->id,
            'player_id' => $playerId,
            'team_id' => $teamId,
            'minutes' => '30:00',
            'field_goals_made' => 8,
            'field_goals_attempted' => 14,
            'three_point_field_goals_made' => 2,
            'three_point_field_goals_attempted' => 5,
            'free_throws_made' => 4,
            'free_throws_attempted' => 4,
            'offensive_rebounds' => 2,
            'defensive_rebounds' => 3,
            'rebounds' => 5,
            'assists' => 4,
            'steals' => 1,
            'blocks' => 1,
            'turnovers' => 3,
            'fouls' => 2,
            'points' => $points,
            'plus_minus' => 5,
            'starter' => true,
            'ejected' => false,
            'did_not_play' => false,
            'active' => true,
        ]);
    }
}
