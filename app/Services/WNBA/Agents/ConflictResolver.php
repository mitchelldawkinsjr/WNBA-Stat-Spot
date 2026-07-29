<?php

namespace App\Services\WNBA\Agents;

use App\Models\WnbaDataConflict;

/**
 * Field-level conflict resolution between sources. Replaces silent
 * last-write-wins: when an incoming value disagrees with a stored value from a
 * different source, the winner is chosen by configured source priority and the
 * disagreement is logged with both candidates.
 */
class ConflictResolver
{
    private ?AgentRunReporter $reporter = null;

    public function setReporter(?AgentRunReporter $reporter): void
    {
        $this->reporter = $reporter;
    }

    /**
     * Decide whether incoming stat fields may overwrite existing ones.
     *
     * @param  array<string, mixed>  $existingValues  field => stored value
     * @param  array<string, mixed>  $incomingValues  field => incoming value
     * @return bool true when the incoming source wins and may overwrite
     */
    public function resolveStatConflicts(
        string $entityType,
        string $entityKey,
        ?string $existingSource,
        string $incomingSource,
        array $existingValues,
        array $incomingValues,
    ): bool {
        $incomingWins = $this->sourceWins($incomingSource, $existingSource);

        // Same source updating its own record (e.g. a live box going final, or
        // a source correction) is not a conflict.
        if ($existingSource === null || $existingSource === $incomingSource) {
            return true;
        }

        // Live scores only move forward. Prefer the higher team_score /
        // opponent_team_score regardless of source priority so Tank01 live
        // updates are not blocked by an earlier ESPN placeholder write.
        if ($this->incomingScoresAreAhead($existingValues, $incomingValues)) {
            return true;
        }

        $winningSource = $incomingWins ? $incomingSource : $existingSource;

        foreach ($incomingValues as $field => $incomingValue) {
            $existingValue = $existingValues[$field] ?? null;

            if ($existingValue === null || $this->valuesMatch($existingValue, $incomingValue)) {
                continue;
            }

            $selected = $incomingWins ? $incomingValue : $existingValue;

            WnbaDataConflict::create([
                'entity_type' => $entityType,
                'entity_key' => $entityKey,
                'field' => $field,
                'candidates' => [
                    ['source_id' => $existingSource, 'value' => $existingValue],
                    ['source_id' => $incomingSource, 'value' => $incomingValue, 'observed_at' => now()->toIso8601String()],
                ],
                'selected_value' => $selected === null ? null : (string) $selected,
                'selected_source' => $winningSource,
                'resolution_reason' => sprintf(
                    'source priority: %s (%d) over %s (%d)',
                    $winningSource,
                    $this->priority($winningSource),
                    $incomingWins ? $existingSource : $incomingSource,
                    $this->priority($incomingWins ? $existingSource : $incomingSource),
                ),
                'confidence' => $this->confidenceFor($winningSource),
                'requires_review' => $this->requiresReview($existingValue, $incomingValue),
                'resolved_at' => now(),
                'agent_run_id' => $this->reporter?->runId(),
            ]);

            $this->reporter?->increment('conflicts_detected');
            $this->reporter?->increment('conflicts_resolved');
        }

        return $incomingWins;
    }

    public function priority(?string $source): int
    {
        if ($source === null) {
            return PHP_INT_MAX;
        }

        $priorities = config('wnba.agents.source_priority', []);

        return (int) ($priorities[$source] ?? PHP_INT_MAX - 1);
    }

    private function sourceWins(string $incoming, ?string $existing): bool
    {
        return $this->priority($incoming) <= $this->priority($existing);
    }

    private function valuesMatch(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return $a == $b;
    }

    /**
     * @param  array<string, mixed>  $existingValues
     * @param  array<string, mixed>  $incomingValues
     */
    private function incomingScoresAreAhead(array $existingValues, array $incomingValues): bool
    {
        $scoreFields = ['team_score', 'opponent_team_score'];
        $sawScoreField = false;
        $ahead = false;

        foreach ($scoreFields as $field) {
            if (! array_key_exists($field, $incomingValues)) {
                continue;
            }
            $sawScoreField = true;
            $existing = $existingValues[$field] ?? null;
            $incoming = $incomingValues[$field];
            if (! is_numeric($incoming)) {
                return false;
            }
            if ($existing !== null && is_numeric($existing) && (float) $incoming < (float) $existing) {
                return false;
            }
            if ($existing === null || (float) $incoming > (float) $existing) {
                $ahead = true;
            }
        }

        return $sawScoreField && $ahead;
    }

    private function confidenceFor(string $winningSource): float
    {
        return match ($this->priority($winningSource)) {
            1 => 0.95,
            2 => 0.85,
            3 => 0.75,
            default => 0.60,
        };
    }

    /**
     * Large disagreements on counting stats indicate more than provider lag
     * and should be reviewed by a human.
     */
    private function requiresReview(mixed $existing, mixed $incoming): bool
    {
        if (! is_numeric($existing) || ! is_numeric($incoming)) {
            return true;
        }

        return abs((float) $existing - (float) $incoming) > 5;
    }
}
