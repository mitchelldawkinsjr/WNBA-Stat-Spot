<?php

namespace Tests\Unit\Jobs;

use App\Http\Controllers\Api\PredictionsController;
use App\Jobs\GradePredictionsJob;
use App\Jobs\RunAnalyticsAgent;
use App\Models\WnbaAgentRun;
use App\Services\WNBA\Agents\AgentRunReporter;
use App\Services\WNBA\Agents\AggregateComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class GradePredictionsJobChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_todays_props_cache_key_matches_record_command_version(): void
    {
        $key = PredictionsController::todaysPropsCacheKey('America/New_York');

        $this->assertStringContainsString(
            PredictionsController::TODAYS_PROPS_CACHE_VERSION,
            $key
        );
        $this->assertSame(
            'todays_best_props_with_odds_v6_America_New_York',
            $key
        );
    }

    public function test_analytics_agent_dispatches_grade_predictions_job(): void
    {
        Bus::fake([GradePredictionsJob::class]);

        $run = new WnbaAgentRun([
            'run_uuid' => 'test-uuid',
            'agent' => 'analytics',
            'status' => 'success',
            'counters' => [],
        ]);

        $aggregates = Mockery::mock(AggregateComputationService::class);
        $aggregates->shouldReceive('setReporter')->once();
        $aggregates->shouldReceive('computeSeason')->once();

        $reporter = Mockery::mock(AgentRunReporter::class);
        $reporter->shouldReceive('start')->once();
        $reporter->shouldReceive('finish')->once()->andReturn($run);

        $job = new RunAnalyticsAgent(['season' => 2026]);
        $job->handle($aggregates, $reporter);

        Bus::assertDispatched(GradePredictionsJob::class);
    }

    public function test_analytics_agent_dry_run_does_not_dispatch_grade_job(): void
    {
        Bus::fake([GradePredictionsJob::class]);

        $run = new WnbaAgentRun([
            'run_uuid' => 'dry-uuid',
            'agent' => 'analytics',
            'status' => 'success',
            'counters' => [],
        ]);

        $aggregates = Mockery::mock(AggregateComputationService::class);
        $aggregates->shouldReceive('setReporter')->once();
        $aggregates->shouldNotReceive('computeSeason');

        $reporter = Mockery::mock(AgentRunReporter::class);
        $reporter->shouldReceive('start')->once();
        $reporter->shouldReceive('warn')->once();
        $reporter->shouldReceive('finish')->once()->andReturn($run);

        $job = new RunAnalyticsAgent(['season' => 2026, 'dry_run' => true]);
        $job->handle($aggregates, $reporter);

        Bus::assertNotDispatched(GradePredictionsJob::class);
    }
}
