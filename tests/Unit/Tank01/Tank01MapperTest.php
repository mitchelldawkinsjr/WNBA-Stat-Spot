<?php

namespace Tests\Unit\Tank01;

use App\Services\RapidApi\Tank01UsageTracker;
use App\Services\WNBA\Data\Mappers\Tank01Mapper;
use App\Services\Odds\Mappers\Tank01OddsMapper;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Tank01MapperTest extends TestCase
{
    public function test_maps_schedule_from_teams_fixture(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/tank01/teams.json')),
            true
        );
        $mapper = new Tank01Mapper(2024);
        $schedule = $mapper->mapScheduleFromTeams($fixture['body']);

        $this->assertCount(1, $schedule);
        $this->assertSame('20240624_WAS@LAS', $schedule[0]['game_id']);
        $this->assertSame('2024-06-24', $schedule[0]['game_date']);
        $this->assertSame('LAS', $schedule[0]['home_team_abbreviation']);
        $this->assertSame('WAS', $schedule[0]['away_team_abbreviation']);
    }

    public function test_maps_box_score_fixture(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/tank01/box_score.json')),
            true
        );
        $mapper = new Tank01Mapper(2024);
        $mapped = $mapper->mapBoxScore($fixture['body']);

        $this->assertCount(2, $mapped['player']);
        $this->assertCount(2, $mapped['team']);
        $this->assertSame('94144265527', $mapped['player'][0]['athlete_id']);
        $this->assertSame(24, $mapped['player'][0]['points']);
    }

    public function test_box_score_emits_team_rows_from_points_without_team_stats(): void
    {
        $mapper = new Tank01Mapper(2026);
        $mapped = $mapper->mapBoxScore([
            'gameID' => '20260701_NY@ATL',
            'gameDate' => '20260701',
            'away' => 'NY',
            'home' => 'ATL',
            'teamIDAway' => '9',
            'teamIDHome' => '5',
            'awayPts' => 41,
            'homePts' => 48,
            'playerStats' => [],
            'teamStats' => [],
        ]);

        $this->assertCount(2, $mapped['team']);
        $home = collect($mapped['team'])->firstWhere('home_away', 'home');
        $this->assertSame(48, $home['team_score']);
        $this->assertSame(41, $home['opponent_team_score']);
    }

    public function test_schedule_game_normalizes_live_status_and_scores(): void
    {
        $mapper = new Tank01Mapper(2026);
        $row = $mapper->mapScheduleGame('20260701_NY@ATL', [
            'gameDate' => '20260701',
            'away' => 'NY',
            'home' => 'ATL',
            'teamIDAway' => '9',
            'teamIDHome' => '5',
            'awayPts' => 22,
            'homePts' => 19,
            'gameStatus' => 'In Progress',
            'gameStatusCode' => '1',
        ]);

        $this->assertSame('STATUS_IN_PROGRESS', $row['status_name']);
        $this->assertSame(19, $row['home_team_score']);
        $this->assertSame(22, $row['away_team_score']);
    }

    public function test_usage_tracker_blocks_near_monthly_limit(): void
    {
        Cache::flush();
        config([
            'tank01.api_key' => 'test-key',
            'tank01.rate_limit.requests_per_month' => 1000,
            'tank01.rate_limit.daily_target' => 30,
            'tank01.rate_limit.block_threshold' => 0.95,
        ]);

        $tracker = new Tank01UsageTracker;
        $month = now()->format('Y-m');
        Cache::put("tank01_requests_month_{$month}", 960);

        $this->assertFalse($tracker->canMakeRequest());
        $this->assertTrue($tracker->canMakeRequest(essential: true));
    }

    public function test_odds_mapper_maps_events_and_props(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/tank01/betting_odds.json')),
            true
        );
        $mapper = new Tank01OddsMapper;
        $events = $mapper->mapToEvents($fixture['body']);
        $props = $mapper->mapToPlayerProps($fixture['body']);

        $this->assertSame('20240625_MIN@NYL', $events[0]['id']);
        $this->assertNotEmpty($events[0]['bookmakers']);
        $this->assertSame('player_points', $props[0]['stat_type']);
        $this->assertSame('Napheesa Collier', $props[0]['player_name']);
    }

    public function test_maps_player_gamelog_fixture(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/tank01/games_for_player.json')),
            true
        );
        $mapper = new Tank01Mapper(2025);
        $rows = $mapper->mapPlayerGamelog($fixture['body'], '1004', 2025);

        $this->assertCount(2, $rows);
        $this->assertSame('20250807_IND@PHX', $rows[0]['game_id']);
        $this->assertSame('1004', $rows[0]['athlete_id']);
        $this->assertSame(1, $rows[0]['rebounds']);
        $this->assertSame('20250805_IND@LA', $rows[1]['game_id']);
    }
}
