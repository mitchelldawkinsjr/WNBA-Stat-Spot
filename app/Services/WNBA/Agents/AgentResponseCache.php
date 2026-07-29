<?php

namespace App\Services\WNBA\Agents;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Flushes Laravel response caches after agent writes so schedule, box,
 * preview, and aggregate endpoints do not keep serving pre-run snapshots.
 */
class AgentResponseCache
{
    public static function clear(string $reason): bool
    {
        try {
            Artisan::call('cache:clear');
            Log::info('Agent response cache cleared', ['reason' => $reason]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Agent response cache clear failed', [
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Targeted bust for live score freshness without a full cache:clear.
     */
    public static function forgetGameListCaches(int $season): void
    {
        try {
            Cache::forget("game_list_{$season}_live");
            Cache::forget("game_list_{$season}_db");
        } catch (\Throwable $e) {
            Log::warning('Failed to forget game list caches', [
                'season' => $season,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
