<?php

namespace Tests\Unit\WNBA;

use App\Services\WNBA\Data\Mappers\EspnMapper;
use Tests\TestCase;

class EspnMapperIntelTest extends TestCase
{
    public function test_map_athlete_overview_extracts_season_stats_and_news(): void
    {
        $mapper = new EspnMapper(2026);

        $mapped = $mapper->mapAthleteOverview([
            'statistics' => [
                'labels' => ['GP', 'PTS'],
                'names' => ['gamesPlayed', 'avgPoints'],
                'splits' => [
                    [
                        'displayName' => 'Regular Season',
                        'stats' => ['16', '16.6'],
                    ],
                ],
            ],
            'news' => [
                [
                    'id' => 1,
                    'headline' => 'Player hits game winner',
                    'description' => 'Long description',
                    'published' => '2026-06-23T00:00:00Z',
                    'links' => ['web' => ['href' => 'https://espn.com/news/1']],
                ],
            ],
            'nextGame' => [
                'league' => [
                    'events' => [
                        [
                            'id' => '401857017',
                            'name' => 'PHX @ IND',
                            'date' => '2026-06-24T23:30:00Z',
                            'location' => 'Gainbridge Fieldhouse',
                            'links' => [
                                ['rel' => ['summary'], 'href' => 'https://espn.com/game/401857017'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('16', $mapped['season_stats']['gamesPlayed']);
        $this->assertSame('Player hits game winner', $mapped['news'][0]['headline']);
        $this->assertSame('401857017', $mapped['next_game']['game_id']);
    }

    public function test_filter_player_injuries_from_league_feed(): void
    {
        $mapper = new EspnMapper(2026);

        $items = $mapper->filterPlayerInjuries([
            'injuries' => [
                [
                    'id' => '20',
                    'displayName' => 'Indiana Fever',
                    'injuries' => [
                        [
                            'id' => '1',
                            'status' => 'Out',
                            'shortComment' => 'Knee',
                            'athlete' => ['id' => '4432831', 'displayName' => 'Aliyah Boston'],
                        ],
                        [
                            'id' => '2',
                            'status' => 'Questionable',
                            'athlete' => ['id' => '9999999', 'displayName' => 'Other Player'],
                        ],
                    ],
                ],
            ],
        ], '4432831');

        $this->assertCount(1, $items);
        $this->assertSame('Out', $items[0]['status']);
        $this->assertSame('Indiana Fever', $items[0]['team_name']);
    }
}
