<?php

namespace App\Providers;

use App\Contracts\OddsProvider;
use App\Contracts\WnbaStatsProvider;
use App\Services\Odds\OddsApiService;
use App\Services\Odds\OddsService;
use App\Services\Odds\Providers\OddsApiProvider;
use App\Services\Odds\Providers\Tank01OddsProvider;
use App\Services\RapidApi\RapidApiClient;
use App\Services\RapidApi\Tank01UsageTracker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Tank01UsageTracker::class);
        $this->app->singleton(RapidApiClient::class);

        $this->app->singleton(OddsApiService::class, function ($app) {
            return new OddsApiService;
        });

        $this->app->bind(OddsProvider::class, function ($app) {
            return match (config('odds-api.provider', 'tank01')) {
                'tank01' => $app->make(Tank01OddsProvider::class),
                default => $app->make(OddsApiProvider::class),
            };
        });

        $this->app->singleton(OddsService::class, function ($app) {
            return new OddsService(
                $app->make(OddsProvider::class),
                $app->make(OddsApiService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
