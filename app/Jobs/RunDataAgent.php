<?php

namespace App\Jobs;

use App\Services\WNBA\Agents\AgentResponseCache;
use App\Services\WNBA\Agents\DataAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunDataAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  array{mode?: string, season?: int|null, dry_run?: bool, with_pbp?: bool|null, chain?: bool}  $params
     */
    public function __construct(
        public array $params = [],
    ) {
        $this->onQueue(config('wnba.agents.queue', 'default'));
    }

    public function handle(DataAgentService $agent): void
    {
        $run = $agent->run($this->params);

        Log::info('Data agent run finished', [
            'run_uuid' => $run->run_uuid,
            'status' => $run->status,
            'counters' => $run->counters,
        ]);

        $dryRun = (bool) ($this->params['dry_run'] ?? false);

        // Schedule/box/injury rows changed; clear before entity/analytics finish
        // so the API does not keep serving pre-ingest snapshots for hours.
        if (! $dryRun && $run->status !== 'failed') {
            AgentResponseCache::clear('data_agent');
        }

        // Change-driven chaining: audit touched entities, then recompute
        // aggregates. Skipped for dry runs and when explicitly disabled.
        $chain = $this->params['chain'] ?? true;
        if ($chain && ! $dryRun && $run->status !== 'failed') {
            $season = $this->params['season'] ?? null;
            RunEntityAgent::dispatch(['mode' => 'audit', 'season' => $season, 'chain' => true]);
        }
    }
}
