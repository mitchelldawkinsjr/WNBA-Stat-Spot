<?php

namespace App\Services\WNBA\Predictions;

/**
 * Resolve a placeable prop line: live sportsbook first, else market-like player average.
 */
class PropLineResolver
{
    /**
     * @param  array<string, mixed>  $oddsData
     * @param  array{recent_average?: float|int, season_average?: float|int, suggested_line?: float|int}  $recentStats
     * @return array{line: float|null, source: string}
     */
    public function resolve(array $oddsData, array $recentStats): array
    {
        if (($oddsData['available'] ?? false) && isset($oddsData['line']) && is_numeric($oddsData['line'])) {
            $bookLine = (float) $oddsData['line'];
            if ($bookLine > 0) {
                return ['line' => $bookLine, 'source' => 'odds_api'];
            }
        }

        if (isset($recentStats['suggested_line']) && is_numeric($recentStats['suggested_line'])) {
            $avgLine = (float) $recentStats['suggested_line'];
            if ($avgLine > 0) {
                return ['line' => $avgLine, 'source' => 'player_average'];
            }
        }

        $recent = (float) ($recentStats['recent_average'] ?? 0);
        $season = (float) ($recentStats['season_average'] ?? $recent);
        if ($recent <= 0 && $season <= 0) {
            return ['line' => null, 'source' => 'unavailable'];
        }

        $blended = ($recent + $season) / 2;

        return [
            'line' => max(0.5, round($blended * 2) / 2),
            'source' => 'player_average',
        ];
    }
}
