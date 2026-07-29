<?php

namespace Tests\Unit\WNBA;

use App\Models\GameScorePrediction;
use App\Models\TrackedPropPrediction;
use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\WNBA\Predictions\PredictionAccuracyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionAccuracyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PredictionAccuracyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PredictionAccuracyService::class);
    }

    public function test_records_game_score_prediction_once_and_skips_graded_updates(): void
    {
        $preview = $this->samplePreview('401900001');

        $first = $this->service->recordGameScorePrediction($preview);
        $this->assertNotNull($first);
        $this->assertSame(1, GameScorePrediction::query()->count());
        $this->assertSame(82.0, (float) $first->predicted_home_score);

        $preview['prediction']['projected_score']['home'] = 90;
        $second = $this->service->recordGameScorePrediction($preview);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(90.0, (float) $second->fresh()->predicted_home_score);

        $second->update([
            'graded_at' => now(),
            'winner_correct' => true,
            'actual_home_score' => 88,
            'actual_away_score' => 80,
            'actual_winner' => 'home',
        ]);

        $preview['prediction']['projected_score']['home'] = 99;
        $third = $this->service->recordGameScorePrediction($preview);
        $this->assertSame(90.0, (float) $third->fresh()->predicted_home_score);
    }

    public function test_grades_game_score_prediction_when_final(): void
    {
        $home = $this->makeTeam('19', 'NYL');
        $away = $this->makeTeam('5', 'CON');
        $game = $this->makeFinalGame('401900002', $home, $away, 91, 84);

        $this->service->recordGameScorePrediction($this->samplePreview('401900002', 'NYL', 'CON', 'home', 88, 82));
        $graded = $this->service->gradePendingGameScores();

        $this->assertSame(1, $graded);
        $row = GameScorePrediction::query()->where('game_id', '401900002')->first();
        $this->assertTrue($row->winner_correct);
        $this->assertSame(91, $row->actual_home_score);
        $this->assertSame(84, $row->actual_away_score);
        $this->assertSame(3.0, (float) $row->home_score_error);
        $this->assertTrue($row->total_within_5);
    }

    public function test_records_top_prop_of_day_and_grades_result(): void
    {
        $player = WnbaPlayer::query()->create([
            'athlete_id' => '1001',
            'athlete_display_name' => 'Test Player',
            'athlete_short_name' => 'T. Player',
        ]);
        $home = $this->makeTeam('3', 'LAS');
        $away = $this->makeTeam('6', 'MIN');
        $game = $this->makeFinalGame('401900003', $home, $away, 80, 75);

        WnbaPlayerGame::query()->create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $home->team_id,
            'points' => 24,
            'rebounds' => 8,
            'assists' => 5,
            'steals' => 1,
            'blocks' => 0,
            'turnovers' => 2,
            'fouls' => 2,
            'field_goals_made' => 9,
            'field_goals_attempted' => 18,
            'three_point_field_goals_made' => 2,
            'three_point_field_goals_attempted' => 6,
            'free_throws_made' => 4,
            'free_throws_attempted' => 4,
            'offensive_rebounds' => 2,
            'defensive_rebounds' => 6,
            'plus_minus' => 5,
            'starter' => true,
            'ejected' => false,
            'did_not_play' => false,
            'active' => true,
        ]);

        $this->service->recordTodaysProps([
            [
                'player_id' => '1001',
                'player_name' => 'Test Player',
                'team_abbreviation' => 'LAS',
                'opponent' => 'vs MIN',
                'game_id' => '401900003',
                'stat_type' => 'points',
                'suggested_line' => 20.5,
                'predicted_value' => 23.1,
                'recommendation' => 'over',
                'confidence' => 72,
                'expected_value' => 4.5,
                'probability_over' => 61,
                'probability_under' => 39,
                'betting_value' => 'good',
                'reasoning' => 'Strong recent form',
            ],
            [
                'player_id' => '1002',
                'player_name' => 'Other Player',
                'team_abbreviation' => 'MIN',
                'opponent' => '@ LAS',
                'game_id' => '401900003',
                'stat_type' => 'rebounds',
                'suggested_line' => 7.5,
                'predicted_value' => 8.2,
                'recommendation' => 'over',
                'confidence' => 60,
                'expected_value' => 1.2,
                'betting_value' => 'fair',
                'reasoning' => 'Secondary pick',
            ],
        ], 'America/New_York');

        $top = TrackedPropPrediction::query()->where('is_top_prop', true)->first();
        $this->assertNotNull($top);
        $this->assertSame('1001', $top->player_id);
        $this->assertSame(1, $top->rank);

        $graded = $this->service->gradePendingProps();
        $this->assertSame(1, $graded);
        $this->assertTrue($top->fresh()->correct);

        $dashboard = $this->service->getAccuracyDashboard('America/New_York');
        $this->assertSame(100.0, $dashboard['props']['accuracy']);
        $this->assertSame(100.0, $dashboard['props']['top_prop_accuracy']);
        $this->assertSame('Test Player', $dashboard['top_prop_of_day']['player_name']);
    }

    public function test_locks_first_prop_pick_of_the_day(): void
    {
        $this->service->recordTodaysProps([
            [
                'player_id' => '1001',
                'player_name' => 'Test Player',
                'stat_type' => 'points',
                'suggested_line' => 20.5,
                'predicted_value' => 23.1,
                'recommendation' => 'over',
                'confidence' => 72,
            ],
        ], 'America/New_York');

        $this->service->recordTodaysProps([
            [
                'player_id' => '1001',
                'player_name' => 'Test Player',
                'stat_type' => 'points',
                'suggested_line' => 20.5,
                'predicted_value' => 99.0,
                'recommendation' => 'under',
                'confidence' => 10,
            ],
        ], 'America/New_York');

        $row = TrackedPropPrediction::query()->first();
        $this->assertSame(1, TrackedPropPrediction::query()->count());
        $this->assertSame(23.1, (float) $row->predicted_value);
        $this->assertSame('over', $row->recommendation);
    }

    public function test_grades_dnp_props_as_void_without_affecting_accuracy(): void
    {
        $player = WnbaPlayer::query()->create([
            'athlete_id' => '1009',
            'athlete_display_name' => 'DNP Player',
            'athlete_short_name' => 'D. Player',
        ]);
        $home = $this->makeTeam('11', 'SEA');
        $away = $this->makeTeam('12', 'PHX');
        $game = $this->makeFinalGame('401900009', $home, $away, 80, 75);

        WnbaPlayerGame::query()->create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $home->team_id,
            'points' => 0,
            'rebounds' => 0,
            'assists' => 0,
            'steals' => 0,
            'blocks' => 0,
            'turnovers' => 0,
            'fouls' => 0,
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
            'three_point_field_goals_made' => 0,
            'three_point_field_goals_attempted' => 0,
            'free_throws_made' => 0,
            'free_throws_attempted' => 0,
            'offensive_rebounds' => 0,
            'defensive_rebounds' => 0,
            'plus_minus' => 0,
            'starter' => false,
            'ejected' => false,
            'did_not_play' => true,
            'active' => false,
        ]);

        $this->service->recordTodaysProps([
            [
                'player_id' => '1009',
                'player_name' => 'DNP Player',
                'game_id' => '401900009',
                'stat_type' => 'points',
                'suggested_line' => 12.5,
                'predicted_value' => 14.0,
                'recommendation' => 'over',
                'confidence' => 60,
            ],
        ], 'America/New_York');

        $graded = $this->service->gradePendingProps();
        $this->assertSame(1, $graded);

        $row = TrackedPropPrediction::query()->first();
        $this->assertNotNull($row->graded_at);
        $this->assertNull($row->correct);
        $this->assertNull($row->actual_value);

        $dashboard = $this->service->getAccuracyDashboard('America/New_York');
        $this->assertSame(0, $dashboard['props']['graded']);
    }

    /**
     * @return array<string, mixed>
     */
    private function samplePreview(
        string $gameId,
        string $homeAbbr = 'NYL',
        string $awayAbbr = 'CON',
        string $winner = 'home',
        float $homeScore = 82,
        float $awayScore = 78,
    ): array {
        return [
            'game' => [
                'game_id' => $gameId,
                'season' => 2026,
                'game_date' => '2026-07-04',
            ],
            'home_team' => ['abbreviation' => $homeAbbr],
            'away_team' => ['abbreviation' => $awayAbbr],
            'prediction' => [
                'predicted_winner' => $winner,
                'projected_score' => [
                    'home' => $homeScore,
                    'away' => $awayScore,
                    'total' => $homeScore + $awayScore,
                ],
                'projected_spread' => $homeScore - $awayScore,
                'win_probability' => ['home' => 58, 'away' => 42],
                'confidence' => 16,
            ],
        ];
    }

    private function makeTeam(string $teamId, string $abbr): WnbaTeam
    {
        return WnbaTeam::query()->create([
            'team_id' => $teamId,
            'espn_team_id' => $teamId,
            'team_name' => $abbr,
            'team_display_name' => $abbr,
            'team_abbreviation' => $abbr,
            'team_location' => $abbr,
            'team_uid' => "s:40~l:59~t:{$teamId}",
            'team_slug' => strtolower($abbr),
        ]);
    }

    private function makeFinalGame(string $gameId, WnbaTeam $home, WnbaTeam $away, int $homeScore, int $awayScore): WnbaGame
    {
        $game = WnbaGame::query()->create([
            'game_id' => $gameId,
            'season' => 2026,
            'season_type' => 2,
            'game_date' => now()->toDateString(),
            'game_date_time' => now(),
            'status_name' => 'STATUS_FINAL',
            'status_type' => 'final',
            'status_abbreviation' => 'F',
        ]);

        WnbaGameTeam::query()->create([
            'game_id' => $game->id,
            'team_id' => $home->team_id,
            'opponent_team_id' => $away->team_id,
            'home_away' => 'home',
            'team_winner' => $homeScore > $awayScore,
            'team_score' => $homeScore,
            'opponent_team_score' => $awayScore,
            'field_goals_made' => 30,
            'field_goals_attempted' => 70,
            'three_point_field_goals_made' => 8,
            'three_point_field_goals_attempted' => 24,
            'free_throws_made' => 10,
            'free_throws_attempted' => 12,
            'offensive_rebounds' => 8,
            'defensive_rebounds' => 24,
            'rebounds' => 32,
            'assists' => 18,
            'steals' => 6,
            'blocks' => 4,
            'turnovers' => 12,
            'fouls' => 16,
        ]);

        WnbaGameTeam::query()->create([
            'game_id' => $game->id,
            'team_id' => $away->team_id,
            'opponent_team_id' => $home->team_id,
            'home_away' => 'away',
            'team_winner' => $awayScore > $homeScore,
            'team_score' => $awayScore,
            'opponent_team_score' => $homeScore,
            'field_goals_made' => 28,
            'field_goals_attempted' => 68,
            'three_point_field_goals_made' => 7,
            'three_point_field_goals_attempted' => 22,
            'free_throws_made' => 9,
            'free_throws_attempted' => 11,
            'offensive_rebounds' => 7,
            'defensive_rebounds' => 22,
            'rebounds' => 29,
            'assists' => 16,
            'steals' => 5,
            'blocks' => 3,
            'turnovers' => 14,
            'fouls' => 15,
        ]);

        return $game;
    }
}
