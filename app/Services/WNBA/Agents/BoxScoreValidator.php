<?php

namespace App\Services\WNBA\Agents;

/**
 * Basketball-logic validation for box-score records before acceptance.
 * Returns a status of valid, warning, or invalid plus the specific failures,
 * so bad rows are flagged instead of silently flowing into predictions.
 */
class BoxScoreValidator
{
    public const STATUS_VALID = 'valid';

    public const STATUS_WARNING = 'warning';

    public const STATUS_INVALID = 'invalid';

    private const MAX_MINUTES_WITH_OT = 60; // 40 regulation + generous OT allowance

    /**
     * @param  array<string, mixed>  $record  normalized player box-score record
     * @return array{status: string, failures: array<int, string>}
     */
    public function validatePlayerRecord(array $record): array
    {
        $failures = [];
        $warnings = [];

        $fgm = $this->int($record, 'field_goals_made');
        $fga = $this->int($record, 'field_goals_attempted');
        $tpm = $this->int($record, 'three_point_field_goals_made');
        $tpa = $this->int($record, 'three_point_field_goals_attempted');
        $ftm = $this->int($record, 'free_throws_made');
        $fta = $this->int($record, 'free_throws_attempted');
        $oreb = $this->int($record, 'offensive_rebounds');
        $dreb = $this->int($record, 'defensive_rebounds');
        $reb = $this->int($record, 'rebounds');
        $points = $this->int($record, 'points');

        if ($fgm > $fga) {
            $failures[] = "FGM {$fgm} > FGA {$fga}";
        }
        if ($tpm > $tpa) {
            $failures[] = "3PM {$tpm} > 3PA {$tpa}";
        }
        if ($tpm > $fgm) {
            $failures[] = "3PM {$tpm} > FGM {$fgm}";
        }
        if ($ftm > $fta) {
            $failures[] = "FTM {$ftm} > FTA {$fta}";
        }

        foreach (['field_goals_made', 'field_goals_attempted', 'three_point_field_goals_made',
            'three_point_field_goals_attempted', 'free_throws_made', 'free_throws_attempted',
            'offensive_rebounds', 'defensive_rebounds', 'rebounds', 'assists', 'steals',
            'blocks', 'turnovers', 'fouls', 'points'] as $stat) {
            if ($this->int($record, $stat) < 0) {
                $failures[] = "negative {$stat}";
            }
        }

        $expectedPoints = 2 * ($fgm - $tpm) + 3 * $tpm + $ftm;
        if ($points !== $expectedPoints) {
            $failures[] = "points {$points} != computed {$expectedPoints}";
        }

        if ($oreb + $dreb !== $reb && ($oreb > 0 || $dreb > 0)) {
            $warnings[] = "OREB {$oreb} + DREB {$dreb} != REB {$reb}";
        }

        $minutes = $this->parseMinutes($record['minutes'] ?? null);
        if ($minutes !== null && $minutes > self::MAX_MINUTES_WITH_OT) {
            $failures[] = "minutes {$minutes} exceed possible game duration";
        }

        $didNotPlay = (bool) ($record['did_not_play'] ?? false);
        if ($didNotPlay && ($points > 0 || $reb > 0 || $fga > 0 || ($minutes !== null && $minutes > 0))) {
            $warnings[] = 'DNP player has playing statistics';
        }

        return [
            'status' => $failures !== []
                ? self::STATUS_INVALID
                : ($warnings !== [] ? self::STATUS_WARNING : self::STATUS_VALID),
            'failures' => array_merge($failures, $warnings),
        ];
    }

    /**
     * @param  array<string, mixed>  $record  normalized team box-score record
     * @return array{status: string, failures: array<int, string>}
     */
    public function validateTeamRecord(array $record): array
    {
        $failures = [];

        $fgm = $this->int($record, 'field_goals_made');
        $fga = $this->int($record, 'field_goals_attempted');
        $tpm = $this->int($record, 'three_point_field_goals_made');
        $tpa = $this->int($record, 'three_point_field_goals_attempted');
        $ftm = $this->int($record, 'free_throws_made');
        $fta = $this->int($record, 'free_throws_attempted');

        if ($fgm > $fga) {
            $failures[] = "FGM {$fgm} > FGA {$fga}";
        }
        if ($tpm > $tpa) {
            $failures[] = "3PM {$tpm} > 3PA {$tpa}";
        }
        if ($ftm > $fta) {
            $failures[] = "FTM {$ftm} > FTA {$fta}";
        }

        if (($record['team_id'] ?? null) !== null
            && ($record['opponent_team_id'] ?? null) !== null
            && (string) $record['team_id'] === (string) $record['opponent_team_id']) {
            $failures[] = 'team and opponent are the same';
        }

        return [
            'status' => $failures !== [] ? self::STATUS_INVALID : self::STATUS_VALID,
            'failures' => $failures,
        ];
    }

    public function parseMinutes(mixed $minutes): ?float
    {
        if ($minutes === null || $minutes === '') {
            return null;
        }

        if (is_numeric($minutes)) {
            return (float) $minutes;
        }

        if (is_string($minutes) && str_contains($minutes, ':')) {
            [$mins, $secs] = array_pad(explode(':', $minutes, 2), 2, '0');

            if (is_numeric($mins) && is_numeric($secs)) {
                return round((float) $mins + ((float) $secs) / 60, 2);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function int(array $record, string $key): int
    {
        $value = $record[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
