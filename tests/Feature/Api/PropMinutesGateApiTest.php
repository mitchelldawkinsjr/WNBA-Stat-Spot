<?php

namespace Tests\Feature\Api;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\Odds\OddsService;
use App\Services\WNBA\Data\GameScheduleService;
use App\Services\WNBA\Predictions\PredictionModelParamStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PropMinutesGateApiTest extends TestCase
{
    use RefreshDatabase;

    private const SEASON = 2026;

    protected function setUp(): void
    {
        parent::setUp();
        config(['wnba.seasons.current_season' => self::SEASON]);
        config(['wnba.predictions.defaults.gates.min_avg_minutes' => 15]);
        config(['wnba.predictions.defaults.gates.min_game_minutes' => 1]);
        app(PredictionModelParamStore::class)->forgetCache();
        Cache::flush();
    }

    public function test_todays_best_excludes_players_below_min_avg_minutes(): void
    {
        $this->seedTeams();

        $eligible = $this->seedPlayer('3149101', 'Eligible Minutes');
        $bench = $this->seedPlayer('3149102', 'Bench Zero');

        $today = now('America/New_York');
        $gameDateTime = $today->copy()->setTime(19, 0)->toIso8601String();

        $this->mock(GameScheduleService::class, function ($mock) use ($gameDateTime) {
            $mock->shouldReceive('list')->andReturn([
                [
                    'id' => 1,
                    'game_id' => '40187001',
                    'season' => (string) self::SEASON,
                    'game_date' => now('America/New_York')->toDateString(),
                    'game_date_time' => $gameDateTime,
                    'status_name' => 'STATUS_SCHEDULED',
                    'home_team' => [
                        'team_id' => '5',
                        'abbreviation' => 'ATL',
                        'name' => 'Atlanta Dream',
                    ],
                    'away_team' => [
                        'team_id' => '9',
                        'abbreviation' => 'NY',
                        'name' => 'New York Liberty',
                    ],
                ],
            ]);
        });

        $odds = Mockery::mock(OddsService::class);
        $odds->shouldReceive('getPlayerOdds')->andReturn(null);
        $this->app->instance(OddsService::class, $odds);

        $this->seedPriorBoxScore($eligible->id, '5', '30:00', 22);
        $this->seedPriorBoxScore($bench->id, '5', '0:00', 0);

        $response = $this->getJson('/api/wnba/predictions/todays-best?timezone=America/New_York');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(15.0, (float) $response->json('gates.min_avg_minutes'));

        $names = collect($response->json('data'))->pluck('player_name')->unique()->values()->all();
        $this->assertContains('Eligible Minutes', $names);
        $this->assertNotContains('Bench Zero', $names);

        foreach ($response->json('data') as $prop) {
            $this->assertArrayHasKey('avg_minutes', $prop);
            $this->assertGreaterThanOrEqual(15, $prop['avg_minutes']);
        }
    }

    public function test_param_store_exposes_minutes_gates_from_config(): void
    {
        config(['wnba.predictions.defaults.gates.min_avg_minutes' => 20]);
        config(['wnba.predictions.defaults.gates.min_game_minutes' => 5]);
        $store = app(PredictionModelParamStore::class);
        $store->forgetCache();

        $this->assertSame(20.0, $store->minAvgMinutes());
        $this->assertSame(5.0, $store->minGameMinutes());
    }

    private function seedTeams(): void
    {
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
    }

    private function seedPlayer(string $athleteId, string $name): WnbaPlayer
    {
        return WnbaPlayer::create([
            'athlete_id' => $athleteId,
            'athlete_display_name' => $name,
            'athlete_short_name' => $name,
            'athlete_position_abbreviation' => 'F',
        ]);
    }

    private function seedPriorBoxScore(int $playerId, string $teamId, string $minutes, int $points): void
    {
        $date = now('America/New_York')->subDays(2)->toDateString();
        $game = WnbaGame::create([
            'game_id' => '4018'.(2000 + $playerId),
            'season' => self::SEASON,
            'season_type' => 2,
            'game_date' => $date,
            'game_date_time' => $date.' 19:00:00',
        ]);
        WnbaGameTeam::create([
            'game_id' => $game->id,
            'team_id' => $teamId,
            'opponent_team_id' => $teamId === '5' ? '9' : '5',
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
            'player_id' => $playerId,
            'team_id' => $teamId,
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
            'minutes' => $minutes,
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
