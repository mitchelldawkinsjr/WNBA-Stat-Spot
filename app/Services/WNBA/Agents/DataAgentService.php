<?php

namespace App\Services\WNBA\Agents;

use App\Contracts\WnbaStatsProvider;
use App\Models\WnbaAgentRun;
use App\Models\WnbaInjuryReport;
use App\Models\WnbaOddsSnapshot;
use App\Models\WnbaPlayer;
use App\Services\Odds\Providers\Tank01OddsProvider;
use App\Services\RapidApi\Tank01UsageTracker;
use App\Services\WNBA\Data\EntityMergeService;
use App\Services\WNBA\Data\Providers\EspnWnbaProvider;
use App\Services\WNBA\Data\WnbaProviderResolver;
use App\Services\WnbaDataService;
use Illuminate\Support\Facades\Log;

/**
 * Data Agent orchestrator: ingests schedule, box scores, play-by-play,
 * injuries, and odds through the existing provider pipeline while adding raw
 * payload storage, lineage, validation, and conflict resolution. Produces a
 * structured run summary for every invocation.
 */
class DataAgentService
{
    public function __construct(
        private WnbaProviderResolver $resolver,
        private RawPayloadStore $rawPayloadStore,
        private AgentRunReporter $reporter,
        private ConflictResolver $conflictResolver,
    ) {}

    /**
     * @param  array{mode?: string, season?: int|null, dry_run?: bool, with_pbp?: bool|null}  $params
     */
    public function run(array $params = []): WnbaAgentRun
    {
        $mode = $params['mode'] ?? 'incremental';
        $season = (int) ($params['season'] ?? config('wnba.seasons.current_season'));
        $dryRun = (bool) ($params['dry_run'] ?? false);
        $withPbp = $params['with_pbp'] ?? (bool) config('wnba.agents.pbp_default', true);

        $this->reporter->start('data', $mode, ['season' => $season, 'dry_run' => $dryRun]);
        $this->conflictResolver->setReporter($this->reporter);

        $incremental = $mode !== 'backfill';
        $providerName = $this->resolver->resolveForImport($incremental)->name();

        if ($dryRun) {
            $this->reporter->warn("dry run: would ingest season {$season} via {$providerName}"
                .($withPbp ? ' plus play-by-play' : ''));

            return $this->reporter->finish('success');
        }

        $memoryLimit = (string) config('wnba.import.memory_limit', '512M');
        if ($memoryLimit !== '') {
            ini_set('memory_limit', $memoryLimit);
        }

        config(['wnba.data_source.provider' => $providerName]);
        app()->forgetInstance(WnbaStatsProvider::class);
        app()->forgetInstance(WnbaDataService::class);

        $service = app(WnbaDataService::class);
        $service->setImportOptions(['incremental' => $incremental, 'force' => false]);
        $service->setAgentContext($this->reporter, $this->conflictResolver);
        $this->reporter->set('sources_attempted', 1);

        $this->step('identity_sync', function () use ($season) {
            $merge = app(EntityMergeService::class);
            $this->reporter->increment('team_identities_synced', $merge->syncTeamMappings());
            $this->reporter->increment('player_identities_synced', $merge->syncPlayerMappings($season));
            $this->reporter->increment('game_identities_synced', $merge->syncGameMappings($season));
        });

        $this->step('schedule', function () use ($service, $season, $providerName) {
            if ($providerName === 'espn') {
                $count = $service->importEspnScheduleByTeam($season);
            } else {
                $records = $service->parseTeamScheduleData($service->downloadTeamScheduleData());
                $service->saveTeamScheduleData($records);
                $count = count($records);
            }
            $this->reporter->increment('schedule_records', $count);
        });

        $this->step('team_box_scores', function () use ($service) {
            if ($service->usesBatchedProviderImport()) {
                $result = $service->importTeamBoxScoresInBatches();
                $this->reporter->increment('team_box_records', $result['records_saved']);
            } else {
                $records = $service->parseTeamData($service->downloadTeamData());
                $service->saveTeamData($records);
                $this->reporter->increment('team_box_records', count($records));
            }
        });

        $this->step('player_box_scores', function () use ($service) {
            if ($service->usesBatchedProviderImport()) {
                $result = $service->importBoxScoresInBatches();
                $this->reporter->increment('player_box_records', $result['records_saved']);
            } else {
                $records = $service->parseBoxScoreData($service->downloadBoxScoreData());
                $service->saveBoxScoreData($records);
                $this->reporter->increment('player_box_records', count($records));
            }
        });

        if ($withPbp && $mode !== 'live') {
            $this->step('play_by_play', function () {
                $pbpProvider = $this->resolver->resolve('play_by_play');
                if (! $pbpProvider->supportsPlayByPlay()) {
                    $this->reporter->warn('configured play-by-play provider does not support PBP; skipped');

                    return;
                }

                $pbpService = new WnbaDataService($pbpProvider);
                $pbpService->setAgentContext($this->reporter, $this->conflictResolver);
                $records = $pbpService->parsePbpData($pbpService->downloadPbpData());
                $pbpService->savePbpData($records);
                $this->reporter->increment('pbp_records', count($records));
            });
        }

        if (config('wnba.agents.persist_injuries', true)) {
            $this->step('injuries', fn () => $this->persistInjuries());
        }

        if (config('wnba.agents.persist_odds', true)) {
            $this->step('odds', fn () => $this->persistOddsSnapshots());
        }

        $pruned = $this->rawPayloadStore->prune();
        if ($pruned > 0) {
            $this->reporter->increment('raw_payloads_pruned', $pruned);
        }

        $this->reporter->set('sources_succeeded', 1);

        return $this->reporter->finish();
    }

    private function step(string $name, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::error("Data agent step failed: {$name}", ['error' => $e->getMessage()]);
            $this->reporter->error("{$name}: {$e->getMessage()}");
        }
    }

    private function persistInjuries(): void
    {
        $payload = app(EspnWnbaProvider::class)->fetchLeagueInjuries();
        $stored = $this->rawPayloadStore->store('espn', 'injuries', $payload);

        if (! $stored['changed']) {
            $this->reporter->increment('injury_reports_unchanged');

            return;
        }

        foreach ($payload['teams'] ?? [] as $team) {
            foreach ($team['injuries'] ?? [] as $injury) {
                $athleteId = $injury['athlete_id'] ?? null;
                $status = $this->normalizeInjuryStatus((string) ($injury['status'] ?? ''));
                $reportedAt = $injury['date'] ?? now()->toIso8601String();

                $hash = hash('sha256', json_encode([
                    $athleteId, $status, $reportedAt, $injury['short_comment'] ?? null,
                ]));

                $existing = WnbaInjuryReport::query()
                    ->where('source_id', 'espn')
                    ->where('report_hash', $hash)
                    ->exists();

                if ($existing) {
                    continue;
                }

                $player = $athleteId !== null ? WnbaPlayer::findByExternalId((string) $athleteId) : null;

                WnbaInjuryReport::create([
                    'player_id' => $player?->id,
                    'player_external_id' => $athleteId,
                    'player_name' => $injury['athlete_display_name'] ?? 'Unknown',
                    'team_id' => $team['team_id'] ?? null,
                    'status' => $status,
                    'description' => $injury['short_comment'] ?? $injury['long_comment'] ?? null,
                    'reported_at' => $reportedAt,
                    'source_id' => 'espn',
                    'report_hash' => $hash,
                    'raw_payload_id' => $stored['id'],
                    'agent_run_id' => $this->reporter->runId(),
                ]);
                $this->reporter->increment('injury_reports_created');
            }
        }
    }

    private function persistOddsSnapshots(): void
    {
        $oddsProvider = app(Tank01OddsProvider::class);
        $usageTracker = app(Tank01UsageTracker::class);

        // Nightly data-agent odds are essential so daily_target does not starve
        // snapshots while monthly budget remains. If monthly is exhausted, skip
        // cleanly instead of returning empty without a signal.
        if (! $usageTracker->canMakeRequest(essential: true)) {
            $this->reporter->increment('odds_skipped_budget');
            $this->reporter->warn('odds skipped: Tank01 API budget exhausted');

            return;
        }

        $capturedAt = now();

        $events = $oddsProvider->getWnbaEvents(['essential' => true]);
        if ($events !== []) {
            $stored = $this->rawPayloadStore->store('tank01', 'odds_events', $events);
            if ($stored['changed']) {
                foreach ($events as $event) {
                    $this->storeOddsSnapshot('game', $event, $capturedAt, [
                        'event_id' => $event['id'] ?? null,
                        'game_date' => $this->gameDateFromCommence($event['commence_time'] ?? null),
                    ]);
                }
            } else {
                $this->reporter->increment('odds_snapshots_unchanged');
            }
        }

        $props = $oddsProvider->getWnbaPlayerProps(['essential' => true]);
        if ($props !== []) {
            $stored = $this->rawPayloadStore->store('tank01', 'odds_player_props', $props);
            if ($stored['changed']) {
                foreach ($props as $prop) {
                    $this->storeOddsSnapshot('player_prop', $prop, $capturedAt, [
                        'event_id' => $prop['event_id'] ?? null,
                        'game_date' => $this->gameDateFromCommence($prop['commence_time'] ?? null),
                        'stat_type' => $prop['stat_type'] ?? null,
                        'player_name' => $prop['player_name'] ?? null,
                        'line' => $prop['line'] ?? null,
                    ]);
                }
            } else {
                $this->reporter->increment('odds_snapshots_unchanged');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $attributes
     */
    private function storeOddsSnapshot(string $marketType, array $payload, \DateTimeInterface $capturedAt, array $attributes): void
    {
        $hash = hash('sha256', json_encode([$marketType, $payload, $capturedAt->format('YmdHi')]));

        $exists = WnbaOddsSnapshot::query()
            ->where('source_id', 'tank01')
            ->where('snapshot_hash', $hash)
            ->exists();

        if ($exists) {
            return;
        }

        WnbaOddsSnapshot::create(array_merge([
            'source_id' => 'tank01',
            'market_type' => $marketType,
            'payload' => $payload,
            'snapshot_hash' => $hash,
            'captured_at' => $capturedAt,
            'agent_run_id' => $this->reporter->runId(),
        ], $attributes));

        $this->reporter->increment('odds_snapshots_created');
    }

    private function normalizeInjuryStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match (true) {
            str_contains($normalized, 'out') => 'out',
            str_contains($normalized, 'doubtful') => 'doubtful',
            str_contains($normalized, 'questionable'), str_contains($normalized, 'day-to-day') => 'questionable',
            str_contains($normalized, 'probable') => 'probable',
            str_contains($normalized, 'active'), str_contains($normalized, 'available') => 'available',
            str_contains($normalized, 'inactive') => 'inactive',
            default => $normalized !== '' ? $normalized : 'unknown',
        };
    }

    private function gameDateFromCommence(?string $commenceTime): ?string
    {
        if ($commenceTime === null || $commenceTime === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($commenceTime)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
