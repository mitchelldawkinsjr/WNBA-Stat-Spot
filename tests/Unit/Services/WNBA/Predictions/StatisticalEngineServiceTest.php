<?php

namespace Tests\Unit\Services\WNBA\Predictions;

use App\Services\WNBA\Predictions\StatisticalEngineService;
use Tests\TestCase;

class StatisticalEngineServiceTest extends TestCase
{
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
    public function it_calculates_confidence_interval_for_sample_data()
    {
        $data = [10, 12, 14, 16, 18];
        $result = $this->service->calculateConfidenceInterval($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('lower', $result);
        $this->assertArrayHasKey('upper', $result);
        $this->assertLessThan($result['upper'], $result['lower']);
    }
}
