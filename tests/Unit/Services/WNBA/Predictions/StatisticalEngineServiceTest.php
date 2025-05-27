<?php

namespace Tests\Unit\Services\WNBA\Predictions;

use App\Services\WNBA\Predictions\StatisticalEngineService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StatisticalEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private StatisticalEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StatisticalEngineService();
    }

    /** @test */
    public function it_calculates_bayesian_probability_correctly()
    {
        $prior = 0.5;
        $likelihood = 0.8;
        $evidence = 0.6;

        $result = $this->service->updateBayesianProbability($prior, $likelihood, $evidence);

        $this->assertEquals(0.67, round($result, 2));
    }

    /** @test */
    public function it_calculates_poisson_probability_correctly()
    {
        $lambda = 2.5;
        $k = 3;

        $result = $this->service->calculatePoissonProbability($lambda, $k);

        $this->assertEquals(0.21, round($result, 2));
    }

    /** @test */
    public function it_calculates_poisson_over_probability_correctly()
    {
        $lambda = 2.5;
        $threshold = 3;

        $result = $this->service->calculatePoissonOverProbability($lambda, $threshold);

        $this->assertEquals(0.24, round($result, 2));
    }

    /** @test */
    public function it_calculates_normal_probability_correctly()
    {
        $mean = 10;
        $stdDev = 2;
        $value = 12;

        $result = $this->service->calculateNormalProbability($mean, $stdDev, $value);

        $this->assertEquals(0.12, round($result, 2));
    }

    /** @test */
    public function it_runs_monte_carlo_simulation_with_valid_data()
    {
        $playerId = 1;
        $gameId = 1;
        $statType = 'points';
        $lineValue = 15.5;
        $iterations = 1000;

        $result = $this->service->runMonteCarloSimulation(
            $playerId,
            $gameId,
            $statType,
            $lineValue,
            $iterations
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('over_probability', $result);
        $this->assertArrayHasKey('expected_value', $result);
        $this->assertArrayHasKey('standard_deviation', $result);
        $this->assertArrayHasKey('confidence_intervals', $result);
        $this->assertGreaterThanOrEqual(0, $result['over_probability']);
        $this->assertLessThanOrEqual(1, $result['over_probability']);
    }

    /** @test */
    public function it_handles_empty_historical_data()
    {
        $playerId = 999; // Non-existent player
        $gameId = 1;
        $statType = 'points';
        $lineValue = 15.5;

        $result = $this->service->runMonteCarloSimulation(
            $playerId,
            $gameId,
            $statType,
            $lineValue
        );

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['over_probability']);
        $this->assertEquals(0, $result['expected_value']);
        $this->assertEquals(0, $result['standard_deviation']);
    }

    /** @test */
    public function it_calculates_confidence_intervals_correctly()
    {
        $data = [10, 12, 14, 16, 18];
        $result = $this->service->calculateConfidenceIntervals($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('95', $result);
        $this->assertArrayHasKey('99', $result);
        $this->assertCount(2, $result['95']);
        $this->assertCount(2, $result['99']);
    }

    /** @test */
    public function it_generates_values_from_normal_distribution()
    {
        $mean = 10;
        $stdDev = 2;
        $iterations = 1000;

        $values = [];
        for ($i = 0; $i < $iterations; $i++) {
            $values[] = $this->service->generateNormalValue($mean, $stdDev);
        }

        $sampleMean = array_sum($values) / count($values);
        $sampleStdDev = sqrt(
            array_sum(array_map(function($x) use ($sampleMean) {
                return pow($x - $sampleMean, 2);
            }, $values)) / count($values)
        );

        $this->assertEqualsWithDelta($mean, $sampleMean, 0.5);
        $this->assertEqualsWithDelta($stdDev, $sampleStdDev, 0.5);
    }

    /** @test */
    public function it_generates_values_from_poisson_distribution()
    {
        $lambda = 2.5;
        $iterations = 1000;

        $values = [];
        for ($i = 0; $i < $iterations; $i++) {
            $values[] = $this->service->generatePoissonValue($lambda);
        }

        $sampleMean = array_sum($values) / count($values);
        $this->assertEqualsWithDelta($lambda, $sampleMean, 0.2);
    }

    /** @test */
    public function it_generates_values_from_binomial_distribution()
    {
        $n = 10;
        $p = 0.5;
        $iterations = 1000;

        $values = [];
        for ($i = 0; $i < $iterations; $i++) {
            $values[] = $this->service->generateBinomialValue($n, $p);
        }

        $sampleMean = array_sum($values) / count($values);
        $expectedMean = $n * $p;
        $this->assertEqualsWithDelta($expectedMean, $sampleMean, 0.5);
    }
}
