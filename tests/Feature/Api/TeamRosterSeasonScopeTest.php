<?php

namespace Tests\Feature\Api;

use App\Models\WnbaGame;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamRosterSeasonScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_roster_defaults_to_current_season_only(): void
    {
        config(['wnba.seasons.current_season' => 2026]);

        $team = WnbaTeam::create([
            'team_id' => '17',
            'team_name' => 'Aces',
            'team_location' => 'Las Vegas',
            'team_abbreviation' => 'LV',
            'team_display_name' => 'Las Vegas Aces',
        ]);

        $current = WnbaPlayer::create([
            'athlete_id' => '3149391',
            'athlete_display_name' => 'A. Wilson',
            'athlete_short_name' => 'A. Wilson',
        ]);
        $prior = WnbaPlayer::create([
            'athlete_id' => '2999345',
            'athlete_display_name' => 'Former Ace',
            'athlete_short_name' => 'F. Ace',
        ]);

        $this->seedPlayerGame($current->id, $team->team_id, 2026, '401920101', '2026-06-01');
        $this->seedPlayerGame($prior->id, $team->team_id, 2025, '401920102', '2025-06-01');

        $this->getJson("/api/teams/{$team->team_id}/players")
            ->assertOk()
            ->assertJsonPath('meta.season', 2026)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.athlete_id', '3149391');
    }

    public function test_team_roster_accepts_season_filter(): void
    {
        config(['wnba.seasons.current_season' => 2026]);

        $team = WnbaTeam::create([
            'team_id' => '18',
            'team_name' => 'Sparks',
            'team_location' => 'Los Angeles',
            'team_abbreviation' => 'LA',
            'team_display_name' => 'Los Angeles Sparks',
        ]);

        $current = WnbaPlayer::create([
            'athlete_id' => '1001',
            'athlete_display_name' => 'Current Player',
            'athlete_short_name' => 'C. Player',
        ]);
        $prior = WnbaPlayer::create([
            'athlete_id' => '1002',
            'athlete_display_name' => 'Prior Player',
            'athlete_short_name' => 'P. Player',
        ]);

        $this->seedPlayerGame($current->id, $team->team_id, 2026, '401920201', '2026-06-01');
        $this->seedPlayerGame($prior->id, $team->team_id, 2025, '401920202', '2025-06-01');

        $this->getJson("/api/teams/{$team->team_id}/players?season=2025")
            ->assertOk()
            ->assertJsonPath('meta.season', 2025)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.athlete_id', '1002');
    }

    private function seedPlayerGame(
        int $playerId,
        string $teamId,
        int $season,
        string $gameId,
        string $date,
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
            'points' => 22,
            'plus_minus' => 5,
            'starter' => true,
            'ejected' => false,
            'did_not_play' => false,
            'active' => true,
        ]);
    }
}
