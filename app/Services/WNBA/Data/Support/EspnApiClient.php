<?php

namespace App\Services\WNBA\Data\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EspnApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function getSite(string $path, array $query = [], ?int $cacheTtl = null): array
    {
        $base = rtrim((string) config('espn.site_base'), '/');
        $url = $base.'/'.ltrim($path, '/');

        return $this->request($url, $query, $cacheTtl);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWeb(string $path, array $query = [], ?int $cacheTtl = null): array
    {
        $base = rtrim((string) config('espn.web_base'), '/');
        $url = $base.'/'.ltrim($path, '/');

        return $this->request($url, $query, $cacheTtl);
    }

    public function summary(string $gameId, bool $live = false): array
    {
        $ttl = $live
            ? (int) config('espn.cache_ttl.summary_live')
            : (int) config('espn.cache_ttl.summary_final');

        return $this->getSite('summary', ['event' => $gameId], $ttl);
    }

    public function teams(): array
    {
        return $this->getSite('teams', [], (int) config('espn.cache_ttl.teams'));
    }

    public function teamSchedule(string $teamId, int $season): array
    {
        return $this->getSite("teams/{$teamId}/schedule", [
            'season' => $season,
        ], (int) config('espn.cache_ttl.schedule'));
    }

    public function teamRoster(string $teamId): array
    {
        return $this->getSite("teams/{$teamId}/roster", [], (int) config('espn.cache_ttl.roster'));
    }

    public function scoreboard(string $dates): array
    {
        return $this->getSite('scoreboard', ['dates' => $dates], (int) config('espn.cache_ttl.scoreboard'));
    }

    public function athleteGamelog(string $athleteId, int $season): array
    {
        return $this->getWeb("athletes/{$athleteId}/gamelog", [
            'season' => $season,
        ], (int) config('espn.cache_ttl.gamelog'));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $url, array $query, ?int $cacheTtl): array
    {
        $cacheKey = config('espn.cache_prefix').md5($url.serialize($query));

        if ($cacheTtl !== null) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $this->throttle();

        $response = Http::acceptJson()
            ->timeout((int) config('espn.timeout', 30))
            ->get($url, $query);

        if ($response->status() === 429) {
            Log::warning('ESPN API rate limited', ['url' => $url]);

            throw new RuntimeException('ESPN API rate limited (429)');
        }

        if (! $response->successful()) {
            throw new RuntimeException("ESPN API request failed ({$response->status()}): {$url}");
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException("ESPN API returned invalid JSON: {$url}");
        }

        if ($cacheTtl !== null) {
            Cache::put($cacheKey, $body, $cacheTtl);
        }

        return $body;
    }

    private function throttle(): void
    {
        $limit = max(1, (int) config('espn.rate_limit_per_minute', 30));
        $bucketKey = config('espn.cache_prefix').'throttle';
        $count = (int) Cache::get($bucketKey, 0);

        if ($count >= $limit) {
            usleep(250_000);
            Cache::forget($bucketKey);
            $count = 0;
        }

        Cache::put($bucketKey, $count + 1, 60);
    }
}
