<?php

namespace Tests\Feature\Api;

use App\Models\PredictionChampionReport;
use App\Models\PredictionFeedbackRun;
use App\Models\PredictionModelParam;
use App\Models\TrackedPropPrediction;
use App\Services\WNBA\Predictions\PredictionAccuracyService;
use App\Services\WNBA\Predictions\PredictionFeedbackService;
use App\Services\WNBA\Predictions\PredictionModelParamStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PredictionFeedbackLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_props_stores_feature_snapshot_and_model_version(): void
    {
        $service = app(PredictionAccuracyService::class);
        $version = app(PredictionModelParamStore::class)->championVersion();

        $recorded = $service->recordTodaysProps([
            [
                'player_id' => '4398915',
                'player_name' => 'Test Player',
                'stat_type' => 'points',
                'suggested_line' => 18.5,
                'predicted_value' => 20.5,
                'recommendation' => 'over',
                'confidence' => 70,
                'expected_value' => 4.2,
                'probability_over' => 62,
                'probability_under' => 38,
                'feature_snapshot' => [
                    'base_value' => 19.5,
                    'adjusted_value' => 20.5,
                    'rest_days' => 1,
                    'home_away' => 'home',
                ],
                'model_version' => $version,
            ],
        ], 'America/New_York');

        $this->assertCount(1, $recorded);
        $row = TrackedPropPrediction::query()->first();
        $this->assertSame($version, $row->model_version);
        $this->assertSame(20.5, (float) $row->feature_snapshot['adjusted_value']);
        $this->assertSame(1, $row->feature_snapshot['rest_days']);
    }

    public function test_feedback_promotes_challenger_and_creates_champion_report(): void
    {
        config([
            'wnba.predictions.auto_tune_enabled' => true,
            'wnba.predictions.min_learn_samples' => 40,
            'wnba.predictions.eval_window_size' => 80,
            'wnba.predictions.promotion.brier_improvement' => 0.0,
            'wnba.predictions.promotion.hit_rate_improvement' => 0.0,
            'wnba.predictions.promotion.max_brier_regression' => 1.0,
        ]);

        // Seed graded props with overconfident wrong probs so shrinkage helps Brier.
        for ($i = 0; $i < 50; $i++) {
            TrackedPropPrediction::query()->create([
                'prediction_date' => now()->subDays($i % 10)->toDateString(),
                'player_id' => (string) (1000 + $i),
                'player_name' => 'Player '.$i,
                'stat_type' => $i % 2 === 0 ? 'points' : 'rebounds',
                'line' => 18.5,
                'predicted_value' => 20.0,
                'recommendation' => 'over',
                'confidence' => 70,
                'expected_value' => 3.0,
                'probability_over' => 0.85,
                'probability_under' => 0.15,
                'feature_snapshot' => [
                    'base_value' => 20.0,
                    'adjusted_value' => 20.0,
                    'rest_days' => $i % 5 === 0 ? 1 : 2,
                    'home_away' => $i % 3 === 0 ? 'home' : 'away',
                    'opponent_defense_rating' => 100,
                ],
                'model_version' => 'bootstrap.1',
                'actual_value' => $i % 3 === 0 ? 22.0 : 10.0,
                'correct' => $i % 3 === 0,
                'predicted_at' => now()->subDays($i % 10),
                'graded_at' => now()->subDays($i % 10),
            ]);
        }

        $result = app(PredictionFeedbackService::class)->run();

        $this->assertSame('completed', $result['status']);
        $this->assertTrue($result['promoted'], 'Expected challenger promotion with relaxed thresholds');
        $this->assertDatabaseHas('prediction_champion_reports', [
            'to_version' => $result['champion_version'],
        ]);

        $report = PredictionChampionReport::query()->latest('id')->first();
        $this->assertNotNull($report);
        $this->assertNotEmpty($report->headline);
        $this->assertStringContainsString('Why promoted', $report->summary_markdown);
        $this->assertSame(
            'champion',
            PredictionModelParam::query()->where('version', $result['champion_version'])->value('status')
        );
    }

    public function test_non_promotion_does_not_create_champion_report_when_auto_tune_disabled(): void
    {
        config([
            'wnba.predictions.auto_tune_enabled' => false,
            'wnba.predictions.min_learn_samples' => 40,
        ]);

        for ($i = 0; $i < 45; $i++) {
            TrackedPropPrediction::query()->create([
                'prediction_date' => now()->toDateString(),
                'player_id' => (string) (2000 + $i),
                'player_name' => 'P'.$i,
                'stat_type' => 'points',
                'line' => 15.5,
                'predicted_value' => 16.0,
                'recommendation' => 'over',
                'confidence' => 65,
                'expected_value' => 2.0,
                'probability_over' => 0.6,
                'probability_under' => 0.4,
                'feature_snapshot' => ['adjusted_value' => 16.0, 'rest_days' => 2],
                'model_version' => 'bootstrap.1',
                'actual_value' => 17.0,
                'correct' => true,
                'predicted_at' => now(),
                'graded_at' => now(),
            ]);
        }

        $result = app(PredictionFeedbackService::class)->run();
        $this->assertSame('skipped', $result['status']);
        $this->assertFalse($result['promoted']);
        $this->assertSame(0, PredictionChampionReport::query()->count());
        $this->assertSame(1, PredictionFeedbackRun::query()->count());
    }

    public function test_champion_reports_api_list_and_show(): void
    {
        $run = PredictionFeedbackRun::query()->create([
            'run_uuid' => (string) Str::uuid(),
            'status' => 'completed',
            'promoted' => true,
            'champion_version' => '2026-07-26.1',
            'sample_size' => 50,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);

        $report = PredictionChampionReport::query()->create([
            'report_uuid' => (string) Str::uuid(),
            'feedback_run_id' => $run->id,
            'from_version' => 'bootstrap.1',
            'to_version' => '2026-07-26.1',
            'promoted_at' => now(),
            'headline' => 'Promoted bootstrap.1 → 2026-07-26.1 — Brier 0.220 → 0.210',
            'summary_markdown' => "# Champion promotion\n\n## Why promoted\n- Holdout Brier improved\n",
            'changes' => [
                ['path' => 'calibration.shrinkage', 'from' => 0.0, 'to' => 0.1, 'why' => null],
            ],
            'metrics_before' => ['brier' => 0.22, 'hit_rate' => 0.5],
            'metrics_after' => ['brier' => 0.21, 'hit_rate' => 0.52],
            'reasons' => ['holdout_brier_improved'],
            'calibration_buckets' => [
                ['bucket' => '0.60-0.65', 'predicted' => 0.62, 'observed' => 0.55, 'count' => 10],
            ],
        ]);

        $list = $this->getJson('/api/wnba/predictions/champion-reports');
        $list->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($list->json('data.reports'));
        $this->assertSame($report->report_uuid, $list->json('data.reports.0.report_uuid'));

        $show = $this->getJson('/api/wnba/predictions/champion-reports/'.$report->report_uuid);
        $show->assertOk()
            ->assertJsonPath('data.headline', $report->headline)
            ->assertJsonPath('data.changes.0.path', 'calibration.shrinkage');
    }

    public function test_accuracy_dashboard_includes_model_summary(): void
    {
        $response = $this->getJson('/api/wnba/predictions/accuracy?timezone=America/New_York');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'model' => [
                        'model_version',
                        'gates',
                        'calibration',
                        'auto_tune_enabled',
                    ],
                ],
            ]);
    }
}
