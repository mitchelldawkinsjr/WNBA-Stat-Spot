<?php

namespace Tests\Unit\Services\WNBA\Data\Mappers;

use App\Services\WNBA\Data\Mappers\WnbaStatsMapper;
use Tests\TestCase;

class WnbaStatsMapperTest extends TestCase
{
    private WnbaStatsMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new WnbaStatsMapper;
    }

    public function test_map_schedule_normalizes_date_and_tricodes(): void
    {
        $rows = $this->mapper->mapSchedule([
            'leagueSchedule' => [
                'gameDates' => [
                    [
                        'gameDate' => '07/20/2026 00:00:00',
                        'games' => [
                            [
                                'gameId' => '1022600123',
                                'gameStatusText' => 'Final',
                                'homeTeam' => ['teamId' => 1611661319, 'teamTricode' => 'LVA'],
                                'awayTeam' => ['teamId' => 1611661313, 'teamTricode' => 'NYL'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('1022600123', $rows[0]['game_id']);
        $this->assertSame('2026-07-20', $rows[0]['game_date']);
        $this->assertSame('LV', $rows[0]['home_tricode']);
        $this->assertSame('NY', $rows[0]['away_tricode']);
        $this->assertSame('1611661319', $rows[0]['home_team_id']);
    }

    public function test_map_box_score_traditional_maps_player_and_team_stats(): void
    {
        $mapped = $this->mapper->mapBoxScoreTraditional([
            'boxScoreTraditional' => [
                'gameId' => '1022600123',
                'homeTeam' => [
                    'teamId' => 1611661319,
                    'teamTricode' => 'LVA',
                    'statistics' => [
                        'points' => 88,
                        'fieldGoalsMade' => 30,
                        'fieldGoalsAttempted' => 70,
                        'threePointersMade' => 8,
                        'threePointersAttempted' => 25,
                        'freeThrowsMade' => 20,
                        'freeThrowsAttempted' => 24,
                        'reboundsOffensive' => 10,
                        'reboundsDefensive' => 30,
                        'reboundsTotal' => 40,
                        'assists' => 20,
                        'steals' => 7,
                        'blocks' => 4,
                        'turnovers' => 12,
                        'foulsPersonal' => 18,
                    ],
                    'players' => [
                        [
                            'personId' => 1628932,
                            'firstName' => 'A\'ja',
                            'familyName' => 'Wilson',
                            'jerseyNum' => '22',
                            'comment' => '',
                            'statistics' => [
                                'minutes' => 'PT32M15.00S',
                                'points' => 25,
                                'fieldGoalsMade' => 10,
                                'fieldGoalsAttempted' => 18,
                                'threePointersMade' => 1,
                                'threePointersAttempted' => 3,
                                'freeThrowsMade' => 4,
                                'freeThrowsAttempted' => 5,
                                'reboundsOffensive' => 2,
                                'reboundsDefensive' => 8,
                                'reboundsTotal' => 10,
                                'assists' => 3,
                                'steals' => 1,
                                'blocks' => 2,
                                'turnovers' => 2,
                                'foulsPersonal' => 3,
                                'plusMinusPoints' => 12,
                            ],
                        ],
                        [
                            'personId' => 999,
                            'firstName' => 'Bench',
                            'familyName' => 'Player',
                            'comment' => "DNP - Coach's Decision",
                            'statistics' => [
                                'minutes' => 'PT00M00.00S',
                                'points' => 0,
                                'fieldGoalsMade' => 0,
                                'fieldGoalsAttempted' => 0,
                                'threePointersMade' => 0,
                                'threePointersAttempted' => 0,
                                'freeThrowsMade' => 0,
                                'freeThrowsAttempted' => 0,
                                'reboundsOffensive' => 0,
                                'reboundsDefensive' => 0,
                                'reboundsTotal' => 0,
                                'assists' => 0,
                                'steals' => 0,
                                'blocks' => 0,
                                'turnovers' => 0,
                                'foulsPersonal' => 0,
                                'plusMinusPoints' => 0,
                            ],
                        ],
                    ],
                ],
                'awayTeam' => [
                    'teamId' => 1611661313,
                    'teamTricode' => 'NYL',
                    'statistics' => [
                        'points' => 80,
                        'fieldGoalsMade' => 28,
                        'fieldGoalsAttempted' => 65,
                        'threePointersMade' => 6,
                        'threePointersAttempted' => 20,
                        'freeThrowsMade' => 18,
                        'freeThrowsAttempted' => 20,
                        'reboundsOffensive' => 8,
                        'reboundsDefensive' => 25,
                        'reboundsTotal' => 33,
                        'assists' => 18,
                        'steals' => 5,
                        'blocks' => 3,
                        'turnovers' => 14,
                        'foulsPersonal' => 20,
                    ],
                    'players' => [],
                ],
            ],
        ]);

        $this->assertSame('1022600123', $mapped['game_id']);
        $this->assertCount(2, $mapped['teams']);
        $this->assertSame(88, $mapped['teams'][0]['team_score']);
        $this->assertSame('LV', $mapped['teams'][0]['team_tricode']);

        $this->assertCount(2, $mapped['players']);
        $player = $mapped['players'][0];
        $this->assertSame('1628932', $player['wnba_stats_player_id']);
        $this->assertSame(25, $player['points']);
        $this->assertSame(10, $player['rebounds']);
        $this->assertSame('32:15', $player['minutes']);
        $this->assertFalse($player['did_not_play']);
        $this->assertTrue($mapped['players'][1]['did_not_play']);
    }

    public function test_to_float_minutes_parses_iso_duration(): void
    {
        $this->assertSame(32.25, $this->mapper->toFloatMinutes('PT32M15.00S'));
        $this->assertSame(10.5, $this->mapper->toFloatMinutes('10:30'));
        $this->assertNull($this->mapper->toFloatMinutes(null));
    }
}
