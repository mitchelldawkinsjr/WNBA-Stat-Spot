<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WNBA\Data\DataAggregatorService;
use App\Services\WnbaDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DataAggregatorController extends Controller
{
    public function __construct(
        private DataAggregatorService $dataAggregator,
        private WnbaDataService $wnbaDataService
    ) {}

    /**
     * Import all WNBA data (incremental update)
     */
    public function importData(Request $request): JsonResponse
    {
        try {
            Log::info('Starting WNBA data import via API');

            // Run the import command
            $exitCode = Artisan::call('app:import-wnba-data');

            if ($exitCode === 0) {
                $summary = $this->getDataSummaryArray();

                return response()->json([
                    'success' => true,
                    'message' => 'WNBA data imported successfully',
                    'data' => $summary,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Import failed with exit code: '.$exitCode,
                    'error' => Artisan::output(),
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('WNBA data import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force import all WNBA data (overwrites existing)
     */
    public function forceImportData(Request $request): JsonResponse
    {
        try {
            Log::info('Starting forced WNBA data import via API');

            // Run the import command with force flag
            $exitCode = Artisan::call('app:import-wnba-data', ['--force' => true]);

            if ($exitCode === 0) {
                $summary = $this->getDataSummaryArray();

                return response()->json([
                    'success' => true,
                    'message' => 'WNBA data force imported successfully',
                    'data' => $summary,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Force import failed with exit code: '.$exitCode,
                    'error' => Artisan::output(),
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('WNBA data force import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Force import failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get import status and data summary
     */
    public function getImportStatus(): JsonResponse
    {
        try {
            $summary = $this->getDataSummaryArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'ready',
                    'last_updated' => now()->toISOString(),
                    'summary' => $summary,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get import status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get import status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get data summary
     */
    public function getDataSummary(): JsonResponse
    {
        try {
            $summary = $this->getDataSummaryArray();

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get data summary', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get data summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import teams data only
     */
    public function importTeams(): JsonResponse
    {
        try {
            Log::info('Starting teams data import via API');

            $teamSchedulePath = $this->wnbaDataService->downloadTeamScheduleData();
            $teamScheduleData = $this->wnbaDataService->parseTeamScheduleData($teamSchedulePath);
            $this->wnbaDataService->saveTeamScheduleData($teamScheduleData);

            $teamCount = DB::table('wnba_teams')->count();

            return response()->json([
                'success' => true,
                'message' => 'Teams data imported successfully',
                'data' => [
                    'teams_imported' => $teamCount,
                    'imported_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Teams data import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Teams import failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import games data only
     */
    public function importGames(): JsonResponse
    {
        try {
            Log::info('Starting games data import via API');

            $teamSchedulePath = $this->wnbaDataService->downloadTeamScheduleData();
            $teamScheduleData = $this->wnbaDataService->parseTeamScheduleData($teamSchedulePath);
            $this->wnbaDataService->saveTeamScheduleData($teamScheduleData);

            $gameCount = DB::table('wnba_games')->count();

            return response()->json([
                'success' => true,
                'message' => 'Games data imported successfully',
                'data' => [
                    'games_imported' => $gameCount,
                    'imported_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Games data import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Games import failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import player stats data only
     */
    public function importPlayerStats(): JsonResponse
    {
        try {
            Log::info('Starting player stats data import via API');

            // Import box score data for comprehensive player stats and player syncing.
            $boxScorePath = $this->wnbaDataService->downloadBoxScoreData();
            $boxScoreData = $this->wnbaDataService->parseBoxScoreData($boxScorePath);
            $this->wnbaDataService->saveBoxScoreData($boxScoreData);

            $playerCount = DB::table('wnba_players')->count();
            $statsCount = DB::table('wnba_player_games')->count();

            return response()->json([
                'success' => true,
                'message' => 'Player stats data imported successfully',
                'data' => [
                    'players_imported' => $playerCount,
                    'player_stats_imported' => $statsCount,
                    'imported_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Player stats data import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Player stats import failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import players data only
     */
    public function importPlayers(): JsonResponse
    {
        try {
            Log::info('Starting players data import via API');

            // Players are imported as part of box score data
            $boxScorePath = $this->wnbaDataService->downloadBoxScoreData();
            $boxScoreData = $this->wnbaDataService->parseBoxScoreData($boxScorePath);
            $this->wnbaDataService->saveBoxScoreData($boxScoreData);

            $playerCount = DB::table('wnba_players')->count();

            return response()->json([
                'success' => true,
                'message' => 'Players data imported successfully',
                'data' => [
                    'players_imported' => $playerCount,
                    'imported_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Players data import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Players import failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper method to get data summary as array
     */
    private function getDataSummaryArray(): array
    {
        return [
            'teams' => DB::table('wnba_teams')->count(),
            'players' => DB::table('wnba_players')->count(),
            'games' => DB::table('wnba_games')->count(),
            'player_stats' => DB::table('wnba_player_games')->count(),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get aggregated player data
     */
    public function getPlayerData(Request $request, int $playerId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'season' => 'nullable|integer',
            'last_n_games' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Convert athlete_id to internal player_id if needed
            $player = \App\Models\WnbaPlayer::where('athlete_id', (string) $playerId)->first();

            if (! $player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found',
                ], 404);
            }

            $internalPlayerId = $player->id;
            $season = $request->input('season') ?? (int) config('wnba.seasons.current_season');
            $lastNGames = $request->input('last_n_games');

            $data = $this->dataAggregator->aggregatePlayerData($internalPlayerId, $season, $lastNGames);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Player data aggregation failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to aggregate player data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get aggregated team data
     */
    public function getTeamData(Request $request, int $teamId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'season' => 'nullable|integer',
            'last_n_games' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $season = $request->input('season') ?? (int) config('wnba.seasons.current_season');
            $lastNGames = $request->input('last_n_games');

            $data = $this->dataAggregator->aggregateTeamData($teamId, $season, $lastNGames);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Team data aggregation failed', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to aggregate team data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get aggregated game data
     */
    public function getGameData(Request $request, int $gameId): JsonResponse
    {
        try {
            $data = $this->dataAggregator->aggregateGameData($gameId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Game data aggregation failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to aggregate game data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get aggregated matchup data
     */
    public function getMatchupData(Request $request, int $team1Id, int $team2Id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'season' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $season = $request->input('season') ?? (int) config('wnba.seasons.current_season');

            $data = $this->dataAggregator->aggregateMatchupData($team1Id, $team2Id, $season);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Matchup data aggregation failed', [
                'team1_id' => $team1Id,
                'team2_id' => $team2Id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to aggregate matchup data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get aggregated league data
     */
    public function getLeagueData(Request $request, int $season): JsonResponse
    {
        try {
            $data = $this->dataAggregator->aggregateLeagueData($season);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('League data aggregation failed', [
                'season' => $season,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to aggregate league data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get aggregated prop data for betting analysis
     */
    public function getPropData(Request $request, int $playerId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stat_type' => 'required|string|in:points,rebounds,assists,steals,blocks,turnovers,minutes,field_goals_made,field_goals_attempted,three_point_field_goals_made,three_point_field_goals_attempted,free_throws_made,free_throws_attempted',
            'season' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Convert athlete_id to internal player_id if needed
            $player = \App\Models\WnbaPlayer::where('athlete_id', (string) $playerId)->first();

            if (! $player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found',
                ], 404);
            }

            $internalPlayerId = $player->id;
            $statType = $request->input('stat_type');
            $season = $request->input('season') ?? (int) config('wnba.seasons.current_season');

            $data = $this->dataAggregator->aggregatePropData($internalPlayerId, $statType, $season);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Prop data aggregation failed', [
                'player_id' => $playerId,
                'stat_type' => $request->input('stat_type'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to aggregate prop data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
