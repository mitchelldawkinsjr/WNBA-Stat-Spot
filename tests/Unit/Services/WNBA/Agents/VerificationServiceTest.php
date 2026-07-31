<?php

namespace Tests\Unit\Services\WNBA\Agents;

use App\Models\WnbaDataConflict;
use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\WNBA\Agents\BoxScoreValidator;
use App\Services\WNBA\Agents\RawPayloadStore;
use App\Services\WNBA\Agents\VerificationService;
use App\Services\WNBA\Data\Mappers\WnbaStatsMapper;
use App\Services\WNBA\Data\Support\WnbaStatsApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private WnbaStatsApiClient $client;

    private VerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(WnbaStatsApiClient::class);
        $this->service = new VerificationService(
            $this->client,
            new WnbaStatsMapper,
            new RawPayloadStore,
            new BoxScoreValidator,
        );
        config(['wnba.seasons.current_season' => 2026]);
        config(['wnba.agents.verification.lookback_days' => 30]);
        config(['wnba.agents.verification.request_delay_ms' => 0]);
    }

    public function test_stat_mismatch_opens_review_queue_item(): void
    {
        [$game, $player] = $this->seedFinalGame(playerPoints: 20);
        $player->wnba_stats_player_id = '1628932';
        $player->save();

        $this->client->shouldReceive('schedule')->once()->andReturn($this->schedulePayload());
        $this->client->shouldReceive('boxScoreTraditional')
            ->once()
            ->with('1022600123')
            ->andReturn($this->boxPayload(playerPoints: 25));

        $findings = $this->service->verify(2026, fullSeason: true);

        $this->assertSame(1, $findings['games_checked']);
        $this->assertGreaterThan(0, $findings['stat_mismatches']);

        $conflict = WnbaDataConflict::query()
            ->where('entity_type', 'player_game_stat')
            ->where('field', 'points')
            ->first();

        $this->assertNotNull($conflict);
        $this->assertTrue((bool) $conflict->requires_review);
        $this->assertSame("{$game->game_id}|{$player->athlete_id}", $conflict->entity_key);
        $this->assertCount(2, $conflict->candidates);
    }

    public function test_matching_boxes_produce_zero_stat_mismatches(): void
    {
        [$game, $player] = $this->seedFinalGame(playerPoints: 25);
        $player->wnba_stats_player_id = '1628932';
        $player->save();

        $this->client->shouldReceive('schedule')->once()->andReturn($this->schedulePayload());
        $this->client->shouldReceive('boxScoreTraditional')
            ->once()
            ->andReturn($this->boxPayload(playerPoints: 25));

        $findings = $this->service->verify(2026, fullSeason: true);

        $this->assertSame(1, $findings['games_checked']);
        $this->assertSame(0, $findings['stat_mismatches']);
        $this->assertSame(0, WnbaDataConflict::where('field', 'points')->count());
    }

    public function test_repeated_runs_do_not_duplicate_open_findings(): void
    {
        [, $player] = $this->seedFinalGame(playerPoints: 20);
        $player->wnba_stats_player_id = '1628932';
        $player->save();

        $this->client->shouldReceive('schedule')->twice()->andReturn($this->schedulePayload());
        $this->client->shouldReceive('boxScoreTraditional')
            ->twice()
            ->andReturn($this->boxPayload(playerPoints: 25));

        $this->service->verify(2026, fullSeason: true);
        $this->service->verify(2026, fullSeason: true);

        $this->assertSame(
            1,
            WnbaDataConflict::query()
                ->where('entity_type', 'player_game_stat')
                ->where('field', 'points')
                ->where('requires_review', true)
                ->count()
        );
    }

    public function test_fingerprint_links_player_id_when_stats_match(): void
    {
        [, $player] = $this->seedFinalGame(playerPoints: 25);
        $this->assertNull($player->wnba_stats_player_id);

        $this->client->shouldReceive('schedule')->once()->andReturn($this->schedulePayload());
        $this->client->shouldReceive('boxScoreTraditional')
            ->once()
            ->andReturn($this->boxPayload(playerPoints: 25));

        $findings = $this->service->verify(2026, fullSeason: true);

        $this->assertSame(1, $findings['ids_linked']);
        $this->assertSame('1628932', $player->fresh()->wnba_stats_player_id);
        $this->assertSame(0, $findings['stat_mismatches']);
    }

    public function test_unmapped_game_is_flagged_and_skipped(): void
    {
        $this->seedFinalGame(playerPoints: 25, homeAbbr: 'ATL', awayAbbr: 'CHI');

        $this->client->shouldReceive('schedule')->once()->andReturn($this->schedulePayload());
        $this->client->shouldReceive('boxScoreTraditional')->never();

        $findings = $this->service->verify(2026, fullSeason: true);

        $this->assertSame(0, $findings['games_checked']);
        $this->assertSame(1, $findings['games_unmapped']);
        $this->assertTrue(
            WnbaDataConflict::query()
                ->where('field', 'oracle_game_unmapped')
                ->exists()
        );
    }

    public function test_dry_run_does_not_write_conflicts(): void
    {
        [, $player] = $this->seedFinalGame(playerPoints: 20);
        $player->wnba_stats_player_id = '1628932';
        $player->save();

        $this->client->shouldReceive('schedule')->once()->andReturn($this->schedulePayload());
        $this->client->shouldReceive('boxScoreTraditional')
            ->once()
            ->andReturn($this->boxPayload(playerPoints: 25));

        $this->service->verify(2026, fullSeason: true, dryRun: true);

        $this->assertSame(0, WnbaDataConflict::count());
    }

    /**
     * @return array{0: WnbaGame, 1: WnbaPlayer}
     */
    private function seedFinalGame(int $playerPoints, string $homeAbbr = 'LV', string $awayAbbr = 'NY'): array
    {
        $home = WnbaTeam::create([
            'team_id' => '17',
            'team_name' => 'Aces',
            'team_location' => 'Las Vegas',
            'team_abbreviation' => $homeAbbr,
            'team_display_name' => 'Las Vegas Aces',
        ]);
        $away = WnbaTeam::create([
            'team_id' => '18',
            'team_name' => 'Liberty',
            'team_location' => 'New York',
            'team_abbreviation' => $awayAbbr,
            'team_display_name' => 'New York Liberty',
        ]);

        $game = WnbaGame::create([
            'game_id' => '401857123',
            'season' => 2026,
            'season_type' => 2,
            'game_date' => '2026-07-20',
            'game_date_time' => '2026-07-20 19:00:00',
            'status_name' => 'STATUS_FINAL',
            'status_type' => 'post',
        ]);

        WnbaGameTeam::create([
            'game_id' => $game->id,
            'team_id' => $home->team_id,
            'opponent_team_id' => $away->team_id,
            'home_away' => 'home',
            'team_winner' => true,
            'team_score' => 88,
            'opponent_team_score' => 80,
            'field_goals_made' => 30,
            'field_goals_attempted' => 70,
            'three_point_field_goals_made' => 8,
            'three_point_field_goals_attempted' => 25,
            'free_throws_made' => 20,
            'free_throws_attempted' => 24,
            'offensive_rebounds' => 10,
            'defensive_rebounds' => 30,
            'rebounds' => 40,
            'assists' => 20,
            'steals' => 7,
            'blocks' => 4,
            'turnovers' => 12,
            'fouls' => 18,
        ]);
        WnbaGameTeam::create([
            'game_id' => $game->id,
            'team_id' => $away->team_id,
            'opponent_team_id' => $home->team_id,
            'home_away' => 'away',
            'team_winner' => false,
            'team_score' => 80,
            'opponent_team_score' => 88,
            'field_goals_made' => 28,
            'field_goals_attempted' => 65,
            'three_point_field_goals_made' => 6,
            'three_point_field_goals_attempted' => 20,
            'free_throws_made' => 18,
            'free_throws_attempted' => 20,
            'offensive_rebounds' => 8,
            'defensive_rebounds' => 25,
            'rebounds' => 33,
            'assists' => 18,
            'steals' => 5,
            'blocks' => 3,
            'turnovers' => 14,
            'fouls' => 20,
        ]);

        $player = WnbaPlayer::create([
            'athlete_id' => '4066457',
            'espn_athlete_id' => '4066457',
            'athlete_display_name' => "A'ja Wilson",
            'athlete_short_name' => 'A. Wilson',
        ]);

        WnbaPlayerGame::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $home->team_id,
            'minutes' => '32:15',
            'points' => $playerPoints,
            'rebounds' => 10,
            'offensive_rebounds' => 2,
            'defensive_rebounds' => 8,
            'assists' => 3,
            'steals' => 1,
            'blocks' => 2,
            'turnovers' => 2,
            'fouls' => 3,
            'field_goals_made' => 10,
            'field_goals_attempted' => 18,
            'three_point_field_goals_made' => 1,
            'three_point_field_goals_attempted' => 3,
            'free_throws_made' => 4,
            'free_throws_attempted' => 5,
            'plus_minus' => 12,
            'starter' => true,
            'ejected' => false,
            'did_not_play' => false,
            'active' => true,
        ]);

        return [$game, $player];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulePayload(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boxPayload(int $playerPoints): array
    {
        return [
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
                            'firstName' => "A'ja",
                            'familyName' => 'Wilson',
                            'comment' => '',
                            'statistics' => [
                                'minutes' => 'PT32M15.00S',
                                'points' => $playerPoints,
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
        ];
    }
}
