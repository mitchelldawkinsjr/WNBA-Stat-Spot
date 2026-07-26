<?php

namespace App\Jobs;

use App\Services\WNBA\Agents\AgentRunReporter;
use App\Services\WNBA\Agents\EntityIntegrityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunEntityAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    /**
     * @param  array{mode?: string, season?: int|null, dry_run?: bool, chain?: bool}  $params
     */
    public function __construct(
        public array $params = [],
    ) {
        $this->onQueue(config('wnba.agents.queue', 'default'));
    }

    public function handle(EntityIntegrityService $service, AgentRunReporter $reporter): void
    {
        $season = isset($this->params['season']) ? (int) $this->params['season'] : (int) config('wnba.seasons.current_season');

        $reporter->start('entity', $this->params['mode'] ?? 'audit', [
            'season' => $season,
            'dry_run' => (bool) ($this->params['dry_run'] ?? false),
        ]);
        $service->setReporter($reporter);

        try {
            $findings = $service->audit($season);
            $run = $reporter->finish();

            Log::info('Entity integrity agent run finished', [
                'run_uuid' => $run->run_uuid,
                'status' => $run->status,
                'findings' => $findings,
            ]);
        } catch (\Throwable $e) {
            $reporter->error($e->getMessage());
            $reporter->finish('failed');
            throw $e;
        }

        // Analytics runs after the audit so freshly flagged identity conflicts
        // are excluded from aggregate recomputation.
        if (($this->params['chain'] ?? false) && ! ($this->params['dry_run'] ?? false)) {
            RunAnalyticsAgent::dispatch(['season' => $season]);
        }
    }
}
