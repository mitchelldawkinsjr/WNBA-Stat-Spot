<?php

namespace App\Services\WNBA\Predictions;

use App\Models\PredictionFeedbackRun;
use App\Models\PredictionModelParam;
use App\Models\TrackedPropPrediction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PredictionFeedbackService
{
    public function __construct(
        private PredictionAccuracyService $accuracyService,
        private PredictionModelParamStore $paramStore,
        private ChampionReportBuilder $reportBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $run = PredictionFeedbackRun::query()->create([
            'run_uuid' => (string) Str::uuid(),
            'status' => 'running',
            'promoted' => false,
            'started_at' => now(),
        ]);

        try {
            $this->accuracyService->gradePendingPredictions();

            $windowSize = (int) config('wnba.predictions.eval_window_size', 120);
            $minSamples = (int) config('wnba.predictions.min_learn_samples', 40);

            $props = TrackedPropPrediction::query()
                ->graded()
                ->whereNotNull('correct')
                ->whereNotNull('probability_over')
                ->orderByDesc('graded_at')
                ->limit($windowSize)
                ->get();

            if ($props->count() < $minSamples) {
                return $this->finish($run, 'aborted', [
                    'notes' => ['Insufficient graded props to learn', "have={$props->count()}", "need={$minSamples}"],
                    'sample_size' => $props->count(),
                    'champion_version' => $this->paramStore->championVersion(),
                ]);
            }

            $champion = $this->paramStore->champion();
            $train = $props->filter(fn (TrackedPropPrediction $p) => $p->id % 2 === 0)->values();
            $holdout = $props->filter(fn (TrackedPropPrediction $p) => $p->id % 2 === 1)->values();
            if ($holdout->count() < 10) {
                $holdout = $props;
                $train = $props;
            }

            $championMetrics = $this->scoreProps($holdout, (float) $champion['calibration']['shrinkage']);
            $calibration = $this->optimizeShrinkage($train);
            $gates = $this->optimizeGates($train, $champion['gates']);
            [$adjustments, $changeMeta] = $this->tuneAdjustments($train, $champion['adjustments']);

            $challengerParams = [
                'adjustments' => $adjustments,
                'calibration' => ['shrinkage' => $calibration['shrinkage']],
                'gates' => $gates,
            ];

            $challengerMetrics = $this->scoreProps($holdout, $calibration['shrinkage']);
            $challengerVersion = now()->format('Y-m-d').'.'.$run->id;

            $autoTune = (bool) config('wnba.predictions.auto_tune_enabled', true);
            $promotion = $this->shouldPromote($championMetrics, $challengerMetrics, $props->count());

            $challengerRow = PredictionModelParam::query()->create([
                'version' => $challengerVersion,
                'status' => 'challenger',
                'params' => $challengerParams,
                'metrics' => $challengerMetrics,
                'sample_size' => $props->count(),
                'window_start' => $props->min('graded_at'),
                'window_end' => $props->max('graded_at'),
            ]);

            if (! $autoTune) {
                $challengerRow->update(['status' => 'retired']);

                return $this->finish($run, 'skipped', [
                    'promoted' => false,
                    'sample_size' => $props->count(),
                    'champion_version' => $champion['version'],
                    'challenger_version' => $challengerVersion,
                    'challenger_params' => $challengerParams,
                    'metrics' => [
                        'champion' => $championMetrics,
                        'challenger' => $challengerMetrics,
                        'calibration_buckets' => $calibration['buckets'],
                    ],
                    'notes' => ['auto_tune_disabled'],
                ]);
            }

            if (! $promotion['promote']) {
                $challengerRow->update(['status' => 'retired']);

                return $this->finish($run, 'completed', [
                    'promoted' => false,
                    'sample_size' => $props->count(),
                    'champion_version' => $champion['version'],
                    'challenger_version' => $challengerVersion,
                    'challenger_params' => $challengerParams,
                    'metrics' => [
                        'champion' => $championMetrics,
                        'challenger' => $challengerMetrics,
                        'calibration_buckets' => $calibration['buckets'],
                        'promotion_reasons' => $promotion['reasons'],
                    ],
                    'notes' => ['challenger_not_promoted'],
                ]);
            }

            $report = null;
            DB::transaction(function () use (
                $champion,
                $challengerRow,
                $challengerParams,
                $challengerMetrics,
                $championMetrics,
                $promotion,
                $calibration,
                $changeMeta,
                $run,
                &$report
            ) {
                PredictionModelParam::query()
                    ->where('status', 'champion')
                    ->update(['status' => 'retired']);

                $challengerRow->update([
                    'status' => 'champion',
                    'promoted_at' => now(),
                    'params' => $challengerParams,
                    'metrics' => $challengerMetrics,
                ]);

                $report = $this->reportBuilder->build(
                    $run,
                    $champion['version'],
                    $challengerRow->version,
                    $champion,
                    array_merge($challengerParams, ['version' => $challengerRow->version]),
                    $championMetrics,
                    $challengerMetrics,
                    $promotion['reasons'],
                    $calibration['buckets'],
                    $changeMeta
                );
            });

            $this->paramStore->forgetCache();

            return $this->finish($run, 'completed', [
                'promoted' => true,
                'sample_size' => $props->count(),
                'champion_version' => $challengerRow->version,
                'challenger_version' => $challengerRow->version,
                'champion_report_id' => $report?->id,
                'challenger_params' => $challengerParams,
                'metrics' => [
                    'champion' => $championMetrics,
                    'challenger' => $challengerMetrics,
                    'calibration_buckets' => $calibration['buckets'],
                    'promotion_reasons' => $promotion['reasons'],
                ],
                'notes' => ['champion_promoted'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Prediction feedback run failed', [
                'run_uuid' => $run->run_uuid,
                'error' => $e->getMessage(),
            ]);

            return $this->finish($run, 'aborted', [
                'notes' => ['exception', $e->getMessage()],
                'champion_version' => $this->paramStore->championVersion(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function finish(PredictionFeedbackRun $run, string $status, array $payload): array
    {
        $run->fill([
            'status' => $status,
            'promoted' => (bool) ($payload['promoted'] ?? false),
            'champion_version' => $payload['champion_version'] ?? null,
            'challenger_version' => $payload['challenger_version'] ?? null,
            'champion_report_id' => $payload['champion_report_id'] ?? null,
            'sample_size' => (int) ($payload['sample_size'] ?? 0),
            'metrics' => $payload['metrics'] ?? null,
            'challenger_params' => $payload['challenger_params'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'finished_at' => now(),
        ]);
        $run->save();

        return array_merge(['run_uuid' => $run->run_uuid, 'status' => $status], $payload);
    }

    /**
     * @return array{shrinkage: float, buckets: list<array<string, mixed>>}
     */
    private function optimizeShrinkage(Collection $props): array
    {
        $bestS = 0.0;
        $bestBrier = PHP_FLOAT_MAX;

        for ($s = 0.0; $s <= 0.5 + 1e-9; $s += 0.05) {
            $metrics = $this->scoreProps($props, $s);
            if ($metrics['brier'] < $bestBrier) {
                $bestBrier = $metrics['brier'];
                $bestS = $s;
            }
        }

        return [
            'shrinkage' => round($bestS, 2),
            'buckets' => $this->calibrationBuckets($props, $bestS),
        ];
    }

    /**
     * @param  array{min_confidence: float, min_ev: float, by_stat: array<string, mixed>}  $current
     * @return array{min_confidence: float, min_ev: float, by_stat: array<string, array{min_confidence: float, min_ev: float}>}
     */
    private function optimizeGates(Collection $props, array $current): array
    {
        $clamps = config('wnba.predictions.clamps');
        $global = $this->bestGateForSlice($props, $current, $clamps);

        $byStat = [];
        foreach ($props->groupBy('stat_type') as $stat => $slice) {
            if ($slice->count() < 15) {
                continue;
            }
            $byStat[$stat] = $this->bestGateForSlice($slice, $current, $clamps);
        }

        return [
            'min_confidence' => $global['min_confidence'],
            'min_ev' => $global['min_ev'],
            'by_stat' => $byStat,
        ];
    }

    /**
     * @param  array{min_confidence: float, min_ev: float, by_stat?: array}  $current
     * @param  array<string, array{0: float, 1: float}>  $clamps
     * @return array{min_confidence: float, min_ev: float}
     */
    private function bestGateForSlice(Collection $props, array $current, array $clamps): array
    {
        $minKeep = max(1, (int) floor($props->count() * 0.2));
        $best = [
            'min_confidence' => (float) $current['min_confidence'],
            'min_ev' => (float) $current['min_ev'],
        ];
        $bestHit = -1.0;

        $confRange = $clamps['min_confidence'] ?? [0.5, 0.8];
        $evRange = $clamps['min_ev'] ?? [0.0, 0.15];

        for ($c = $confRange[0]; $c <= $confRange[1] + 1e-9; $c += 0.05) {
            for ($e = $evRange[0]; $e <= $evRange[1] + 1e-9; $e += 0.01) {
                $kept = $props->filter(function (TrackedPropPrediction $p) use ($c, $e) {
                    $confidence = $this->normalizeConfidence($p->confidence);
                    $ev = abs((float) ($p->expected_value ?? 0));
                    // expected_value is often stored as percentage points in today's props
                    if ($ev > 1) {
                        $ev = $ev / 100;
                    }

                    return $confidence >= $c && $ev >= $e;
                });

                if ($kept->count() < $minKeep) {
                    continue;
                }

                $hit = $kept->where('correct', true)->count() / $kept->count();
                if ($hit > $bestHit) {
                    $bestHit = $hit;
                    $best = [
                        'min_confidence' => round($c, 2),
                        'min_ev' => round($e, 2),
                    ];
                }
            }
        }

        return $best;
    }

    /**
     * @param  array{rest_b2b: float, rest_well: float, home: float, opponent_scale: float}  $current
     * @return array{0: array{rest_b2b: float, rest_well: float, home: float, opponent_scale: float}, 1: array<string, string>}
     */
    private function tuneAdjustments(Collection $props, array $current): array
    {
        $step = (float) config('wnba.predictions.max_weight_step', 0.03);
        $clamps = config('wnba.predictions.clamps');
        $next = $current;
        $meta = [];

        $residuals = [
            'rest_b2b' => [],
            'rest_well' => [],
            'home' => [],
            'opponent_scale' => [],
        ];

        foreach ($props as $prop) {
            $snapshot = $prop->feature_snapshot ?? [];
            $predicted = (float) ($snapshot['adjusted_value'] ?? $prop->predicted_value ?? 0);
            $actual = (float) ($prop->actual_value ?? 0);
            if ($predicted <= 0) {
                continue;
            }
            $ratio = $actual / $predicted;
            $restDays = $snapshot['rest_days'] ?? null;
            $homeAway = $snapshot['home_away'] ?? null;

            if ($restDays !== null && (int) $restDays <= 1) {
                $residuals['rest_b2b'][] = $ratio;
            } elseif ($restDays !== null && (int) $restDays >= 4) {
                $residuals['rest_well'][] = $ratio;
            }
            if ($homeAway === 'home') {
                $residuals['home'][] = $ratio;
            }
            if (isset($snapshot['opponent_defense_rating'])) {
                $residuals['opponent_scale'][] = $ratio;
            }
        }

        foreach ($residuals as $key => $values) {
            if (count($values) < 8) {
                continue;
            }
            $mean = array_sum($values) / count($values);
            $delta = max(-$step, min($step, $mean - 1.0));
            if (abs($delta) < 0.005) {
                continue;
            }

            $candidate = (float) $current[$key] + $delta;
            if ($key === 'opponent_scale') {
                // residual on predicted value is coarse for scale; nudge gently toward mean
                $candidate = (float) $current[$key] * (1 + max(-$step, min($step, $mean - 1.0)));
            }

            [$lo, $hi] = $clamps[$key] ?? [$candidate, $candidate];
            $clamped = max($lo, min($hi, $candidate));
            if (abs($clamped - (float) $current[$key]) < 1e-9) {
                continue;
            }

            $next[$key] = round($clamped, 4);
            $direction = $mean > 1 ? 'under-predicted' : 'over-predicted';
            $meta['adjustments.'.$key] = sprintf(
                'slice %s on average (mean actual/predicted=%.3f); stepped %+0.3f',
                $direction,
                $mean,
                $next[$key] - (float) $current[$key]
            );
        }

        return [$next, $meta];
    }

    /**
     * @return array{promote: bool, reasons: list<string>}
     */
    public function shouldPromote(array $championMetrics, array $challengerMetrics, int $sampleSize): array
    {
        $minSamples = (int) config('wnba.predictions.min_learn_samples', 40);
        if ($sampleSize < $minSamples) {
            return ['promote' => false, 'reasons' => ['insufficient_samples']];
        }

        $cfg = config('wnba.predictions.promotion');
        $brierImprove = (float) ($cfg['brier_improvement'] ?? 0.002);
        $hitImprove = (float) ($cfg['hit_rate_improvement'] ?? 0.02);
        $maxBrierReg = (float) ($cfg['max_brier_regression'] ?? 0.005);

        $reasons = [];
        $champBrier = (float) $championMetrics['brier'];
        $chalBrier = (float) $challengerMetrics['brier'];
        $champHit = (float) $championMetrics['hit_rate'];
        $chalHit = (float) $challengerMetrics['hit_rate'];

        if ($chalBrier <= $champBrier - $brierImprove) {
            $reasons[] = 'holdout_brier_improved';
        }

        if ($chalHit >= $champHit + $hitImprove && $chalBrier <= $champBrier + $maxBrierReg) {
            $reasons[] = 'holdout_hit_rate_improved';
        }

        return [
            'promote' => $reasons !== [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{brier: float, log_loss: float, hit_rate: float, sample_size: int, window_start: ?string, window_end: ?string}
     */
    public function scoreProps(Collection $props, float $shrinkage): array
    {
        $brierSum = 0.0;
        $logLossSum = 0.0;
        $n = 0;
        $correct = 0;

        foreach ($props as $prop) {
            $pOver = $this->applyShrinkage((float) $prop->probability_over, $shrinkage);
            // Normalize if stored as percentage
            if ($pOver > 1) {
                $pOver = $pOver / 100;
            }
            $pOver = max(1e-6, min(1 - 1e-6, $pOver));

            $sideProb = $prop->recommendation === 'under' ? (1 - $pOver) : $pOver;
            $outcome = $prop->correct ? 1.0 : 0.0;

            $brierSum += ($sideProb - $outcome) ** 2;
            $logLossSum += -($outcome * log($sideProb) + (1 - $outcome) * log(1 - $sideProb));
            $n++;
            if ($prop->correct) {
                $correct++;
            }
        }

        $windowStart = $props->min('graded_at');
        $windowEnd = $props->max('graded_at');

        return [
            'brier' => $n > 0 ? round($brierSum / $n, 6) : 1.0,
            'log_loss' => $n > 0 ? round($logLossSum / $n, 6) : 10.0,
            'hit_rate' => $n > 0 ? round($correct / $n, 6) : 0.0,
            'sample_size' => $n,
            'window_start' => $this->formatTimestamp($windowStart),
            'window_end' => $this->formatTimestamp($windowEnd),
        ];
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toIso8601String();
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<array{bucket: string, predicted: float, observed: float, count: int}>
     */
    private function calibrationBuckets(Collection $props, float $shrinkage): array
    {
        $buckets = [];
        for ($i = 0; $i < 20; $i++) {
            $lo = $i * 0.05;
            $hi = $lo + 0.05;
            $buckets[sprintf('%.2f-%.2f', $lo, $hi)] = ['sum_p' => 0.0, 'sum_y' => 0.0, 'count' => 0];
        }

        foreach ($props as $prop) {
            $pOver = (float) $prop->probability_over;
            if ($pOver > 1) {
                $pOver /= 100;
            }
            $sideProb = $prop->recommendation === 'under'
                ? 1 - $this->applyShrinkage($pOver, $shrinkage)
                : $this->applyShrinkage($pOver, $shrinkage);
            $sideProb = max(0.0, min(0.999, $sideProb));
            $idx = min(19, (int) floor($sideProb / 0.05));
            $lo = $idx * 0.05;
            $key = sprintf('%.2f-%.2f', $lo, $lo + 0.05);
            $buckets[$key]['sum_p'] += $sideProb;
            $buckets[$key]['sum_y'] += $prop->correct ? 1.0 : 0.0;
            $buckets[$key]['count']++;
        }

        $out = [];
        foreach ($buckets as $key => $data) {
            if ($data['count'] === 0) {
                continue;
            }
            $out[] = [
                'bucket' => $key,
                'predicted' => round($data['sum_p'] / $data['count'], 4),
                'observed' => round($data['sum_y'] / $data['count'], 4),
                'count' => $data['count'],
            ];
        }

        return $out;
    }

    public function applyShrinkage(float $probability, float $shrinkage): float
    {
        $shrinkage = max(0.0, min(0.5, $shrinkage));

        return 0.5 + ((1.0 - $shrinkage) * ($probability - 0.5));
    }

    private function normalizeConfidence(?float $confidence): float
    {
        $c = (float) ($confidence ?? 0);
        if ($c > 1) {
            $c = $c / 100;
        }

        return $c;
    }
}
