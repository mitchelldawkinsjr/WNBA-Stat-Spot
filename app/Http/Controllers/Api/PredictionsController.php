<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WNBA\Predictions\PropsPredictionService;
use App\Services\WNBA\Predictions\StatisticalEngineService;
use App\Services\WNBA\Analytics\PlayerAnalyticsService;
use App\Services\WNBA\Analytics\TeamAnalyticsService;
use App\Services\WNBA\Analytics\GameAnalyticsService;
use App\Services\WNBA\Analytics\BettingAnalyticsService;
use App\Services\WNBA\Data\DataAggregatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PredictionsController extends Controller
{
    private PropsPredictionService $propsPrediction;
    private StatisticalEngineService $statisticalEngine;
    private PlayerAnalyticsService $playerAnalytics;
    private TeamAnalyticsService $teamAnalytics;
    private GameAnalyticsService $gameAnalytics;
    private BettingAnalyticsService $bettingAnalytics;
    private DataAggregatorService $dataAggregator;

    public function __construct(
        PropsPredictionService $propsPrediction,
        StatisticalEngineService $statisticalEngine,
        PlayerAnalyticsService $playerAnalytics,
        TeamAnalyticsService $teamAnalytics,
        GameAnalyticsService $gameAnalytics,
        BettingAnalyticsService $bettingAnalytics,
        DataAggregatorService $dataAggregator
    ) {
        $this->propsPrediction = $propsPrediction;
        $this->statisticalEngine = $statisticalEngine;
        $this->playerAnalytics = $playerAnalytics;
        $this->teamAnalytics = $teamAnalytics;
        $this->gameAnalytics = $gameAnalytics;
        $this->bettingAnalytics = $bettingAnalytics;
        $this->dataAggregator = $dataAggregator;
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

            $recommendation = $this->bettingAnalytics->getRecommendation(
                $validated['player_id'],
                $validated['game_id'],
                $validated['prop_type'],
                $validated['line_value'],
                $validated['odds_over'] ?? null,
                $validated['odds_under'] ?? null
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
     * Warm up prediction cache
     */
    public function warmCache()
    {
        try {
            // Implement cache warming logic here
            // This would pre-compute and cache common predictions

            return response()->json([
                'status' => 'success',
                'message' => 'Cache warming initiated'
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
}
