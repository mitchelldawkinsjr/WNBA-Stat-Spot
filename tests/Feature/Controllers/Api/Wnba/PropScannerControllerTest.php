<?php

namespace Tests\Feature\Controllers\Api\Wnba;

use Tests\TestCase;
use App\Models\Player;
use App\Models\Game;
use App\Models\Team;
use App\Models\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class PropScannerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    /** @test */
    public function it_returns_prop_predictions_for_all_players()
    {
        // Create test data
        $team = Team::factory()->create();
        $player = Player::factory()->create(['team_id' => $team->id]);
        $game = Game::factory()->create([
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create()->id
        ]);

        // Create historical stats
        Stat::factory()->count(5)->create([
            'player_id' => $player->id,
            'game_id' => $game->id,
            'points' => 15,
            'rebounds' => 5,
            'assists' => 3
        ]);

        $response = $this->getJson('/api/wnba/prop-scanner/scan-all');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'player_id',
                        'player_name',
                        'game_id',
                        'game_date',
                        'stat_type',
                        'line_value',
                        'prediction' => [
                            'over_probability',
                            'expected_value',
                            'standard_deviation',
                            'confidence_intervals'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_caches_results_for_performance()
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create(['team_id' => $team->id]);
        $game = Game::factory()->create([
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create()->id
        ]);

        Stat::factory()->count(5)->create([
            'player_id' => $player->id,
            'game_id' => $game->id,
            'points' => 15
        ]);

        // First request should cache the results
        $this->getJson('/api/wnba/prop-scanner/scan-all');

        $this->assertTrue(Cache::has('prop_scanner_results'));

        // Second request should use cached results
        $response = $this->getJson('/api/wnba/prop-scanner/scan-all');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_no_available_games()
    {
        $response = $this->getJson('/api/wnba/prop-scanner/scan-all');

        $response->assertStatus(200)
            ->assertJson([
                'data' => []
            ]);
    }

    /** @test */
    public function it_returns_predictions_for_specific_player()
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create(['team_id' => $team->id]);
        $game = Game::factory()->create([
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create()->id
        ]);

        Stat::factory()->count(5)->create([
            'player_id' => $player->id,
            'game_id' => $game->id,
            'points' => 15
        ]);

        $response = $this->getJson("/api/wnba/prop-scanner/player/{$player->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'player_id',
                        'player_name',
                        'game_id',
                        'game_date',
                        'stat_type',
                        'line_value',
                        'prediction'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_invalid_player()
    {
        $response = $this->getJson('/api/wnba/prop-scanner/player/999');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_predictions_for_specific_game()
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create(['team_id' => $team->id]);
        $game = Game::factory()->create([
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create()->id
        ]);

        Stat::factory()->count(5)->create([
            'player_id' => $player->id,
            'game_id' => $game->id,
            'points' => 15
        ]);

        $response = $this->getJson("/api/wnba/prop-scanner/game/{$game->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'player_id',
                        'player_name',
                        'game_id',
                        'game_date',
                        'stat_type',
                        'line_value',
                        'prediction'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_invalid_game()
    {
        $response = $this->getJson('/api/wnba/prop-scanner/game/999');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_validates_input_parameters()
    {
        $response = $this->getJson('/api/wnba/prop-scanner/player/invalid-id');

        $response->assertStatus(422);
    }
}
