<?php

namespace App\Services\WNBA\Data\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HTTP client for the league stats API behind wnba.com (stats.wnba.com)
 * and the public CDN schedule feed. Used by the Verification Agent only —
 * not an ingest write path.
 */
class WnbaStatsApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function schedule(): array
    {
        $url = (string) config('wnba.agents.verification.schedule_url');
        $ttl = (int) config('wnba.agents.verification.cache_ttl.schedule', 3600);

        return $this->request($url, [], $ttl, useStatsHeaders: false);
    }

    /**
     * Traditional box score for a league GameID (e.g. 1022500001).
     *
     * @return array<string, mixed>
     */
    public function boxScoreTraditional(string $gameId): array
    {
        $base = rtrim((string) config('wnba.agents.verification.stats_base_url'), '/');
        $url = $base.'/stats/boxscoretraditionalv3';
        $ttl = (int) config('wnba.agents.verification.cache_ttl.boxscore', 21600);

        return $this->request($url, [
            'GameID' => $gameId,
            'StartPeriod' => 0,
            'EndPeriod' => 14,
            'StartRange' => 0,
            'EndRange' => 0,
            'RangeType' => 0,
        ], $ttl, useStatsHeaders: true);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $url, array $query, ?int $cacheTtl, bool $useStatsHeaders): array
    {
        $cacheKey = config('wnba.agents.verification.cache_prefix', 'wnba_stats:').md5($url.serialize($query));

        if ($cacheTtl !== null) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $this->throttle();

        $pending = Http::acceptJson()
            ->timeout((int) config('wnba.agents.verification.timeout', 30))
            ->withHeaders([
                'User-Agent' => (string) config('wnba.agents.verification.user_agent'),
                'Referer' => (string) config('wnba.agents.verification.referer', 'https://www.wnba.com/'),
            ]);

        if ($useStatsHeaders) {
            $pending = $pending->withHeaders([
                'Origin' => 'https://www.wnba.com',
                'x-nba-stats-origin' => 'stats',
                'x-nba-stats-token' => 'true',
            ]);
        }

        $response = $pending->get($url, $query);

        if ($response->status() === 429) {
            Log::warning('WNBA Stats API rate limited', ['url' => $url]);

            throw new RuntimeException('WNBA Stats API rate limited (429)');
        }

        if (! $response->successful()) {
            throw new RuntimeException("WNBA Stats API request failed ({$response->status()}): {$url}");
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException("WNBA Stats API returned invalid JSON: {$url}");
        }

        if ($cacheTtl !== null) {
            Cache::put($cacheKey, $body, $cacheTtl);
        }

        return $body;
    }

    private function throttle(): void
    {
        $delayMs = max(0, (int) config('wnba.agents.verification.request_delay_ms', 400));
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }
}
