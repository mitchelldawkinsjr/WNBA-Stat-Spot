<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:import-wnba-data')
    ->dailyAt('02:00')
    ->description('Incremental WNBA data import')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('app:sync-wnba-live')
    ->everyThirtyMinutes()
    ->between('17:00', '23:59')
    ->description('Live WNBA sync via Tank01')
    ->withoutOverlapping()
    ->when(fn () => config('wnba.features.enable_live_updates') || config('tank01.live_sync.enabled'));

Schedule::command('queue:health-check')
    ->everyThirtyMinutes()
    ->description('Check queue health')
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=48')
    ->daily()
    ->description('Clean up old failed jobs');
