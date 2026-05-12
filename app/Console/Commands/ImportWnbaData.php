<?php

namespace App\Console\Commands;

use App\Services\WnbaDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportWnbaData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-wnba-data
                            {--force : Clear existing WNBA rows and reimport from scratch}
                            {--if-empty : Skip import when wnba_players already has data (unless --force)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import all WNBA data (teams, players, games, and statistics)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏀 Starting comprehensive WNBA data import...');
        $this->newLine();

        $service = new WnbaDataService();
        $force = $this->option('force');

        try {
            if ($this->option('if-empty') && ! $force && Schema::hasTable('wnba_players')) {
                $existing = DB::table('wnba_players')->count();
                if ($existing > 0) {
                    $this->info("⏭️  Skipping import: {$existing} players already in database (use --force to reimport).");

                    return 0;
                }
            }

            // Step 0: Ensure database tables exist
            $this->info('🗄️  Step 0: Ensuring database tables exist...');
            $this->call('migrate', ['--force' => true]);
            $this->info('✅ Database tables ready.');
            $this->newLine();

            // Force clean: Clear existing data if --force flag is used
            if ($force) {
                $this->info('🧹 Force mode: Clearing existing WNBA data...');
                $this->clearExistingData();
                $this->info('✅ Existing data cleared.');
                $this->newLine();
            }

            // Step 1: Import team data
            $this->info('📊 Step 1: Downloading and importing team data...');
            $teamDataPath = $service->downloadTeamData();
            $teamData = $service->parseTeamData($teamDataPath);
            $service->saveTeamData($teamData);
            $this->info('✅ Team data imported successfully.');
            $this->newLine();

            // Step 2: Import team schedule/game data
            $this->info('📅 Step 2: Downloading and importing game schedule data...');
            $teamSchedulePath = $service->downloadTeamScheduleData();
            $teamScheduleData = $service->parseTeamScheduleData($teamSchedulePath);
            $service->saveTeamScheduleData($teamScheduleData);
            $this->info('✅ Game schedule data imported successfully.');
            $this->newLine();

            // Step 3: Import play-by-play/box score data (contains player stats)
            $this->info('🏀 Step 3: Downloading and importing player statistics...');
            $pbpPath = $service->downloadPbpData();
            $pbpData = $service->parsePbpData($pbpPath);
            $service->savePbpData($pbpData);
            $this->info('✅ Play-by-play data imported successfully.');
            $this->newLine();

            // Step 4: Import player/box score data (contains player stats)
            $this->info('🏀 Step 4: Downloading player boxscore data...');
            $boxScorePath = $service->downloadBoxScoreData();
            $boxScoreData = $service->parseBoxScoreData($boxScorePath);
            $service->saveBoxScoreData($boxScoreData);
            $this->info('✅ Player boxscore data imported successfully.');
            $this->newLine();

            // Display summary
            $this->displayImportSummary();

            try {
                Artisan::call('cache:clear');
                $this->info('🧹 Application cache cleared so API lists reflect new data.');
            } catch (\Throwable $e) {
                $this->warn('Could not clear cache: '.$e->getMessage());
            }

        } catch (\Exception $e) {
            $this->error('❌ Import failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        $this->info('🎉 WNBA data import completed successfully!');
        $this->info('💡 You can now access the analytics dashboard and prediction engine.');
        return 0;
    }

    /**
     * Clear all existing WNBA data from the database
     */
    private function clearExistingData(): void
    {
        $tables = [
            'wnba_player_games',
            'wnba_plays',
            'wnba_game_teams',
            'wnba_games',
            'wnba_players',
            'wnba_teams',
        ];

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                try {
                    $count = DB::table($table)->count();
                    if ($count > 0) {
                        DB::table($table)->truncate();
                        $this->line("   🗑️  Cleared {$count} records from {$table}");
                    } else {
                        $this->line("   ✓ {$table} was already empty");
                    }
                } catch (\Exception $e) {
                    try {
                        $deleted = DB::table($table)->delete();
                        $this->line("   🗑️  Cleared {$deleted} records from {$table} (delete fallback)");
                    } catch (\Exception $deleteError) {
                        $this->warn("   ⚠️  Could not clear {$table}: ".$deleteError->getMessage());
                    }
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function displayImportSummary()
    {
        $this->info('📈 Import Summary:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        try {
            $teamCount = DB::table('wnba_teams')->count();
            $playerCount = DB::table('wnba_players')->count();
            $gameCount = DB::table('wnba_games')->count();
            $statsCount = DB::table('wnba_player_games')->count();

            $this->line("🏀 Teams imported: {$teamCount}");
            $this->line("👥 Players imported: {$playerCount}");
            $this->line("🎮 Games imported: {$gameCount}");
            $this->line("📊 Player game stats: {$statsCount}");
        } catch (\Exception $e) {
            $this->warn('Could not retrieve import counts: ' . $e->getMessage());
        }

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
    }
}
