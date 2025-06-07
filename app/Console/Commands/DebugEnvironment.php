<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DebugEnvironment extends Command
{
    protected $signature = 'debug:environment';
    protected $description = 'Debug environment variables and database configuration';

    public function handle()
    {
        $this->info('🔍 Environment Debug Information');
        $this->info('================================');

        // Check environment
        $this->info('📋 Basic Environment:');
        $this->line('  APP_ENV: ' . env('APP_ENV', 'NOT SET'));
        $this->line('  APP_DEBUG: ' . env('APP_DEBUG', 'NOT SET'));
        $this->line('  APP_KEY: ' . (env('APP_KEY') ? 'SET' : 'NOT SET'));
        $this->line('  APP_URL: ' . env('APP_URL', 'NOT SET'));

        $this->newLine();
        $this->info('🗄️  Database Environment Variables:');
        $this->line('  DB_CONNECTION: ' . env('DB_CONNECTION', 'NOT SET'));
        $this->line('  DB_HOST: ' . env('DB_HOST', 'NOT SET'));
        $this->line('  DB_PORT: ' . env('DB_PORT', 'NOT SET'));
        $this->line('  DB_DATABASE: ' . env('DB_DATABASE', 'NOT SET'));
        $this->line('  DB_USERNAME: ' . env('DB_USERNAME', 'NOT SET'));
        $this->line('  DB_PASSWORD: ' . (env('DB_PASSWORD') ? 'SET (' . strlen(env('DB_PASSWORD')) . ' chars)' : 'NOT SET'));

        $this->newLine();
        $this->info('⚙️  Database Configuration (from config/database.php):');
        $defaultConnection = Config::get('database.default');
        $this->line('  Default connection: ' . $defaultConnection);

        $dbConfig = Config::get("database.connections.{$defaultConnection}");
        if ($dbConfig) {
            $this->line('  Driver: ' . ($dbConfig['driver'] ?? 'NOT SET'));
            $this->line('  Host: ' . ($dbConfig['host'] ?? 'NOT SET'));
            $this->line('  Port: ' . ($dbConfig['port'] ?? 'NOT SET'));
            $this->line('  Database: ' . ($dbConfig['database'] ?? 'NOT SET'));
            $this->line('  Username: ' . ($dbConfig['username'] ?? 'NOT SET'));
            $this->line('  Password: ' . (isset($dbConfig['password']) && $dbConfig['password'] ? 'SET' : 'NOT SET'));
        } else {
            $this->error('  Database configuration not found!');
        }

        $this->newLine();
        $this->info('🔗 Database Connection Test:');
        try {
            $pdo = DB::connection()->getPdo();
            $this->line('  ✅ Database connection: SUCCESS');

            // Get actual connection info
            $host = DB::connection()->getConfig('host');
            $port = DB::connection()->getConfig('port');
            $database = DB::connection()->getConfig('database');
            $username = DB::connection()->getConfig('username');

            $this->line("  Connected to: {$host}:{$port}");
            $this->line("  Database: {$database}");
            $this->line("  Username: {$username}");

        } catch (\Exception $e) {
            $this->error('  ❌ Database connection: FAILED');
            $this->error('  Error: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('📊 Queue Configuration:');
        $this->line('  QUEUE_CONNECTION: ' . env('QUEUE_CONNECTION', 'NOT SET'));
        $this->line('  CACHE_DRIVER: ' . env('CACHE_DRIVER', 'NOT SET'));
        $this->line('  SESSION_DRIVER: ' . env('SESSION_DRIVER', 'NOT SET'));

        $this->newLine();
        $this->info('🌐 Render-specific Variables:');
        $this->line('  PORT: ' . env('PORT', 'NOT SET'));
        $this->line('  RENDER: ' . env('RENDER', 'NOT SET'));
        $this->line('  RENDER_SERVICE_ID: ' . env('RENDER_SERVICE_ID', 'NOT SET'));
        $this->line('  RENDER_SERVICE_NAME: ' . env('RENDER_SERVICE_NAME', 'NOT SET'));

        $this->newLine();
        $this->info('🔍 All Environment Variables (DB related):');
        $envVars = $_ENV;
        ksort($envVars);
        foreach ($envVars as $key => $value) {
            if (str_contains(strtoupper($key), 'DB_') ||
                str_contains(strtoupper($key), 'DATABASE') ||
                str_contains(strtoupper($key), 'POSTGRES') ||
                str_contains(strtoupper($key), 'RENDER')) {
                $displayValue = (str_contains(strtoupper($key), 'PASSWORD') || str_contains(strtoupper($key), 'SECRET'))
                    ? 'SET (' . strlen($value) . ' chars)'
                    : $value;
                $this->line("  {$key}: {$displayValue}");
            }
        }

        $this->newLine();
        $this->info('🌐 Railway-specific Variables:');
        $this->line('  PORT: ' . env('PORT', 'NOT SET'));
        $this->line('  RAILWAY_ENVIRONMENT: ' . env('RAILWAY_ENVIRONMENT', 'NOT SET'));
        $this->line('  RAILWAY_PUBLIC_DOMAIN: ' . env('RAILWAY_PUBLIC_DOMAIN', 'NOT SET'));
        $this->line('  RAILWAY_STATIC_URL: ' . env('RAILWAY_STATIC_URL', 'NOT SET'));

        $this->newLine();
        $this->info('🔗 Database URL Configuration:');
        $databaseUrl = env('DATABASE_URL');
        if ($databaseUrl) {
            // Parse the DATABASE_URL to show components (without exposing password)
            $parsed = parse_url($databaseUrl);
            $this->line('  DATABASE_URL is set:');
            $this->line('    Scheme: ' . ($parsed['scheme'] ?? 'unknown'));
            $this->line('    Host: ' . ($parsed['host'] ?? 'unknown'));
            $this->line('    Port: ' . ($parsed['port'] ?? 'unknown'));
            $this->line('    Path: ' . ($parsed['path'] ?? 'unknown'));
            $this->line('    User: ' . ($parsed['user'] ?? 'unknown'));
            $this->line('    Password: ' . (isset($parsed['pass']) ? 'SET' : 'NOT SET'));

            // Check for SSL mode
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
                $this->line('    SSL Mode: ' . ($queryParams['sslmode'] ?? 'not specified'));
            }
        } else {
            $this->error('  DATABASE_URL: NOT SET');
            $this->line('    This is likely why you\'re connecting to localhost');
        }

        $this->newLine();
        $this->info('🚨 Connection Issues Detected:');

        // Check for localhost connection in production
        $currentHost = DB::connection()->getConfig('host');
        if ($currentHost === '127.0.0.1' || $currentHost === 'localhost') {
            $this->error('  ❌ Connecting to localhost in production!');
            $this->line('     Current host: ' . $currentHost);
            $this->line('     This will fail in Railway deployment');

            if (!$databaseUrl && !env('DB_HOST')) {
                $this->line('     🔧 SOLUTION: Set DATABASE_URL or DB_HOST in Railway');
            }
        } else {
            $this->line('  ✅ Database host looks correct: ' . $currentHost);
        }

        // Check SSL configuration
        if (env('APP_ENV') === 'production' && !env('DB_SSLMODE')) {
            $this->warn('  ⚠️  SSL mode not configured for production');
            $this->line('     Add DB_SSLMODE=require to Railway environment');
        }

        $this->newLine();
        return 0;
    }
}
