<?php

namespace App\Console\Commands;

use App\Jobs\RunAnalyticsAgent;
use App\Jobs\RunDataAgent;
use App\Jobs\RunEntityAgent;
use App\Models\WnbaAgentRun;
use App\Services\WNBA\Agents\AgentRunReporter;
use App\Services\WNBA\Agents\AggregateComputationService;
use App\Services\WNBA\Agents\DataAgentService;
use App\Services\WNBA\Agents\EntityIntegrityService;
use Illuminate\Console\Command;

class RunWnbaAgent extends Command
{
    protected $signature = 'app:wnba-agent
                            {agent : Which agent to run (data|analytics|entity)}
                            {--mode=incremental : incremental | backfill | repair | audit | live}
                            {--season= : Season year (defaults to WNBA_CURRENT_SEASON; "all" audits every season)}
                            {--dry-run : Validate and report without writing canonical data}
                            {--no-pbp : Skip play-by-play ingestion (data agent only)}
                            {--no-chain : Do not chain entity/analytics agents after the data agent}
                            {--queue : Dispatch to the queue instead of running inline}';

    protected $description = 'Run a WNBA agent (data ingestion, analytics aggregation, or entity integrity audit)';

    public function handle(): int
    {
        $agent = strtolower((string) $this->argument('agent'));
        if (! in_array($agent, ['data', 'analytics', 'entity'], true)) {
            $this->error("Unknown agent [{$agent}]. Use data, analytics, or entity.");

            return 1;
        }

        $seasonOption = $this->option('season');
        $season = match (true) {
            $seasonOption === 'all' => null,
            $seasonOption !== null => (int) $seasonOption,
            default => (int) config('wnba.seasons.current_season'),
        };

        $params = [
            'mode' => (string) $this->option('mode'),
            'season' => $season,
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        if ($agent === 'data') {
            $params['chain'] = ! $this->option('no-chain');
            if ($this->option('no-pbp')) {
                $params['with_pbp'] = false;
            }
        }

        if ($this->option('queue')) {
            match ($agent) {
                'data' => RunDataAgent::dispatch($params),
                'analytics' => RunAnalyticsAgent::dispatch($params),
                'entity' => RunEntityAgent::dispatch($params),
            };
            $this->info("Queued {$agent} agent run (mode: {$params['mode']}, season: {$params['season']}).");

            return 0;
        }

        $run = $this->runInline($agent, $params);
        $this->renderRun($run);

        // Inline data-agent runs chain via queued jobs like scheduled runs do.
        if ($agent === 'data' && ($params['chain'] ?? false) && ! $params['dry_run'] && $run->status !== 'failed') {
            RunEntityAgent::dispatch(['mode' => 'audit', 'season' => $params['season'], 'chain' => true]);
            $this->info('Chained entity + analytics agent runs dispatched to the queue.');
        }

        return $run->status === 'failed' ? 1 : 0;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function runInline(string $agent, array $params): WnbaAgentRun
    {
        if ($agent === 'data') {
            return app(DataAgentService::class)->run($params);
        }

        $reporter = app(AgentRunReporter::class);
        $reporter->start($agent, (string) $params['mode'], $params);

        try {
            if ($agent === 'analytics') {
                $service = app(AggregateComputationService::class);
                $service->setReporter($reporter);
                if (! $params['dry_run']) {
                    $service->computeSeason((int) $params['season']);
                } else {
                    $reporter->warn('dry run: no aggregates recomputed');
                }
            } else {
                $service = app(EntityIntegrityService::class);
                $service->setReporter($reporter);
                $service->audit($params['season'] !== null ? (int) $params['season'] : null);
            }
        } catch (\Throwable $e) {
            $reporter->error($e->getMessage());

            return $reporter->finish('failed');
        }

        return $reporter->finish();
    }

    private function renderRun(WnbaAgentRun $run): void
    {
        $this->info("Run {$run->run_uuid} [{$run->agent}/{$run->mode}] finished: {$run->status}");

        foreach ($run->counters ?? [] as $counter => $value) {
            $this->line("  {$counter}: {$value}");
        }
        foreach ($run->warnings ?? [] as $warning) {
            $this->warn("  warning: {$warning}");
        }
        foreach ($run->errors ?? [] as $error) {
            $this->error("  error: {$error}");
        }
    }
}
