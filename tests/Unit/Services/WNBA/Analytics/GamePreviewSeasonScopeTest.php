<?php

namespace Tests\Unit\Services\WNBA\Analytics;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\WNBA\Agents\AggregateComputationService;
use App\Services\WNBA\Analytics\GamePreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class GamePreviewSeasonScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_to_head_uses_requested_season_only(): void
    {
        $home = WnbaTeam::create([
            'team_id' => '17',
            'team_name' => 'Aces',
            'team_location' => 'Las Vegas',
            'team_abbreviation' => 'LV',
            'team_display_name' => 'Las Vegas Aces',
        ]);
        $away = WnbaTeam::create([
            'team_id' => '18',
            'team_name' => 'Storm',
            'team_location' => 'Seattle',
            'team_abbreviation' => 'SEA',
            'team_display_name' => 'Seattle Storm',
        ]);

        $this->seedMeeting($home->team_id, $away->team_id, 2025, '401900001', '2025-07-01', 90, 80);
        $this->seedMeeting($home->team_id, $away->team_id, 2026, '401900002', '2026-07-01', 95, 88);
        $this->seedMeeting($home->team_id, $away->team_id, 2026, '401900003', '2026-07-15', 100, 92);

        // Preview reads season-scoped precomputed matchup rows, not raw game_teams.
        app(AggregateComputationService::class)->computeMatchupSummaries(2025);
        app(AggregateComputationService::class)->computeMatchupSummaries(2026);

        $service = app(GamePreviewService::class);
        $method = new ReflectionMethod(GamePreviewService::class, 'buildHeadToHead');
        $method->setAccessible(true);

        $h2h = $method->invoke($service, (int) $home->team_id, (int) $away->team_id, 2026);

        $this->assertSame(2, $h2h['total_games']);
        $this->assertCount(2, $h2h['recent_meetings']);
        foreach ($h2h['recent_meetings'] as $meeting) {
            $this->assertSame(2026, (int) $meeting['season']);
        }
    }

    public function test_key_players_expose_athlete_id_not_database_pk(): void
    {
        $home = WnbaTeam::create([
            'team_id' => '19',
            'team_name' => 'Sparks',
            'team_location' => 'Los Angeles',
            'team_abbreviation' => 'LA',
            'team_display_name' => 'Los Angeles Sparks',
        ]);
        $away = WnbaTeam::create([
            'team_id' => '20',
            'team_name' => 'Mercury',
            'team_location' => 'Phoenix',
            'team_abbreviation' => 'PHX',
            'team_display_name' => 'Phoenix Mercury',
        ]);

        $player = WnbaPlayer::create([
            'athlete_id' => '4433630',
            'athlete_display_name' => 'Rickea Jackson',
            'athlete_short_name' => 'R. Jackson',
            'athlete_position_abbreviation' => 'F',
        ]);

        $game = WnbaGame::create([
            'game_id' => '401900010',
            'season' => 2026,
            'season_type' => 2,
            'game_date' => '2026-07-01',
            'game_date_time' => '2026-07-01 19:00:00',
        ]);

        WnbaPlayerGame::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $home->team_id,
            'minutes' => '32:00',
            'points' => 22,
            'rebounds' => 6,
            'assists' => 2,
            'field_goals_made' => 8,
            'field_goals_attempted' => 16,
            'three_point_field_goals_made' => 2,
            'three_point_field_goals_attempted' => 5,
            'free_throws_made' => 4,
            'free_throws_attempted' => 4,
            'offensive_rebounds' => 1,
            'defensive_rebounds' => 5,
            'steals' => 1,
            'blocks' => 0,
            'turnovers' => 2,
            'fouls' => 2,
            'plus_minus' => 8,
            'starter' => true,
            'ejected' => false,
            'did_not_play' => false,
            'active' => true,
        ]);

        $this->assertNotSame('4433630', (string) $player->id, 'DB pk must differ from athlete_id for this assertion');

        $service = app(GamePreviewService::class);
        $method = new ReflectionMethod(GamePreviewService::class, 'getKeyPlayers');
        $method->setAccessible(true);

        $keyPlayers = $method->invoke($service, (int) $home->team_id, 2026, (int) $away->team_id);

        $this->assertCount(1, $keyPlayers);
        $this->assertSame('4433630', $keyPlayers[0]['player_id']);
        $this->assertSame('Rickea Jackson', $keyPlayers[0]['name']);
        $this->assertNotSame((string) $player->id, $keyPlayers[0]['player_id']);
    }

    private function seedMeeting(
        string $homeTeamId,
        string $awayTeamId,
        int $season,
        string $gameId,
        string $date,
        int $homeScore,
        int $awayScore,
    ): void {
        $game = WnbaGame::create([
            'game_id' => $gameId,
            'season' => $season,
            'season_type' => 2,
            'game_date' => $date,
            'game_date_time' => $date.' 19:00:00',
        ]);

        $shared = [
            'field_goals_made' => 30,
            'field_goals_attempted' => 70,
            'three_point_field_goals_made' => 8,
            'three_point_field_goals_attempted' => 25,
            'free_throws_made' => 10,
            'free_throws_attempted' => 12,
            'offensive_rebounds' => 10,
            'defensive_rebounds' => 25,
            'rebounds' => 35,
            'assists' => 20,
            'steals' => 5,
            'blocks' => 3,
            'turnovers' => 12,
            'fouls' => 15,
        ];

        WnbaGameTeam::create(array_merge($shared, [
            'game_id' => $game->id,
            'team_id' => $homeTeamId,
            'opponent_team_id' => $awayTeamId,
            'home_away' => 'home',
            'team_score' => $homeScore,
            'opponent_team_score' => $awayScore,
            'team_winner' => $homeScore > $awayScore,
        ]));

        WnbaGameTeam::create(array_merge($shared, [
            'game_id' => $game->id,
            'team_id' => $awayTeamId,
            'opponent_team_id' => $homeTeamId,
            'home_away' => 'away',
            'team_score' => $awayScore,
            'opponent_team_score' => $homeScore,
            'team_winner' => $awayScore > $homeScore,
        ]));
    }
}
