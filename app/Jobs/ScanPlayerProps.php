<?php

namespace App\Jobs;

use App\Services\WNBA\Predictions\PropsPredictionService;
use App\Services\WNBA\Predictions\BettingRecommendationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScanPlayerProps implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $gameId;
    private array $props;
    private string $cacheKey;

    /**
     * Create a new job instance.
     */
    public function __construct(int $gameId, array $props)
    {
        $this->gameId = $gameId;
        $this->props = $props;
        $this->cacheKey = "prop_scan:{$gameId}";
    }

    /**
     * Execute the job.
     */
    public function handle(
        PropsPredictionService $predictionService,
        BettingRecommendationService $recommendationService
    ): void {
        try {
            Log::info('Starting prop scan', [
                'game_id' => $this->gameId,
                'props_count' => count($this->props)
            ]);

            $results = [];
            foreach ($this->props as $prop) {
                try {
                    // Get prediction
                    $prediction = $predictionService->predict(
                        $prop['player_id'],
                        $this->gameId,
                        $prop['stat_type'],
                        $prop['line_value']
                    );

                    // Get betting recommendation
                    $recommendation = $recommendationService->getRecommendation(
                        $prop['player_id'],
                        $prop['stat_type'],
                        $prop['line_value'],
                        $prop['odds_over'] ?? null,
                        $prop['odds_under'] ?? null,
                        $this->gameId
                    );

                    $results[] = [
                        'player_id' => $prop['player_id'],
                        'stat_type' => $prop['stat_type'],
                        'line_value' => $prop['line_value'],
                        'prediction' => $prediction,
                        'recommendation' => $recommendation
                    ];

                } catch (\Exception $e) {
                    Log::error('Error processing prop', [
                        'game_id' => $this->gameId,
                        'player_id' => $prop['player_id'],
                        'stat_type' => $prop['stat_type'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Cache results
            Cache::put($this->cacheKey, $results, now()->addHours(24));

            Log::info('Prop scan completed', [
                'game_id' => $this->gameId,
                'results_count' => count($results)
            ]);

        } catch (\Exception $e) {
            Log::error('Prop scan failed', [
                'game_id' => $this->gameId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Prop scan job failed', [
            'game_id' => $this->gameId,
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Get the cache key for the scan results.
     */
    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }
}
