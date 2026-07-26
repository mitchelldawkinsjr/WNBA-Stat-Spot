<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScanPlayerProps;
use App\Models\WnbaPlayer;
use App\Models\WnbaGame;
use App\Services\Odds\OddsService;
use App\Services\WNBA\Predictions\PropLineResolver;
use App\Services\WNBA\Predictions\PropsPredictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PropScannerController extends Controller
{
    protected $propsPredictionService;

    protected OddsService $oddsApi;

    protected PropLineResolver $propLineResolver;

    public function __construct(
        PropsPredictionService $propsPredictionService,
        OddsService $oddsApi,
        PropLineResolver $propLineResolver,
    ) {
        $this->propsPredictionService = $propsPredictionService;
        $this->oddsApi = $oddsApi;
        $this->propLineResolver = $propLineResolver;
    }

    /**
     * Scan all available player props
     */
    public function scanAll()
    {
        try {
            $cacheKey = 'prop_scanner_results';

            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'success',
                    'source' => 'cache',
                    'data' => Cache::get($cacheKey)
                ]);
            }

            // Get upcoming games and generate prop predictions
            $games = WnbaGame::where('game_date', '>=', now())
                ->where('game_date', '<=', now()->addDays(7))
                ->limit(10)
                ->get();

            $results = [];

            foreach ($games as $game) {
                // Get players from recent games (since we don't have direct team relationships)
                $players = WnbaPlayer::limit(20)->get();

                foreach ($players as $player) {
                    $playerResults = $this->generatePlayerPropPredictions($player, $game);
                    $results = array_merge($results, $playerResults);
                }
            }

            // Cache results for 30 minutes
            Cache::put($cacheKey, $results, 1800);

            return response()->json([
                'status' => 'success',
                'source' => 'generated',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Prop scanner scan all failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'An error occurred while scanning props',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Scan props for a specific player
     */
    public function scanPlayer($playerId)
    {
        try {
            // Validate player ID
            if (!is_numeric($playerId)) {
                return response()->json([
                    'error' => 'Invalid player ID'
                ], 422);
            }

            $player = WnbaPlayer::where('athlete_id', $playerId)->first();

            if (!$player) {
                return response()->json([
                    'error' => 'Player not found'
                ], 404);
            }

            $cacheKey = "prop_scanner_player_{$playerId}";

            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'success',
                    'source' => 'cache',
                    'data' => Cache::get($cacheKey)
                ]);
            }

            // Get upcoming games
            $games = WnbaGame::where('game_date', '>=', now())
                ->where('game_date', '<=', now()->addDays(7))
                ->limit(5)
                ->get();

            $results = [];

            foreach ($games as $game) {
                $playerResults = $this->generatePlayerPropPredictions($player, $game);
                $results = array_merge($results, $playerResults);
            }

            // Cache results for 30 minutes
            Cache::put($cacheKey, $results, 1800);

            return response()->json([
                'status' => 'success',
                'source' => 'generated',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Prop scanner scan player failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'An error occurred while scanning player props',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Scan props for a specific game
     */
    public function scanGame($gameId)
    {
        try {
            $game = WnbaGame::find($gameId);

            if (!$game) {
                return response()->json([
                    'error' => 'Game not found'
                ], 404);
            }

            $cacheKey = "prop_scanner_game_{$gameId}";

            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'success',
                    'source' => 'cache',
                    'data' => Cache::get($cacheKey)
                ]);
            }

            // Get players from recent games
            $players = WnbaPlayer::limit(30)->get();

            $results = [];

            foreach ($players as $player) {
                $playerResults = $this->generatePlayerPropPredictions($player, $game);
                $results = array_merge($results, $playerResults);
            }

            // Cache results for 30 minutes
            Cache::put($cacheKey, $results, 1800);

            return response()->json([
                'status' => 'success',
                'source' => 'generated',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Prop scanner scan game failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'An error occurred while scanning game props',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate prop predictions for a player in a specific game
     */
    private function generatePlayerPropPredictions(WnbaPlayer $player, WnbaGame $game)
    {
        $results = [];
        $statTypes = ['points', 'rebounds', 'assists', 'steals', 'blocks'];
        $playerName = $player->athlete_display_name ?? $player->athlete_short_name ?? '';

        foreach ($statTypes as $statType) {
            $resolved = $this->resolveScannerLine($player, $playerName, $statType);
            $line = $resolved['line'];
            if ($line === null) {
                continue;
            }

            try {
                $prediction = match ($statType) {
                    'points' => $this->propsPredictionService->predictPoints($player->id, $game->id, $line),
                    'rebounds' => $this->propsPredictionService->predictRebounds($player->id, $game->id, $line),
                    'assists' => $this->propsPredictionService->predictAssists($player->id, $game->id, $line),
                    'steals' => $this->propsPredictionService->predictSteals($player->id, $game->id, $line),
                    'blocks' => $this->propsPredictionService->predictBlocks($player->id, $game->id, $line),
                    default => $this->propsPredictionService->predictProp($player->id, $game->id, $statType, $line)
                };

                $results[] = [
                    'player_id' => $player->athlete_id,
                    'player_name' => $playerName,
                    'team_id' => null,
                    'game_id' => $game->id,
                    'game_date' => $game->game_date,
                    'stat_type' => $statType,
                    'line_value' => $line,
                    'line_source' => $resolved['source'],
                    'odds_data' => $prediction['odds_data'] ?? $resolved['odds_data'],
                    'prediction' => [
                        'over_probability' => $prediction['prediction']['over_probability'] ?? 0.5,
                        'expected_value' => $prediction['prediction']['expected_value'] ?? 0,
                        'confidence' => $prediction['prediction']['confidence_score'] ?? 'medium',
                        'reasoning' => $prediction['reasoning'] ?? 'Based on recent performance',
                        'recent_average' => $prediction['recent_average'] ?? $line,
                        'season_average' => $prediction['season_average'] ?? $line,
                    ],
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to generate prediction for player prop', [
                    'player_id' => $player->athlete_id,
                    'stat_type' => $statType,
                    'line' => $line,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'player_id' => $player->athlete_id,
                    'player_name' => $playerName,
                    'team_id' => null,
                    'game_id' => $game->id,
                    'game_date' => $game->game_date,
                    'stat_type' => $statType,
                    'line_value' => $line,
                    'line_source' => $resolved['source'],
                    'odds_data' => $resolved['odds_data'],
                    'prediction' => [
                        'over_probability' => 0.5,
                        'expected_value' => 0,
                        'confidence' => 'low',
                        'reasoning' => 'Insufficient data for prediction',
                        'recent_average' => $line,
                        'season_average' => $line,
                    ],
                ];
            }
        }

        return $results;
    }

    /**
     * Prefer live book line; otherwise half-point round of recent/season average.
     *
     * @return array{line: float|null, source: string, odds_data: array<string, mixed>|null}
     */
    private function resolveScannerLine(WnbaPlayer $player, string $playerName, string $statType): array
    {
        $oddsPayload = null;
        $oddsData = ['available' => false, 'line' => null];

        try {
            $playerOdds = $this->oddsApi->getPlayerOdds($playerName, $statType, 'basketball_wnba');
            if ($playerOdds && isset($playerOdds['line']) && (float) $playerOdds['line'] > 0) {
                $oddsPayload = $playerOdds;
                $oddsData = [
                    'available' => true,
                    'line' => (float) $playerOdds['line'],
                ];
            }
        } catch (\Throwable $e) {
            Log::info('Prop scanner odds lookup failed', [
                'player' => $playerName,
                'stat_type' => $statType,
                'error' => $e->getMessage(),
            ]);
        }

        $recentStats = $this->estimatePlayerAverages($player->id, $statType);
        $resolved = $this->propLineResolver->resolve($oddsData, $recentStats);

        return [
            'line' => $resolved['line'],
            'source' => $resolved['source'],
            'odds_data' => $oddsPayload,
        ];
    }

    /**
     * @return array{recent_average?: float, season_average?: float, suggested_line?: float}
     */
    private function estimatePlayerAverages(int $playerId, string $statType): array
    {
        $numericStats = ['points', 'rebounds', 'assists', 'steals', 'blocks'];
        if (! in_array($statType, $numericStats, true)) {
            return [];
        }

        $season = (int) config('wnba.seasons.current_season');

        try {
            $recent = DB::table('wnba_player_games as pg')
                ->join('wnba_games as g', 'pg.game_id', '=', 'g.id')
                ->where('pg.player_id', $playerId)
                ->where('g.season', $season)
                ->orderByDesc('g.game_date')
                ->orderByDesc('g.id')
                ->limit(5)
                ->pluck('pg.'.$statType);

            if ($recent->isEmpty()) {
                return [];
            }

            $recentAvg = (float) $recent->avg();
            $seasonAvg = (float) (DB::table('wnba_player_games as pg')
                ->join('wnba_games as g', 'pg.game_id', '=', 'g.id')
                ->where('pg.player_id', $playerId)
                ->where('g.season', $season)
                ->avg('pg.'.$statType) ?? $recentAvg);

            return [
                'recent_average' => round($recentAvg, 1),
                'season_average' => round($seasonAvg, 1),
                'suggested_line' => max(0.5, round((($recentAvg + $seasonAvg) / 2) * 2) / 2),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Scan player props for a game
     */
    public function scan(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'game_id' => 'required|integer',
                'props' => 'required|array',
                'props.*.player_id' => 'required|integer',
                'props.*.stat_type' => 'required|string',
                'props.*.line_value' => 'required|numeric',
                'props.*.odds_over' => 'nullable|numeric',
                'props.*.odds_under' => 'nullable|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $gameId = $request->input('game_id');
            $props = $request->input('props');

            // Check cache first
            $cacheKey = "prop_scan:{$gameId}";
            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'success',
                    'source' => 'cache',
                    'data' => Cache::get($cacheKey)
                ]);
            }

            // Dispatch job
            ScanPlayerProps::dispatch($gameId, $props);

            return response()->json([
                'status' => 'success',
                'message' => 'Prop scan job dispatched',
                'job_id' => $gameId
            ]);

        } catch (\Exception $e) {
            Log::error('Prop scan request failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => 'An error occurred while processing your request',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scan results
     */
    public function getResults(int $gameId)
    {
        try {
            $cacheKey = "prop_scan:{$gameId}";

            if (!Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'pending',
                    'message' => 'Scan results not yet available'
                ], 202);
            }

            return response()->json([
                'status' => 'success',
                'source' => 'cache',
                'data' => Cache::get($cacheKey)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get scan results', [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'An error occurred while retrieving scan results',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scan status
     */
    public function getStatus(int $gameId)
    {
        try {
            $cacheKey = "prop_scan:{$gameId}";

            return response()->json([
                'status' => Cache::has($cacheKey) ? 'completed' : 'pending',
                'game_id' => $gameId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get scan status', [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'An error occurred while checking scan status',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
