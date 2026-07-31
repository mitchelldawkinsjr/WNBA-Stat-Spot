<?php

namespace Tests\Unit\Services\WNBA\Data\Support;

use App\Services\WNBA\Data\Support\WnbaStatsApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WnbaStatsApiClientTest extends TestCase
{
    public function test_schedule_and_box_score_requests(): void
    {
        Http::fake([
            'cdn.wnba.com/*' => Http::response(['leagueSchedule' => ['gameDates' => []]], 200),
            'stats.wnba.com/stats/boxscoretraditionalv3*' => Http::response([
                'boxScoreTraditional' => ['gameId' => '1022600123'],
            ], 200),
        ]);

        config(['wnba.agents.verification.request_delay_ms' => 0]);
        config(['wnba.agents.verification.cache_ttl.schedule' => 0]);
        config(['wnba.agents.verification.cache_ttl.boxscore' => 0]);

        $client = new WnbaStatsApiClient;

        $schedule = $client->schedule();
        $this->assertArrayHasKey('leagueSchedule', $schedule);

        $box = $client->boxScoreTraditional('1022600123');
        $this->assertSame('1022600123', $box['boxScoreTraditional']['gameId']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'scheduleLeagueV2.json');
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'boxscoretraditionalv3')
                && $request->hasHeader('Referer', 'https://www.wnba.com/');
        });
    }
}
