<?php

namespace Tests\Feature\Api;

use App\Services\WNBA\Data\PlayerIntelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerIntelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_overview_endpoint_returns_intel_bundle(): void
    {
        $this->mock(PlayerIntelService::class, function ($mock) {
            $mock->shouldReceive('overview')
                ->once()
                ->with('4432831', 2026)
                ->andReturn([
                    'provider' => 'espn',
                    'season' => 2026,
                    'player_id' => '4432831',
                    'canonical_player_id' => '4432831',
                    'season_stats' => ['avgPoints' => '16.6'],
                    'splits' => [],
                    'news' => [['headline' => 'Test headline']],
                    'injuries' => [],
                    'next_game' => ['game_id' => '401857017'],
                    'fantasy_outlook' => null,
                ]);
        });

        $this->getJson('/api/players/4432831/overview?season=2026')
            ->assertOk()
            ->assertJsonPath('data.season', 2026)
            ->assertJsonPath('data.season_stats.avgPoints', '16.6')
            ->assertJsonPath('data.news.0.headline', 'Test headline');
    }

    public function test_player_season_stats_endpoint(): void
    {
        $this->mock(PlayerIntelService::class, function ($mock) {
            $mock->shouldReceive('seasonStats')
                ->once()
                ->with('4432831', 2026)
                ->andReturn([
                    'provider' => 'espn',
                    'season' => 2026,
                    'player_id' => '4432831',
                    'canonical_player_id' => '4432831',
                    'season_stats' => ['gamesPlayed' => '16'],
                    'splits' => [],
                ]);
        });

        $this->getJson('/api/players/4432831/season-stats?season=2026')
            ->assertOk()
            ->assertJsonPath('data.season_stats.gamesPlayed', '16');
    }

    public function test_player_news_endpoint(): void
    {
        $this->mock(PlayerIntelService::class, function ($mock) {
            $mock->shouldReceive('news')
                ->once()
                ->with('4432831', 5)
                ->andReturn([
                    'provider' => 'espn',
                    'player_id' => '4432831',
                    'canonical_player_id' => '4432831',
                    'items' => [['headline' => 'Breaking news']],
                ]);
        });

        $this->getJson('/api/players/4432831/news?limit=5')
            ->assertOk()
            ->assertJsonPath('data.items.0.headline', 'Breaking news');
    }

    public function test_player_injuries_endpoint(): void
    {
        $this->mock(PlayerIntelService::class, function ($mock) {
            $mock->shouldReceive('injuries')
                ->once()
                ->with('4432831', null)
                ->andReturn([
                    'provider' => 'espn',
                    'player_id' => '4432831',
                    'canonical_player_id' => '4432831',
                    'items' => [['status' => 'Out']],
                ]);
        });

        $this->getJson('/api/players/4432831/injuries')
            ->assertOk()
            ->assertJsonPath('data.items.0.status', 'Out');
    }
}
