<?php

namespace App\Jobs;

use App\Services\WNBA\Agents\AgentResponseCache;
use App\Services\WNBA\Agents\AgentRunReporter;
use App\Services\WNBA\Agents\AggregateComputationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAnalyticsAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * @param  array{mode?: string, season?: int|null, dry_run?: bool}  $params
     */
    public function __construct(
        public array $params = [],
    ) {
        $this->onQueue(config('wnba.agents.queue', 'default'));
    }

    public function handle(AggregateComputationService $service, AgentRunReporter $reporter): void
    {
        $season = isset($this->params['season']) ? (int) $this->params['season'] : (int) config('wnba.seasons.current_season');

        $reporter->start('analytics', $this->params['mode'] ?? 'incremental', [
            'season' => $season,
            'dry_run' => (bool) ($this->params['dry_run'] ?? false),
        ]);
        $service->setReporter($reporter);

        if ($this->params['dry_run'] ?? false) {
            $reporter->warn("dry run: would recompute aggregates for season {$season}");
            $reporter->finish('success');

            return;
        }

        try {
            $service->computeSeason($season);
            $run = $reporter->finish();

            Log::info('Analytics agent run finished', [
                'run_uuid' => $run->run_uuid,
                'status' => $run->status,
                'counters' => $run->counters,
            ]);
        } catch (\Throwable $e) {
            $reporter->error($e->getMessage());
            $reporter->finish('failed');
            throw $e;
        }

        // Aggregates changed; clear response caches so the API serves fresh data.
        AgentResponseCache::clear('analytics_agent');

        // After finals/boxes are ingested and aggregates refresh, grade yesterday's
        // (and any other pending) game-score + prop picks for the accuracy board.
        GradePredictionsJob::dispatch();
    }
}
