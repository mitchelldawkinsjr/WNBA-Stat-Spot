<?php

namespace App\Jobs;

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

        // Change-driven chaining: audit touched entities, then recompute
        // aggregates. Skipped for dry runs and when explicitly disabled.
        $chain = $this->params['chain'] ?? true;
        if ($chain && ! ($this->params['dry_run'] ?? false) && $run->status !== 'failed') {
            $season = $this->params['season'] ?? null;
            RunEntityAgent::dispatch(['mode' => 'audit', 'season' => $season, 'chain' => true]);
        }
    }
}
