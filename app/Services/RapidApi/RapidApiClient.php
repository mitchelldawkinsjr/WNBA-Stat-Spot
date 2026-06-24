<?php

namespace App\Services\RapidApi;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RapidApiClient
{
    public function __construct(
        private Tank01UsageTracker $usageTracker
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(
        string $endpoint,
        array $query = [],
        ?int $cacheTtl = null,
        bool $essential = false,
        bool $countTowardQuota = true
    ): array {
        $cacheKey = config('tank01.cache_prefix').md5($endpoint.serialize($query));

        if ($cacheTtl !== null) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        if ($countTowardQuota && ! $this->usageTracker->canMakeRequest($essential)) {
            throw new RuntimeException("Tank01 API budget exceeded; cannot call {$endpoint}");
        }

        $apiKey = config('tank01.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('RAPIDAPI_KEY is not configured');
        }

        $url = rtrim((string) config('tank01.base_url'), '/').'/'.ltrim($endpoint, '/');

        $response = Http::withHeaders([
            'x-rapidapi-key' => $apiKey,
            'x-rapidapi-host' => config('tank01.host'),
            'Content-Type' => 'application/json',
        ])
            ->timeout((int) config('tank01.timeout', 30))
            ->get($url, $query);

        if ($response->status() === 429) {
            Log::error('Tank01 rate limited by RapidAPI', ['endpoint' => $endpoint]);

            throw new RuntimeException('Tank01 API rate limited (429)');
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "Tank01 request failed for {$endpoint}: HTTP {$response->status()}"
            );
        }

        if ($countTowardQuota) {
            $this->usageTracker->recordRequest($endpoint);
        }

        $data = $this->normalizeResponse($response);

        if ($cacheTtl !== null) {
            Cache::put($cacheKey, $data, $cacheTtl);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResponse(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        if (isset($json['body']) && is_array($json['body'])) {
            return $json['body'];
        }

        return $json;
    }
}
