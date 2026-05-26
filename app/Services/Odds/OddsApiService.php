<?php

namespace App\Services\Odds;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

class OddsApiService
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private array $config;

    public function __construct()
    {
        $this->config = config('odds-api');
        $this->apiKey = (string) ($this->config['api_key'] ?? '');
        $this->baseUrl = $this->config['base_url'];
        $this->timeout = $this->config['timeout'];

        if ($this->apiKey === '') {
            Log::warning('ODDS_API_KEY is not set; Odds API requests are disabled until it is configured.');
        }
    }

    /**
     * Get available sports
     */
    public function getSports(): array
    {
        $cacheKey = $this->config['cache']['prefix'] . 'sports';
        $cacheTtl = $this->config['cache']['sports_ttl'];

        return Cache::remember($cacheKey, $cacheTtl, function () {
            // Check rate limits before making request
            if (!$this->canMakeRequest()) {
                Log::warning('Rate limit exceeded, returning cached/empty sports data');
                return $this->getFallbackSports();
            }

            try {
                $response = $this->makeRequest('/sports');
                return $response['data'] ?? [];
            } catch (\Exception $e) {
                Log::error('Failed to fetch sports from Odds API', [
                    'error' => $e->getMessage()
                ]);
                return $this->getFallbackSports();
            }
        });
    }

    /**
     * Get odds for a specific sport with aggressive caching
     */
    public function getOdds(
        string $sport = 'basketball_wnba',
        array $markets = ['h2h', 'spreads', 'totals'],
        array $bookmakers = null,
        string $region = 'us',
        string $oddsFormat = 'american'
    ): array {
        $bookmakers = $bookmakers ?? $this->config['bookmakers']['preferred'];
        $cacheKey = $this->config['cache']['prefix'] . "odds_{$sport}_" . md5(serialize([$markets, $bookmakers, $region, $oddsFormat]));
        $cacheTtl = $this->config['cache']['odds_ttl'];

        return Cache::remember($cacheKey, $cacheTtl, function () use ($sport, $markets, $bookmakers, $region, $oddsFormat) {
            // Check rate limits before making request
            if (!$this->canMakeRequest()) {
                Log::warning('Rate limit exceeded, returning cached odds data');
                return $this->getCachedOddsOrEmpty($sport);
            }

            try {
                $params = [
                    'regions' => $region,
                    'markets' => implode(',', $markets),
                    'oddsFormat' => $oddsFormat,
                    'bookmakers' => implode(',', $bookmakers),
                ];

                $response = $this->makeRequest("/sports/{$sport}/odds", $params);
                $processedData = $this->processOddsResponse($response['data'] ?? []);

                // Store backup cache with longer TTL
                $this->storeBackupCache("odds_{$sport}", $processedData);

                return $processedData;
            } catch (\Exception $e) {
                Log::error('Failed to fetch odds from Odds API', [
                    'sport' => $sport,
                    'error' => $e->getMessage()
                ]);
                return $this->getCachedOddsOrEmpty($sport);
            }
        });
    }

    /**
     * Get player props for a specific sport
     */
    public function getPlayerProps(
        string $sport = 'basketball_wnba',
        array $markets = ['player_points', 'player_rebounds', 'player_assists'],
        array $bookmakers = null,
        string $region = 'us'
    ): array {
        $bookmakers = $bookmakers ?? $this->config['bookmakers']['preferred'];
        $cacheKey = "odds_api_props_{$sport}_" . md5(serialize([$markets, $bookmakers, $region]));
        $cacheTtl = $this->config['cache_ttl']['odds'];

        return Cache::remember($cacheKey, $cacheTtl, function () use ($sport, $markets, $bookmakers, $region) {
            $allProps = [];

            foreach ($markets as $market) {
                try {
                    $params = [
                        'regions' => $region,
                        'markets' => $market,
                        'oddsFormat' => $this->config['odds_format'],
                        'bookmakers' => implode(',', $bookmakers),
                    ];

                    $response = $this->makeRequest("/sports/{$sport}/odds", $params);
                    $props = $this->processPlayerPropsResponse($response['data'] ?? [], $market);
                    $allProps = array_merge($allProps, $props);

                    // Rate limiting - small delay between requests
                    usleep(100000); // 100ms delay
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch player props for market', [
                        'sport' => $sport,
                        'market' => $market,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $allProps;
        });
    }

    /**
     * Get odds for a specific player and stat type
     */
    public function getPlayerOdds(string $playerName, string $statType, string $sport = 'basketball_wnba'): ?array
    {
        $marketMapping = [
            'points' => 'player_points',
            'rebounds' => 'player_rebounds',
            'assists' => 'player_assists',
            'three_point_field_goals_made' => 'player_threes',
        ];

        $market = $marketMapping[$statType] ?? null;
        if (!$market) {
            Log::warning('No market mapping found for stat type', [
                'stat_type' => $statType,
                'player' => $playerName
            ]);
            return null;
        }

        $props = $this->getPlayerProps($sport, [$market]);

        // Find props for the specific player
        $playerProps = array_filter($props, function ($prop) use ($playerName) {
            return $this->matchPlayerName($prop['player_name'], $playerName);
        });

        if (empty($playerProps)) {
            Log::info('No props found for player', [
                'player' => $playerName,
                'stat_type' => $statType,
                'market' => $market
            ]);
            return null;
        }

        // Return the best odds (highest over, lowest under)
        return $this->selectBestOdds($playerProps);
    }

    /**
     * Get upcoming events for a sport
     */
    public function getEvents(string $sport = 'basketball_wnba'): array
    {
        $cacheKey = "odds_api_events_{$sport}";
        $cacheTtl = $this->config['cache_ttl']['events'];

        return Cache::remember($cacheKey, $cacheTtl, function () use ($sport) {
            try {
                $response = $this->makeRequest("/sports/{$sport}/odds", [
                    'regions' => $this->config['regions']['default'],
                    'markets' => 'h2h',
                    'oddsFormat' => $this->config['odds_format'],
                ]);

                return $this->processEventsResponse($response['data'] ?? []);
            } catch (\Exception $e) {
                Log::error('Failed to fetch events from Odds API', [
                    'sport' => $sport,
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Get historical odds (if available in your plan)
     */
    public function getHistoricalOdds(string $sport, Carbon $date): array
    {
        // Note: Historical odds require a paid plan
        $cacheKey = "odds_api_historical_{$sport}_{$date->format('Y-m-d')}";
        $cacheTtl = $this->config['cache_ttl']['odds'] * 10; // Cache longer for historical data

        return Cache::remember($cacheKey, $cacheTtl, function () use ($sport, $date) {
            try {
                $params = [
                    'regions' => $this->config['regions']['default'],
                    'markets' => 'h2h,spreads,totals',
                    'oddsFormat' => $this->config['odds_format'],
                    'date' => $date->toISOString(),
                ];

                $response = $this->makeRequest("/sports/{$sport}/odds-history", $params);
                return $this->processOddsResponse($response['data'] ?? []);
            } catch (\Exception $e) {
                Log::warning('Failed to fetch historical odds', [
                    'sport' => $sport,
                    'date' => $date->format('Y-m-d'),
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Clear all odds-related cache
     */
    public function clearCache(): void
    {
        $patterns = [
            'odds_api_sports',
            'odds_api_odds_*',
            'odds_api_props_*',
            'odds_api_events_*',
            'odds_api_historical_*',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we'd need to implement cache tag support
                // For now, just clear specific known keys
                continue;
            }
            Cache::forget($pattern);
        }

        Log::info('Odds API cache cleared');
    }

    /**
     * Get API usage statistics with enhanced tracking
     */
    public function getUsageStats(): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $month = Carbon::now()->format('Y-m');

        $dailyRequests = Cache::get("odds_api_requests_today_{$today}", 0);
        $monthlyRequests = Cache::get("odds_api_requests_month_{$month}", 0);
        $lastRequest = Cache::get('odds_api_last_request');

        $monthlyLimit = $this->config['rate_limit']['requests_per_month'];
        $dailyTarget = $this->config['rate_limit']['daily_target'];

        return [
            'requests_today' => $dailyRequests,
            'requests_this_month' => $monthlyRequests,
            'monthly_limit' => $monthlyLimit,
            'daily_target' => $dailyTarget,
            'monthly_usage_percent' => round(($monthlyRequests / $monthlyLimit) * 100, 1),
            'daily_usage_percent' => round(($dailyRequests / $dailyTarget) * 100, 1),
            'last_request' => $lastRequest,
            'can_make_request' => $this->canMakeRequest(),
            'requests_remaining_today' => max(0, $dailyTarget - $dailyRequests),
            'requests_remaining_month' => max(0, $monthlyLimit - $monthlyRequests),
            'status' => $this->getUsageStatus($monthlyRequests, $monthlyLimit, $dailyRequests, $dailyTarget)
        ];
    }

    /**
     * Get WNBA player props with enhanced caching
     */
    public function getWnbaPlayerProps(array $options = []): array
    {
        $cacheKey = $this->config['cache']['prefix'] . 'wnba_player_props_' . md5(serialize($options));
        $cacheTtl = $this->config['cache']['props_ttl'];

        return Cache::remember($cacheKey, $cacheTtl, function () use ($options) {
            // Check rate limits before making request
            if (!$this->canMakeRequest()) {
                Log::warning('Rate limit exceeded, returning cached player props');
                return $this->getCachedPlayerPropsOrEmpty();
            }

            try {
                // First get WNBA events (cached)
                $events = $this->getWnbaEvents();

                if (empty($events)) {
                    Log::info('No WNBA events found for player props');
                    return [];
                }

                $allPlayerProps = [];

                // Limit to first 2 events to conserve API calls
                $limitedEvents = array_slice($events, 0, 2);

                // Get player props for each event
                foreach ($limitedEvents as $event) {
                    $eventProps = $this->getEventPlayerProps($event['id'], $options);
                    if (!empty($eventProps)) {
                        $allPlayerProps = array_merge($allPlayerProps, $eventProps);
                    }

                    // Small delay between requests to be respectful
                    usleep(200000); // 200ms delay
                }

                // Store backup cache
                $this->storeBackupCache('wnba_player_props', $allPlayerProps);

                return $allPlayerProps;

            } catch (\Exception $e) {
                Log::error('Failed to fetch WNBA player props', [
                    'error' => $e->getMessage(),
                    'options' => $options
                ]);
                return $this->getCachedPlayerPropsOrEmpty();
            }
        });
    }

    /**
     * Get WNBA events with enhanced caching
     */
    public function getWnbaEvents(): array
    {
        $cacheKey = $this->config['cache']['prefix'] . 'wnba_events';
        $cacheTtl = $this->config['cache']['events_ttl'];

        return Cache::remember($cacheKey, $cacheTtl, function () {
            // Check rate limits before making request
            if (!$this->canMakeRequest()) {
                Log::warning('Rate limit exceeded, returning cached events');
                return $this->getCachedEventsOrEmpty();
            }

            try {
                $response = $this->makeRequest("/sports/basketball_wnba/odds", [
                    'regions' => $this->config['regions']['default'],
                    'markets' => 'h2h',
                    'oddsFormat' => $this->config['odds_format'],
                ]);

                $processedData = $this->processEventsResponse($response['data'] ?? []);

                // Store backup cache
                $this->storeBackupCache('wnba_events', $processedData);

                return $processedData;
            } catch (\Exception $e) {
                Log::error('Failed to fetch WNBA events from Odds API', [
                    'error' => $e->getMessage()
                ]);
                return $this->getCachedEventsOrEmpty();
            }
        });
    }

    /**
     * Get player props for a specific event
     */
    public function getEventPlayerProps(string $eventId, array $options = []): array
    {
        try {
            // Get available player prop markets from config
            $availableMarkets = array_keys($this->config['wnba_player_props']);

            // Use specified markets or default to core props
            $markets = $options['markets'] ?? [
                'player_points',
                'player_rebounds',
                'player_assists',
                'player_threes'
            ];

            // Filter to only valid markets
            $markets = array_intersect($markets, $availableMarkets);

            if (empty($markets)) {
                return [];
            }

            $params = [
                'apiKey' => $this->apiKey,
                'regions' => $options['regions'] ?? 'us',
                'markets' => implode(',', $markets),
                'oddsFormat' => $options['oddsFormat'] ?? 'american',
                'dateFormat' => 'iso'
            ];

            // Add bookmaker filter if specified
            if (!empty($options['bookmakers'])) {
                $params['bookmakers'] = is_array($options['bookmakers'])
                    ? implode(',', $options['bookmakers'])
                    : $options['bookmakers'];
            }

            $response = Http::timeout($this->timeout)
                ->retry($this->config['retry_attempts'], $this->config['retry_delay'])
                ->get("{$this->baseUrl}/sports/basketball_wnba/events/{$eventId}/odds", $params);

            if (!$response->successful()) {
                Log::warning('Failed to fetch event player props', [
                    'event_id' => $eventId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();

            return $this->formatPlayerPropsResponse($data, $options);

        } catch (\Exception $e) {
            Log::error('Error fetching event player props', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Format player props response for consistent structure
     */
    private function formatPlayerPropsResponse(array $data, array $options = []): array
    {
        if (empty($data) || !isset($data['bookmakers'])) {
            return [];
        }

        $playerProps = [];
        $propConfig = $this->config['wnba_player_props'];

        foreach ($data['bookmakers'] as $bookmaker) {
            if (!isset($bookmaker['markets'])) {
                continue;
            }

            foreach ($bookmaker['markets'] as $market) {
                $marketKey = $market['key'];

                if (!isset($propConfig[$marketKey])) {
                    continue;
                }

                foreach ($market['outcomes'] as $outcome) {
                    // Extract player name from outcome description
                    $playerName = $this->extractPlayerName($outcome['description'] ?? $outcome['name'] ?? '');

                    if (empty($playerName)) {
                        continue;
                    }

                    // Filter by player name if specified
                    if (!empty($options['player_name']) &&
                        stripos($playerName, $options['player_name']) === false) {
                        continue;
                    }

                    $line = $outcome['point'] ?? null;
                    $propKey = $playerName . '_' . $marketKey . '_' . ($line ?? 'standard');

                    if (!isset($playerProps[$propKey])) {
                        $playerProps[$propKey] = [
                            'player_name' => $playerName,
                            'stat_type' => $marketKey,
                            'stat_name' => $propConfig[$marketKey]['name'],
                            'line' => $line,
                            'game_id' => $data['id'] ?? null,
                            'sport_key' => $data['sport_key'] ?? 'basketball_wnba',
                            'commence_time' => $data['commence_time'] ?? null,
                            'home_team' => $data['home_team'] ?? null,
                            'away_team' => $data['away_team'] ?? null,
                            'bookmakers' => []
                        ];
                    }

                    $playerProps[$propKey]['bookmakers'][] = [
                        'bookmaker' => $bookmaker['title'],
                        'bookmaker_key' => $bookmaker['key'],
                        'name' => $outcome['name'],
                        'price' => $outcome['price'],
                        'point' => $outcome['point'] ?? null,
                        'description' => $outcome['description'] ?? null,
                        'last_update' => $market['last_update'] ?? null
                    ];
                }
            }
        }

        return array_values($playerProps);
    }

    /**
     * Extract player name from outcome description
     */
    private function extractPlayerName(string $description): string
    {
        if (empty($description)) {
            return '';
        }

        // For The Odds API, the description field often contains just the player name
        // Clean up the description and return it directly if it looks like a player name
        $cleaned = trim($description);

        // If it's just a player name (contains letters, spaces, apostrophes, periods)
        if (preg_match('/^[A-Za-z\s\.\'\-]+$/', $cleaned)) {
            return $cleaned;
        }

        // Common patterns for player prop descriptions
        $patterns = [
            '/^([A-Za-z\s\.\']+?)\s+(?:Over|Under|O|U)\s+[\d\.]+/',
            '/^([A-Za-z\s\.\']+?)\s+[\d\.]+\+/',
            '/^([A-Za-z\s\.\']+?)\s+(?:Points|Rebounds|Assists|Threes|Steals|Blocks|Turnovers)/',
            '/^([A-Za-z\s\.\']+?)(?:\s+-\s+|\s+)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleaned, $matches)) {
                return trim($matches[1]);
            }
        }

        // Fallback: return first part before common keywords
        $keywords = ['Over', 'Under', 'O', 'U', 'Points', 'Rebounds', 'Assists', 'Threes'];
        foreach ($keywords as $keyword) {
            $pos = stripos($cleaned, $keyword);
            if ($pos !== false) {
                return trim(substr($cleaned, 0, $pos));
            }
        }

        return $cleaned;
    }

    /**
     * Get available player prop markets for WNBA
     */
    public function getAvailablePlayerPropMarkets(): array
    {
        return $this->config['wnba_player_props'];
    }

    /**
     * Get best odds for a specific player prop across bookmakers
     */
    public function getBestPlayerPropOdds(string $playerName, string $statType, $line = null): array
    {
        $cacheKey = "best_odds_{$playerName}_{$statType}_" . ($line ?? 'any');

        return Cache::remember($cacheKey, 300, function () use ($playerName, $statType, $line) {
            $playerProps = $this->getWnbaPlayerProps([
                'player_name' => $playerName,
                'markets' => [$statType]
            ]);

            $bestOdds = [
                'over' => ['odds' => null, 'bookmaker' => null],
                'under' => ['odds' => null, 'bookmaker' => null]
            ];

            foreach ($playerProps as $prop) {
                if ($prop['stat_type'] !== $statType) {
                    continue;
                }

                if ($line !== null && $prop['line'] != $line) {
                    continue;
                }

                foreach ($prop['bookmakers'] as $bookmaker) {
                    $isOver = stripos($bookmaker['name'], 'over') !== false ||
                             stripos($bookmaker['name'], 'o ') !== false;
                    $isUnder = stripos($bookmaker['name'], 'under') !== false ||
                              stripos($bookmaker['name'], 'u ') !== false;

                    if ($isOver) {
                        if ($bestOdds['over']['odds'] === null || $bookmaker['price'] > $bestOdds['over']['odds']) {
                            $bestOdds['over'] = [
                                'odds' => $bookmaker['price'],
                                'bookmaker' => $bookmaker['bookmaker'],
                                'line' => $bookmaker['point']
                            ];
                        }
                    } elseif ($isUnder) {
                        if ($bestOdds['under']['odds'] === null || $bookmaker['price'] > $bestOdds['under']['odds']) {
                            $bestOdds['under'] = [
                                'odds' => $bookmaker['price'],
                                'bookmaker' => $bookmaker['bookmaker'],
                                'line' => $bookmaker['point']
                            ];
                        }
                    }
                }
            }

            return $bestOdds;
        });
    }

    // Private helper methods

    /**
     * Make HTTP request to The Odds API
     */
    private function makeRequest(string $endpoint, array $params = []): array
    {
        if ($this->apiKey === '') {
            Log::debug('Odds API request skipped: missing API key', ['endpoint' => $endpoint]);

            return ['data' => []];
        }

        $params['apiKey'] = $this->apiKey;

        // Track API usage
        $this->trackApiUsage();

        $url = $this->baseUrl . $endpoint;

        $response = Http::timeout($this->timeout)
            ->retry($this->config['retry_attempts'], $this->config['retry_delay'])
            ->get($url, $params);

        if (!$response->successful()) {
            throw new \Exception("Odds API request failed: {$response->status()} - {$response->body()}");
        }

        $data = $response->json();

        if ($this->config['logging']['enabled']) {
            Log::info('Odds API request successful', [
                'endpoint' => $endpoint,
                'params' => array_diff_key($params, ['apiKey' => '']), // Don't log API key
                'response_count' => count($data ?? [])
            ]);
        }

        return ['data' => $data];
    }

    /**
     * Process odds response from API
     */
    private function processOddsResponse(array $data): array
    {
        $processed = [];

        foreach ($data as $event) {
            $processedEvent = [
                'id' => $event['id'],
                'sport_key' => $event['sport_key'],
                'sport_title' => $event['sport_title'],
                'commence_time' => $event['commence_time'],
                'home_team' => $event['home_team'],
                'away_team' => $event['away_team'],
                'bookmakers' => []
            ];

            foreach ($event['bookmakers'] ?? [] as $bookmaker) {
                $processedBookmaker = [
                    'key' => $bookmaker['key'],
                    'title' => $bookmaker['title'],
                    'last_update' => $bookmaker['last_update'],
                    'markets' => []
                ];

                foreach ($bookmaker['markets'] ?? [] as $market) {
                    $processedBookmaker['markets'][$market['key']] = [
                        'key' => $market['key'],
                        'outcomes' => $market['outcomes']
                    ];
                }

                $processedEvent['bookmakers'][] = $processedBookmaker;
            }

            $processed[] = $processedEvent;
        }

        return $processed;
    }

    /**
     * Process player props response
     */
    private function processPlayerPropsResponse(array $data, string $market): array
    {
        $props = [];

        foreach ($data as $event) {
            foreach ($event['bookmakers'] ?? [] as $bookmaker) {
                foreach ($bookmaker['markets'] ?? [] as $marketData) {
                    if ($marketData['key'] !== $market) {
                        continue;
                    }

                    foreach ($marketData['outcomes'] ?? [] as $outcome) {
                        if (!isset($outcome['description'])) {
                            continue;
                        }

                        // Extract player name and line from description
                        $description = $outcome['description'];
                        $playerName = $this->extractPlayerName($description);
                        $line = $this->extractLine($description);

                        if (!$playerName || !$line) {
                            continue;
                        }

                        $propKey = "{$playerName}_{$market}_{$line}";

                        if (!isset($props[$propKey])) {
                            $props[$propKey] = [
                                'player_name' => $playerName,
                                'stat_type' => $this->mapMarketToStat($market),
                                'line' => $line,
                                'event_id' => $event['id'],
                                'commence_time' => $event['commence_time'],
                                'home_team' => $event['home_team'],
                                'away_team' => $event['away_team'],
                                'bookmakers' => []
                            ];
                        }

                        $props[$propKey]['bookmakers'][] = [
                            'bookmaker' => $bookmaker['title'],
                            'bookmaker_key' => $bookmaker['key'],
                            'name' => $outcome['name'],
                            'price' => $outcome['price'],
                            'point' => $outcome['point'] ?? null,
                            'last_update' => $bookmaker['last_update']
                        ];
                    }
                }
            }
        }

        return array_values($props);
    }

    /**
     * Process events response
     */
    private function processEventsResponse(array $data): array
    {
        $events = [];

        foreach ($data as $event) {
            $events[] = [
                'id' => $event['id'],
                'sport_key' => $event['sport_key'],
                'sport_title' => $event['sport_title'],
                'commence_time' => $event['commence_time'],
                'home_team' => $event['home_team'],
                'away_team' => $event['away_team'],
                'bookmaker_count' => count($event['bookmakers'] ?? [])
            ];
        }

        return $events;
    }

    /**
     * Match player names accounting for variations
     */
    private function matchPlayerName(string $oddsPlayerName, string $dbPlayerName): bool
    {
        // Direct match
        if (strcasecmp($oddsPlayerName, $dbPlayerName) === 0) {
            return true;
        }

        // Check configured mappings
        $mappings = $this->config['player_name_mappings'];
        foreach ($mappings as $canonical => $variations) {
            if (strcasecmp($dbPlayerName, $canonical) === 0) {
                foreach ($variations as $variation) {
                    if (strcasecmp($oddsPlayerName, $variation) === 0) {
                        return true;
                    }
                }
            }
        }

        // Fuzzy matching (remove punctuation, compare)
        $cleanOdds = preg_replace('/[^a-zA-Z\s]/', '', $oddsPlayerName);
        $cleanDb = preg_replace('/[^a-zA-Z\s]/', '', $dbPlayerName);

        return strcasecmp($cleanOdds, $cleanDb) === 0;
    }

    /**
     * Extract line value from description
     */
    private function extractLine(string $description): ?float
    {
        // Look for numbers that could be lines
        if (preg_match('/(\d+\.?\d*)\+?/', $description, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Map market key to stat type
     */
    private function mapMarketToStat(string $market): string
    {
        $mapping = [
            'player_points' => 'points',
            'player_rebounds' => 'rebounds',
            'player_assists' => 'assists',
            'player_threes' => 'three_point_field_goals_made',
        ];

        return $mapping[$market] ?? $market;
    }

    /**
     * Select best odds from multiple bookmakers
     */
    private function selectBestOdds(array $playerProps): array
    {
        $bestProp = null;
        $bestOverOdds = -999999;
        $bestUnderOdds = 999999;

        foreach ($playerProps as $prop) {
            foreach ($prop['bookmakers'] as $bookmaker) {
                if (stripos($bookmaker['name'], 'over') !== false) {
                    if ($bookmaker['price'] > $bestOverOdds) {
                        $bestOverOdds = $bookmaker['price'];
                        $bestProp = $prop;
                        $bestProp['over_odds'] = $bookmaker['price'];
                        $bestProp['over_bookmaker'] = $bookmaker['bookmaker'];
                    }
                } elseif (stripos($bookmaker['name'], 'under') !== false) {
                    if ($bookmaker['price'] < $bestUnderOdds) {
                        $bestUnderOdds = $bookmaker['price'];
                        if (!$bestProp) $bestProp = $prop;
                        $bestProp['under_odds'] = $bookmaker['price'];
                        $bestProp['under_bookmaker'] = $bookmaker['bookmaker'];
                    }
                }
            }
        }

        return $bestProp ?? [];
    }

    /**
     * Track API usage for rate limiting
     */
    private function trackApiUsage(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $month = Carbon::now()->format('Y-m');
        $now = Carbon::now();

        // Increment counters
        Cache::increment("odds_api_requests_today_{$today}", 1);
        Cache::increment("odds_api_requests_month_{$month}", 1);

        // Track burst usage
        $burstKey = 'odds_api_burst_' . $now->format('Y-m-d-H-i');
        Cache::increment($burstKey, 1);
        Cache::put($burstKey, Cache::get($burstKey, 1), $this->config['rate_limit']['cooldown_period']);

        // Update last request time
        Cache::put('odds_api_last_request', $now->toISOString());

        // Log usage if approaching limits
        $monthlyRequests = Cache::get("odds_api_requests_month_{$month}", 0);
        $monthlyLimit = $this->config['rate_limit']['requests_per_month'];
        $warnThreshold = $this->config['rate_limit']['warn_threshold'];

        if ($monthlyRequests >= ($monthlyLimit * $warnThreshold)) {
            Log::warning('API usage approaching monthly limit', [
                'monthly_requests' => $monthlyRequests,
                'monthly_limit' => $monthlyLimit,
                'usage_percent' => round(($monthlyRequests / $monthlyLimit) * 100, 1)
            ]);
        }
    }

    /**
     * Extract player name from odds description (alternative method for different contexts)
     */
    private function extractPlayerNameFromOdds(string $description): ?string
    {
        // Common patterns for player props
        $patterns = [
            '/^([^-]+)\s*-\s*/', // "Player Name - Stat"
            '/^([^:]+):\s*/', // "Player Name: Stat"
            '/^([^(]+)\s*\(/', // "Player Name (Team)"
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Check if we can make an API request based on rate limits
     */
    private function canMakeRequest(): bool
    {
        if ($this->apiKey === '') {
            return false;
        }

        $today = Carbon::today()->format('Y-m-d');
        $month = Carbon::now()->format('Y-m');

        $dailyRequests = Cache::get("odds_api_requests_today_{$today}", 0);
        $monthlyRequests = Cache::get("odds_api_requests_month_{$month}", 0);

        $monthlyLimit = $this->config['rate_limit']['requests_per_month'];
        $dailyTarget = $this->config['rate_limit']['daily_target'];
        $blockThreshold = $this->config['rate_limit']['block_threshold'];

        // Check monthly limit
        if ($monthlyRequests >= ($monthlyLimit * $blockThreshold)) {
            Log::warning('Monthly API limit nearly reached', [
                'monthly_requests' => $monthlyRequests,
                'monthly_limit' => $monthlyLimit,
                'threshold' => $blockThreshold
            ]);
            return false;
        }

        // Check daily target
        if ($dailyRequests >= $dailyTarget) {
            Log::info('Daily API target reached', [
                'daily_requests' => $dailyRequests,
                'daily_target' => $dailyTarget
            ]);
            return false;
        }

        // Check burst limit
        $burstKey = 'odds_api_burst_' . Carbon::now()->format('Y-m-d-H-i');
        $burstCount = Cache::get($burstKey, 0);
        $burstLimit = $this->config['rate_limit']['burst_limit'];

        if ($burstCount >= $burstLimit) {
            Log::info('Burst limit reached', [
                'burst_count' => $burstCount,
                'burst_limit' => $burstLimit
            ]);
            return false;
        }

        return true;
    }

    /**
     * Store backup cache with longer TTL for fallback
     */
    private function storeBackupCache(string $key, array $data): void
    {
        $backupKey = $this->config['cache']['prefix'] . 'backup_' . $key;
        $backupTtl = 86400 * 7; // 7 days

        Cache::put($backupKey, [
            'data' => $data,
            'cached_at' => now()->toISOString(),
            'expires_at' => now()->addSeconds($backupTtl)->toISOString()
        ], $backupTtl);
    }

    /**
     * Get cached odds or return empty array
     */
    private function getCachedOddsOrEmpty(string $sport): array
    {
        $backupKey = $this->config['cache']['prefix'] . "backup_odds_{$sport}";
        $backup = Cache::get($backupKey);

        if ($backup && isset($backup['data'])) {
            Log::info('Returning backup cached odds data', ['sport' => $sport]);
            return $backup['data'];
        }

        return [];
    }

    /**
     * Get cached player props or return empty array
     */
    private function getCachedPlayerPropsOrEmpty(): array
    {
        $backupKey = $this->config['cache']['prefix'] . 'backup_wnba_player_props';
        $backup = Cache::get($backupKey);

        if ($backup && isset($backup['data'])) {
            Log::info('Returning backup cached player props data');
            return $backup['data'];
        }

        return [];
    }

    /**
     * Get cached events or return empty array
     */
    private function getCachedEventsOrEmpty(): array
    {
        $backupKey = $this->config['cache']['prefix'] . 'backup_wnba_events';
        $backup = Cache::get($backupKey);

        if ($backup && isset($backup['data'])) {
            Log::info('Returning backup cached events data');
            return $backup['data'];
        }

        return [];
    }

    /**
     * Get fallback sports data
     */
    private function getFallbackSports(): array
    {
        return [
            [
                'key' => 'basketball_wnba',
                'group' => 'Basketball',
                'title' => 'WNBA',
                'description' => 'Women\'s National Basketball Association',
                'active' => true,
                'has_outrights' => false
            ]
        ];
    }

    /**
     * Get usage status message
     */
    private function getUsageStatus(int $monthlyRequests, int $monthlyLimit, int $dailyRequests, int $dailyTarget): string
    {
        $monthlyPercent = ($monthlyRequests / $monthlyLimit) * 100;
        $dailyPercent = ($dailyRequests / $dailyTarget) * 100;

        if ($monthlyPercent >= 95) {
            return 'critical';
        } elseif ($monthlyPercent >= 80) {
            return 'warning';
        } elseif ($dailyPercent >= 100) {
            return 'daily_limit_reached';
        } elseif ($dailyPercent >= 80) {
            return 'approaching_daily_limit';
        } else {
            return 'normal';
        }
    }
}
