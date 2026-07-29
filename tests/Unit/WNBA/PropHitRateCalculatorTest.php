<?php

namespace Tests\Unit\WNBA;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\WNBA\Predictions\PredictionModelParamStore;
use App\Services\WNBA\Predictions\PropHitRateCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PropHitRateCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private const SEASON = 2026;

    private PropHitRateCalculator $calculator;

    private int $playerId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['wnba.seasons.current_season' => self::SEASON]);
        config(['wnba.predictions.defaults.gates.min_game_minutes' => 1]);
        config(['wnba.predictions.defaults.gates.min_avg_minutes' => 15]);
        app(PredictionModelParamStore::class)->forgetCache();
        $this->calculator = app(PropHitRateCalculator::class);

        WnbaTeam::create([
            'team_id' => '5',
            'team_name' => 'Dream',
            'team_location' => 'Atlanta',
            'team_abbreviation' => 'ATL',
            'team_display_name' => 'Atlanta Dream',
        ]);
        WnbaTeam::create([
            'team_id' => '9',
            'team_name' => 'Liberty',
            'team_location' => 'New York',
            'team_abbreviation' => 'NY',
            'team_display_name' => 'New York Liberty',
        ]);
        WnbaTeam::create([
            'team_id' => '18',
            'team_name' => 'Storm',
            'team_location' => 'Seattle',
            'team_abbreviation' => 'SEA',
            'team_display_name' => 'Seattle Storm',
        ]);

        $player = WnbaPlayer::create([
            'athlete_id' => '9990001',
            'athlete_display_name' => 'Hit Rate Player',
            'athlete_short_name' => 'H. Player',
        ]);
        $this->playerId = (int) $player->id;

        // Newest first conceptually: dates ascending with later dates = more recent.
        // Points vs line 20.5: over on 22,22,22,22,19,18,25,21,20,15 (L5: 4/5 overs if newest are first four 22s + 19)
        $pointSequence = [
            ['2026-06-01', 15, '9'],
            ['2026-06-03', 20, '9'], // equal to 20.0 line test below; vs 20.5 = under
            ['2026-06-05', 21, '18'],
            ['2026-06-07', 25, '18'],
            ['2026-06-09', 18, '9'],
            ['2026-06-11', 19, '9'],
            ['2026-06-13', 22, '18'],
            ['2026-06-15', 22, '18'],
            ['2026-06-17', 22, '9'],
            ['2026-06-19', 22, '9'],
        ];

        foreach ($pointSequence as $i => [$date, $points, $opp]) {
            $this->seedPlayerGame($date, '4019'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), $points, $opp);
        }
    }

    #[Test]
    public function it_computes_window_hit_rates_vs_line(): void
    {
        $result = $this->calculator->calculate($this->playerId, 'points', 20.5, '9', self::SEASON);

        // Newest 5: 22,22,22,22,19 → 4 hits
        $this->assertSame(4, $result['l5']['hits']);
        $this->assertSame(5, $result['l5']['games']);
        $this->assertEqualsWithDelta(0.8, $result['l5']['rate'], 0.0001);

        // L10: all 10 games — overs: 22x4, 21, 25 = 6; unders: 19,18,20,15 = 4 → 6/10
        $this->assertSame(6, $result['l10']['hits']);
        $this->assertSame(10, $result['l10']['games']);
        $this->assertEqualsWithDelta(0.6, $result['l10']['rate'], 0.0001);

        $this->assertSame(10, $result['season']['games']);
        $this->assertCount(10, $result['recent_games']);
        $this->assertTrue($result['recent_games'][0]['over']);
        $this->assertSame(22.0, $result['recent_games'][0]['value']);
    }

    #[Test]
    public function equal_to_line_is_not_a_hit(): void
    {
        $result = $this->calculator->calculate($this->playerId, 'points', 20.0, null, self::SEASON);

        // Value 20 appears once — not a hit
        $seasonHits = $result['season']['hits'];
        $oversStrict = 0;
        foreach ([15, 20, 21, 25, 18, 19, 22, 22, 22, 22] as $v) {
            if ($v > 20.0) {
                $oversStrict++;
            }
        }
        $this->assertSame($oversStrict, $seasonHits);
    }

    #[Test]
    public function h2h_filters_to_opponent(): void
    {
        $result = $this->calculator->calculate($this->playerId, 'points', 20.5, '9', self::SEASON);

        // vs team 9: 15,20,18,19,22,22 → overs: 22,22 = 2/6
        $this->assertNotNull($result['h2h']);
        $this->assertSame(2, $result['h2h']['hits']);
        $this->assertSame(6, $result['h2h']['games']);
    }

    #[Test]
    public function h2h_is_null_without_opponent(): void
    {
        $result = $this->calculator->calculate($this->playerId, 'points', 20.5, null, self::SEASON);
        $this->assertNull($result['h2h']);
    }

    #[Test]
    public function excludes_dnp_and_invalid_rows(): void
    {
        $game = WnbaGame::create([
            'game_id' => '40199999',
            'season' => self::SEASON,
            'season_type' => 2,
            'game_date' => '2026-06-21',
            'game_date_time' => '2026-06-21 19:00:00',
        ]);
        WnbaGameTeam::create([
            'game_id' => $game->id,
            'team_id' => '5',
            'opponent_team_id' => '9',
            'home_away' => 'home',
            'team_winner' => true,
            'team_score' => 80,
            'opponent_team_score' => 70,
            'field_goals_made' => 30, 'field_goals_attempted' => 60,
            'three_point_field_goals_made' => 8, 'three_point_field_goals_attempted' => 25,
            'free_throws_made' => 12, 'free_throws_attempted' => 20,
            'offensive_rebounds' => 10, 'defensive_rebounds' => 25, 'rebounds' => 35,
            'assists' => 20, 'steals' => 7, 'blocks' => 4, 'turnovers' => 15, 'fouls' => 18,
        ]);
        WnbaPlayerGame::create([
            'game_id' => $game->id,
            'player_id' => $this->playerId,
            'team_id' => '5',
            'points' => 40,
            'rebounds' => 0,
            'assists' => 0,
            'steals' => 0,
            'blocks' => 0,
            'did_not_play' => true,
            'active' => false,
            'starter' => false,
            'ejected' => false,
            'plus_minus' => 0,
            'minutes' => '0:00',
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
            'three_point_field_goals_made' => 0,
            'three_point_field_goals_attempted' => 0,
            'free_throws_made' => 0,
            'free_throws_attempted' => 0,
            'offensive_rebounds' => 0,
            'defensive_rebounds' => 0,
            'turnovers' => 0,
            'fouls' => 0,
        ]);

        $result = $this->calculator->calculate($this->playerId, 'points', 20.5, null, self::SEASON);
        $this->assertSame(10, $result['season']['games']);
    }

    #[Test]
    public function excludes_games_below_min_game_minutes(): void
    {
        $game = WnbaGame::create([
            'game_id' => '40198888',
            'season' => self::SEASON,
            'season_type' => 2,
            'game_date' => '2026-06-22',
            'game_date_time' => '2026-06-22 19:00:00',
        ]);
        WnbaGameTeam::create([
            'game_id' => $game->id,
            'team_id' => '5',
            'opponent_team_id' => '9',
            'home_away' => 'home',
            'team_winner' => true,
            'team_score' => 80,
            'opponent_team_score' => 70,
            'field_goals_made' => 30, 'field_goals_attempted' => 60,
            'three_point_field_goals_made' => 8, 'three_point_field_goals_attempted' => 25,
            'free_throws_made' => 12, 'free_throws_attempted' => 20,
            'offensive_rebounds' => 10, 'defensive_rebounds' => 25, 'rebounds' => 35,
            'assists' => 20, 'steals' => 7, 'blocks' => 4, 'turnovers' => 15, 'fouls' => 18,
        ]);
        WnbaPlayerGame::create([
            'game_id' => $game->id,
            'player_id' => $this->playerId,
            'team_id' => '5',
            'points' => 40,
            'rebounds' => 0,
            'assists' => 0,
            'steals' => 0,
            'blocks' => 0,
            'did_not_play' => false,
            'active' => true,
            'starter' => false,
            'ejected' => false,
            'plus_minus' => 0,
            'minutes' => '0:00',
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
            'three_point_field_goals_made' => 0,
            'three_point_field_goals_attempted' => 0,
            'free_throws_made' => 0,
            'free_throws_attempted' => 0,
            'offensive_rebounds' => 0,
            'defensive_rebounds' => 0,
            'turnovers' => 0,
            'fouls' => 0,
        ]);

        $result = $this->calculator->calculate($this->playerId, 'points', 20.5, null, self::SEASON);
        $this->assertSame(10, $result['season']['games']);
    }

    private function seedPlayerGame(string $date, string $gameId, int $points, string $opponentTeamId): void
    {
        $game = WnbaGame::create([
            'game_id' => $gameId,
            'season' => self::SEASON,
            'season_type' => 2,
            'game_date' => $date,
            'game_date_time' => $date.' 19:00:00',
        ]);

        WnbaGameTeam::create([
            'game_id' => $game->id,
            'team_id' => '5',
            'opponent_team_id' => $opponentTeamId,
            'home_away' => 'home',
            'team_winner' => true,
            'team_score' => 80,
            'opponent_team_score' => 70,
            'field_goals_made' => 30, 'field_goals_attempted' => 60,
            'three_point_field_goals_made' => 8, 'three_point_field_goals_attempted' => 25,
            'free_throws_made' => 12, 'free_throws_attempted' => 20,
            'offensive_rebounds' => 10, 'defensive_rebounds' => 25, 'rebounds' => 35,
            'assists' => 20, 'steals' => 7, 'blocks' => 4, 'turnovers' => 15, 'fouls' => 18,
        ]);

        WnbaPlayerGame::create([
            'game_id' => $game->id,
            'player_id' => $this->playerId,
            'team_id' => '5',
            'points' => $points,
            'rebounds' => 5,
            'assists' => 3,
            'steals' => 1,
            'blocks' => 0,
            'did_not_play' => false,
            'active' => true,
            'starter' => true,
            'ejected' => false,
            'plus_minus' => 0,
            'minutes' => '30:00',
            'field_goals_made' => 8,
            'field_goals_attempted' => 16,
            'three_point_field_goals_made' => 1,
            'three_point_field_goals_attempted' => 4,
            'free_throws_made' => 2,
            'free_throws_attempted' => 2,
            'offensive_rebounds' => 1,
            'defensive_rebounds' => 4,
            'turnovers' => 2,
            'fouls' => 2,
        ]);
    }
}
