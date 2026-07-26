<?php

namespace App\Services\WNBA\Predictions;

use App\Models\PredictionChampionReport;
use App\Models\PredictionFeedbackRun;
use Illuminate\Support\Str;

class ChampionReportBuilder
{
    /**
     * @param  array<string, mixed>  $fromParams  normalized champion params before promotion
     * @param  array<string, mixed>  $toParams  normalized challenger params after promotion
     * @param  array<string, mixed>  $metricsBefore
     * @param  array<string, mixed>  $metricsAfter
     * @param  list<string>  $reasons
     * @param  list<array<string, mixed>>  $calibrationBuckets
     * @param  array<string, mixed>  $changeMeta  optional residual notes keyed by param path
     */
    public function build(
        PredictionFeedbackRun $run,
        string $fromVersion,
        string $toVersion,
        array $fromParams,
        array $toParams,
        array $metricsBefore,
        array $metricsAfter,
        array $reasons,
        array $calibrationBuckets = [],
        array $changeMeta = []
    ): PredictionChampionReport {
        $changes = $this->diffParams($fromParams, $toParams, $changeMeta);
        $brierBefore = $metricsBefore['brier'] ?? null;
        $brierAfter = $metricsAfter['brier'] ?? null;
        $hitBefore = $metricsBefore['hit_rate'] ?? null;
        $hitAfter = $metricsAfter['hit_rate'] ?? null;

        $headlineParts = ["Promoted {$fromVersion} → {$toVersion}"];
        if ($brierBefore !== null && $brierAfter !== null) {
            $headlineParts[] = sprintf('Brier %.3f → %.3f', $brierBefore, $brierAfter);
        }
        if ($hitBefore !== null && $hitAfter !== null) {
            $headlineParts[] = sprintf(
                'hit rate %.1f%% → %.1f%%',
                $hitBefore * 100,
                $hitAfter * 100
            );
        }

        $headline = implode(' — ', $headlineParts);
        $summary = $this->renderMarkdown(
            $fromVersion,
            $toVersion,
            $reasons,
            $changes,
            $metricsBefore,
            $metricsAfter
        );

        return PredictionChampionReport::query()->create([
            'report_uuid' => (string) Str::uuid(),
            'feedback_run_id' => $run->id,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'promoted_at' => now(),
            'headline' => $headline,
            'summary_markdown' => $summary,
            'changes' => $changes,
            'metrics_before' => $metricsBefore,
            'metrics_after' => $metricsAfter,
            'reasons' => $reasons,
            'calibration_buckets' => $calibrationBuckets,
        ]);
    }

    /**
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @param  array<string, mixed>  $changeMeta
     * @return list<array{path: string, from: mixed, to: mixed, why: string|null}>
     */
    public function diffParams(array $from, array $to, array $changeMeta = []): array
    {
        $flatFrom = $this->flatten($from);
        $flatTo = $this->flatten($to);
        $keys = array_unique(array_merge(array_keys($flatFrom), array_keys($flatTo)));
        sort($keys);

        $changes = [];
        foreach ($keys as $path) {
            $old = $flatFrom[$path] ?? null;
            $new = $flatTo[$path] ?? null;
            if ($this->valuesEqual($old, $new)) {
                continue;
            }

            $changes[] = [
                'path' => $path,
                'from' => $old,
                'to' => $new,
                'why' => $changeMeta[$path] ?? null,
            ];
        }

        return $changes;
    }

    /**
     * @param  list<string>  $reasons
     * @param  list<array{path: string, from: mixed, to: mixed, why: string|null}>  $changes
     * @param  array<string, mixed>  $metricsBefore
     * @param  array<string, mixed>  $metricsAfter
     */
    private function renderMarkdown(
        string $fromVersion,
        string $toVersion,
        array $reasons,
        array $changes,
        array $metricsBefore,
        array $metricsAfter
    ): string {
        $lines = [];
        $lines[] = "# Champion promotion: {$fromVersion} → {$toVersion}";
        $lines[] = '';
        $lines[] = '## Why promoted';
        if ($reasons === []) {
            $lines[] = '- Holdout metrics improved enough to replace the previous champion.';
        } else {
            foreach ($reasons as $reason) {
                $lines[] = '- '.$this->humanizeReason($reason, $metricsBefore, $metricsAfter);
            }
        }

        $lines[] = '';
        $lines[] = '## What changed';
        if ($changes === []) {
            $lines[] = '- No parameter values changed (version bump only).';
        } else {
            foreach ($changes as $change) {
                $from = $this->formatValue($change['from']);
                $to = $this->formatValue($change['to']);
                $line = "- `{$change['path']}`: {$from} → {$to}";
                if (! empty($change['why'])) {
                    $line .= " — {$change['why']}";
                }
                $lines[] = $line;
            }
        }

        $lines[] = '';
        $lines[] = '## Eval window';
        $lines[] = sprintf(
            '- Sample size: %s',
            $metricsAfter['sample_size'] ?? $metricsBefore['sample_size'] ?? 'n/a'
        );
        if (isset($metricsAfter['window_start'], $metricsAfter['window_end'])) {
            $lines[] = sprintf(
                '- Window: %s → %s',
                $metricsAfter['window_start'],
                $metricsAfter['window_end']
            );
        }
        $lines[] = sprintf(
            '- Brier: %s → %s',
            $this->formatValue($metricsBefore['brier'] ?? null),
            $this->formatValue($metricsAfter['brier'] ?? null)
        );
        $lines[] = sprintf(
            '- Hit rate: %s → %s',
            $this->formatPct($metricsBefore['hit_rate'] ?? null),
            $this->formatPct($metricsAfter['hit_rate'] ?? null)
        );
        $lines[] = sprintf(
            '- Log loss: %s → %s',
            $this->formatValue($metricsBefore['log_loss'] ?? null),
            $this->formatValue($metricsAfter['log_loss'] ?? null)
        );

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $metricsBefore
     * @param  array<string, mixed>  $metricsAfter
     */
    private function humanizeReason(string $reason, array $metricsBefore, array $metricsAfter): string
    {
        return match ($reason) {
            'holdout_brier_improved' => sprintf(
                'Holdout Brier improved (%.3f → %.3f)',
                (float) ($metricsBefore['brier'] ?? 0),
                (float) ($metricsAfter['brier'] ?? 0)
            ),
            'holdout_hit_rate_improved' => sprintf(
                'Holdout hit rate improved (%.1f%% → %.1f%%) without meaningful Brier regression',
                ((float) ($metricsBefore['hit_rate'] ?? 0)) * 100,
                ((float) ($metricsAfter['hit_rate'] ?? 0)) * 100
            ),
            default => str_replace('_', ' ', $reason),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($key === 'version') {
                continue;
            }
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value) && $this->isAssoc($value)) {
                $out = array_merge($out, $this->flatten($value, $path));
            } else {
                $out[$path] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<mixed>  $array
     */
    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 1e-9;
        }

        return $a === $b;
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'n/a';
        }
        if (is_float($value) || is_int($value)) {
            return rtrim(rtrim(sprintf('%.4f', (float) $value), '0'), '.');
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value) ?: '[]';
        }

        return (string) $value;
    }

    private function formatPct(mixed $value): string
    {
        if ($value === null || ! is_numeric($value)) {
            return 'n/a';
        }

        return sprintf('%.1f%%', ((float) $value) * 100);
    }
}
