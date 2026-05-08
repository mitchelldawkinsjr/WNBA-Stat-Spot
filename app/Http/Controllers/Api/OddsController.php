<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheHelper;
use App\Services\Odds\OddsApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class OddsController extends Controller
{
    use ApiResponseTrait, CacheHelper;

    private OddsApiService $oddsApi;

    public function __construct(OddsApiService $oddsApi)
    {
        $this->oddsApi = $oddsApi;
    }

    /**
     * Get available sports from The Odds API
     */
    public function getSports()
    {
        try {
            $sports = $this->oddsApi->getSports();

            return $this->successResponse([
                'data' => $sports,
                'count' => count($sports)
            ], 'Sports retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Fetching sports data from Odds API');
        }
    }

    /**
     * Get odds for WNBA games
     */
    public function getWnbaOdds(Request $request)
    {
        try {
            $this->validateRequest($request->all(), [
                'markets' => 'nullable|array',
                'markets.*' => 'string|in:h2h,spreads,totals',
                'bookmakers' => 'nullable|array',
                'bookmakers.*' => 'string',
                'region' => 'nullable|string|in:us,uk,eu,au',
                'odds_format' => 'nullable|string|in:american,decimal,fractional'
            ]);

            $validated = $request->only(['markets', 'bookmakers', 'region', 'odds_format']);

            $markets = $validated['markets'] ?? ['h2h', 'spreads', 'totals'];
            $bookmakers = $validated['bookmakers'] ?? null;
            $region = $validated['region'] ?? 'us';
            $oddsFormat = $validated['odds_format'] ?? 'american';

            $odds = $this->oddsApi->getOdds(
                'basketball_wnba',
                $markets,
                $bookmakers,
                $region,
                $oddsFormat
            );

            return response()->json([
                'success' => true,
                'data' => $odds,
                'count' => count($odds),
                'filters' => [
                    'markets' => $markets,
                    'region' => $region,
                    'odds_format' => $oddsFormat
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get WNBA odds', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch WNBA odds',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get WNBA player props with filtering options
     */
    public function getWnbaPlayerProps(Request $request)
    {
        try {
            $options = [
                'markets' => $request->input('markets', ['player_points', 'player_rebounds', 'player_assists', 'player_threes']),
                'bookmakers' => $request->input('bookmakers'),
                'player_name' => $request->input('player_name'),
                'regions' => $request->input('regions', 'us'),
                'oddsFormat' => $request->input('oddsFormat', 'american')
            ];

            // Convert comma-separated strings to arrays
            if (is_string($options['markets'])) {
                $options['markets'] = explode(',', $options['markets']);
            }
            if (is_string($options['bookmakers'])) {
                $options['bookmakers'] = explode(',', $options['bookmakers']);
            }

            $playerProps = $this->oddsApi->getWnbaPlayerProps($options);

            return response()->json([
                'success' => true,
                'data' => $playerProps,
                'count' => count($playerProps),
                'filters' => $options,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get WNBA player props', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch WNBA player props',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available player prop markets for WNBA
     */
    public function getPlayerPropMarkets()
    {
        try {
            $markets = $this->oddsApi->getAvailablePlayerPropMarkets();

            return response()->json([
                'success' => true,
                'data' => $markets,
                'count' => count($markets)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch player prop markets',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get best odds for a specific player prop
     */
    public function getBestPlayerPropOdds(Request $request)
    {
        $request->validate([
            'player_name' => 'required|string',
            'stat_type' => 'required|string',
            'line' => 'nullable|numeric'
        ]);

        try {
            $bestOdds = $this->oddsApi->getBestPlayerPropOdds(
                $request->input('player_name'),
                $request->input('stat_type'),
                $request->input('line')
            );

            return response()->json([
                'success' => true,
                'data' => $bestOdds,
                'player_name' => $request->input('player_name'),
                'stat_type' => $request->input('stat_type'),
                'line' => $request->input('line')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch best odds',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get player props for a specific event
     */
    public function getEventPlayerProps(Request $request, string $eventId)
    {
        try {
            $options = [
                'markets' => $request->input('markets', ['player_points', 'player_rebounds', 'player_assists']),
                'bookmakers' => $request->input('bookmakers'),
                'player_name' => $request->input('player_name'),
                'regions' => $request->input('regions', 'us'),
                'oddsFormat' => $request->input('oddsFormat', 'american')
            ];

            // Convert comma-separated strings to arrays
            if (is_string($options['markets'])) {
                $options['markets'] = explode(',', $options['markets']);
            }
            if (is_string($options['bookmakers'])) {
                $options['bookmakers'] = explode(',', $options['bookmakers']);
            }

            $playerProps = $this->oddsApi->getEventPlayerProps($eventId, $options);

            return response()->json([
                'success' => true,
                'data' => $playerProps,
                'count' => count($playerProps),
                'event_id' => $eventId,
                'filters' => $options
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch event player props',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive player props analysis
     */
    public function getPlayerPropsAnalysis(Request $request)
    {
        try {
            $playerName = $request->input('player_name');
            $markets = $request->input('markets', ['player_points', 'player_rebounds', 'player_assists']);

            if (is_string($markets)) {
                $markets = explode(',', $markets);
            }

            $analysis = [];

            foreach ($markets as $market) {
                $bestOdds = $this->oddsApi->getBestPlayerPropOdds($playerName, $market);
                $analysis[$market] = [
                    'market' => $market,
                    'best_odds' => $bestOdds,
                    'available_lines' => $this->getAvailableLines($playerName, $market)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $analysis,
                'player_name' => $playerName,
                'markets_analyzed' => $markets
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to analyze player props',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available lines for a player and market
     */
    private function getAvailableLines(string $playerName, string $market): array
    {
        try {
            $playerProps = $this->oddsApi->getWnbaPlayerProps([
                'player_name' => $playerName,
                'markets' => [$market]
            ]);

            $lines = [];
            foreach ($playerProps as $prop) {
                if ($prop['stat_type'] === $market && $prop['line'] !== null) {
                    $lines[] = $prop['line'];
                }
            }

            return array_unique($lines);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get odds for a specific player and stat
     */
    public function getPlayerOdds(Request $request)
    {
        try {
            $validated = $request->validate([
                'player_name' => 'required|string',
                'stat_type' => 'required|string|in:points,rebounds,assists,three_point_field_goals_made',
                'sport' => 'nullable|string'
            ]);

            $playerName = $validated['player_name'];
            $statType = $validated['stat_type'];
            $sport = $validated['sport'] ?? 'basketball_wnba';

            $odds = $this->oddsApi->getPlayerOdds($playerName, $statType, $sport);

            if (!$odds) {
                return response()->json([
                    'success' => false,
                    'message' => 'No odds found for this player and stat combination',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $odds,
                'player_name' => $playerName,
                'stat_type' => $statType
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get player odds', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch player odds',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming WNBA events
     */
    public function getWnbaEvents()
    {
        try {
            $events = $this->oddsApi->getEvents('basketball_wnba');

            return response()->json([
                'success' => true,
                'data' => $events,
                'count' => count($events)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get WNBA events', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch WNBA events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get historical odds for a specific date
     */
    public function getHistoricalOdds(Request $request)
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'sport' => 'nullable|string'
            ]);

            $date = Carbon::parse($validated['date']);
            $sport = $validated['sport'] ?? 'basketball_wnba';

            $odds = $this->oddsApi->getHistoricalOdds($sport, $date);

            return response()->json([
                'success' => true,
                'data' => $odds,
                'count' => count($odds),
                'date' => $date->format('Y-m-d'),
                'sport' => $sport
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get historical odds', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch historical odds',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get API usage statistics
     */
    public function getUsageStats()
    {
        try {
            $stats = $this->oddsApi->getUsageStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get usage stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve usage statistics'
            ], 500);
        }
    }

    /**
     * Clear all odds-related cache
     */
    public function clearCache()
    {
        try {
            $this->oddsApi->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear cache', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to clear cache'
            ], 500);
        }
    }

    /**
     * Get cache status and information
     */
    public function getCacheStatus()
    {
        try {
            $config = config('odds-api');
            $prefix = $config['cache']['prefix'];

            $cacheInfo = [
                'cache_keys' => [
                    'sports' => Cache::has($prefix . 'sports'),
                    'wnba_events' => Cache::has($prefix . 'wnba_events'),
                    'wnba_odds' => Cache::has($prefix . 'odds_basketball_wnba_' . md5(serialize(['h2h', 'spreads', 'totals']))),
                    'player_props' => Cache::has($prefix . 'wnba_player_props_' . md5(serialize([]))),
                ],
                'backup_cache' => [
                    'wnba_events' => Cache::has($prefix . 'backup_wnba_events'),
                    'player_props' => Cache::has($prefix . 'backup_wnba_player_props'),
                    'odds' => Cache::has($prefix . 'backup_odds_basketball_wnba'),
                ],
                'cache_config' => [
                    'odds_ttl' => $config['cache']['odds_ttl'],
                    'props_ttl' => $config['cache']['props_ttl'],
                    'events_ttl' => $config['cache']['events_ttl'],
                    'live_odds_ttl' => $config['cache']['live_odds_ttl'],
                ],
                'rate_limits' => [
                    'daily_target' => $config['rate_limit']['daily_target'],
                    'monthly_limit' => $config['rate_limit']['requests_per_month'],
                    'burst_limit' => $config['rate_limit']['burst_limit'],
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $cacheInfo
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get cache status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve cache status'
            ], 500);
        }
    }

    /**
     * Force refresh specific data (bypasses cache)
     */
    public function forceRefresh(Request $request)
    {
        $request->validate([
            'type' => 'required|in:odds,events,props,sports'
        ]);

        try {
            $type = $request->input('type');

            // Clear specific cache first
            switch ($type) {
                case 'odds':
                    Cache::forget('odds_api_odds_basketball_wnba_' . md5('default'));
                    $data = $this->oddsApi->getOdds();
                    break;
                case 'events':
                    Cache::forget('odds_api_wnba_events');
                    $data = $this->oddsApi->getWnbaEvents();
                    break;
                case 'props':
                    Cache::forget('odds_api_wnba_player_props_' . md5('default'));
                    $data = $this->oddsApi->getWnbaPlayerProps();
                    break;
                case 'sports':
                    Cache::forget('odds_api_sports');
                    $data = $this->oddsApi->getSports();
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid refresh type');
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully refreshed {$type} data",
                'data' => $data,
                'count' => count($data)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to force refresh data', [
                'type' => $request->input('type'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to refresh data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get best odds comparison across bookmakers
     */
    public function getBestOdds(Request $request)
    {
        try {
            $validated = $request->validate([
                'market' => 'required|string|in:h2h,spreads,totals,player_points,player_rebounds,player_assists',
                'player_name' => 'nullable|string',
                'team' => 'nullable|string'
            ]);

            $market = $validated['market'];
            $playerName = $validated['player_name'] ?? null;
            $team = $validated['team'] ?? null;

            // Get all odds for the market
            if (str_starts_with($market, 'player_')) {
                $allOdds = $this->oddsApi->getPlayerProps('basketball_wnba', [$market]);
            } else {
                $allOdds = $this->oddsApi->getOdds('basketball_wnba', [$market]);
            }

            // Filter and find best odds
            $bestOdds = $this->findBestOdds($allOdds, $market, $playerName, $team);

            return response()->json([
                'success' => true,
                'data' => $bestOdds,
                'market' => $market,
                'filters' => [
                    'player_name' => $playerName,
                    'team' => $team
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get best odds', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch best odds',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live odds updates
     */
    public function getLiveOdds()
    {
        try {
            // Get fresh odds (bypass cache for live updates)
            Cache::forget('odds_api_odds_basketball_wnba_*');

            $odds = $this->oddsApi->getOdds('basketball_wnba', ['h2h', 'spreads', 'totals']);

            // Filter for games starting soon or in progress
            $liveOdds = array_filter($odds, function ($event) {
                $commenceTime = Carbon::parse($event['commence_time']);
                $now = Carbon::now();

                // Games starting within 2 hours or already started
                return $commenceTime->diffInHours($now) <= 2;
            });

            return response()->json([
                'success' => true,
                'data' => array_values($liveOdds),
                'count' => count($liveOdds),
                'last_update' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get live odds', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live odds',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test API configuration and connectivity
     */
    public function testConfiguration()
    {
        try {
            $config = config('odds-api');

            $diagnostics = [
                'api_key_configured' => !empty($config['api_key']),
                'api_key_length' => $config['api_key'] ? strlen($config['api_key']) : 0,
                'api_key_preview' => $config['api_key'] ? substr($config['api_key'], 0, 8) . '...' : null,
                'base_url' => $config['base_url'],
                'timeout' => $config['timeout'],
                'cache_enabled' => !empty($config['cache']),
                'environment_check' => [
                    'ODDS_API_KEY' => !empty(env('ODDS_API_KEY')),
                    'ODDS_API_BASE_URL' => env('ODDS_API_BASE_URL', 'not set'),
                ]
            ];

            // Test basic API connectivity
            if ($config['api_key']) {
                try {
                    $testResponse = $this->oddsApi->getSports();
                    $diagnostics['api_test'] = [
                        'success' => true,
                        'sports_count' => count($testResponse),
                        'message' => 'API connection successful'
                    ];
                } catch (\Exception $e) {
                    $diagnostics['api_test'] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'API connection failed'
                    ];
                }
            } else {
                $diagnostics['api_test'] = [
                    'success' => false,
                    'error' => 'No API key configured',
                    'message' => 'API key is required'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $diagnostics,
                'recommendations' => $this->getConfigurationRecommendations($diagnostics)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Configuration test failed',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get configuration recommendations based on diagnostics
     */
    private function getConfigurationRecommendations(array $diagnostics): array
    {
        $recommendations = [];

        if (!$diagnostics['api_key_configured']) {
            $recommendations[] = 'Add ODDS_API_KEY to your .env file';
        }

        if ($diagnostics['api_key_length'] > 0 && $diagnostics['api_key_length'] < 20) {
            $recommendations[] = 'API key seems too short - verify it\'s correct';
        }

        if (!$diagnostics['environment_check']['ODDS_API_KEY']) {
            $recommendations[] = 'Environment variable ODDS_API_KEY is not set';
        }

        if (isset($diagnostics['api_test']) && !$diagnostics['api_test']['success']) {
            $recommendations[] = 'API connection failed - check your API key and network connectivity';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Configuration looks good!';
        }

        return $recommendations;
    }

    // Private helper methods

    /**
     * Find best odds from multiple bookmakers
     */
    private function findBestOdds(array $allOdds, string $market, ?string $playerName = null, ?string $team = null): array
    {
        $bestOdds = [];

        foreach ($allOdds as $event) {
            // Apply filters
            if ($playerName && isset($event['player_name']) && stripos($event['player_name'], $playerName) === false) {
                continue;
            }

            if ($team && (!str_contains($event['home_team'], $team) && !str_contains($event['away_team'], $team))) {
                continue;
            }

            // Find best odds for this event
            $eventBestOdds = [
                'event_id' => $event['id'] ?? $event['event_id'],
                'home_team' => $event['home_team'],
                'away_team' => $event['away_team'],
                'commence_time' => $event['commence_time'],
                'best_odds' => []
            ];

            if (isset($event['player_name'])) {
                $eventBestOdds['player_name'] = $event['player_name'];
                $eventBestOdds['stat_type'] = $event['stat_type'];
                $eventBestOdds['line'] = $event['line'];
            }

            // Process bookmakers to find best odds
            $bookmakers = $event['bookmakers'] ?? [];
            foreach ($bookmakers as $bookmaker) {
                // Implementation depends on the specific market structure
                // This is a simplified version
                $eventBestOdds['best_odds'][] = [
                    'bookmaker' => $bookmaker['title'] ?? $bookmaker['bookmaker'],
                    'odds' => $bookmaker['price'] ?? $bookmaker['odds'],
                    'last_update' => $bookmaker['last_update']
                ];
            }

            $bestOdds[] = $eventBestOdds;
        }

        return $bestOdds;
    }
}
