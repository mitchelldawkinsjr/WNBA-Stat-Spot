<?php

namespace App\Services\WNBA\Predictions;

use App\Services\WNBA\Data\Support\TeamForeignKeyResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Empirical over/under hit rates vs a prop line.
 * Hit = value > line (equal to line is not a hit / push treated as miss).
 */
class PropHitRateCalculator
{
    private const ALLOWED_STATS = ['points', 'rebounds', 'assists', 'steals', 'blocks'];

    /**
     * @return array{
     *   l5: array{hits: int, games: int, rate: float|null},
     *   l10: array{hits: int, games: int, rate: float|null},
     *   l20: array{hits: int, games: int, rate: float|null},
     *   season: array{hits: int, games: int, rate: float|null},
     *   h2h: array{hits: int, games: int, rate: float|null}|null,
     *   recent_games: list<array{game_date: string|null, value: float, over: bool}>
     * }
     */
    public function calculate(
        int $playerId,
        string $statType,
        float $line,
        ?string $opponentTeamId = null,
        ?int $season = null,
    ): array {
        $statType = in_array($statType, self::ALLOWED_STATS, true) ? $statType : 'points';
        $season = $season ?? (int) config('wnba.seasons.current_season');

        $games = $this->fetchSeasonGames($playerId, $statType, $season);
        $empty = $this->emptyWindow();

        if ($games->isEmpty()) {
            return [
                'l5' => $empty,
                'l10' => $empty,
                'l20' => $empty,
                'season' => $empty,
                'h2h' => $opponentTeamId !== null && $opponentTeamId !== '' ? $empty : null,
                'recent_games' => [],
            ];
        }

        $values = $games->pluck('value')->map(fn ($v) => (float) $v)->all();

        $h2h = null;
        if ($opponentTeamId !== null && $opponentTeamId !== '') {
            $oppKeys = TeamForeignKeyResolver::foreignKeysForReference($opponentTeamId);
            $h2hValues = $games
                ->filter(fn ($row) => in_array((string) ($row->opponent_team_id ?? ''), $oppKeys, true))
                ->pluck('value')
                ->map(fn ($v) => (float) $v)
                ->all();
            $h2h = $this->windowStats($h2hValues, $line);
        }

        $recentGames = $games->take(10)->map(function ($row) use ($line) {
            $value = (float) $row->value;

            return [
                'game_date' => $row->game_date !== null ? (string) $row->game_date : null,
                'value' => $value,
                'over' => $value > $line,
            ];
        })->values()->all();

        return [
            'l5' => $this->windowStats(array_slice($values, 0, 5), $line),
            'l10' => $this->windowStats(array_slice($values, 0, 10), $line),
            'l20' => $this->windowStats(array_slice($values, 0, 20), $line),
            'season' => $this->windowStats($values, $line),
            'h2h' => $h2h,
            'recent_games' => $recentGames,
        ];
    }

    /**
     * @return Collection<int, object{value: float|int, game_date: mixed, opponent_team_id: mixed}>
     */
    private function fetchSeasonGames(int $playerId, string $statType, int $season): Collection
    {
        return DB::table('wnba_player_games as pg')
            ->join('wnba_games as g', 'pg.game_id', '=', 'g.id')
            ->leftJoin('wnba_game_teams as gt', function ($join) {
                $join->on('gt.game_id', '=', 'g.id')
                    ->whereColumn('gt.team_id', 'pg.team_id');
            })
            ->where('pg.player_id', $playerId)
            ->where('g.season', $season)
            ->where('pg.did_not_play', false)
            ->where(function ($query) {
                $query->whereNull('pg.validation_status')
                    ->orWhere('pg.validation_status', '!=', 'invalid');
            })
            ->orderByDesc('g.game_date')
            ->orderByDesc('g.id')
            ->select([
                "pg.{$statType} as value",
                'g.game_date',
                'gt.opponent_team_id',
            ])
            ->get();
    }

    /**
     * @param  list<float>  $values
     * @return array{hits: int, games: int, rate: float|null}
     */
    private function windowStats(array $values, float $line): array
    {
        $games = count($values);
        if ($games === 0) {
            return $this->emptyWindow();
        }

        $hits = 0;
        foreach ($values as $value) {
            if ($value > $line) {
                $hits++;
            }
        }

        return [
            'hits' => $hits,
            'games' => $games,
            'rate' => round($hits / $games, 4),
        ];
    }

    /**
     * @return array{hits: int, games: int, rate: float|null}
     */
    private function emptyWindow(): array
    {
        return ['hits' => 0, 'games' => 0, 'rate' => null];
    }
}
