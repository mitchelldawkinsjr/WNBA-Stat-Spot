<?php

namespace App\Console\Commands;

use App\Services\WNBA\Predictions\PredictionFeedbackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunPredictionFeedback extends Command
{
    protected $signature = 'app:run-prediction-feedback';

    protected $description = 'Calibrate probabilities, tune gates/weights, and promote a challenger when holdout improves';

    public function handle(PredictionFeedbackService $feedback): int
    {
        $this->info('Running prediction feedback loop...');

        $result = $feedback->run();

        $this->info(sprintf(
            'Feedback run %s finished with status=%s promoted=%s sample_size=%s',
            $result['run_uuid'] ?? 'n/a',
            $result['status'] ?? 'n/a',
            ! empty($result['promoted']) ? 'yes' : 'no',
            $result['sample_size'] ?? 0
        ));

        Log::info('Prediction feedback run complete', $result);

        return self::SUCCESS;
    }
}
