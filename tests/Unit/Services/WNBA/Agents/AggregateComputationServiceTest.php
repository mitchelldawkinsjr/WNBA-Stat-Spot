<?php

namespace Tests\Unit\Services\WNBA\Agents;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaMatchupSummary;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaPlayerGameAdvanced;
use App\Models\WnbaPlayerPerformanceTrend;
use App\Models\WnbaPlayerSeasonStat;
use App\Models\WnbaPlayerVsDefense;
use App\Models\WnbaTeam;
use App\Models\WnbaTeamPerformanceTrend;
use App\Models\WnbaTeamSeasonStat;
use App\Services\WNBA\Agents\AggregateComputationService;
use App\Services\WNBA\Agents\BoxScoreValidator;
use App\Services\WNBA\Agents\RankingsInsightsComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateComputationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SEASON = 2026;

    private AggregateComputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AggregateComputationService(
            new BoxScoreValidator,
            new RankingsInsightsComputationService
        );
        $this->seedTwoGames();
    }

    private function seedTwoGames(): void
    {
        foreach ([['5', 'ATL'], ['9', 'NY']] as [$id, $abbr]) {
            WnbaTeam::create([
                'team_id' => $id,
                'team_name' => "Team {$abbr}",
                'team_location' => 'City',
                'team_abbreviation' => $abbr,
                'team_display_name' => "City Team {$abbr}",
            ]);
        }

        WnbaPlayer::create([
            'athlete_id' => '1234567',
            'athlete_display_name' => 'Test Player',
            'athlete_short_name' => 'T. Player',
        ]);

        $this->seedGame('401001', '2026-06-01', homeScore: 80, awayScore: 70);
        $this->seedGame('401002', '2026-06-05', homeScore: 60, awayScore: 90, homeTeam: '9', awayTeam: '5');

        $gameOne = WnbaGame::where('game_id', '401001')->first();
        $gameTwo = WnbaGame::where('game_id', '401002')->first();
        $player = WnbaPlayer::first();

        // Game 1: 22 pts on 8/14 FG (2/5 3P), 4/4 FT, 30 minutes.
        $this->seedPlayerGame($gameOne->id, $player->id, '5', [
            'minutes' => '30:00',
            'field_goals_made' => 8, 'field_goals_attempted' => 14,
            'three_point_field_goals_made' => 2, 'three_point_field_goals_attempted' => 5,
            'free_throws_made' => 4, 'free_throws_attempted' => 4,
            'offensive_rebounds' => 2, 'defensive_rebounds' => 3, 'rebounds' => 5,
            'assists' => 4, 'steals' => 1, 'blocks' => 1, 'turnovers' => 3, 'fouls' => 2,
            'points' => 22, 'starter' => true,
        ]);

        // Game 2: 10 pts on 4/10 FG (1/4 3P), 1/2 FT, 25 minutes.
        $this->seedPlayerGame($gameTwo->id, $player->id, '5', [
            'minutes' => '25:00',
            'field_goals_made' => 4, 'field_goals_attempted' => 10,
            'three_point_field_goals_made' => 1, 'three_point_field_goals_attempted' => 4,
            'free_throws_made' => 1, 'free_throws_attempted' => 2,
            'offensive_rebounds' => 3, 'defensive_rebounds' => 5, 'rebounds' => 8,
            'assists' => 2, 'steals' => 0, 'blocks' => 0, 'turnovers' => 1, 'fouls' => 3,
            'points' => 10, 'starter' => true,
        ]);
    }

    private function seedGame(string $gameId, string $date, int $homeScore, int $awayScore, string $homeTeam = '5', string $awayTeam = '9'): void
    {
        $game = WnbaGame::create([
            'game_id' => $gameId,
            'season' => self::SEASON,
            'season_type' => 2,
            'game_date' => $date,
            'game_date_time' => $date.' 19:00:00',
        ]);

        $sharedStats = [
            'field_goals_made' => 30, 'field_goals_attempted' => 60,
            'three_point_field_goals_made' => 8, 'three_point_field_goals_attempted' => 25,
            'free_throws_made' => 12, 'free_throws_attempted' => 20,
            'offensive_rebounds' => 10, 'defensive_rebounds' => 25, 'rebounds' => 35,
            'assists' => 20, 'steals' => 7, 'blocks' => 4, 'turnovers' => 15, 'fouls' => 18,
        ];

        WnbaGameTeam::create(array_merge($sharedStats, [
            'game_id' => $game->id,
            'team_id' => $homeTeam,
            'opponent_team_id' => $awayTeam,
            'home_away' => 'home',
            'team_winner' => $homeScore > $awayScore,
            'team_score' => $homeScore,
            'opponent_team_score' => $awayScore,
        ]));

        WnbaGameTeam::create(array_merge($sharedStats, [
            'game_id' => $game->id,
            'team_id' => $awayTeam,
            'opponent_team_id' => $homeTeam,
            'home_away' => 'away',
            'team_winner' => $awayScore > $homeScore,
            'team_score' => $awayScore,
            'opponent_team_score' => $homeScore,
        ]));
    }

    private function seedPlayerGame(int $gameId, int $playerId, string $teamId, array $stats): WnbaPlayerGame
    {
        return WnbaPlayerGame::create(array_merge([
            'game_id' => $gameId,
            'player_id' => $playerId,
            'team_id' => $teamId,
            'starter' => false,
            'ejected' => false,
            'did_not_play' => false,
            'active' => true,
            'plus_minus' => 0,
        ], $stats));
    }

    public function test_player_season_stats_totals_and_percentages(): void
    {
        $this->service->computePlayerSeasonStats(self::SEASON);

        $stat = WnbaPlayerSeasonStat::first();
        $this->assertNotNull($stat);
        $this->assertSame(2, $stat->games_played);
        $this->assertSame(32, $stat->points_total);
        $this->assertSame(16.0, round($stat->points_avg, 1));
        $this->assertSame(6.5, round($stat->rebounds_avg, 1));
        $this->assertSame(0.5, round($stat->fg_pct, 4)); // 12/24
        $this->assertSame(round(3 / 9, 4), round($stat->three_pct, 4));
        // TS% = 32 / (2 * (24 + 0.44*6))
        $this->assertSame(round(32 / (2 * (24 + 0.44 * 6)), 4), round($stat->ts_pct, 4));
        $this->assertSame(27.5, round($stat->minutes_avg, 1));
        $this->assertSame(AggregateComputationService::FORMULA_VERSION, $stat->formula_version);

        // Home/away splits: game 1 home (22 pts), game 2 away (10 pts).
        $this->assertSame(22.0, (float) $stat->splits['home']['points']);
        $this->assertSame(10.0, (float) $stat->splits['away']['points']);
        // Per-36: 32 points in 55 minutes.
        $this->assertSame(round(32 * 36 / 55, 2), (float) $stat->splits['per_36']['points']);
        // Per-30: 32 points in 55 minutes.
        $this->assertSame(round(32 * 30 / 55, 2), (float) $stat->splits['per_30']['points']);
        $this->assertArrayHasKey('steals', $stat->splits['per_30']);
        $this->assertSame(0.0, (float) $stat->splits['plus_minus_avg']);
    }

    public function test_invalid_rows_are_excluded_from_season_stats(): void
    {
        WnbaPlayerGame::query()
            ->orderByDesc('id')
            ->first()
            ->update(['validation_status' => BoxScoreValidator::STATUS_INVALID]);

        $this->service->computePlayerSeasonStats(self::SEASON);

        $stat = WnbaPlayerSeasonStat::first();
        $this->assertSame(1, $stat->games_played);
        $this->assertSame(22, $stat->points_total);
    }

    public function test_player_game_advanced_metrics(): void
    {
        $this->service->computePlayerGameAdvanced(self::SEASON);

        $this->assertSame(2, WnbaPlayerGameAdvanced::count());

        $gameOne = WnbaPlayerGameAdvanced::query()
            ->whereHas('playerGame', fn ($query) => $query->where('points', 22))
            ->first();

        $this->assertSame(30.0, (float) $gameOne->minutes_decimal);
        // TS% = 22 / (2 * (14 + 0.44*4))
        $this->assertSame(round(22 / (2 * (14 + 0.44 * 4)), 4), round($gameOne->ts_pct, 4));
        // eFG% = (8 + 0.5*2) / 14
        $this->assertSame(round(9 / 14, 4), round($gameOne->efg_pct, 4));
        $this->assertNotNull($gameOne->usage_pct);
        $this->assertNotNull($gameOne->game_score);
    }

    public function test_team_season_stats_ratings_and_record(): void
    {
        $this->service->computeTeamSeasonStats(self::SEASON);

        $stat = WnbaTeamSeasonStat::where('team_id', '5')->first();
        $this->assertNotNull($stat);
        $this->assertSame(2, $stat->games_played);
        // Team 5 won both: 80-70 at home, 90-60 on the road.
        $this->assertSame(2, $stat->wins);
        $this->assertSame(0, $stat->losses);
        // Team 5 scored 80 (home) and 90 (away): avg 85.
        $this->assertSame(85.0, (float) $stat->points_for_avg);
        $this->assertSame(65.0, (float) $stat->points_against_avg);
        // Possessions per game: 60 + 0.44*20 - 10 + 15 = 73.8.
        $this->assertSame(73.8, (float) $stat->possessions_per_game);
        // Offensive rating: 170 * 100 / 147.6.
        $this->assertSame(round(170 * 100 / 147.6, 2), (float) $stat->offensive_rating);
        $this->assertNotNull($stat->efg_pct);
        $this->assertNotNull($stat->net_rating);
    }

    public function test_matchup_summaries(): void
    {
        $this->service->computeMatchupSummaries(self::SEASON);

        $summary = WnbaMatchupSummary::first();
        $this->assertNotNull($summary);
        $this->assertSame('5', $summary->team_a_id);
        $this->assertSame('9', $summary->team_b_id);
        $this->assertSame(2, $summary->games_played);
        // Team 5 won both games (80-70 at home, 90-60 on the road).
        $this->assertSame(2, $summary->team_a_wins);
        $this->assertSame(0, $summary->team_b_wins);
        $this->assertSame(150.0, (float) $summary->avg_total_points);
        $this->assertSame(20.0, (float) $summary->avg_margin);
        $this->assertCount(2, $summary->recent_meetings);
    }

    public function test_recompute_is_idempotent(): void
    {
        $this->service->computeSeason(self::SEASON);
        $firstPoints = WnbaPlayerSeasonStat::first()->points_total;

        $this->service->computeSeason(self::SEASON);

        $this->assertSame(1, WnbaPlayerSeasonStat::count());
        $this->assertSame($firstPoints, WnbaPlayerSeasonStat::first()->points_total);
        $this->assertSame(2, WnbaTeamSeasonStat::count());
        $this->assertSame(1, WnbaMatchupSummary::count());
        $this->assertGreaterThan(0, WnbaPlayerVsDefense::count());
        $this->assertGreaterThan(0, WnbaPlayerPerformanceTrend::count());
        $this->assertGreaterThan(0, WnbaTeamPerformanceTrend::count());
    }

    public function test_player_vs_defense_buckets_by_opponent_drtg(): void
    {
        $this->service->computeTeamSeasonStats(self::SEASON);
        $this->service->computePlayerGameAdvanced(self::SEASON);
        $this->service->computePlayerVsDefense(self::SEASON);

        // Opponent team 9 allows 170 pts on 147.6 poss → DRtg ≈ 115.18 → poor.
        $row = WnbaPlayerVsDefense::where('defense_bucket', 'poor')->first();
        $this->assertNotNull($row);
        $this->assertSame(2, $row->games);
        $this->assertSame(16.0, (float) $row->points_avg);
        $this->assertSame(AggregateComputationService::FORMULA_VERSION, $row->formula_version);
    }

    public function test_player_performance_trends_windows_and_slope(): void
    {
        $this->service->computePlayerPerformanceTrends(self::SEASON);

        $l5 = WnbaPlayerPerformanceTrend::where('window', 'l5')->first();
        $l20 = WnbaPlayerPerformanceTrend::where('window', 'l20')->first();
        $season = WnbaPlayerPerformanceTrend::where('window', 'season')->first();

        $this->assertNotNull($l5);
        $this->assertNotNull($l20);
        $this->assertNotNull($season);
        $this->assertSame(2, $l5->games);
        $this->assertSame(2, $l20->games);
        $this->assertSame(16.0, (float) $season->points_avg);
        // Points series [22, 10] → slope -12.
        $this->assertSame(-12.0, (float) $season->points_slope);
    }

    public function test_team_performance_trends(): void
    {
        $this->service->computeTeamPerformanceTrends(self::SEASON);

        $trend = WnbaTeamPerformanceTrend::where('team_id', '5')->where('window', 'season')->first();
        $this->assertNotNull($trend);
        $this->assertSame(2, $trend->games);
        $this->assertSame(2, $trend->wins);
        $this->assertSame(85.0, (float) $trend->points_for_avg);
        $this->assertNotNull($trend->offensive_rating);
        $this->assertNotNull($trend->defensive_rating);
    }

    public function test_all_star_exhibition_games_excluded_from_primary_stats(): void
    {
        foreach ([['133383', 'SPO', 'TEAM SPOON'], ['133384', 'COOP', 'TEAM COOP']] as [$id, $abbr, $name]) {
            WnbaTeam::create([
                'team_id' => $id,
                'team_name' => $name,
                'team_location' => 'All-Star',
                'team_abbreviation' => $abbr,
                'team_display_name' => $name,
            ]);
        }

        $player = WnbaPlayer::first();
        $asg = WnbaGame::create([
            'game_id' => '401857320',
            'season' => self::SEASON,
            'season_type' => 2, // ESPN stores All-Star as regular-season type
            'game_date' => '2026-07-25',
            'game_date_time' => '2026-07-25 20:00:00',
        ]);

        $this->seedPlayerGame($asg->id, $player->id, '133383', [
            'minutes' => '20:00',
            'field_goals_made' => 5, 'field_goals_attempted' => 10,
            'three_point_field_goals_made' => 1, 'three_point_field_goals_attempted' => 3,
            'free_throws_made' => 2, 'free_throws_attempted' => 2,
            'offensive_rebounds' => 1, 'defensive_rebounds' => 2, 'rebounds' => 3,
            'assists' => 2, 'steals' => 0, 'blocks' => 0, 'turnovers' => 1, 'fouls' => 1,
            'points' => 40, 'starter' => true,
        ]);

        // Stale exhibition team season row should be purged on recompute.
        WnbaTeamSeasonStat::create([
            'team_id' => '133383',
            'season' => self::SEASON,
            'games_played' => 1,
            'wins' => 1,
            'losses' => 0,
            'formula_version' => AggregateComputationService::FORMULA_VERSION,
            'computed_at' => now(),
        ]);

        $this->service->computePlayerSeasonStats(self::SEASON);
        $this->service->computeTeamSeasonStats(self::SEASON);
        $this->service->computePlayerPerformanceTrends(self::SEASON);

        $stat = WnbaPlayerSeasonStat::where('player_id', $player->id)->first();
        $this->assertSame(2, $stat->games_played);
        $this->assertSame(32, $stat->points_total);
        $this->assertSame('5', $stat->team_id);

        $this->assertNull(WnbaTeamSeasonStat::where('team_id', '133383')->first());

        $seasonTrend = WnbaPlayerPerformanceTrend::where('player_id', $player->id)
            ->where('window', 'season')
            ->first();
        $this->assertSame(2, $seasonTrend->games);
        $this->assertSame(16.0, (float) $seasonTrend->points_avg);
    }
}
