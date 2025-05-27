<?php

namespace Tests\Unit\Services\WNBA\Predictions;

use App\Services\WNBA\Predictions\PropsPredictionService;
use App\Services\WNBA\Predictions\StatisticalEngineService;
use App\Services\WNBA\Data\WnbaDataService;
use App\Services\WNBA\Predictions\MonteCarloSimulator;
use App\Services\WNBA\Predictions\DistributionAnalyzer;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class PropsPredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PropsPredictionService $service;
    private $statisticalEngine;
    private $wnbaDataService;
    private $monteCarloSimulator;
    private $distributionAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statisticalEngine = Mockery::mock(StatisticalEngineService::class);
        $this->wnbaDataService = Mockery::mock(WnbaDataService::class);
        $this->monteCarloSimulator = Mockery::mock(MonteCarloSimulator::class);
        $this->distributionAnalyzer = Mockery::mock(DistributionAnalyzer::class);

        $this->service = new PropsPredictionService(
            $this->statisticalEngine,
            $this->wnbaDataService,
            $this->monteCarloSimulator,
            $this->distributionAnalyzer
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_calculates_prediction_with_valid_data()
    {
        $playerId = 1;
        $gameId = 1;
        $statType = 'points';
        $lineValue = 15.5;

        $this->wnbaDataService
            ->shouldReceive('getPlayerStats')
            ->with($playerId, $statType)
            ->andReturn([
                ['value' => 18],
                ['value' => 15],
                ['value' => 20],
                ['value' => 16],
                ['value' => 19]
            ]);

        $this->statisticalEngine
            ->shouldReceive('runMonteCarloSimulation')
            ->andReturn([
                'over_probability' => 0.65,
                'expected_value' => 17.5,
                'standard_deviation' => 2.1,
                'confidence_intervals' => [
                    '95' => [13.4, 21.6],
                    '99' => [11.1, 23.9]
                ]
            ]);

        $result = $this->service->predict($playerId, $gameId, $statType, $lineValue);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('over_probability', $result);
        $this->assertArrayHasKey('expected_value', $result);
        $this->assertArrayHasKey('standard_deviation', $result);
        $this->assertArrayHasKey('confidence_intervals', $result);
        $this->assertEquals(0.65, $result['over_probability']);
        $this->assertEquals(17.5, $result['expected_value']);
    }

    /** @test */
    public function it_handles_insufficient_historical_data()
    {
        $playerId = 1;
        $gameId = 1;
        $statType = 'points';
        $lineValue = 15.5;

        $this->wnbaDataService
            ->shouldReceive('getPlayerStats')
            ->with($playerId, $statType)
            ->andReturn([
                ['value' => 18],
                ['value' => 15]
            ]);

        $result = $this->service->predict($playerId, $gameId, $statType, $lineValue);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['over_probability']);
        $this->assertEquals(0, $result['expected_value']);
        $this->assertEquals(0, $result['standard_deviation']);
    }

    /** @test */
    public function it_validates_input_parameters()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->predict(0, 1, 'points', 15.5);
    }

    /** @test */
    public function it_handles_invalid_stat_type()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->predict(1, 1, 'invalid_stat', 15.5);
    }

    /** @test */
    public function it_handles_negative_line_value()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->predict(1, 1, 'points', -15.5);
    }

    /** @test */
    public function it_calculates_prediction_with_different_stat_types()
    {
        $statTypes = ['points', 'rebounds', 'assists', 'steals', 'blocks'];
        $playerId = 1;
        $gameId = 1;
        $lineValue = 5.5;

        foreach ($statTypes as $statType) {
            $this->wnbaDataService
                ->shouldReceive('getPlayerStats')
                ->with($playerId, $statType)
                ->andReturn([
                    ['value' => 6],
                    ['value' => 5],
                    ['value' => 7],
                    ['value' => 4],
                    ['value' => 6]
                ]);

            $this->statisticalEngine
                ->shouldReceive('runMonteCarloSimulation')
                ->andReturn([
                    'over_probability' => 0.6,
                    'expected_value' => 5.6,
                    'standard_deviation' => 1.1,
                    'confidence_intervals' => [
                        '95' => [3.4, 7.8],
                        '99' => [2.3, 8.9]
                    ]
                ]);

            $result = $this->service->predict($playerId, $gameId, $statType, $lineValue);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('over_probability', $result);
            $this->assertArrayHasKey('expected_value', $result);
            $this->assertArrayHasKey('standard_deviation', $result);
            $this->assertArrayHasKey('confidence_intervals', $result);
        }
    }
}
