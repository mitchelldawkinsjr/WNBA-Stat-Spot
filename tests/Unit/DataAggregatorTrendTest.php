<?php

namespace Tests\Unit;

use App\Services\WNBA\Data\DataAggregatorService;
use ReflectionMethod;
use Tests\TestCase;

class DataAggregatorTrendTest extends TestCase
{
    public function test_calculate_trend_handles_clock_style_minutes(): void
    {
        $service = app(DataAggregatorService::class);
        $method = new ReflectionMethod(DataAggregatorService::class, 'calculateTrend');
        $method->setAccessible(true);

        $trend = $method->invoke($service, ['32:45', '28:10', '35:00']);

        $this->assertIsFloat($trend);
    }
}
