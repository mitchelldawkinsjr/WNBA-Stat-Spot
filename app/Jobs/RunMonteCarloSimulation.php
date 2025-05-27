<?php

namespace App\Jobs;

use App\Services\WNBA\Predictions\StatisticalEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunMonteCarloSimulation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $playerId;
    private int $gameId;
    private string $statType;
    private float $lineValue;
    private int $iterations;
    private string $cacheKey;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $playerId,
        int $gameId,
        string $statType,
        float $lineValue,
        int $iterations = 10000
    ) {
        $this->playerId = $playerId;
        $this->gameId = $gameId;
        $this->statType = $statType;
        $this->lineValue = $lineValue;
        $this->iterations = $iterations;
        $this->cacheKey = "monte_carlo:{$playerId}:{$gameId}:{$statType}:{$lineValue}";
    }

    /**
     * Execute the job.
     */
    public function handle(StatisticalEngineService $statisticalEngine): void
    {
        try {
            Log::info('Starting Monte Carlo simulation', [
                'player_id' => $this->playerId,
                'game_id' => $this->gameId,
                'stat_type' => $this->statType,
                'line_value' => $this->lineValue,
                'iterations' => $this->iterations
            ]);

            // Run simulation
            $results = $statisticalEngine->runMonteCarloSimulation(
                $this->playerId,
                $this->gameId,
                $this->statType,
                $this->lineValue,
                $this->iterations
            );

            // Cache results
            Cache::put($this->cacheKey, $results, now()->addHours(24));

            Log::info('Monte Carlo simulation completed', [
                'player_id' => $this->playerId,
                'game_id' => $this->gameId,
                'stat_type' => $this->statType,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Monte Carlo simulation failed', [
                'player_id' => $this->playerId,
                'game_id' => $this->gameId,
                'stat_type' => $this->statType,
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
        Log::error('Monte Carlo simulation job failed', [
            'player_id' => $this->playerId,
            'game_id' => $this->gameId,
            'stat_type' => $this->statType,
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Get the cache key for the simulation results.
     */
    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }
}
