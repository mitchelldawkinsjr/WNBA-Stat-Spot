<?php

namespace App\Jobs;

use App\Services\WNBA\Predictions\PredictionAccuracyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Grade pending game-score and prop predictions against final box scores.
 * Dispatched after the nightly analytics agent so finals from the data agent
 * are available before accuracy is computed.
 */
class GradePredictionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue(config('wnba.agents.queue', 'default'));
    }

    public function handle(PredictionAccuracyService $accuracy): void
    {
        $result = $accuracy->gradePendingPredictions();

        Log::info('Post-agent prediction grading complete', $result);
    }
}
