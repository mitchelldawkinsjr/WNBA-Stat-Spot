<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly Data Agent run: ingests schedule/boxes/PBP/injuries/odds with raw
// payload lineage, then chains Entity → Analytics → GradePredictionsJob so
// yesterday's saved game-score + prop picks are graded once finals land.
Schedule::command('app:wnba-agent data --mode=incremental')
    ->dailyAt('02:00')
    ->description('WNBA data agent: incremental ingest + chained audits/aggregates/grading')
    ->withoutOverlapping()
    ->onOneServer();

// Weekly full-database entity audit across all seasons.
Schedule::command('app:wnba-agent entity --mode=audit --season=all')
    ->weeklyOn(1, '03:00')
    ->description('WNBA entity integrity agent: weekly full audit')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('app:sync-wnba-live')
    ->everyFiveMinutes()
    ->timezone('America/New_York')
    ->between('11:00', '23:59')
    ->description('Live WNBA sync via Tank01 (ET afternoon/evening window)')
    ->withoutOverlapping()
    ->when(fn () => config('wnba.features.enable_live_updates') || config('tank01.live_sync.enabled'));

// Early-morning ET spillover for West Coast / late tips (00:00–02:00 ET).
Schedule::command('app:sync-wnba-live')
    ->everyFiveMinutes()
    ->timezone('America/New_York')
    ->between('00:00', '02:00')
    ->description('Live WNBA sync via Tank01 (late ET spillover)')
    ->withoutOverlapping()
    ->when(fn () => config('wnba.features.enable_live_updates') || config('tank01.live_sync.enabled'));

// Grading only reads the local DB, so an hourly run is cheap. It picks up
// finals from the evening live sync; the nightly agent chain also grades.
Schedule::command('app:grade-predictions')
    ->hourly()
    ->description('Grade pending predictions against final results')
    ->withoutOverlapping()
    ->onOneServer();

// Morning slate capture so feedback has feature snapshots even if no UI hit.
Schedule::command('app:record-todays-predictions')
    ->dailyAt('10:00')
    ->timezone('America/New_York')
    ->description("Record today's predictions with feature snapshots")
    ->withoutOverlapping()
    ->onOneServer();

// Nightly learning loop after data agent / finals land (grades again, then tunes).
Schedule::command('app:run-prediction-feedback')
    ->dailyAt('03:30')
    ->timezone('America/New_York')
    ->description('Calibrate and auto-tune prediction model params')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('queue:health-check')
    ->everyThirtyMinutes()
    ->description('Check queue health')
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=48')
    ->daily()
    ->description('Clean up old failed jobs');
