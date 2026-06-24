<?php

namespace App\Console\Commands;

use App\Services\WNBA\Data\EntityMergeService;
use Illuminate\Console\Command;

class SyncEntityIdentities extends Command
{
    protected $signature = 'app:sync-entity-identities
                            {--season= : Season year (defaults to WNBA_CURRENT_SEASON)}';

    protected $description = 'Sync ESPN and Tank01 player, team, and game identity mappings';

    public function handle(EntityMergeService $merge): int
    {
        $season = (int) ($this->option('season') ?? config('wnba.seasons.current_season'));

        $this->info("Syncing provider identity mappings for {$season}...");

        $stats = $merge->syncAll($season);

        $this->table(
            ['Entity', 'Mappings synced'],
            [
                ['Teams', $stats['teams']],
                ['Players', $stats['players']],
                ['Games', $stats['games']],
            ]
        );

        $this->info('Identity sync complete.');

        return 0;
    }
}
