<?php

namespace Tests\Feature\Api;

use App\Models\WnbaPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PlayerApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestPlayers();
    }

    public function test_players_index_returns_successful_response(): void
    {
        $response = $this->get('/api/players');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'athlete_id',
                            'athlete_display_name',
                            'athlete_position_name',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'meta' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    }

    public function test_players_index_with_search(): void
    {
        $player = WnbaPlayer::first();
        $searchTerm = urlencode(trim(substr($player->athlete_display_name, 0, 5)));

        $response = $this->get("/api/players?search={$searchTerm}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'athlete_display_name' => $player->athlete_display_name,
            ]);
    }

    public function test_players_index_with_pagination(): void
    {
        $response = $this->get('/api/players?per_page=5&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.current_page', 1);
    }

    public function test_player_show_returns_correct_data(): void
    {
        $player = WnbaPlayer::first();

        $response = $this->get("/api/players/{$player->athlete_id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $player->id,
                'athlete_display_name' => $player->athlete_display_name,
            ]);
    }

    public function test_player_show_returns_404_for_nonexistent_player(): void
    {
        $response = $this->get('/api/players/99999');

        $response->assertStatus(404);
    }

    public function test_players_api_validation(): void
    {
        $response = $this->get('/api/players?per_page=invalid');

        $this->assertNotEquals(500, $response->status());
    }

    public function test_players_api_returns_consistent_structure(): void
    {
        $response1 = $this->get('/api/players');
        $response2 = $this->get('/api/players?page=1');

        $data1 = $response1->json('data');
        $data2 = $response2->json('data');

        $this->assertEquals(array_keys($data1), array_keys($data2));
        $this->assertEquals(array_keys($data1['meta']), array_keys($data2['meta']));
    }

    private function createTestPlayers(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            WnbaPlayer::create([
                'athlete_id' => "test-player-{$i}",
                'athlete_display_name' => "Test Player {$i}",
                'athlete_short_name' => "Player{$i}",
                'athlete_jersey' => (string) $i,
                'athlete_position_name' => $i <= 5 ? 'Guard' : 'Forward',
                'athlete_position_abbreviation' => $i <= 5 ? 'G' : 'F',
            ]);
        }
    }
}
