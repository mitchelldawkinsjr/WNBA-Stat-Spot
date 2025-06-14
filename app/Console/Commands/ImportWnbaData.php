<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\WnbaDataService;

class ImportWnbaData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-wnba-data {--force : Force reimport even if data exists}';

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
            $service->saveBoxScoreData($pbpData);
            $this->info('✅ Player statistics imported successfully.');
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
    private function clearExistingData()
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'wnba_player_games',  // Clear this first due to foreign key constraints
            'wnba_plays',         // Clear plays that might reference games and teams
            'wnba_game_teams',    // Clear this before games - team associations
            'wnba_games',         // Clear games before teams (if there are direct FK constraints)
            'wnba_players',       // Clear players
            'wnba_teams'          // Clear teams last
        ];

        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                if ($count > 0) {
                    DB::table($table)->truncate();
                    $this->line("   🗑️  Cleared {$count} records from {$table}");
                } else {
                    $this->line("   ✓ {$table} was already empty");
                }
            } catch (\Exception $e) {
                // If truncate fails due to foreign keys, try delete
                try {
                    $count = DB::table($table)->count();
                    if ($count > 0) {
                        DB::table($table)->delete();
                        $this->line("   🗑️  Cleared {$count} records from {$table} (using DELETE)");
                    }
                } catch (\Exception $deleteError) {
                    $this->warn("   ⚠️  Could not clear {$table}: " . $deleteError->getMessage());
                }
            }
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
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
