<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\DataAggregatorController;
use App\Http\Controllers\Api\PropScannerController;
use App\Http\Controllers\WnbaPredictionsController;
use App\Http\Controllers\Api\BettingAnalyticsController;
use App\Http\Controllers\Api\DataQualityController;
use App\Http\Controllers\Api\PredictionTestingController;
use App\Http\Controllers\Api\PredictionsController;
use App\Http\Controllers\Api\OddsController;
use App\Http\Controllers\Api\HealthController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Simple test endpoint (no database required)
Route::get('/test', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is working',
        'timestamp' => now()->toISOString(),
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version()
    ]);
});

// Health check endpoints for CI/CD monitoring
Route::get('/health', [HealthController::class, 'health']);
Route::get('/health/detailed', [HealthController::class, 'detailed']);
Route::get('/health/database', [HealthController::class, 'database']);
Route::get('/health/cache', [HealthController::class, 'cache']);
Route::get('/health/queue', [HealthController::class, 'queue']);

// Database setup status endpoint
Route::get('/status', function () {
    try {
        $status = [
            'api' => 'ok',
            'database_connected' => false,
            'migrations_table' => false,
            'wnba_tables' => [
                'wnba_players' => false,
                'wnba_teams' => false,
                'wnba_games' => false,
                'wnba_player_games' => false,
            ],
            'queue_tables' => [
                'jobs' => false,
                'failed_jobs' => false,
                'job_batches' => false,
            ],
            'setup_complete' => false,
            'message' => 'Checking database status...'
        ];

        // Test database connection
        try {
            DB::connection()->getPdo();
            $status['database_connected'] = true;
        } catch (\Exception $e) {
            $status['message'] = 'Database connection failed: ' . $e->getMessage();
            return response()->json($status, 503);
        }

        // Check if migrations table exists
        try {
            DB::table('migrations')->count();
            $status['migrations_table'] = true;
        } catch (\Exception $e) {
            $status['message'] = 'Migrations table not found. Database setup in progress...';
            return response()->json($status, 503);
        }

        // Check WNBA tables
        foreach ($status['wnba_tables'] as $table => $exists) {
            try {
                $status['wnba_tables'][$table] = Schema::hasTable($table);
            } catch (\Exception $e) {
                // Table check failed
            }
        }

        // Check queue tables
        foreach ($status['queue_tables'] as $table => $exists) {
            try {
                $status['queue_tables'][$table] = Schema::hasTable($table);
            } catch (\Exception $e) {
                // Table check failed
            }
        }

        // Determine if setup is complete
        $wnbaTablesReady = array_sum($status['wnba_tables']) >= 2; // At least players and teams
        $queueTablesReady = array_sum($status['queue_tables']) >= 2; // At least jobs and failed_jobs

        $status['setup_complete'] = $status['database_connected'] &&
                                   $status['migrations_table'] &&
                                   $wnbaTablesReady &&
                                   $queueTablesReady;

        if ($status['setup_complete']) {
            $status['message'] = 'Database setup complete. All systems ready.';
        } else {
            $status['message'] = 'Database setup in progress. Please wait...';
        }

        return response()->json($status, $status['setup_complete'] ? 200 : 503);

    } catch (\Exception $e) {
        return response()->json([
            'api' => 'ok',
            'database_connected' => false,
            'setup_complete' => false,
            'message' => 'Status check failed: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ], 503);
    }
});

// Basic data endpoints
Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/summary', [TeamController::class, 'summary']);
Route::get('/teams/{teamId}', [TeamController::class, 'show']);
Route::get('/teams/{teamId}/players', [TeamController::class, 'players']);
Route::post('/teams/clear-cache', [TeamController::class, 'clearCache']);

Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/summary', [PlayerController::class, 'summary']);
Route::post('/players/clear-cache', [PlayerController::class, 'clearCache']);
Route::get('/players/{id}', [PlayerController::class, 'show']);

Route::get('/games', [GameController::class, 'index']);
Route::get('/stats', [StatsController::class, 'index']);

// WNBA Analytics and Predictions API Routes
Route::prefix('wnba')->group(function () {
    // Predictions
    Route::prefix('predictions')->group(function () {
        Route::post('/generate', [PredictionsController::class, 'generatePrediction']);
        Route::get('/prop-bets', [WnbaPredictionsController::class, 'getPropBets']);
        Route::get('/todays-best', [PredictionsController::class, 'getTodaysBestProps']);
    });

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/player/{playerId}', [PredictionsController::class, 'getPlayerAnalytics']);
        Route::get('/team/{teamId}', [PredictionsController::class, 'getTeamAnalytics']);
        Route::get('/game/{gameId}', [PredictionsController::class, 'getGameAnalytics']);
    });

    // Data Import/Update Routes
    Route::prefix('data')->group(function () {
        Route::post('/import', [DataAggregatorController::class, 'importData']);
        Route::post('/import/force', [DataAggregatorController::class, 'forceImportData']);
        Route::get('/import/status', [DataAggregatorController::class, 'getImportStatus']);
        Route::get('/stats/summary', [DataAggregatorController::class, 'getDataSummary']);

        // Individual data type imports
        Route::post('/import/teams', [DataAggregatorController::class, 'importTeams']);
        Route::post('/import/players', [DataAggregatorController::class, 'importPlayers']);
        Route::post('/import/games', [DataAggregatorController::class, 'importGames']);
        Route::post('/import/stats', [DataAggregatorController::class, 'importPlayerStats']);

        Route::get('/players/{playerId}', [DataAggregatorController::class, 'getPlayerData']);
        Route::get('/players/{playerId}/props', [DataAggregatorController::class, 'getPropData']);
        Route::get('/teams/{teamId}', [DataAggregatorController::class, 'getTeamData']);
        Route::get('/games/{gameId}', [DataAggregatorController::class, 'getGameData']);
        Route::get('/matchups/{team1Id}/{team2Id}', [DataAggregatorController::class, 'getMatchupData']);
        Route::get('/league/{season}', [DataAggregatorController::class, 'getLeagueData']);
    });

    // Cache Management
    Route::prefix('cache')->group(function () {
        Route::get('/stats', [PredictionsController::class, 'getCacheStats']);
        Route::post('/clear', [PredictionsController::class, 'clearCache']);
        Route::post('/warm', [PredictionsController::class, 'warmCache']);
    });

    // Prop Scanner
    Route::prefix('prop-scanner')->group(function () {
        Route::get('/scan-all', [PropScannerController::class, 'scanAll']);
        Route::get('/scan-player/{playerId}', [PropScannerController::class, 'scanPlayer']);
        Route::get('/player/{playerId}', [PropScannerController::class, 'scanPlayer']);
        Route::get('/game/{gameId}', [PropScannerController::class, 'scanGame']);
        Route::post('/scan', [PropScannerController::class, 'scan']);
        Route::get('/results/{gameId}', [PropScannerController::class, 'getResults']);
        Route::get('/status/{gameId}', [PropScannerController::class, 'getStatus']);
    });

    // Betting Analytics
    Route::prefix('betting')->group(function () {
        Route::get('/analytics', [BettingAnalyticsController::class, 'getAnalytics']);
    });

    // Data Quality
    Route::prefix('quality')->group(function () {
        Route::get('/metrics', [DataQualityController::class, 'getMetrics']);
    });

    // Testing & Validation
    Route::prefix('testing')->group(function () {
        Route::post('/player-accuracy', [PredictionTestingController::class, 'testPlayerAccuracy']);
        Route::post('/bulk-testing', [PredictionTestingController::class, 'runBulkTesting']);
        Route::get('/historical', [PredictionTestingController::class, 'getHistoricalTests']);
        Route::post('/historical/start', [PredictionTestingController::class, 'startHistoricalTesting']);
        Route::get('/historical/results', [PredictionTestingController::class, 'getHistoricalResults']);
        Route::get('/historical/status', [PredictionTestingController::class, 'getTestingStatus']);
        Route::get('/historical/leaderboard', [PredictionTestingController::class, 'getLeaderboard']);
    });
});

// The Odds API Routes
Route::prefix('odds')->group(function () {
    Route::get('/sports', [OddsController::class, 'getSports']);
    Route::get('/wnba', [OddsController::class, 'getWnbaOdds']);
    Route::get('/wnba/events', [OddsController::class, 'getWnbaEvents']);

    // Configuration and Testing
    Route::get('/test-config', [OddsController::class, 'testConfiguration']);

    // Cache Management
    Route::post('/clear-cache', [OddsController::class, 'clearCache']);
    Route::get('/cache-status', [OddsController::class, 'getCacheStatus']);
    Route::post('/force-refresh', [OddsController::class, 'forceRefresh']);

    // Usage Statistics and Rate Limiting
    Route::get('/usage', [OddsController::class, 'getUsageStats']);

    // Live Odds (with aggressive caching)
    Route::get('/live', [OddsController::class, 'getLiveOdds']);

    // Player Props Routes (based on The Odds API documentation)
    Route::get('/wnba/props', [OddsController::class, 'getWnbaPlayerProps']);
    Route::get('/wnba/props/markets', [OddsController::class, 'getPlayerPropMarkets']);
    Route::get('/wnba/props/best', [OddsController::class, 'getBestPlayerPropOdds']);
    Route::get('/wnba/props/analysis', [OddsController::class, 'getPlayerPropsAnalysis']);
    Route::get('/wnba/events/{eventId}/props', [OddsController::class, 'getEventPlayerProps']);
});
