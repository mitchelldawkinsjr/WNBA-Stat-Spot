<?php

namespace App\Providers;

use App\Contracts\WnbaStatsProvider;
use App\Services\WNBA\Analytics\GameAnalyticsService;
use App\Services\WNBA\Analytics\PlayerAnalyticsService;
use App\Services\WNBA\Analytics\TeamAnalyticsService;
use App\Services\WNBA\Data\DataAggregatorService;
use App\Services\WNBA\Data\EntityMergeService;
use App\Services\WNBA\Data\PlayerGamelogService;
use App\Services\WNBA\Data\WnbaProviderResolver;
use App\Services\WNBA\Predictions\PredictionEngine;
use App\Services\WNBA\Predictions\PropsPredictionService;
use App\Services\WNBA\Predictions\StatisticalEngineService;
use App\Services\WnbaDataService;
use Illuminate\Support\ServiceProvider;

class WnbaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WnbaProviderResolver::class);

        $this->app->bind(WnbaStatsProvider::class, function ($app) {
            return $app->make(WnbaProviderResolver::class)->make(
                (string) config('wnba.data_source.provider', 'tank01')
            );
        });

        $this->app->singleton(WnbaDataService::class, function ($app) {
            return new WnbaDataService($app->make(WnbaStatsProvider::class));
        });

        $this->app->singleton(StatisticalEngineService::class);
        $this->app->singleton(DataAggregatorService::class);
        $this->app->singleton(PlayerGamelogService::class);
        $this->app->singleton(EntityMergeService::class);
        $this->app->singleton(PlayerAnalyticsService::class);
        $this->app->singleton(TeamAnalyticsService::class);
        $this->app->singleton(GameAnalyticsService::class);
        $this->app->singleton(PredictionEngine::class);
        $this->app->singleton(PropsPredictionService::class);

        $this->app->alias(PropsPredictionService::class, 'wnba.predictions');
        $this->app->alias(PlayerAnalyticsService::class, 'wnba.player.analytics');
        $this->app->alias(TeamAnalyticsService::class, 'wnba.team.analytics');
        $this->app->alias(GameAnalyticsService::class, 'wnba.game.analytics');
        $this->app->alias(PredictionEngine::class, 'wnba.prediction.engine');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/wnba.php' => config_path('wnba.php'),
        ], 'wnba-config');
    }

    public function provides(): array
    {
        return [
            DataAggregatorService::class,
            PlayerAnalyticsService::class,
            TeamAnalyticsService::class,
            GameAnalyticsService::class,
            StatisticalEngineService::class,
            PredictionEngine::class,
            PropsPredictionService::class,
            'wnba.predictions',
            'wnba.player.analytics',
            'wnba.team.analytics',
            'wnba.game.analytics',
            'wnba.prediction.engine',
        ];
    }
}
