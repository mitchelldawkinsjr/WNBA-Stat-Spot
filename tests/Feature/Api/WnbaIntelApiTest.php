<?php

namespace Tests\Feature\Api;

use App\Services\WNBA\Data\PlayerIntelService;
use Tests\TestCase;

class WnbaIntelApiTest extends TestCase
{
    public function test_league_news_endpoint(): void
    {
        $this->mock(PlayerIntelService::class, function ($mock) {
            $mock->shouldReceive('leagueNews')
                ->once()
                ->with(10, null)
                ->andReturn([
                    'provider' => 'espn',
                    'items' => [['headline' => 'League headline']],
                ]);
        });

        $this->getJson('/api/wnba/news?limit=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.headline', 'League headline');
    }

    public function test_league_injuries_endpoint(): void
    {
        $this->mock(PlayerIntelService::class, function ($mock) {
            $mock->shouldReceive('leagueInjuries')
                ->once()
                ->andReturn([
                    'provider' => 'espn',
                    'season' => ['year' => 2026],
                    'teams' => [
                        ['team_name' => 'Indiana Fever', 'injuries' => []],
                    ],
                ]);
        });

        $this->getJson('/api/wnba/injuries')
            ->assertOk()
            ->assertJsonPath('data.season.year', 2026)
            ->assertJsonPath('data.teams.0.team_name', 'Indiana Fever');
    }
}
