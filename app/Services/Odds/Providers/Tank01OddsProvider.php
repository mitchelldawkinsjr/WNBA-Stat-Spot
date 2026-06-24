<?php

namespace App\Services\Odds\Providers;

use App\Contracts\OddsProvider;
use App\Services\Odds\Mappers\Tank01OddsMapper;
use App\Services\Odds\OddsApiService;
use App\Services\RapidApi\RapidApiClient;
use App\Services\RapidApi\Tank01UsageTracker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Tank01OddsProvider implements OddsProvider
{
    public function __construct(
        private RapidApiClient $client,
        private Tank01UsageTracker $usageTracker,
        private Tank01OddsMapper $mapper
    ) {}

    public function name(): string
    {
        return 'tank01';
    }

    public function getSports(): array
    {
        return [
            [
                'key' => 'basketball_wnba',
                'group' => 'Basketball',
                'title' => 'WNBA',
                'description' => 'Women\'s National Basketball Association',
                'active' => true,
                'has_outrights' => false,
            ],
        ];
    }

    public function getOdds(
        string $sport = 'basketball_wnba',
        array $markets = ['h2h', 'spreads', 'totals'],
        ?array $bookmakers = null,
        string $region = 'us',
        string $oddsFormat = 'american'
    ): array {
        $events = $this->fetchBettingOdds();

        return array_values(array_filter($events, function (array $event) use ($markets) {
            foreach ($event['bookmakers'] ?? [] as $bookmaker) {
                foreach ($bookmaker['markets'] ?? [] as $market) {
                    if (in_array($market['key'] ?? '', $markets, true)) {
                        return true;
                    }
                }
            }

            return false;
        }));
    }

    public function getPlayerProps(
        string $sport = 'basketball_wnba',
        array $markets = ['player_points', 'player_rebounds', 'player_assists'],
        ?array $bookmakers = null,
        string $region = 'us'
    ): array {
        $props = $this->fetchPlayerProps();

        return array_values(array_filter($props, function (array $prop) use ($markets) {
            return in_array($prop['stat_type'] ?? '', $markets, true);
        }));
    }

    public function getWnbaPlayerProps(array $options = []): array
    {
        $markets = $options['markets'] ?? ['player_points', 'player_rebounds', 'player_assists', 'player_threes'];
        if (is_string($markets)) {
            $markets = explode(',', $markets);
        }

        $props = $this->fetchPlayerProps();
        $playerName = $options['player_name'] ?? null;

        return array_values(array_filter($props, function (array $prop) use ($markets, $playerName) {
            if ($playerName && stripos($prop['player_name'] ?? '', $playerName) === false) {
                return false;
            }

            return in_array($prop['stat_type'] ?? '', $markets, true);
        }));
    }

    public function getWnbaEvents(): array
    {
        return $this->fetchBettingOdds();
    }

    public function getPlayerOdds(string $playerName, string $statType, string $sport = 'basketball_wnba'): ?array
    {
        $marketMapping = [
            'points' => 'player_points',
            'rebounds' => 'player_rebounds',
            'assists' => 'player_assists',
            'three_point_field_goals_made' => 'player_threes',
        ];

        $market = $marketMapping[$statType] ?? null;
        if (! $market) {
            return null;
        }

        $props = $this->getPlayerProps($sport, [$market]);
        foreach ($props as $prop) {
            if (stripos($prop['player_name'] ?? '', $playerName) !== false) {
                return $prop;
            }
        }

        return null;
    }

    public function getUsageStats(): array
    {
        return $this->usageTracker->getUsageStats();
    }

    public function clearCache(): void
    {
        // Tank01 odds cache keys use tank01_ prefix via RapidApiClient
        Log::info('Tank01 odds cache clear requested');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRawBettingOdds(): array
    {
        $cacheKey = config('tank01.cache_prefix').'betting_odds_raw';

        return Cache::remember($cacheKey, config('tank01.cache_ttl.odds'), function () use ($cacheKey) {
            if (! $this->usageTracker->canMakeRequest()) {
                return Cache::get($cacheKey.'_backup', []);
            }

            try {
                $body = $this->client->get(
                    config('tank01.endpoints.betting_odds'),
                    [],
                    null,
                );
                $games = is_array($body) && array_is_list($body) ? $body : [$body];
                Cache::put($cacheKey.'_backup', $games, config('tank01.cache_ttl.odds') * 2);

                return $games;
            } catch (\Throwable $e) {
                Log::warning('Tank01 betting odds fetch failed', ['error' => $e->getMessage()]);

                return Cache::get($cacheKey.'_backup', []);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBettingOdds(): array
    {
        return $this->mapper->mapToEvents($this->fetchRawBettingOdds());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPlayerProps(): array
    {
        return $this->mapper->mapToPlayerProps($this->fetchRawBettingOdds());
    }
}
