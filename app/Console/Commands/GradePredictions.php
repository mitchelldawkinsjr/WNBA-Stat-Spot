<?php

namespace App\Console\Commands;

use App\Models\GameScorePrediction;
use App\Models\TrackedPropPrediction;
use App\Services\WNBA\Predictions\PredictionAccuracyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GradePredictions extends Command
{
    protected $signature = 'app:grade-predictions';

    protected $description = 'Grade pending game score and prop predictions against final game results';

    public function handle(PredictionAccuracyService $accuracyService): int
    {
        $pendingGames = GameScorePrediction::query()->ungraded()->count();
        $pendingProps = TrackedPropPrediction::query()->ungraded()->count();

        if ($pendingGames === 0 && $pendingProps === 0) {
            $this->info('No pending predictions to grade.');

            return self::SUCCESS;
        }

        $this->info("Grading pending predictions ({$pendingGames} game scores, {$pendingProps} props)...");

        $result = $accuracyService->gradePendingPredictions();

        $this->info(sprintf(
            'Graded %d game score predictions and %d prop predictions.',
            $result['game_scores_graded'],
            $result['props_graded']
        ));

        if ($result['game_scores_graded'] > 0 || $result['props_graded'] > 0) {
            Log::info('Prediction grading run complete', $result);
        }

        return self::SUCCESS;
    }
}
