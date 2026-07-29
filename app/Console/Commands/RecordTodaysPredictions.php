<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\PredictionsController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecordTodaysPredictions extends Command
{
    protected $signature = 'app:record-todays-predictions {--timezone=America/New_York}';

    protected $description = "Record today's prop and game-score predictions with feature snapshots for feedback grading";

    public function handle(PredictionsController $controller): int
    {
        $timezone = (string) $this->option('timezone');
        $cacheKey = PredictionsController::todaysPropsCacheKey($timezone);

        $this->info("Recording today's predictions for timezone {$timezone}...");

        try {
            // Bust the warm cache so generation + tracking always run for the
            // morning slate capture (tracking also runs on cache hits via API).
            Cache::forget($cacheKey);

            $response = $controller->getTodaysBestProps(new Request([
                'timezone' => $timezone,
                'record' => true,
            ]));
            $payload = $response->getData(true);
            $count = is_array($payload['data'] ?? null) ? count($payload['data']) : 0;

            $this->info("Recorded today's slate ({$count} props in response).");
            Log::info('Recorded today\'s predictions', [
                'timezone' => $timezone,
                'prop_count' => $count,
                'cache_key' => $cacheKey,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to record today\'s predictions: '.$e->getMessage());
            Log::error('Failed to record today\'s predictions', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
