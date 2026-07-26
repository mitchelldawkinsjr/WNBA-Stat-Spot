<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly Data Agent run: ingests schedule/boxes/PBP/injuries/odds with raw
// payload lineage, then chains the Entity Integrity and Analytics agents.
Schedule::command('app:wnba-agent data --mode=incremental')
    ->dailyAt('02:00')
    ->description('WNBA data agent: incremental ingest + chained audits/aggregates')
    ->withoutOverlapping()
    ->onOneServer();

// Weekly full-database entity audit across all seasons.
Schedule::command('app:wnba-agent entity --mode=audit --season=all')
    ->weeklyOn(1, '03:00')
    ->description('WNBA entity integrity agent: weekly full audit')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('app:sync-wnba-live')
    ->everyThirtyMinutes()
    ->between('17:00', '23:59')
    ->description('Live WNBA sync via Tank01')
    ->withoutOverlapping()
    ->when(fn () => config('wnba.features.enable_live_updates') || config('tank01.live_sync.enabled'));

// Grading only reads the local DB, so an hourly run is cheap. It picks up
// finals from the evening live sync and from the 02:00 daily import.
Schedule::command('app:grade-predictions')
    ->hourly()
    ->description('Grade pending predictions against final results')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('queue:health-check')
    ->everyThirtyMinutes()
    ->description('Check queue health')
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=48')
    ->daily()
    ->description('Clean up old failed jobs');
