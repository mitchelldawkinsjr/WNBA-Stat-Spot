<?php

namespace App\Providers;

use App\Contracts\OddsProvider;
use App\Services\Odds\OddsService;
use App\Services\Odds\Providers\Tank01OddsProvider;
use App\Services\RapidApi\RapidApiClient;
use App\Services\RapidApi\Tank01UsageTracker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tank01UsageTracker::class);
        $this->app->singleton(RapidApiClient::class);

        $this->app->bind(OddsProvider::class, function ($app) {
            return $app->make(Tank01OddsProvider::class);
        });

        $this->app->singleton(OddsService::class, function ($app) {
            return new OddsService($app->make(OddsProvider::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
