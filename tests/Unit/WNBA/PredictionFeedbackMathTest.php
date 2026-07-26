<?php

namespace Tests\Unit\WNBA;

use App\Services\WNBA\Predictions\ChampionReportBuilder;
use App\Services\WNBA\Predictions\PredictionFeedbackService;
use App\Services\WNBA\Predictions\PredictionModelParamStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PredictionFeedbackMathTest extends TestCase
{
    use RefreshDatabase;

    public function test_shrinkage_pulls_probability_toward_half(): void
    {
        $store = app(PredictionModelParamStore::class);
        $feedback = app(PredictionFeedbackService::class);

        $this->assertEqualsWithDelta(0.7, $store->applyShrinkage(0.7), 1e-9);
        $this->assertEqualsWithDelta(0.6, $feedback->applyShrinkage(0.7, 0.5), 1e-9);
        // Shrinkage is clamped to 0.5 max → 0.5 + 0.5*(0.9-0.5) = 0.7
        $this->assertEqualsWithDelta(0.7, $feedback->applyShrinkage(0.9, 1.0), 1e-9);
    }

    public function test_promotion_requires_brier_or_hit_rate_improvement(): void
    {
        $feedback = app(PredictionFeedbackService::class);

        $no = $feedback->shouldPromote(
            ['brier' => 0.20, 'hit_rate' => 0.55],
            ['brier' => 0.199, 'hit_rate' => 0.56],
            50
        );
        $this->assertFalse($no['promote']);

        $brier = $feedback->shouldPromote(
            ['brier' => 0.20, 'hit_rate' => 0.55],
            ['brier' => 0.197, 'hit_rate' => 0.55],
            50
        );
        $this->assertTrue($brier['promote']);
        $this->assertContains('holdout_brier_improved', $brier['reasons']);

        $hit = $feedback->shouldPromote(
            ['brier' => 0.20, 'hit_rate' => 0.50],
            ['brier' => 0.204, 'hit_rate' => 0.53],
            50
        );
        $this->assertTrue($hit['promote']);
        $this->assertContains('holdout_hit_rate_improved', $hit['reasons']);
    }

    public function test_report_builder_diffs_only_changed_paths(): void
    {
        $builder = app(ChampionReportBuilder::class);
        $changes = $builder->diffParams(
            [
                'adjustments' => ['rest_b2b' => 0.9, 'home' => 1.05],
                'calibration' => ['shrinkage' => 0.0],
            ],
            [
                'adjustments' => ['rest_b2b' => 0.87, 'home' => 1.05],
                'calibration' => ['shrinkage' => 0.1],
            ],
            ['adjustments.rest_b2b' => 'B2B over-predicted']
        );

        $paths = array_column($changes, 'path');
        $this->assertContains('adjustments.rest_b2b', $paths);
        $this->assertContains('calibration.shrinkage', $paths);
        $this->assertNotContains('adjustments.home', $paths);

        $rest = collect($changes)->firstWhere('path', 'adjustments.rest_b2b');
        $this->assertSame('B2B over-predicted', $rest['why']);
    }

    public function test_score_props_computes_brier_and_hit_rate(): void
    {
        $feedback = app(PredictionFeedbackService::class);
        $props = new Collection([
            (object) [
                'probability_over' => 0.7,
                'recommendation' => 'over',
                'correct' => true,
                'graded_at' => now(),
            ],
            (object) [
                'probability_over' => 0.7,
                'recommendation' => 'over',
                'correct' => false,
                'graded_at' => now(),
            ],
        ]);

        $metrics = $feedback->scoreProps($props, 0.0);
        $this->assertSame(2, $metrics['sample_size']);
        $this->assertEqualsWithDelta(0.5, $metrics['hit_rate'], 1e-6);
        $this->assertGreaterThan(0, $metrics['brier']);
    }
}
