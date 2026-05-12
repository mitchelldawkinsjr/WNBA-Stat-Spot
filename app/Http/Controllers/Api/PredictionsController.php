<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WNBA\Predictions\PropsPredictionService;
use App\Services\WNBA\Predictions\StatisticalEngineService;
use App\Services\WNBA\Analytics\PlayerAnalyticsService;
use App\Services\WNBA\Analytics\TeamAnalyticsService;
use App\Services\WNBA\Analytics\GameAnalyticsService;
use App\Services\WNBA\Data\DataAggregatorService;
use App\Services\Odds\OddsApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PredictionsController extends Controller
{
    private PropsPredictionService $propsPrediction;
    private StatisticalEngineService $statisticalEngine;
    private PlayerAnalyticsService $playerAnalytics;
    private TeamAnalyticsService $teamAnalytics;
    private GameAnalyticsService $gameAnalytics;
    private DataAggregatorService $dataAggregator;
    private OddsApiService $oddsApi;

    public function __construct(
        PropsPredictionService $propsPrediction,
        StatisticalEngineService $statisticalEngine,
        PlayerAnalyticsService $playerAnalytics,
        TeamAnalyticsService $teamAnalytics,
        GameAnalyticsService $gameAnalytics,
        DataAggregatorService $dataAggregator,
        OddsApiService $oddsApi
    ) {
        $this->propsPrediction = $propsPrediction;
        $this->statisticalEngine = $statisticalEngine;
        $this->playerAnalytics = $playerAnalytics;
        $this->teamAnalytics = $teamAnalytics;
        $this->gameAnalytics = $gameAnalytics;
        $this->dataAggregator = $dataAggregator;
        $this->oddsApi = $oddsApi;
    }

    /**
     * Get player analytics
     */
    public function getPlayerAnalytics(int $playerId)
    {
        try {
            $cacheKey = "player_analytics:{$playerId}";

            return response()->json([
                'status' => 'success',
                'data' => Cache::remember($cacheKey, now()->addHours(24), function() use ($playerId) {
                    return $this->playerAnalytics->getAnalytics($playerId);
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get player analytics', [
                'player_id' => $playerId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get player analytics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get player prop predictions
     */
    public function getPlayerPropPredictions(Request $request)
    {
        try {
            $validated = $request->validate([
                'player_id' => 'required|integer',
                'game_id' => 'required|integer',
                'prop_type' => 'required|string',
                'line_value' => 'required|numeric'
            ]);

            $prediction = $this->propsPrediction->predictProp(
                $validated['player_id'],
                $validated['game_id'],
                $validated['prop_type'],
                $validated['line_value']
            );

            return response()->json([
                'status' => 'success',
                'data' => $prediction
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get prop predictions', [
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get prop predictions',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get betting recommendations
     */
    public function getBettingRecommendations(Request $request)
    {
        try {
            $validated = $request->validate([
                'player_id' => 'required|integer',
                'game_id' => 'required|integer',
                'prop_type' => 'required|string',
                'line_value' => 'required|numeric',
                'odds_over' => 'nullable|numeric',
                'odds_under' => 'nullable|numeric'
            ]);

            $recommendation = $this->propsPrediction->getBettingRecommendation(
                $validated['player_id'],
                $validated['prop_type'],
                $validated['line_value'],
                $validated['odds_over'] ?? -110,
                $validated['odds_under'] ?? -110,
                $validated['game_id']
            );

            return response()->json([
                'status' => 'success',
                'data' => $recommendation
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get betting recommendations', [
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get betting recommendations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team analytics
     */
    public function getTeamAnalytics(int $teamId)
    {
        try {
            $cacheKey = "team_analytics:{$teamId}";

            return response()->json([
                'status' => 'success',
                'data' => Cache::remember($cacheKey, now()->addHours(24), function() use ($teamId) {
                    return $this->teamAnalytics->getAnalytics($teamId);
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get team analytics', [
                'team_id' => $teamId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get team analytics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get game analytics
     */
    public function getGameAnalytics(int $gameId)
    {
        try {
            $cacheKey = "game_analytics:{$gameId}";

            return response()->json([
                'status' => 'success',
                'data' => Cache::remember($cacheKey, now()->addHours(24), function() use ($gameId) {
                    return $this->gameAnalytics->getAnalytics($gameId);
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get game analytics', [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get game analytics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run Monte Carlo simulation
     */
    public function runMonteCarloSimulation(Request $request)
    {
        try {
            $validated = $request->validate([
                'player_id' => 'required|integer',
                'game_id' => 'required|integer',
                'stat_type' => 'required|string',
                'line_value' => 'required|numeric',
                'iterations' => 'nullable|integer|min:1000|max:100000'
            ]);

            $results = $this->statisticalEngine->runMonteCarloSimulation(
                $validated['player_id'],
                $validated['game_id'],
                $validated['stat_type'],
                $validated['line_value'],
                $validated['iterations'] ?? 10000
            );

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Monte Carlo simulation failed', [
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Monte Carlo simulation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats()
    {
        try {
            $stats = [
                'player_analytics' => Cache::get('player_analytics:*') ? count(Cache::get('player_analytics:*')) : 0,
                'team_analytics' => Cache::get('team_analytics:*') ? count(Cache::get('team_analytics:*')) : 0,
                'game_analytics' => Cache::get('game_analytics:*') ? count(Cache::get('game_analytics:*')) : 0,
                'prop_predictions' => Cache::get('prop_prediction:*') ? count(Cache::get('prop_prediction:*')) : 0,
                'monte_carlo' => Cache::get('monte_carlo:*') ? count(Cache::get('monte_carlo:*')) : 0
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get cache stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get cache stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear prediction cache
     */
    public function clearCache()
    {
        try {
            Cache::tags(['predictions', 'analytics'])->flush();

            return response()->json([
                'status' => 'success',
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear cache', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to clear cache',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Warm cache with frequently accessed data
     */
    public function warmCache()
    {
        try {
            // Warm cache for top players
            $topPlayerIds = [1, 2, 3, 4, 5]; // Would get from database

            foreach ($topPlayerIds as $playerId) {
                $this->getPlayerAnalytics($playerId);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Cache warmed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to warm cache', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to warm cache',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's best prop bets based on mathematical analysis
     */
    public function getTodaysBestProps(Request $request)
    {
        try {
            // Get timezone from request parameter, default to UTC
            $timezone = $request->get('timezone', 'UTC');

            // Validate timezone
            try {
                $userTimezone = new \DateTimeZone($timezone);
            } catch (\Exception $e) {
                Log::warning('Invalid timezone provided, falling back to UTC', [
                    'provided_timezone' => $timezone,
                    'error' => $e->getMessage()
                ]);
                $timezone = 'UTC';
                $userTimezone = new \DateTimeZone('UTC');
            }

            Log::info('API called with timezone', [
                'timezone' => $timezone,
                'server_time_utc' => Carbon::now('UTC')->toString(),
                'user_time' => Carbon::now($timezone)->toString()
            ]);

            $cacheKey = 'todays_best_props_with_odds_v2_' . str_replace('/', '_', $timezone);

            return response()->json([
                'success' => true,
                'data' => Cache::remember($cacheKey, now()->addMinutes(30), function () use ($timezone) {
                    try {
                        $props = $this->generateTodaysBestProps($timezone);

                        if (empty($props)) {
                            Log::info('No games scheduled for today in user timezone - returning empty props list', [
                                'timezone' => $timezone,
                            ]);

                            return [];
                        }

                        return $props;
                    } catch (\Throwable $e) {
                        Log::warning('generateTodaysBestProps failed; returning empty props list', [
                            'timezone' => $timezone,
                            'error' => $e->getMessage(),
                        ]);

                        return [];
                    }
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get today\'s best props', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Props unavailable; check logs or configuration.',
            ]);
        }
    }

    /**
     * Generate today's best props by analyzing today's games
     */
    private function generateTodaysBestProps(string $timezone = 'UTC'): array
    {
        $todaysGames = $this->getTodaysGames($timezone);

        Log::info('DEBUG: generateTodaysBestProps starting', [
            'games_found' => count($todaysGames),
            'timezone' => $timezone
        ]);

        if (empty($todaysGames)) {
            Log::info('No games scheduled for today in user timezone - returning empty props list', [
                'timezone' => $timezone
            ]);
            return [];
        }

        $bestProps = [];

        foreach ($todaysGames as $game) {
            Log::info('DEBUG: Processing game in generateTodaysBestProps', [
                'game_id' => $game['game_id']
            ]);

            $players = $this->getPlayersFromGame($game);

            if (empty($players)) {
                Log::info('No players found for game, skipping', [
                    'game_id' => $game['game_id']
                ]);
                continue;
            }

            Log::info('DEBUG: Found players for game', [
                'game_id' => $game['game_id'],
                'player_count' => count($players)
            ]);

            foreach ($players as $player) {
                $props = $this->generatePlayerPropsWithOdds($player, $game);
                $bestProps = array_merge($bestProps, $props);

                Log::info('DEBUG: Merged props for player', [
                    'player_name' => $player['name'] ?? 'unknown',
                    'props_added' => count($props),
                    'total_props_so_far' => count($bestProps)
                ]);
            }
        }

        Log::info('DEBUG: Before sorting and filtering', [
            'total_props_generated' => count($bestProps)
        ]);

        if (empty($bestProps)) {
            Log::info('No props generated from today\'s games in user timezone', [
                'timezone' => $timezone
            ]);
            return [];
        }

        // Sort by expected value and return top props
        usort($bestProps, function($a, $b) {
            return $b['expected_value'] <=> $a['expected_value'];
        });

        $topProps = array_slice($bestProps, 0, 20);

        Log::info('Generated today\'s best props', [
            'total_props' => count($bestProps),
            'returned_props' => count($topProps),
            'games_processed' => count($todaysGames),
            'timezone' => $timezone,
            'top_expected_values' => array_slice(array_map(function($prop) {
                return $prop['expected_value'];
            }, $topProps), 0, 5)
        ]);

        return $topProps;
    }

    /**
     * Generate props for a list of players using the odds-integrated prediction system
     */
    private function generatePropsForPlayers(array $players): array
    {
        $bestProps = [];

        foreach ($players as $player) {
            $props = $this->generatePlayerPropsWithOdds($player, null);
            $bestProps = array_merge($bestProps, $props);
        }

        // Sort by expected value and return top props
        usort($bestProps, function($a, $b) {
            return $b['expected_value'] <=> $a['expected_value'];
        });

        return array_slice($bestProps, 0, 20);
    }

    /**
     * Generate player props using the same odds-integrated prediction system
     */
    private function generatePlayerPropsWithOdds(array $player, ?array $game): array
    {
        Log::info('DEBUG: generatePlayerPropsWithOdds called', [
            'player_name' => $player['name'] ?? $player['athlete_display_name'] ?? 'unknown',
            'player_id' => $player['athlete_id'] ?? $player['id'] ?? 'unknown',
            'game_id' => $game['game_id'] ?? 'no game'
        ]);

        $props = [];
        $statTypes = ['points', 'rebounds', 'assists', 'steals', 'blocks'];

        // Common prop lines for each stat type
        $commonLines = [
            'points' => [15.5, 18.5, 22.5, 25.5],
            'rebounds' => [6.5, 8.5, 10.5, 12.5],
            'assists' => [3.5, 5.5, 7.5, 9.5],
            'steals' => [0.5, 1.5, 2.5],
            'blocks' => [0.5, 1.5, 2.5]
        ];

        foreach ($statTypes as $statType) {
            $lines = $commonLines[$statType] ?? [10.5];

            // Use the best line for this stat type (usually the middle one)
            $bestLineIndex = floor(count($lines) / 2);
            $line = $lines[$bestLineIndex];

            try {
                // Use the same prediction system as the individual predictions
                $playerId = $player['athlete_id'] ?? $player['id'];

                // Always use the fallback prediction with odds since PropsPredictionService
                // requires specific player/game ID combinations that may not exist
                $prediction = $this->generatePredictionWithOdds($player, $statType, $line);

                // Get odds data - always fetch if not included in prediction data
                $oddsData = $prediction['odds_data'] ?? [];

                // Check if odds data is empty, null, or invalid
                $needsOddsData = empty($oddsData) ||
                               $oddsData === null ||
                               !isset($oddsData['over_odds']) ||
                               $oddsData['over_odds'] === null ||
                               !isset($oddsData['available']);

                if ($needsOddsData) {
                    // Fetch odds data if not included in prediction
                    $oddsData = $this->getRealBettingLines($player['name'] ?? $player['athlete_display_name'], $statType);
                    if (empty($oddsData) || !isset($oddsData['available']) || !$oddsData['available']) {
                        // Use fallback odds if real odds not available
                        $oddsData = $this->getFallbackOdds($statType);
                    }
                }

                // Calculate expected value using real odds
                $expectedValue = $this->calculateExpectedValueWithRealOdds(
                    $prediction['prediction']['over_probability'] ?? 0.5,
                    $prediction['prediction']['confidence_score'] ?? 0.75,
                    $oddsData
                );

                Log::info('DEBUG: Generated prop for player', [
                    'player_name' => $player['name'] ?? $player['athlete_display_name'] ?? 'unknown',
                    'stat_type' => $statType,
                    'expected_value' => $expectedValue,
                    'confidence' => $prediction['prediction']['confidence_score'] ?? 0,
                    'will_include' => ($expectedValue > -5.0 && ($prediction['prediction']['confidence_score'] ?? 0) > 0.5)
                ]);

                // Include props with positive expected value or decent confidence
                if ($expectedValue > -5.0 && ($prediction['prediction']['confidence_score'] ?? 0) > 0.5) {
                    $props[] = [
                        'player_id' => $playerId,
                        'player_name' => $player['name'] ?? $player['athlete_display_name'],
                        'team_abbreviation' => $this->getTeamAbbreviation($player['team_id'] ?? null),
                        'opponent' => $game ? $this->getOpponent($player['team_id'] ?? 1, $game) : 'TBD',
                        'game_time' => $game ? $this->formatGameTime($game['game_date_time'] ?? '') : 'TBD',
                        'stat_type' => $statType,
                        'suggested_line' => $line,
                        'predicted_value' => $prediction['prediction']['predicted_value'] ?? $line,
                        'confidence' => ($prediction['prediction']['confidence_score'] ?? 0.75) * 100,
                        'recommendation' => $this->getRecommendationFromPrediction($prediction, $line),
                        'expected_value' => $expectedValue,
                        'probability_over' => ($prediction['prediction']['over_probability'] ?? 0.5) * 100,
                        'probability_under' => ($prediction['prediction']['under_probability'] ?? 0.5) * 100,
                        'recent_form' => $prediction['recent_average'] ?? $line,
                        'season_average' => $prediction['season_average'] ?? $line,
                        'matchup_difficulty' => $game ? $this->getMatchupDifficulty($player, $game) : 'neutral',
                        'betting_value' => $this->getBettingValue($expectedValue),
                        'reasoning' => $this->generateReasoningWithOddsData(
                            $prediction,
                            $statType,
                            $prediction['prediction']['predicted_value'] ?? $line,
                            $line,
                            $oddsData
                        ),
                        // Add odds data for frontend display
                        'odds_api_line' => $oddsData['line'] ?? $line,
                        'odds_api_odds' => [
                            'over' => $oddsData['over_odds'] ?? -110,
                            'under' => $oddsData['under_odds'] ?? -110
                        ],
                        'odds_available' => $oddsData['available'] ?? false,
                        'odds_source' => $oddsData['source'] ?? 'estimated',
                        'bookmakers' => [
                            'over' => $oddsData['bookmaker_over'] ?? 'Estimated',
                            'under' => $oddsData['bookmaker_under'] ?? 'Estimated'
                        ]
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to generate prop for player', [
                    'player_id' => $playerId,
                    'stat_type' => $statType,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('DEBUG: generatePlayerPropsWithOdds result', [
            'player_name' => $player['name'] ?? $player['athlete_display_name'] ?? 'unknown',
            'props_generated' => count($props)
        ]);

        return $props;
    }

    /**
     * Generate a prediction with odds data when PropsPredictionService fails
     */
    private function generatePredictionWithOdds(array $player, string $statType, float $line): array
    {
        // Get recent stats for the player
        $recentStats = $this->getPlayerRecentStats($player['id'] ?? $player['athlete_id'], $statType);

        // Generate basic prediction
        $prediction = $this->generateMockPrediction($player, $statType, $recentStats);

        // Add odds data
        $oddsData = $this->getRealBettingLines($player['name'] ?? $player['athlete_display_name'], $statType);

        return [
            'prediction' => [
                'predicted_value' => $prediction['predicted_value'],
                'over_probability' => $prediction['probability_over'] / 100,
                'under_probability' => $prediction['probability_under'] / 100,
                'confidence_score' => $prediction['confidence'] / 100
            ],
            'odds_data' => $oddsData,
            'recent_average' => $recentStats['recent_average'],
            'season_average' => $recentStats['season_average']
        ];
    }

    /**
     * Get recommendation from prediction data
     */
    private function getRecommendationFromPrediction(array $prediction, float $line): string
    {
        $predictedValue = $prediction['prediction']['predicted_value'] ?? $line;
        $confidence = $prediction['prediction']['confidence_score'] ?? 0.5;

        // Reduced from 0.6 to 0.55 for more realistic recommendations
        if ($confidence < 0.55) {
            return 'avoid';
        }

        // Consider both direction and confidence
        $difference = abs($predictedValue - $line);
        $significantDifference = $difference > ($line * 0.08); // 8% difference threshold

        // If prediction is very close to line, be more cautious
        if (!$significantDifference && $confidence < 0.65) {
            return 'avoid';
        }

        return $predictedValue > $line ? 'over' : 'under';
    }

    /**
     * Get mock props for demo purposes
     */
    private function getMockTodaysProps(): array
    {
        return [
            [
                'player_id' => '3149391',
                'player_name' => "A'ja Wilson",
                'team_abbreviation' => 'LAS',
                'opponent' => 'vs SEA',
                'game_time' => '7:00 PM ET',
                'stat_type' => 'points',
                'suggested_line' => 22.5,
                'predicted_value' => 24.5,
                'confidence' => 68,
                'recommendation' => 'over',
                'expected_value' => 4.2,
                'probability_over' => 58,
                'probability_under' => 42,
                'recent_form' => 24.8,
                'season_average' => 23.1,
                'matchup_difficulty' => 'favorable',
                'betting_value' => 'good',
                'reasoning' => 'Strong recent form vs weak interior defense'
            ],
            [
                'player_id' => '4066261',
                'player_name' => 'Breanna Stewart',
                'team_abbreviation' => 'NY',
                'opponent' => 'vs CHI',
                'game_time' => '7:30 PM ET',
                'stat_type' => 'rebounds',
                'suggested_line' => 8.5,
                'predicted_value' => 9.5,
                'confidence' => 62,
                'recommendation' => 'over',
                'expected_value' => 3.8,
                'probability_over' => 55,
                'probability_under' => 45,
                'recent_form' => 9.2,
                'season_average' => 8.7,
                'matchup_difficulty' => 'neutral',
                'betting_value' => 'fair',
                'reasoning' => 'Chicago allows high rebounding rate to forwards'
            ],
            [
                'player_id' => '4277956',
                'player_name' => 'Sabrina Ionescu',
                'team_abbreviation' => 'NY',
                'opponent' => 'vs CHI',
                'game_time' => '7:30 PM ET',
                'stat_type' => 'assists',
                'suggested_line' => 6.5,
                'predicted_value' => 7.5,
                'confidence' => 65,
                'recommendation' => 'over',
                'expected_value' => 3.5,
                'probability_over' => 56,
                'probability_under' => 44,
                'recent_form' => 7.3,
                'season_average' => 6.9,
                'matchup_difficulty' => 'favorable',
                'betting_value' => 'fair',
                'reasoning' => 'High pace game expected, strong assist rate'
            ],
            [
                'player_id' => '4066262',
                'player_name' => 'Kelsey Plum',
                'team_abbreviation' => 'LAS',
                'opponent' => 'vs SEA',
                'game_time' => '7:00 PM ET',
                'stat_type' => 'points',
                'suggested_line' => 17.5,
                'predicted_value' => 19.0,
                'confidence' => 59,
                'recommendation' => 'over',
                'expected_value' => 2.8,
                'probability_over' => 54,
                'probability_under' => 46,
                'recent_form' => 18.5,
                'season_average' => 17.8,
                'matchup_difficulty' => 'neutral',
                'betting_value' => 'fair',
                'reasoning' => 'Consistent scoring vs average defense'
            ],
            [
                'player_id' => '4066264',
                'player_name' => 'Napheesa Collier',
                'team_abbreviation' => 'MIN',
                'opponent' => '@ CONN',
                'game_time' => '8:00 PM ET',
                'stat_type' => 'points',
                'suggested_line' => 20.5,
                'predicted_value' => 22.0,
                'confidence' => 61,
                'recommendation' => 'over',
                'expected_value' => 3.2,
                'probability_over' => 55,
                'probability_under' => 45,
                'recent_form' => 21.8,
                'season_average' => 20.9,
                'matchup_difficulty' => 'favorable',
                'betting_value' => 'fair',
                'reasoning' => 'Strong road performance, favorable matchup'
            ]
        ];
    }

    /**
     * Get today's games that are scheduled but not completed
     */
    private function getTodaysGames(string $timezone = 'UTC'): array
    {
        try {
            // Use the user's timezone to determine what "today" means
            $userToday = Carbon::now($timezone);
            $userStartOfDay = $userToday->copy()->startOfDay();
            $userEndOfDay = $userToday->copy()->endOfDay();

            // Convert to UTC for database queries (since dates are stored in UTC)
            $utcStartOfDay = $userStartOfDay->copy()->utc();
            $utcEndOfDay = $userEndOfDay->copy()->utc();

            Log::info('DEBUG: Starting getTodaysGames with timezone', [
                'timezone' => $timezone,
                'user_today' => $userToday->toString(),
                'user_date' => $userToday->toDateString(),
                'utc_start' => $utcStartOfDay->toString(),
                'utc_end' => $utcEndOfDay->toString()
            ]);

            // Get all games for today (in user's timezone) that are not completed
            $games = DB::table('wnba_games')
                ->where('game_date_time', '>=', $utcStartOfDay)
                ->where('game_date_time', '<=', $utcEndOfDay)
                ->where(function($query) {
                    // Include games that are scheduled/upcoming
                    $query->whereIn('status_name', [
                        'STATUS_SCHEDULED', 'SCHEDULED', 'Pre-Game', 'PRE_GAME', 'Pregame'
                    ])
                    // OR games that are currently in progress
                    ->orWhereIn('status_name', [
                        'STATUS_IN_PROGRESS', 'IN_PROGRESS', 'Live', 'LIVE', 'In Progress',
                        'STATUS_HALFTIME', 'HALFTIME', 'Half Time', 'Halftime',
                        'STATUS_END_PERIOD', 'END_PERIOD', 'End of Period', 'End Period'
                    ]);
                })
                // Explicitly exclude completed/final games
                ->whereNotIn('status_name', [
                    'STATUS_FINAL', 'FINAL', 'Final', 'COMPLETED', 'Completed',
                    'STATUS_FINAL_OT', 'FINAL_OT', 'Final OT', 'Final (OT)',
                    'STATUS_POSTPONED', 'POSTPONED', 'Postponed',
                    'STATUS_CANCELED', 'CANCELED', 'Cancelled', 'STATUS_CANCELLED'
                ])
                // Extra safety: exclude anything with FINAL or COMPLETED in the name
                ->where('status_name', 'NOT LIKE', '%FINAL%')
                ->where('status_name', 'NOT LIKE', '%COMPLETED%')
                ->where('status_name', 'NOT LIKE', '%POSTPONED%')
                ->where('status_name', 'NOT LIKE', '%CANCEL%')
                // Also filter by time: only include games that haven't ended yet
                // (give 4 hour buffer for typical game length)
                ->where('game_date_time', '>', $userToday->copy()->subHours(4)->utc())
                ->select(['id', 'game_id', 'game_date_time', 'status_name'])
                ->orderBy('game_date_time', 'asc')
                ->get()
                ->toArray();

            Log::info('DEBUG: Raw query found games', [
                'games_count' => count($games),
                'timezone' => $timezone,
                'games' => array_map(function($game) use ($timezone) {
                    return [
                        'id' => $game->id,
                        'game_id' => $game->game_id,
                        'status' => $game->status_name,
                        'game_time_utc' => $game->game_date_time,
                        'game_time_user' => Carbon::parse($game->game_date_time)->setTimezone($timezone)->toString()
                    ];
                }, $games)
            ]);

            // If no games today that meet criteria, return empty array
            if (empty($games)) {
                Log::info('No eligible games found for today in user timezone', [
                    'timezone' => $timezone,
                    'user_date' => $userToday->toDateString(),
                    'total_games_in_range' => DB::table('wnba_games')
                        ->where('game_date_time', '>=', $utcStartOfDay)
                        ->where('game_date_time', '<=', $utcEndOfDay)
                        ->count(),
                    'all_statuses_in_range' => DB::table('wnba_games')
                        ->where('game_date_time', '>=', $utcStartOfDay)
                        ->where('game_date_time', '<=', $utcEndOfDay)
                        ->pluck('status_name')
                        ->unique()
                        ->values()
                        ->toArray()
                ]);
                return [];
            }

            // Convert to array format expected by the rest of the code
            $formattedGames = [];
            foreach ($games as $game) {
                $gameTimeUtc = Carbon::parse($game->game_date_time);
                $gameTimeUser = $gameTimeUtc->copy()->setTimezone($timezone);
                $teams = $this->getTeamsForGame($game->game_id);

                Log::info('DEBUG: Processing game', [
                    'game_id' => $game->game_id,
                    'teams' => $teams,
                    'status' => $game->status_name,
                    'game_time_utc' => $gameTimeUtc->toString(),
                    'game_time_user' => $gameTimeUser->toString()
                ]);

                // Double-check: only include if game is today (in user's timezone) and not completed
                $isToday = $gameTimeUser->isSameDay($userToday);
                $isNotCompleted = !$this->isGameCompleted($game->status_name);
                $isNotTooOld = $gameTimeUtc->greaterThan($userToday->copy()->subHours(4)->utc());

                if ($isToday && $isNotCompleted && $isNotTooOld) {
                    $formattedGames[] = [
                        'id' => $game->id,
                        'game_id' => $game->game_id,
                        'home_team_id' => $teams['home_team_id'] ?? null,
                        'away_team_id' => $teams['away_team_id'] ?? null,
                        'game_time' => $gameTimeUser->format('H:i:s'),
                        'game_date_time' => $game->game_date_time,
                        'status' => $game->status_name,
                        'is_upcoming' => in_array($game->status_name, ['STATUS_SCHEDULED', 'SCHEDULED', 'Pre-Game', 'PRE_GAME', 'Pregame']),
                        'is_live' => $this->isGameLive($game->status_name),
                        'is_today' => true,
                        'home_team' => $teams['home_team'] ?? 'Unknown',
                        'away_team' => $teams['away_team'] ?? 'Unknown',
                        'user_timezone' => $timezone,
                        'user_game_time' => $gameTimeUser->format('Y-m-d H:i:s T')
                    ];
                } else {
                    Log::info('DEBUG: Excluding game from results', [
                        'game_id' => $game->game_id,
                        'is_today' => $isToday,
                        'is_not_completed' => $isNotCompleted,
                        'is_not_too_old' => $isNotTooOld,
                        'status' => $game->status_name,
                        'timezone' => $timezone
                    ]);
                }
            }

            Log::info('Found eligible games for today in user timezone', [
                'timezone' => $timezone,
                'user_date' => $userToday->toDateString(),
                'games_count' => count($formattedGames),
                'games' => array_map(function($game) {
                    return [
                        'game_id' => $game['game_id'],
                        'status' => $game['status'],
                        'user_game_time' => $game['user_game_time'],
                        'is_upcoming' => $game['is_upcoming'],
                        'is_live' => $game['is_live'],
                        'home_team' => $game['home_team'],
                        'away_team' => $game['away_team']
                    ];
                }, $formattedGames)
            ]);

            return $formattedGames;

        } catch (\Exception $e) {
            Log::error('Failed to get today\'s games', [
                'timezone' => $timezone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [];
        }
    }

    /**
     * Check if a game is completed/final
     */
    private function isGameCompleted(string $status): bool
    {
        $completedStatuses = [
            'STATUS_FINAL', 'FINAL', 'Final', 'COMPLETED', 'Completed',
            'STATUS_FINAL_OT', 'FINAL_OT', 'Final OT', 'Final (OT)',
            'STATUS_POSTPONED', 'POSTPONED', 'Postponed',
            'STATUS_CANCELED', 'CANCELED', 'Cancelled', 'STATUS_CANCELLED'
        ];

        return in_array($status, $completedStatuses) ||
               str_contains($status, 'FINAL') ||
               str_contains($status, 'COMPLETED');
    }

    /**
     * Check if a game is currently live/in progress
     */
    private function isGameLive(string $status): bool
    {
        $liveStatuses = [
            'STATUS_IN_PROGRESS', 'IN_PROGRESS', 'Live', 'LIVE',
            'STATUS_HALFTIME', 'HALFTIME', 'Half Time', 'Halftime',
            'STATUS_END_PERIOD', 'END_PERIOD', 'End of Period', 'End Period'
        ];

        return in_array($status, $liveStatuses) ||
               str_contains($status, 'IN_PROGRESS') ||
               str_contains($status, 'LIVE');
    }

    /**
     * Get players from a game's teams
     */
    private function getPlayersFromGame(array $game): array
    {
        try {
            Log::info('DEBUG: getPlayersFromGame called', [
                'game_id' => $game['game_id'] ?? 'unknown',
                'home_team_id' => $game['home_team_id'] ?? null,
                'away_team_id' => $game['away_team_id'] ?? null
            ]);

            // Get players who have played for these teams in recent games
            $players = DB::table('wnba_player_games')
                ->join('wnba_players', 'wnba_player_games.player_id', '=', 'wnba_players.id')
                ->whereIn('wnba_player_games.team_id', [$game['home_team_id'], $game['away_team_id']])
                ->where('wnba_player_games.active', 1)
                ->select([
                    'wnba_players.id',
                    'wnba_players.athlete_id',
                    'wnba_players.athlete_display_name as name',
                    'wnba_players.athlete_position_abbreviation as position',
                    'wnba_player_games.team_id',
                    'wnba_player_games.minutes',
                    'wnba_player_games.field_goals_made',
                    'wnba_player_games.field_goals_attempted',
                    'wnba_player_games.three_point_field_goals_made',
                    'wnba_player_games.three_point_field_goals_attempted',
                    'wnba_player_games.free_throws_made',
                    'wnba_player_games.free_throws_attempted',
                    'wnba_player_games.offensive_rebounds',
                    'wnba_player_games.defensive_rebounds',
                    'wnba_player_games.rebounds',
                    'wnba_player_games.assists',
                    'wnba_player_games.steals',
                    'wnba_player_games.blocks',
                    'wnba_player_games.turnovers',
                    'wnba_player_games.fouls',
                    'wnba_player_games.plus_minus',
                    'wnba_player_games.points',
                    'wnba_player_games.starter'
                ])
                ->orderBy('wnba_player_games.game_id', 'desc')
                ->get()
                ->unique('athlete_id')
                ->values()
                ->map(function($player) {
                    return (array) $player;
                })
                ->toArray();

            Log::info('DEBUG: getPlayersFromGame result', [
                'game_id' => $game['game_id'] ?? 'unknown',
                'players_found' => count($players),
                'sample_players' => array_slice(array_map(function($p) {
                    return ['name' => $p['name'], 'team_id' => $p['team_id']];
                }, $players), 0, 3)
            ]);

            return $players;
        } catch (\Exception $e) {
            Log::error('Error getting players from game: ' . $e->getMessage(), [
                'game' => $game,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get top players from database for prop generation when team data is not available
     */
    private function getTopPlayersForProps(): array
    {
        try {
            // Get top players based on recent performance
            $players = DB::table('wnba_players')
                ->join('wnba_player_games', 'wnba_players.id', '=', 'wnba_player_games.player_id')
                ->select([
                    'wnba_players.id',
                    'wnba_players.athlete_id',
                    'wnba_players.athlete_display_name as name',
                    'wnba_players.athlete_position_abbreviation as position',
                    'wnba_player_games.team_id',
                    DB::raw('AVG(wnba_player_games.points) as avg_points'),
                    DB::raw('COUNT(*) as games_played')
                ])
                ->where('wnba_player_games.points', '>', 0) // Only players who have scored
                ->groupBy([
                    'wnba_players.id',
                    'wnba_players.athlete_id',
                    'wnba_players.athlete_display_name',
                    'wnba_players.athlete_position_abbreviation',
                    'wnba_player_games.team_id'
                ])
                ->having('games_played', '>=', 3) // At least 3 games played
                ->orderBy('avg_points', 'desc')
                ->limit(12) // Get top 12 players
                ->get()
                ->toArray();

            $formattedPlayers = [];
            foreach ($players as $player) {
                // Get team abbreviation
                $teamAbbr = $this->getTeamAbbreviationFromId($player->team_id);

                $formattedPlayers[] = [
                    'id' => $player->id,
                    'athlete_id' => $player->athlete_id,
                    'name' => $player->name,
                    'team_id' => $player->team_id,
                    'team_abbreviation' => $teamAbbr,
                    'position' => $player->position ?? 'G',
                    'avg_points' => round($player->avg_points, 1),
                    'games_played' => $player->games_played
                ];
            }

            return $formattedPlayers;

        } catch (\Exception $e) {
            Log::error('Failed to get top players for props', [
                'error' => $e->getMessage()
            ]);

            return $this->getMockTopPlayers();
        }
    }

    /**
     * Get mock top players as final fallback
     */
    private function getMockTopPlayers(): array
    {
        return [
            [
                'id' => 1,
                'athlete_id' => '3149391',
                'name' => "A'ja Wilson",
                'team_id' => '1',
                'team_abbreviation' => 'LAS',
                'position' => 'F',
                'avg_points' => 24.5
            ],
            [
                'id' => 2,
                'athlete_id' => '4066261',
                'name' => 'Breanna Stewart',
                'team_id' => '2',
                'team_abbreviation' => 'NY',
                'position' => 'F',
                'avg_points' => 22.8
            ],
            [
                'id' => 3,
                'athlete_id' => '4277956',
                'name' => 'Sabrina Ionescu',
                'team_id' => '2',
                'team_abbreviation' => 'NY',
                'position' => 'G',
                'avg_points' => 18.2
            ],
            [
                'id' => 4,
                'athlete_id' => '4066262',
                'name' => 'Kelsey Plum',
                'team_id' => '1',
                'team_abbreviation' => 'LAS',
                'position' => 'G',
                'avg_points' => 17.8
            ],
            [
                'id' => 5,
                'athlete_id' => '4066263',
                'name' => 'Alyssa Thomas',
                'team_id' => '3',
                'team_abbreviation' => 'CONN',
                'position' => 'F',
                'avg_points' => 16.5
            ],
            [
                'id' => 6,
                'athlete_id' => '4066264',
                'name' => 'Napheesa Collier',
                'team_id' => '4',
                'team_abbreviation' => 'MIN',
                'position' => 'F',
                'avg_points' => 21.2
            ]
        ];
    }

    /**
     * Get teams for a specific game
     */
    private function getTeamsForGame(string $gameId): array
    {
        try {
            // First get the numeric game ID from the games table
            $game = DB::table('wnba_games')
                ->where('game_id', $gameId)
                ->select('id')
                ->first();

            if (!$game) {
                Log::warning('Game not found', ['game_id' => $gameId]);
                return [
                    'home_team_id' => null,
                    'away_team_id' => null,
                    'home_team' => 'Unknown',
                    'away_team' => 'Unknown'
                ];
            }

            $gameTeams = DB::table('wnba_game_teams')
                ->where('game_id', $game->id)
                ->select(['team_id', 'home_away'])
                ->get();

            $homeTeamId = null;
            $awayTeamId = null;
            $homeTeamName = 'Unknown';
            $awayTeamName = 'Unknown';

            foreach ($gameTeams as $gameTeam) {
                if ($gameTeam->home_away === 'home') {
                    $homeTeamId = $gameTeam->team_id;
                    $homeTeamName = $this->getTeamName($gameTeam->team_id);
                } else {
                    $awayTeamId = $gameTeam->team_id;
                    $awayTeamName = $this->getTeamName($gameTeam->team_id);
                }
            }

            return [
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'home_team' => $homeTeamName,
                'away_team' => $awayTeamName
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get teams for game', [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);

            return [
                'home_team_id' => null,
                'away_team_id' => null,
                'home_team' => 'Unknown',
                'away_team' => 'Unknown'
            ];
        }
    }

    /**
     * Format game time for display
     */
    private function formatGameTime(string $gameTime): string
    {
        return date('g:i A', strtotime($gameTime)) . ' ET';
    }

    /**
     * Get matchup difficulty
     */
    private function getMatchupDifficulty(array $player, array $game): string
    {
        // Simplified - would analyze opponent's defensive stats
        return ['favorable', 'neutral', 'difficult'][rand(0, 2)];
    }

    /**
     * Get betting value category
     */
    private function getBettingValue(float $expectedValue): string
    {
        if ($expectedValue >= 15) return 'excellent';
        if ($expectedValue >= 10) return 'good';
        if ($expectedValue >= 5) return 'fair';
        return 'poor';
    }

    /**
     * Generate reasoning for the prop
     */
    private function generateReasoning(array $player, array $stats, array $prediction): string
    {
        $reasons = [];

        if ($stats['recent_average'] > $stats['season_average']) {
            $reasons[] = 'Strong recent form (' . $stats['recent_average'] . ' avg last 5)';
        }

        if ($prediction['confidence'] > 70) {
            $reasons[] = 'High confidence prediction';
        }

        $reasons[] = 'Favorable matchup expected';

        return implode(', ', $reasons);
    }

    /**
     * Generate a single prediction for a player's stat line using real odds data
     */
    public function generatePrediction(Request $request)
    {
        try {
            $validated = $request->validate([
                'player_id' => 'required|string',
                'stat' => 'required|string',
                'line' => 'required|numeric|min:0'
            ]);

            // Get player information
            $player = DB::table('wnba_players')
                ->where('athlete_id', $validated['player_id'])
                ->select([
                    'id',
                    'athlete_id',
                    'athlete_display_name as name',
                    'athlete_position_abbreviation as position'
                ])
                ->first();

            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Use cached prediction data that incorporates real odds
            $cacheKey = "odds_prediction_{$player->id}_{$validated['stat']}_{$validated['line']}";

            $predictionData = Cache::remember($cacheKey, 900, function() use ($player, $validated) {
                try {
                    // Get real betting lines from The Odds API first
                    $realOddsData = $this->getRealBettingLines($player->name, $validated['stat']);

                    // Use the real line if available, otherwise use the provided line
                    $actualLine = $realOddsData['line'] ?? $validated['line'];

                    // Since we don't have a real game ID, create a mock one for prediction purposes
                    $mockGameId = 1; // This could be improved to use an actual upcoming game

                    // Use the PropsPredictionService with the appropriate method for each stat type
                    $prediction = match($validated['stat']) {
                        'points' => $this->propsPrediction->predictPoints($player->id, $mockGameId, $actualLine),
                        'rebounds' => $this->propsPrediction->predictRebounds($player->id, $mockGameId, $actualLine),
                        'assists' => $this->propsPrediction->predictAssists($player->id, $mockGameId, $actualLine),
                        'steals' => $this->propsPrediction->predictSteals($player->id, $mockGameId, $actualLine),
                        'blocks' => $this->propsPrediction->predictBlocks($player->id, $mockGameId, $actualLine),
                        'three_point_field_goals_made' => $this->propsPrediction->predictThreePointersMade($player->id, $mockGameId, $actualLine),
                        'field_goals_made' => $this->propsPrediction->predictFieldGoalsMade($player->id, $mockGameId, $actualLine),
                        'free_throws_made' => $this->propsPrediction->predictFreeThrowsMade($player->id, $mockGameId, $actualLine),
                        'turnovers' => $this->propsPrediction->predictTurnovers($player->id, $mockGameId, $actualLine),
                        'minutes' => $this->propsPrediction->predictMinutesPlayed($player->id, $mockGameId, $actualLine),
                        default => $this->propsPrediction->predictProp($player->id, $mockGameId, $validated['stat'], $actualLine)
                    };

                    // Enhance prediction with real odds data
                    $prediction['odds_data'] = $realOddsData;
                    $prediction['actual_line'] = $actualLine;
                    $prediction['line_source'] = $realOddsData['available'] ? 'odds_api' : 'estimated';

                    return $prediction;

                } catch (\Exception $e) {
                    Log::warning('Failed to use prediction services with odds data, falling back to simple calculation', [
                        'player_id' => $player->id,
                        'stat' => $validated['stat'],
                        'error' => $e->getMessage()
                    ]);

                    // Fallback to the existing simple calculation if services fail
                    $recentStats = $this->getPlayerRecentStats($player->id, $validated['stat']);
                    $fallbackPrediction = $this->generateMockPrediction([
                        'id' => $player->id,
                        'athlete_id' => $player->athlete_id,
                        'name' => $player->name,
                        'position' => $player->position ?? 'G'
                    ], $validated['stat'], array_merge($recentStats, [
                        'suggested_line' => $validated['line']
                    ]));

                    // Add odds data to fallback - always use fallback odds since real API failed
                    $fallbackOdds = $this->getFallbackOdds($validated['stat']);
                    $fallbackPrediction['odds_data'] = $fallbackOdds;
                    $fallbackPrediction['actual_line'] = $validated['line']; // Use original line since no real odds
                    $fallbackPrediction['line_source'] = 'estimated';

                    return $fallbackPrediction;
                }
            });

            // Extract prediction values from the cached data
            // The PropsPredictionService returns a different structure, so we need to adapt
            $predictedValue = $predictionData['prediction']['adjusted_value'] ?? $predictionData['prediction']['predicted_value'] ?? $predictionData['predicted_value'] ?? $validated['line'];
            $confidence = $predictionData['prediction']['confidence'] ?? $predictionData['prediction']['confidence_score'] ?? $predictionData['confidence'] ?? 0.75;
            $probabilityOver = $predictionData['probabilities']['over'] ?? $predictionData['prediction']['over_probability'] ?? $predictionData['probability_over'] ?? 0.5;
            $probabilityUnder = $predictionData['probabilities']['under'] ?? $predictionData['prediction']['under_probability'] ?? $predictionData['probability_under'] ?? 0.5;

            // Get odds data - always fetch if not included in prediction data
            $oddsData = $predictionData['odds_data'] ?? [];

            // Check if odds data is empty, null, or invalid
            $needsOddsData = empty($oddsData) ||
                           $oddsData === null ||
                           !isset($oddsData['over_odds']) ||
                           $oddsData['over_odds'] === null ||
                           !isset($oddsData['available']);

            if ($needsOddsData) {
                // Fetch odds data if not included in prediction
                $oddsData = $this->getRealBettingLines($player->name, $validated['stat']);
                if (empty($oddsData) || !isset($oddsData['available']) || !$oddsData['available']) {
                    // Use fallback odds if real odds not available
                    $oddsData = $this->getFallbackOdds($validated['stat']);
                }
            }

            $actualLine = $predictionData['actual_line'] ?? $oddsData['line'] ?? $validated['line'];
            $lineSource = $predictionData['line_source'] ?? ($oddsData['available'] ? 'odds_api' : 'estimated');

            // Ensure values are in correct format
            if ($confidence > 1) {
                $confidence = $confidence / 100; // Convert percentage to decimal
            }
            if ($probabilityOver > 1) {
                $probabilityOver = $probabilityOver / 100;
                $probabilityUnder = $probabilityUnder / 100;
            }

            // Calculate expected value using real odds if available
            $expectedValue = $this->calculateExpectedValueWithRealOdds($probabilityOver, $confidence, $oddsData);

            // Determine recommendation based on predicted value vs actual line
            $recommendation = 'avoid';
            if ($predictedValue > $actualLine && $probabilityOver > 0.55) {
                $recommendation = 'over';
            } elseif ($predictedValue < $actualLine && $probabilityUnder > 0.55) {
                $recommendation = 'under';
            }

            // Generate reasoning that includes odds information
            $reasoning = $this->generateReasoningWithOddsData(
                $predictionData,
                $validated['stat'],
                $predictedValue,
                $actualLine,
                $oddsData
            );

            // Format response
            $response = [
                'player_id' => $validated['player_id'],
                'player_name' => $player->name,
                'player_position' => $player->position ?? 'G',
                'stat' => $validated['stat'],
                'line' => $actualLine, // Use actual line from odds API
                'original_line' => $validated['line'], // Keep original for reference
                'predicted_value' => round($predictedValue * 2) / 2, // Round to .5 increments
                'confidence' => round($confidence, 3),
                'probability_over' => round($probabilityOver, 3),
                'probability_under' => round($probabilityUnder, 3),
                'recommendation' => $recommendation,
                'expected_value' => round($expectedValue, 3),
                'reasoning' => $reasoning,
                'data_source' => 'cached_prediction_engine_with_odds',
                'line_source' => $lineSource,
                'odds_data' => $oddsData,
                'created_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate prediction with odds data', [
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate prediction'
            ], 500);
        }
    }

    /**
     * Calculate expected value from prediction probability and confidence
     */
    private function calculateExpectedValueFromPrediction(float $probabilityOver, float $confidence): float
    {
        // Market implied probability for -110 odds
        $marketImpliedProbability = 0.5238;

        // Calculate edge based on our probability estimate
        $edge = $probabilityOver - $marketImpliedProbability;

        // Weight the edge by confidence
        $weightedEdge = $edge * $confidence;

        // Scale to realistic EV range (-10% to +15%)
        $expectedValue = $weightedEdge * 20; // Scale factor

        // Clamp to reasonable bounds
        return max(-10, min(15, $expectedValue));
    }

    /**
     * Generate reasoning from cached prediction data
     */
    private function generateReasoningFromCachedData(array $predictionData, string $statType, float $predictedValue, float $line): string
    {
        $reasons = [];

        // Check if we have detailed prediction data
        if (isset($predictionData['factors'])) {
            $factors = $predictionData['factors'];

            if (isset($factors['recent_form']) && $factors['recent_form'] > $factors['season_average']) {
                $reasons[] = "Strong recent form in {$statType}";
            }

            if (isset($factors['consistency_score']) && $factors['consistency_score'] > 0.7) {
                $reasons[] = "High consistency in {$statType}";
            }

            if (isset($factors['matchup_advantage']) && $factors['matchup_advantage'] > 0) {
                $reasons[] = "Favorable matchup";
            }
        }

        // Add confidence-based reasoning
        $confidence = $predictionData['confidence'] ?? 0.75;
        if ($confidence > 0.8) {
            $reasons[] = "High confidence prediction";
        } elseif ($confidence > 0.65) {
            $reasons[] = "Moderate confidence prediction";
        }

        // Add prediction vs line reasoning
        $difference = abs($predictedValue - $line);
        if ($difference > 2) {
            $direction = $predictedValue > $line ? 'above' : 'below';
            $reasons[] = "Prediction significantly {$direction} betting line";
        }

        // Default reasoning if no specific factors found
        if (empty($reasons)) {
            $reasons[] = "Based on comprehensive statistical analysis";
            $reasons[] = "Using cached prediction engine data";
        }

        return implode(', ', $reasons);
    }

    /**
     * Calculate expected value using real odds
     */
    private function calculateExpectedValueWithRealOdds(float $probabilityOver, float $confidence, array $oddsData): float
    {
        // If we have real odds data, use it for more accurate EV calculation
        if (!empty($oddsData) && isset($oddsData['over_odds']) && $oddsData['available']) {
            // Convert American odds to implied probability
            $overOdds = $oddsData['over_odds'];
            $underOdds = $oddsData['under_odds'];

            // Calculate implied probability from odds
            $impliedProbabilityOver = $this->americanOddsToImpliedProbability($overOdds);
            $impliedProbabilityUnder = $this->americanOddsToImpliedProbability($underOdds);

            // Calculate edge (our probability - market probability)
            $edgeOver = $probabilityOver - $impliedProbabilityOver;
            $edgeUnder = (1 - $probabilityOver) - $impliedProbabilityUnder;

            // Use the better edge
            $bestEdge = max($edgeOver, $edgeUnder);

            // Weight by confidence
            $expectedValue = $bestEdge * $confidence * 100; // Convert to percentage

            return max(-15, min(20, $expectedValue)); // Clamp to realistic bounds
        }

        // Fallback to standard calculation if no real odds
        return $this->calculateExpectedValueFromPrediction($probabilityOver, $confidence);
    }

    /**
     * Convert American odds to implied probability
     */
    private function americanOddsToImpliedProbability(int $americanOdds): float
    {
        if ($americanOdds > 0) {
            // Positive odds: implied probability = 100 / (odds + 100)
            return 100 / ($americanOdds + 100);
        } else {
            // Negative odds: implied probability = |odds| / (|odds| + 100)
            return abs($americanOdds) / (abs($americanOdds) + 100);
        }
    }

    /**
     * Generate reasoning with odds data
     */
    private function generateReasoningWithOddsData(array $predictionData, string $statType, float $predictedValue, float $line, array $oddsData): string
    {
        $reasons = [];

        // Check if we have detailed prediction data
        if (isset($predictionData['factors'])) {
            $factors = $predictionData['factors'];

            if (isset($factors['recent_form']) && $factors['recent_form'] > ($factors['season_average'] ?? 0)) {
                $reasons[] = "Strong recent form in {$statType}";
            }

            if (isset($factors['consistency_score']) && $factors['consistency_score'] > 0.7) {
                $reasons[] = "High consistency in {$statType}";
            }

            if (isset($factors['matchup_advantage']) && $factors['matchup_advantage'] > 0) {
                $reasons[] = "Favorable matchup";
            }
        }

        // Add confidence-based reasoning
        $confidence = $predictionData['prediction']['confidence_score'] ?? $predictionData['confidence'] ?? 0.75;
        if ($confidence > 0.8) {
            $reasons[] = "High confidence prediction";
        } elseif ($confidence > 0.65) {
            $reasons[] = "Moderate confidence prediction";
        }

        // Add prediction vs line reasoning
        $difference = abs($predictedValue - $line);
        if ($difference > 2) {
            $direction = $predictedValue > $line ? 'above' : 'below';
            $reasons[] = "Prediction significantly {$direction} betting line";
        }

        // Add odds data reasoning
        if (!empty($oddsData) && $oddsData['available']) {
            $reasons[] = "Using real betting lines from sportsbooks";
            if (isset($oddsData['bookmaker_over'])) {
                $reasons[] = "Line from {$oddsData['bookmaker_over']}";
            }
        } else {
            $reasons[] = "Using estimated betting line";
        }

        // Default reasoning if no specific factors found
        if (empty($reasons)) {
            $reasons[] = "Based on comprehensive statistical analysis";
            $reasons[] = "Using cached prediction engine with odds data";
        }

        return implode(', ', $reasons);
    }

    /**
     * Get fallback odds when real odds are not available
     */
    private function getFallbackOdds(string $statType): array
    {
        // Generate realistic odds based on stat type
        $baseOdds = match($statType) {
            'points' => [-115, -105],
            'rebounds' => [-110, -110],
            'assists' => [-108, -112],
            'three_point_field_goals_made' => [-120, -100],
            'steals' => [-125, -105],
            'blocks' => [-130, -100],
            default => [-110, -110]
        };

        return [
            'line' => null,
            'over_odds' => $baseOdds[0],
            'under_odds' => $baseOdds[1],
            'available' => false,
            'source' => 'fallback',
            'bookmaker_over' => 'Estimated',
            'bookmaker_under' => 'Estimated',
            'last_update' => now()->toISOString(),
            'event_id' => null,
            'commence_time' => null
        ];
    }

    /**
     * Generate mock prediction with realistic values using real odds
     */
    private function generateMockPrediction(array $player, string $statType, array $recentStats): array
    {
        $baseValue = $recentStats['recent_average'];

        // Add some variance based on stat type and player performance
        $variance = match($statType) {
            'points' => rand(-3, 4),
            'rebounds' => rand(-2, 3),
            'assists' => rand(-2, 2),
            default => rand(-1, 2)
        };

        $predictedValue = max(0.5, $baseValue + $variance);
        $predictedValue = round($predictedValue * 2) / 2; // Round to .5 increments

        // Calculate probability based on how much higher/lower than line
        $lineDiff = $predictedValue - $recentStats['suggested_line'];
        $probabilityOver = 50 + ($lineDiff * 5); // Reduced from 8 to 5 for less extreme probabilities
        $probabilityOver = max(35, min(70, $probabilityOver)); // Keep between 35-70% instead of 20-80%

        // Generate more realistic confidence levels (55-75% instead of 65-85%)
        $baseConfidence = 60;
        $confidenceVariance = rand(-5, 15); // -5 to +15
        $confidence = max(55, min(75, $baseConfidence + $confidenceVariance));

        return [
            'predicted_value' => $predictedValue,
            'confidence' => $confidence,
            'probability_over' => $probabilityOver,
            'probability_under' => 100 - $probabilityOver,
        ];
    }

    /**
     * Get real betting lines from The Odds API
     */
    private function getRealBettingLines(string $playerName, string $statType): array
    {
        try {
            // Map our stat types to The Odds API stat types
            $oddsApiStatType = $this->mapStatTypeToOddsApi($statType);

            // Try to get player odds using the correct method signature
            $playerOdds = $this->oddsApi->getPlayerOdds($playerName, $statType, 'basketball_wnba');

            if ($playerOdds && !empty($playerOdds)) {
                return [
                    'line' => $playerOdds['line'] ?? null,
                    'over_odds' => $playerOdds['over_odds'] ?? -110,
                    'under_odds' => $playerOdds['under_odds'] ?? -110,
                    'available' => true,
                    'source' => 'odds_api',
                    'bookmaker_over' => $playerOdds['over_bookmaker'] ?? 'Multiple',
                    'bookmaker_under' => $playerOdds['under_bookmaker'] ?? 'Multiple',
                    'last_update' => now()->toISOString(),
                    'event_id' => $playerOdds['event_id'] ?? null,
                    'commence_time' => $playerOdds['commence_time'] ?? null,
                    'total_bookmakers' => 1
                ];
            }

            Log::info('No odds found for player from Odds API', [
                'player' => $playerName,
                'stat_type' => $statType,
                'mapped_stat' => $oddsApiStatType
            ]);

            return $this->getFallbackOdds($statType);

        } catch (\Exception $e) {
            Log::warning('Failed to fetch odds from Odds API', [
                'player' => $playerName,
                'stat_type' => $statType,
                'error' => $e->getMessage()
            ]);

            return $this->getFallbackOdds($statType);
        }
    }

    /**
     * Map our internal stat types to The Odds API stat types
     */
    private function mapStatTypeToOddsApi(string $statType): string
    {
        $mapping = [
            'points' => 'player_points',
            'rebounds' => 'player_rebounds',
            'assists' => 'player_assists',
            'steals' => 'player_steals',
            'blocks' => 'player_blocks',
            'three_point_field_goals_made' => 'player_threes',
            'field_goals_made' => 'player_field_goals',
            'free_throws_made' => 'player_free_throws',
            'turnovers' => 'player_turnovers',
            'minutes' => 'player_minutes'
        ];

        return $mapping[$statType] ?? $statType;
    }

    /**
     * Get player's recent statistics
     */
    private function getPlayerRecentStats(int $playerId, string $statType): array
    {
        try {
            // Try to get real stats from database using athlete_id
            $recentStats = DB::table('wnba_player_games')
                ->where('player_id', $playerId) // This should be athlete_id
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get();

            if ($recentStats->count() > 0) {
                $statValues = [];
                foreach ($recentStats as $game) {
                    switch ($statType) {
                        case 'points':
                            $statValues[] = $game->points ?? 0;
                            break;
                        case 'rebounds':
                            $statValues[] = $game->rebounds ?? 0;
                            break;
                        case 'assists':
                            $statValues[] = $game->assists ?? 0;
                            break;
                        case 'steals':
                            $statValues[] = $game->steals ?? 0;
                            break;
                        case 'blocks':
                            $statValues[] = $game->blocks ?? 0;
                            break;
                    }
                }

                $recentAvg = array_sum($statValues) / count($statValues);

                // Get season average
                $seasonStats = DB::table('wnba_player_games')
                    ->where('player_id', $playerId)
                    ->selectRaw("AVG({$statType}) as season_avg")
                    ->first();

                $seasonAvg = $seasonStats->season_avg ?? $recentAvg;

                // Generate suggested line based on averages
                $suggestedLine = round(($recentAvg + $seasonAvg) / 2 * 2) / 2; // Round to .5

                return [
                    'recent_average' => round($recentAvg, 1),
                    'season_average' => round($seasonAvg, 1),
                    'suggested_line' => max(0.5, $suggestedLine)
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get real player stats', [
                'player_id' => $playerId,
                'stat_type' => $statType,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to mock data if real stats not available
        $mockStats = [
            'points' => ['recent_average' => 22.5, 'season_average' => 21.8, 'suggested_line' => 21.5],
            'rebounds' => ['recent_average' => 8.2, 'season_average' => 7.9, 'suggested_line' => 8.0],
            'assists' => ['recent_average' => 5.8, 'season_average' => 5.5, 'suggested_line' => 5.5],
            'steals' => ['recent_average' => 1.2, 'season_average' => 1.1, 'suggested_line' => 1.0],
            'blocks' => ['recent_average' => 0.8, 'season_average' => 0.7, 'suggested_line' => 0.5]
        ];

        return $mockStats[$statType] ?? ['recent_average' => 10.0, 'season_average' => 9.5, 'suggested_line' => 9.5];
    }

    /**
     * Get opponent team abbreviation
     */
    private function getOpponent(int $teamId, array $game): string
    {
        if (!isset($game['home_team_id']) || !isset($game['away_team_id'])) {
            return 'vs TBD';
        }

        if ($teamId == $game['home_team_id']) {
            return 'vs ' . $this->getTeamAbbreviation($game['away_team_id']);
        } else {
            return '@ ' . $this->getTeamAbbreviation($game['home_team_id']);
        }
    }

    /**
     * Get team abbreviation by ID
     */
    private function getTeamAbbreviation(?int $teamId): string
    {
        if ($teamId === null) {
            return 'UNK';
        }

        try {
            $team = DB::table('wnba_teams')
                ->where('team_id', $teamId)
                ->select('team_abbreviation')
                ->first();

            return $team ? $team->team_abbreviation : 'UNK';
        } catch (\Exception $e) {
            Log::warning('Failed to get team abbreviation', [
                'team_id' => $teamId,
                'error' => $e->getMessage()
            ]);
            return 'UNK';
        }
    }

    /**
     * Get team name by ID from database
     */
    private function getTeamName(string $teamId): string
    {
        try {
            $team = DB::table('wnba_teams')
                ->where('team_id', $teamId)
                ->select('team_display_name', 'team_name')
                ->first();

            return $team ? ($team->team_display_name ?? $team->team_name) : 'Unknown Team';
        } catch (\Exception $e) {
            Log::warning('Failed to get team name', [
                'team_id' => $teamId,
                'error' => $e->getMessage()
            ]);
            return 'Unknown Team';
        }
    }

    /**
     * Get team abbreviation by ID from database
     */
    private function getTeamAbbreviationFromId(string $teamId): string
    {
        try {
            $team = DB::table('wnba_teams')
                ->where('team_id', $teamId)
                ->select('team_abbreviation')
                ->first();

            return $team ? $team->team_abbreviation : 'UNK';
        } catch (\Exception $e) {
            Log::warning('Failed to get team abbreviation', [
                'team_id' => $teamId,
                'error' => $e->getMessage()
            ]);
            return 'UNK';
        }
    }
}
