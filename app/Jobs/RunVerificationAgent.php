<?php

namespace App\Jobs;

use App\Services\WNBA\Agents\AgentRunReporter;
use App\Services\WNBA\Agents\VerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunVerificationAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * @param  array{mode?: string, season?: int|null, dry_run?: bool, chain?: bool}  $params
     */
    public function __construct(
        public array $params = [],
    ) {
        $this->onQueue(config('wnba.agents.queue', 'default'));
    }

    public function handle(VerificationService $service, AgentRunReporter $reporter): void
    {
        $season = isset($this->params['season']) ? (int) $this->params['season'] : (int) config('wnba.seasons.current_season');
        $mode = $this->params['mode'] ?? 'audit';
        $dryRun = (bool) ($this->params['dry_run'] ?? false);
        $fullSeason = $mode === 'season';

        $reporter->start('verification', $mode, [
            'season' => $season,
            'dry_run' => $dryRun,
        ]);
        $service->setReporter($reporter);

        try {
            $findings = $service->verify($season, $fullSeason, $dryRun);
            $run = $reporter->finish();

            Log::info('Verification agent run finished', [
                'run_uuid' => $run->run_uuid,
                'status' => $run->status,
                'findings' => $findings,
            ]);
        } catch (\Throwable $e) {
            $reporter->error($e->getMessage());
            $reporter->finish('failed');
            throw $e;
        }

        // Analytics runs after verification so the nightly chain is
        // data → entity → verification → analytics.
        if (($this->params['chain'] ?? false) && ! $dryRun) {
            RunAnalyticsAgent::dispatch(['season' => $season]);
        }
    }
}
