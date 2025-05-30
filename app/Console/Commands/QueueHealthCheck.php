<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;

class QueueHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:health-check {--detailed : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health of the queue system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Queue Health Check Starting...');
        $this->newLine();

        $allChecks = true;

        // Check database connection
        $allChecks &= $this->checkDatabaseConnection();

        // Check queue tables
        $allChecks &= $this->checkQueueTables();

        // Check queue configuration
        $allChecks &= $this->checkQueueConfiguration();

        // Check pending jobs
        $allChecks &= $this->checkPendingJobs();

        // Check failed jobs
        $allChecks &= $this->checkFailedJobs();

        $this->newLine();

        if ($allChecks) {
            $this->info('✅ All queue health checks passed!');
            return 0;
        } else {
            $this->error('❌ Some queue health checks failed!');
            return 1;
        }
    }

        private function checkDatabaseConnection(): bool
    {
        $this->info('📊 Checking database connection...');

        try {
            $defaultConnection = config('database.default');
            $this->line("  📋 Default connection: {$defaultConnection}");

            // Check for SQLite misconfiguration
            if ($defaultConnection === 'sqlite') {
                $this->line('  ⚠️  WARNING: Using SQLite in production (should be PostgreSQL)');
            }

            DB::connection()->getPdo();
            $this->line('  ✅ Database connection: OK');

            if ($this->option('detailed')) {
                $driver = config('database.connections.' . $defaultConnection . '.driver');
                $this->line("    Driver: {$driver}");

                // Show environment variables
                $this->line('    Environment:');
                $this->line('      DB_CONNECTION: ' . (env('DB_CONNECTION') ?: 'not_set'));
                $this->line('      DB_HOST: ' . (env('DB_HOST') ?: 'not_set'));
                $this->line('      DB_PORT: ' . (env('DB_PORT') ?: 'not_set'));
                $this->line('      DB_DATABASE: ' . (env('DB_DATABASE') ?: 'not_set'));

                // Test specific connections
                if ($defaultConnection !== 'pgsql') {
                    try {
                        DB::connection('pgsql')->getPdo();
                        $this->line('    ✅ PostgreSQL connection also works');
                    } catch (\Exception $e) {
                        $this->line('    ❌ PostgreSQL connection failed: ' . substr($e->getMessage(), 0, 100));
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->line('  ❌ Database connection: FAILED');
            $this->line('    Error: ' . $e->getMessage());

            // Additional debugging for SQLite errors
            if (str_contains($e->getMessage(), 'database.sqlite')) {
                $this->line('    🔍 This looks like a SQLite configuration issue');
                $this->line('    💡 Check that DB_CONNECTION=pgsql in environment');
            }

            return false;
        }
    }

    private function checkQueueTables(): bool
    {
        $this->info('📋 Checking queue tables...');

        $requiredTables = ['jobs', 'job_batches', 'failed_jobs'];
        $allTablesExist = true;

        foreach ($requiredTables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    $this->line("  ✅ Table '{$table}': EXISTS");

                    if ($this->option('detailed')) {
                        $count = DB::table($table)->count();
                        $this->line("    Records: {$count}");
                    }
                } else {
                    $this->line("  ❌ Table '{$table}': MISSING");
                    $allTablesExist = false;
                }
            } catch (\Exception $e) {
                $this->line("  ❌ Table '{$table}': ERROR - " . $e->getMessage());
                $allTablesExist = false;
            }
        }

        return $allTablesExist;
    }

    private function checkQueueConfiguration(): bool
    {
        $this->info('⚙️  Checking queue configuration...');

        try {
            $connection = config('queue.default');
            $this->line("  ✅ Default connection: {$connection}");

            $driver = config("queue.connections.{$connection}.driver");
            $this->line("  ✅ Driver: {$driver}");

            if ($this->option('detailed')) {
                $this->line('    Configuration:');
                $config = config("queue.connections.{$connection}");
                foreach ($config as $key => $value) {
                    if (!in_array($key, ['password', 'secret'])) {
                        $this->line("      {$key}: " . (is_array($value) ? json_encode($value) : $value));
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->line('  ❌ Configuration check: FAILED');
            $this->line('    Error: ' . $e->getMessage());
            return false;
        }
    }

    private function checkPendingJobs(): bool
    {
        $this->info('📝 Checking pending jobs...');

        try {
            $pendingJobs = DB::table('jobs')->count();
            $this->line("  ✅ Pending jobs: {$pendingJobs}");

            if ($this->option('detailed') && $pendingJobs > 0) {
                $recentJobs = DB::table('jobs')
                    ->select('queue', 'attempts', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();

                $this->line('    Recent jobs:');
                foreach ($recentJobs as $job) {
                    $this->line("      Queue: {$job->queue}, Attempts: {$job->attempts}, Created: {$job->created_at}");
                }
            }

            if ($pendingJobs > 100) {
                $this->line('  ⚠️  Warning: High number of pending jobs');
            }

            return true;
        } catch (\Exception $e) {
            $this->line('  ❌ Pending jobs check: FAILED');
            $this->line('    Error: ' . $e->getMessage());
            return false;
        }
    }

    private function checkFailedJobs(): bool
    {
        $this->info('💥 Checking failed jobs...');

        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $this->line("  ✅ Failed jobs: {$failedJobs}");

            if ($this->option('detailed') && $failedJobs > 0) {
                $recentFailures = DB::table('failed_jobs')
                    ->select('queue', 'exception', 'failed_at')
                    ->orderBy('failed_at', 'desc')
                    ->limit(3)
                    ->get();

                $this->line('    Recent failures:');
                foreach ($recentFailures as $failure) {
                    $exception = substr($failure->exception, 0, 100) . '...';
                    $this->line("      Queue: {$failure->queue}, Failed: {$failure->failed_at}");
                    $this->line("      Error: {$exception}");
                }
            }

            if ($failedJobs > 10) {
                $this->line('  ⚠️  Warning: High number of failed jobs');
            }

            return true;
        } catch (\Exception $e) {
            $this->line('  ❌ Failed jobs check: FAILED');
            $this->line('    Error: ' . $e->getMessage());
            return false;
        }
    }
}
