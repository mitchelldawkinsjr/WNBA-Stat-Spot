<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class HealthController extends Controller
{
    /**
     * Basic health check endpoint
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env')
        ]);
    }

    /**
     * Detailed health check with all system components
     */
    public function detailed(): JsonResponse
    {
        $startTime = microtime(true);

        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage()
        ];

        $overallStatus = $this->determineOverallStatus($checks);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'response_time_ms' => $responseTime,
            'timestamp' => now()->toISOString(),
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0')
        ], $overallStatus === 'ok' ? 200 : 503);
    }

    /**
     * Database-specific health check
     */
    public function database(): JsonResponse
    {
        $result = $this->checkDatabase();

        return response()->json([
            'status' => $result['status'],
            'details' => $result,
            'timestamp' => now()->toISOString()
        ], $result['status'] === 'ok' ? 200 : 503);
    }

    /**
     * Cache/Redis-specific health check
     */
    public function cache(): JsonResponse
    {
        $result = $this->checkCache();

        return response()->json([
            'status' => $result['status'],
            'details' => $result,
            'timestamp' => now()->toISOString()
        ], $result['status'] === 'ok' ? 200 : 503);
    }

    /**
     * Queue system health check
     */
    public function queue(): JsonResponse
    {
        $result = $this->checkQueue();

        return response()->json([
            'status' => $result['status'],
            'details' => $result,
            'timestamp' => now()->toISOString()
        ], $result['status'] === 'ok' ? 200 : 503);
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);

            // Test basic connection
            $pdo = DB::connection()->getPdo();
            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Test query performance
            $startTime = microtime(true);
            $result = DB::select('SELECT 1 as test_query');
            $queryTime = round((microtime(true) - $startTime) * 1000, 2);

            // Check if essential tables exist
            $tables = [
                'users' => DB::getSchemaBuilder()->hasTable('users'),
                'wnba_players' => DB::getSchemaBuilder()->hasTable('wnba_players'),
                'wnba_teams' => DB::getSchemaBuilder()->hasTable('wnba_teams'),
                'wnba_games' => DB::getSchemaBuilder()->hasTable('wnba_games'),
                'jobs' => DB::getSchemaBuilder()->hasTable('jobs'),
                'failed_jobs' => DB::getSchemaBuilder()->hasTable('failed_jobs')
            ];

            // Get database stats
            $stats = [
                'players_count' => $tables['wnba_players'] ? DB::table('wnba_players')->count() : 0,
                'teams_count' => $tables['wnba_teams'] ? DB::table('wnba_teams')->count() : 0,
                'games_count' => $tables['wnba_games'] ? DB::table('wnba_games')->count() : 0,
                'pending_jobs' => $tables['jobs'] ? DB::table('jobs')->count() : 0,
                'failed_jobs' => $tables['failed_jobs'] ? DB::table('failed_jobs')->count() : 0
            ];

            return [
                'status' => 'ok',
                'connection_time_ms' => $connectionTime,
                'query_time_ms' => $queryTime,
                'driver' => config('database.default'),
                'tables' => $tables,
                'stats' => $stats,
                'message' => 'Database is healthy'
            ];

        } catch (Exception $e) {
            Log::error('Database health check failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Database connection failed'
            ];
        }
    }

    /**
     * Check cache system (Redis) connectivity and performance
     */
    private function checkCache(): array
    {
        try {
            $startTime = microtime(true);

            // Test cache write/read
            $testKey = 'health_check_' . time();
            $testValue = 'test_data_' . uniqid();

            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            $writeReadTime = round((microtime(true) - $startTime) * 1000, 2);

            // Clean up test key
            Cache::forget($testKey);

            if ($retrieved !== $testValue) {
                throw new Exception('Cache write/read test failed');
            }

            // Get Redis info if using Redis
            $info = [];
            if (config('cache.default') === 'redis') {
                try {
                    $redis = Cache::getStore()->getRedis();
                    $redisInfo = $redis->info();
                    $info = [
                        'version' => $redisInfo['redis_version'] ?? 'unknown',
                        'connected_clients' => $redisInfo['connected_clients'] ?? 'unknown',
                        'used_memory_human' => $redisInfo['used_memory_human'] ?? 'unknown',
                        'total_commands_processed' => $redisInfo['total_commands_processed'] ?? 'unknown'
                    ];
                } catch (Exception $e) {
                    $info['error'] = 'Could not retrieve Redis info';
                }
            }

            return [
                'status' => 'ok',
                'driver' => config('cache.default'),
                'write_read_time_ms' => $writeReadTime,
                'info' => $info,
                'message' => 'Cache system is healthy'
            ];

        } catch (Exception $e) {
            Log::error('Cache health check failed', [
                'error' => $e->getMessage(),
                'driver' => config('cache.default')
            ]);

            return [
                'status' => 'unhealthy',
                'driver' => config('cache.default'),
                'error' => $e->getMessage(),
                'message' => 'Cache system is not working'
            ];
        }
    }

    /**
     * Check queue system health
     */
    private function checkQueue(): array
    {
        try {
            $stats = [
                'default_connection' => config('queue.default'),
                'connections' => []
            ];

            // Check each configured queue connection
            $connections = config('queue.connections', []);
            foreach ($connections as $name => $config) {
                try {
                    if ($name === config('queue.default')) {
                        // For the default connection, try to get queue size
                        if (DB::getSchemaBuilder()->hasTable('jobs')) {
                            $pendingJobs = DB::table('jobs')->count();
                            $failedJobs = DB::getSchemaBuilder()->hasTable('failed_jobs')
                                ? DB::table('failed_jobs')->count()
                                : 0;

                            $stats['connections'][$name] = [
                                'status' => 'ok',
                                'driver' => $config['driver'],
                                'pending_jobs' => $pendingJobs,
                                'failed_jobs' => $failedJobs
                            ];
                        } else {
                            $stats['connections'][$name] = [
                                'status' => 'warning',
                                'driver' => $config['driver'],
                                'message' => 'Queue tables not found'
                            ];
                        }
                    } else {
                        $stats['connections'][$name] = [
                            'status' => 'configured',
                            'driver' => $config['driver']
                        ];
                    }
                } catch (Exception $e) {
                    $stats['connections'][$name] = [
                        'status' => 'error',
                        'driver' => $config['driver'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Determine overall queue status
            $hasErrors = collect($stats['connections'])->contains('status', 'error');
            $overallStatus = $hasErrors ? 'degraded' : 'ok';

            return [
                'status' => $overallStatus,
                'stats' => $stats,
                'message' => $overallStatus === 'ok' ? 'Queue system is healthy' : 'Queue system has issues'
            ];

        } catch (Exception $e) {
            Log::error('Queue health check failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Queue system check failed'
            ];
        }
    }

    /**
     * Check storage system health
     */
    private function checkStorage(): array
    {
        try {
            $disks = config('filesystems.disks', []);
            $results = [];

            foreach ($disks as $name => $config) {
                try {
                    $disk = Storage::disk($name);

                    // Test write/read for local disks
                    if ($config['driver'] === 'local') {
                        $testFile = 'health_check_' . time() . '.txt';
                        $testContent = 'health check test';

                        $disk->put($testFile, $testContent);
                        $retrieved = $disk->get($testFile);
                        $disk->delete($testFile);

                        $results[$name] = [
                            'status' => $retrieved === $testContent ? 'ok' : 'error',
                            'driver' => $config['driver'],
                            'writable' => true
                        ];
                    } else {
                        // For other drivers, just check if the disk is accessible
                        $results[$name] = [
                            'status' => 'ok',
                            'driver' => $config['driver'],
                            'accessible' => true
                        ];
                    }
                } catch (Exception $e) {
                    $results[$name] = [
                        'status' => 'error',
                        'driver' => $config['driver'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $hasErrors = collect($results)->contains('status', 'error');
            $overallStatus = $hasErrors ? 'degraded' : 'ok';

            return [
                'status' => $overallStatus,
                'disks' => $results,
                'message' => 'Storage system checked'
            ];

        } catch (Exception $e) {
            Log::error('Storage health check failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Storage system check failed'
            ];
        }
    }

    /**
     * Determine overall system status based on individual checks
     */
    private function determineOverallStatus(array $checks): string
    {
        $statuses = collect($checks)->pluck('status');

        if ($statuses->contains('unhealthy')) {
            return 'unhealthy';
        }

        if ($statuses->contains('degraded')) {
            return 'degraded';
        }

        if ($statuses->contains('warning')) {
            return 'warning';
        }

        return 'ok';
    }
}
