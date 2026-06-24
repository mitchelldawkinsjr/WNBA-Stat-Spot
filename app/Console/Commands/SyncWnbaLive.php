<?php

namespace App\Console\Commands;

use App\Services\WnbaDataService;
use App\Services\WNBA\Data\Providers\Tank01WnbaProvider;
use Illuminate\Console\Command;

class SyncWnbaLive extends Command
{
    protected $signature = 'app:sync-wnba-live {--date= : Game date YYYYMMDD, defaults to today}';

    protected $description = 'Budget-capped live WNBA sync via Tank01 (scoreboard + in-progress box scores)';

    public function handle(WnbaDataService $dataService): int
    {
        if (! config('tank01.live_sync.enabled') && ! config('wnba.features.enable_live_updates')) {
            $this->info('Live updates disabled (set WNBA_ENABLE_LIVE_UPDATES=true).');

            return 0;
        }

        if (config('wnba.data_source.provider') !== 'tank01') {
            $this->warn('Live sync only runs when WNBA_DATA_PROVIDER=tank01');

            return 0;
        }

        $provider = app(Tank01WnbaProvider::class);
        $gameDate = $this->option('date') ?: now()->format('Ymd');
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

        $this->info(sprintf(
            'Live sync complete: %d schedule, %d team, %d player records.',
            count($result['schedule']),
            count($result['team']),
            count($result['player'])
        ));

        return 0;
    }
}
