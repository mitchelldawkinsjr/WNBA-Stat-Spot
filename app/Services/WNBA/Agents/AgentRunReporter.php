<?php

namespace App\Services\WNBA\Agents;

use App\Models\WnbaAgentRun;
use Illuminate\Support\Str;

/**
 * Creates and updates the structured run summary (wnba_agent_runs) for every
 * agent invocation, whether triggered by cron, artisan, or the admin API.
 */
class AgentRunReporter
{
    private ?WnbaAgentRun $run = null;

    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<int, string> */
    private array $errors = [];

    /**
     * @param  array{season?: int|null, date_from?: string|null, date_to?: string|null, dry_run?: bool}  $params
     */
    public function start(string $agent, string $mode, array $params = []): WnbaAgentRun
    {
        $this->counters = [];
        $this->warnings = [];
        $this->errors = [];

        $this->run = WnbaAgentRun::create([
            'run_uuid' => (string) Str::uuid(),
            'agent' => $agent,
            'mode' => $mode,
            'status' => 'running',
            'season' => $params['season'] ?? null,
            'date_from' => $params['date_from'] ?? null,
            'date_to' => $params['date_to'] ?? null,
            'dry_run' => (bool) ($params['dry_run'] ?? false),
            'started_at' => now(),
        ]);

        return $this->run;
    }

    public function increment(string $counter, int $by = 1): void
    {
        $this->counters[$counter] = ($this->counters[$counter] ?? 0) + $by;
    }

    public function set(string $counter, int $value): void
    {
        $this->counters[$counter] = $value;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }

    public function runId(): ?int
    {
        return $this->run?->id;
    }

    public function finish(?string $status = null): WnbaAgentRun
    {
        if ($this->run === null) {
            throw new \LogicException('AgentRunReporter::finish() called before start().');
        }

        $status ??= $this->errors === []
            ? ($this->warnings === [] ? 'success' : 'partial')
            : ($this->counters === [] ? 'failed' : 'partial');

        $this->run->update([
            'status' => $status,
            'counters' => $this->counters,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'completed_at' => now(),
        ]);

        return $this->run->refresh();
    }
}
