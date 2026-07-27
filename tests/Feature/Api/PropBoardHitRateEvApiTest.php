<?php

namespace Tests\Feature\Api;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\Odds\OddsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PropBoardHitRateEvApiTest extends TestCase
{
    use RefreshDatabase;

    private const SEASON = 2026;

    protected function setUp(): void
    {
        parent::setUp();
        config(['wnba.seasons.current_season' => self::SEASON]);
        Cache::flush();
    }

    public function test_generate_returns_null_ev_and_hit_rates_without_real_odds(): void
    {
        $player = $this->seedPlayerWithGames();

        $odds = Mockery::mock(OddsService::class);
        $odds->shouldReceive('getPlayerOdds')->andReturn(null);
        $this->app->instance(OddsService::class, $odds);

        $response = $this->postJson('/api/wnba/predictions/generate', [
            'player_id' => $player->athlete_id,
            'stat' => 'points',
            'line' => 20.5,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $data = $response->json('data');

        $this->assertNull($data['expected_value']);
        $this->assertFalse($data['odds_available']);
        $this->assertSame('research', $data['betting_value']);
        $this->assertArrayHasKey('hit_rates', $data);
        $this->assertArrayHasKey('l5', $data['hit_rates']);
        $this->assertSame(3, $data['hit_rates']['l5']['hits']);
        $this->assertSame(5, $data['hit_rates']['l5']['games']);
        $this->assertNotEmpty($data['recent_games']);
        $this->assertNull($data['odds_data']['over_odds'] ?? null);
    }

    public function test_generate_returns_numeric_ev_when_book_odds_available(): void
    {
        $player = $this->seedPlayerWithGames();

        $odds = Mockery::mock(OddsService::class);
        $odds->shouldReceive('getPlayerOdds')->andReturn([
            'line' => 20.5,
            'over_odds' => -110,
            'under_odds' => -110,
            'over_bookmaker' => 'FanDuel',
            'under_bookmaker' => 'FanDuel',
            'bookmakers' => [],
            'event_id' => 'evt1',
            'commence_time' => now()->toISOString(),
        ]);
        $this->app->instance(OddsService::class, $odds);

        $response = $this->postJson('/api/wnba/predictions/generate', [
            'player_id' => $player->athlete_id,
            'stat' => 'points',
            'line' => 20.5,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $data = $response->json('data');

        $this->assertTrue($data['odds_available']);
        $this->assertIsNumeric($data['expected_value']);
        $this->assertNotSame('research', $data['betting_value']);
        $this->assertArrayHasKey('hit_rates', $data);
        $this->assertSame(-110, $data['odds_data']['over_odds']);
    }

    private function seedPlayerWithGames(): WnbaPlayer
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

        $player = WnbaPlayer::create([
            'athlete_id' => '3149001',
            'athlete_display_name' => 'Test Scorer',
            'athlete_short_name' => 'T. Scorer',
            'athlete_position_abbreviation' => 'F',
        ]);

        $points = [15, 18, 22, 24, 25];
        foreach ($points as $i => $pts) {
            $date = sprintf('2026-06-%02d', $i + 1);
            $game = WnbaGame::create([
                'game_id' => '4018'.(1000 + $i),
                'season' => self::SEASON,
                'season_type' => 2,
                'game_date' => $date,
                'game_date_time' => $date.' 19:00:00',
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
                'player_id' => $player->id,
                'team_id' => '5',
                'points' => $pts,
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

        return $player;
    }
}
