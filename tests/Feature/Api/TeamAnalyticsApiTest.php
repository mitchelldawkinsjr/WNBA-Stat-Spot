<?php

namespace Tests\Feature\Api;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaTeam;
use App\Models\WnbaTeamSeasonStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TeamAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_analytics_returns_season_scoped_payload(): void
    {
        Cache::flush();

        $team = WnbaTeam::create([
            'team_id' => '18',
            'team_name' => 'Storm',
            'team_location' => 'Seattle',
            'team_abbreviation' => 'SEA',
            'team_display_name' => 'Seattle Storm',
        ]);
        $opponent = WnbaTeam::create([
            'team_id' => '17',
            'team_name' => 'Aces',
            'team_location' => 'Las Vegas',
            'team_abbreviation' => 'LV',
            'team_display_name' => 'Las Vegas Aces',
        ]);

        $this->seedGame($team->team_id, $opponent->team_id, 2026, '401860001', '2026-06-01', 85, 78, true, 'home');
        $this->seedGame($team->team_id, $opponent->team_id, 2026, '401860002', '2026-06-05', 70, 88, false, 'away');
        $this->seedGame($team->team_id, $opponent->team_id, 2025, '401850001', '2025-06-01', 99, 90, true, 'home');

        WnbaTeamSeasonStat::create([
            'team_id' => $team->team_id,
            'season' => 2026,
            'games_played' => 2,
            'wins' => 1,
            'losses' => 1,
            'points_for_avg' => 77.5,
            'points_against_avg' => 83.0,
            'pace' => 78.5,
            'possessions_per_game' => 78.5,
            'offensive_rating' => 105.2,
            'defensive_rating' => 110.1,
            'net_rating' => -4.9,
            'ts_pct' => 0.545,
            'splits' => [
                'home' => ['games' => 1, 'wins' => 1, 'points_for_avg' => 85.0, 'points_against_avg' => 78.0],
                'away' => ['games' => 1, 'wins' => 0, 'points_for_avg' => 70.0, 'points_against_avg' => 88.0],
            ],
            'formula_version' => 'v1-box-estimate',
            'computed_at' => now(),
        ]);

        $response = $this->getJson('/api/wnba/analytics/team/'.$team->id.'?season=2026');

        $response->assertOk()
            ->assertJsonPath('data.season', 2026)
            ->assertJsonPath('data.season_stats.wins', 1)
            ->assertJsonPath('data.season_stats.losses', 1)
            ->assertJsonPath('data.advanced_metrics.offensive_rating', 105.2)
            ->assertJsonPath('data.advanced_metrics.true_shooting_percentage', 54.5)
            ->assertJsonPath('data.home_away_splits.home.wins', 1)
            ->assertJsonPath('data.home_away_splits.away.losses', 1)
            ->assertJsonCount(2, 'data.game_results');
    }

    public function test_team_analytics_returns_empty_payload_when_no_data(): void
    {
        Cache::flush();

        $team = WnbaTeam::create([
            'team_id' => '99',
            'team_name' => 'Ghosts',
            'team_location' => 'Nowhere',
            'team_abbreviation' => 'GHO',
            'team_display_name' => 'Nowhere Ghosts',
        ]);

        $this->getJson('/api/wnba/analytics/team/'.$team->id.'?season=2026')
            ->assertOk()
            ->assertJsonPath('data.season', 2026)
            ->assertJsonPath('data.season_stats', null)
            ->assertJsonPath('data.game_results', []);
    }

    private function seedGame(
        string $teamId,
        string $opponentId,
        int $season,
        string $gameId,
        string $date,
        int $teamScore,
        int $oppScore,
        bool $won,
        string $homeAway,
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
            'team_id' => $teamId,
            'opponent_team_id' => $opponentId,
            'home_away' => $homeAway,
            'team_score' => $teamScore,
            'opponent_team_score' => $oppScore,
            'team_winner' => $won,
        ]));
    }
}
