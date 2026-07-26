<?php

namespace Tests\Unit\Services\WNBA\Agents;

use App\Models\WnbaDailyInsight;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerPercentileRank;
use App\Models\WnbaPlayerPerformanceTrend;
use App\Models\WnbaPlayerSeasonStat;
use App\Models\WnbaTeam;
use App\Models\WnbaTeamPercentileRank;
use App\Models\WnbaTeamPerformanceTrend;
use App\Models\WnbaTeamPowerRanking;
use App\Models\WnbaTeamSeasonStat;
use App\Services\WNBA\Agents\RankingsInsightsComputationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingsInsightsComputationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SEASON = 2026;

    private RankingsInsightsComputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RankingsInsightsComputationService;
        $this->seedLeague();
    }

    public function test_computes_percentiles_power_rankings_and_insights(): void
    {
        $this->service->computeSeason(self::SEASON, Carbon::parse('2026-07-20'));

        $this->assertSame(2, WnbaTeamPercentileRank::count());
        $this->assertSame(1, WnbaPlayerPercentileRank::count());
        $this->assertSame(2, WnbaTeamPowerRanking::count());
        $this->assertGreaterThan(0, WnbaDailyInsight::count());

        $top = WnbaTeamPowerRanking::query()->where('rank', 1)->first();
        $this->assertNotNull($top);
        $this->assertSame('5', $top->team_id);
        $this->assertSame('2026-07-20', $top->as_of_date->toDateString());

        $playerPctl = WnbaPlayerPercentileRank::first();
        $this->assertNotNull($playerPctl->points_pctl);
        $this->assertSame(50.0, $playerPctl->points_pctl); // single qualified player
    }

    public function test_power_rankings_track_week_over_week_delta(): void
    {
        $this->service->computeTeamPowerRankings(self::SEASON, Carbon::parse('2026-07-13'));

        // Flip strength: make team 9 much better before next snapshot.
        WnbaTeamSeasonStat::where('team_id', '5')->update(['net_rating' => -8, 'wins' => 1, 'losses' => 5]);
        WnbaTeamSeasonStat::where('team_id', '9')->update(['net_rating' => 12, 'wins' => 5, 'losses' => 1]);
        WnbaTeamPerformanceTrend::where('team_id', '5')->where('window', 'l5')->update([
            'offensive_rating' => 95,
            'defensive_rating' => 110,
        ]);
        WnbaTeamPerformanceTrend::where('team_id', '9')->where('window', 'l5')->update([
            'offensive_rating' => 115,
            'defensive_rating' => 98,
        ]);

        $this->service->computeTeamPowerRankings(self::SEASON, Carbon::parse('2026-07-20'));

        $newTop = WnbaTeamPowerRanking::query()
            ->whereDate('as_of_date', '2026-07-20')
            ->where('rank', 1)
            ->first();

        $this->assertSame('9', $newTop->team_id);
        $this->assertSame(2, $newTop->previous_rank);
        $this->assertSame(1, $newTop->rank_delta);
    }

    private function seedLeague(): void
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

        $player = WnbaPlayer::create([
            'athlete_id' => '1234567',
            'athlete_display_name' => 'Test Player',
            'athlete_short_name' => 'T. Player',
        ]);

        WnbaTeamSeasonStat::create([
            'team_id' => '5',
            'season' => self::SEASON,
            'games_played' => 6,
            'wins' => 5,
            'losses' => 1,
            'offensive_rating' => 112,
            'defensive_rating' => 100,
            'net_rating' => 12,
            'pace' => 80,
            'efg_pct' => 0.52,
            'tov_pct' => 0.15,
            'formula_version' => 'v1-box-estimate',
            'computed_at' => now(),
        ]);
        WnbaTeamSeasonStat::create([
            'team_id' => '9',
            'season' => self::SEASON,
            'games_played' => 6,
            'wins' => 2,
            'losses' => 4,
            'offensive_rating' => 100,
            'defensive_rating' => 108,
            'net_rating' => -8,
            'pace' => 78,
            'efg_pct' => 0.48,
            'tov_pct' => 0.18,
            'formula_version' => 'v1-box-estimate',
            'computed_at' => now(),
        ]);

        foreach (['5', '9'] as $teamId) {
            WnbaTeamPerformanceTrend::create([
                'team_id' => $teamId,
                'season' => self::SEASON,
                'window' => 'l5',
                'games' => 5,
                'wins' => $teamId === '5' ? 4 : 1,
                'losses' => $teamId === '5' ? 1 : 4,
                'offensive_rating' => $teamId === '5' ? 114 : 98,
                'defensive_rating' => $teamId === '5' ? 99 : 110,
                'formula_version' => 'v1-box-estimate',
                'computed_at' => now(),
            ]);
            WnbaTeamPerformanceTrend::create([
                'team_id' => $teamId,
                'season' => self::SEASON,
                'window' => 'l10',
                'games' => 6,
                'wins' => $teamId === '5' ? 5 : 2,
                'losses' => $teamId === '5' ? 1 : 4,
                'offensive_rating' => $teamId === '5' ? 112 : 100,
                'defensive_rating' => $teamId === '5' ? 100 : 108,
                'formula_version' => 'v1-box-estimate',
                'computed_at' => now(),
            ]);
        }

        WnbaPlayerSeasonStat::create([
            'player_id' => $player->id,
            'season' => self::SEASON,
            'team_id' => '5',
            'games_played' => 6,
            'games_started' => 6,
            'points_total' => 120,
            'rebounds_total' => 36,
            'offensive_rebounds_total' => 10,
            'defensive_rebounds_total' => 26,
            'assists_total' => 24,
            'steals_total' => 6,
            'blocks_total' => 3,
            'turnovers_total' => 12,
            'fouls_total' => 12,
            'field_goals_made_total' => 40,
            'field_goals_attempted_total' => 90,
            'three_point_made_total' => 12,
            'three_point_attempted_total' => 36,
            'free_throws_made_total' => 28,
            'free_throws_attempted_total' => 32,
            'points_avg' => 20,
            'rebounds_avg' => 6,
            'assists_avg' => 4,
            'steals_avg' => 1,
            'blocks_avg' => 0.5,
            'minutes_avg' => 30,
            'ts_pct' => 0.58,
            'efg_pct' => 0.52,
            'formula_version' => 'v1-box-estimate',
            'computed_at' => now(),
        ]);

        WnbaPlayerPerformanceTrend::create([
            'player_id' => $player->id,
            'season' => self::SEASON,
            'window' => 'l5',
            'games' => 5,
            'points_avg' => 26,
            'rebounds_avg' => 6,
            'assists_avg' => 4,
            'formula_version' => 'v1-box-estimate',
            'computed_at' => now(),
        ]);
    }
}
