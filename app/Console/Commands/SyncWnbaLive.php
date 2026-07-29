<?php

namespace App\Console\Commands;

use App\Services\WNBA\Agents\AgentRunReporter;
use App\Services\WNBA\Agents\ConflictResolver;
use App\Services\WNBA\Data\Providers\Tank01WnbaProvider;
use App\Services\WNBA\Data\WnbaProviderResolver;
use App\Services\WnbaDataService;
use Illuminate\Console\Command;

class SyncWnbaLive extends Command
{
    protected $signature = 'app:sync-wnba-live {--date= : Game date YYYYMMDD, defaults to today}';

    protected $description = 'Budget-capped live WNBA sync via Tank01 (scoreboard + in-progress box scores)';

    public function handle(WnbaProviderResolver $resolver): int
    {
        if (! config('tank01.live_sync.enabled') && ! config('wnba.features.enable_live_updates')) {
            $this->info('Live updates disabled (set WNBA_ENABLE_LIVE_UPDATES=true).');

            return 0;
        }

        // Route by the live_sync task (WNBA_LIVE_PROVIDER), not the global
        // WNBA_DATA_PROVIDER default.
        if ($resolver->resolveName('live_sync') !== 'tank01') {
            $this->warn('Live sync currently supports only tank01 (set WNBA_LIVE_PROVIDER=tank01).');

            return 0;
        }

        $provider = app(Tank01WnbaProvider::class);

        // Build the data service on the live provider so lineage records
        // tank01 as the source regardless of the global provider default.
        $dataService = new WnbaDataService($provider);

        $reporter = app(AgentRunReporter::class);
        $reporter->start('data', 'live', ['season' => (int) config('wnba.seasons.current_season')]);
        $dataService->setAgentContext($reporter, app(ConflictResolver::class));
        // Tank01 scoreboard is calendar-day in US Eastern, not UTC.
        $gameDate = $this->option('date') ?: now('America/New_York')->format('Ymd');
        $maxCalls = (int) config('tank01.live_sync.max_calls_per_run', 5);

        $this->info("Syncing live WNBA data for {$gameDate} (max {$maxCalls} API calls)...");

        $result = $provider->syncLiveGames($gameDate, $maxCalls);

        if (! empty($result['schedule'])) {
            $dataService->saveTeamScheduleData($result['schedule']);
        }
        if (! empty($result['team'])) {
            $dataService->saveTeamData($result['team']);
        }
        if (! empty($result['player'])) {
            $dataService->saveBoxScoreData($result['player']);
        }

        $reporter->set('sources_attempted', 1);
        $reporter->set('sources_succeeded', 1);
        $reporter->finish();

        // Bust the games list so the API does not keep serving pre-sync scores.
        \App\Services\WNBA\Agents\AgentResponseCache::forgetGameListCaches(
            (int) config('wnba.seasons.current_season')
        );

        $this->info(sprintf(
            'Live sync complete: %d schedule, %d team, %d player records.',
            count($result['schedule']),
            count($result['team']),
            count($result['player'])
        ));

        return 0;
    }
}
