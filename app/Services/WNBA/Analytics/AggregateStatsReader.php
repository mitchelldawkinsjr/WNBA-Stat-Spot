<?php

namespace App\Services\WNBA\Analytics;

use App\Models\WnbaMatchupSummary;
use App\Models\WnbaPlayerPerformanceTrend;
use App\Models\WnbaPlayerVsDefense;
use App\Models\WnbaTeamPerformanceTrend;
use App\Models\WnbaTeamSeasonStat;
use App\Services\WNBA\Agents\AggregateComputationService;
use App\Services\WNBA\Data\Support\TeamForeignKeyResolver;
use Illuminate\Support\Collection;

/**
 * Read-only accessors for Analytics Agent aggregate tables.
 * Request paths must use this (or models directly) — never recompute ratings/trends.
 */
class AggregateStatsReader
{
    /** DRtg above league median by this amount → difficult; below → favorable. */
    private const MATCHUP_DIFFICULTY_BAND = 3.0;

    public function teamSeasonStat(string|int $teamId, int $season): ?WnbaTeamSeasonStat
    {
        $keys = TeamForeignKeyResolver::foreignKeysForReference($teamId);

        return WnbaTeamSeasonStat::query()
            ->where('season', $season)
            ->whereIn('team_id', $keys)
            ->first();
    }

    public function offensiveRating(string|int $teamId, int $season): ?float
    {
        $stat = $this->teamSeasonStat($teamId, $season);

        return $stat?->offensive_rating !== null ? (float) $stat->offensive_rating : null;
    }

    public function defensiveRating(string|int $teamId, int $season): ?float
    {
        $stat = $this->teamSeasonStat($teamId, $season);

        return $stat?->defensive_rating !== null ? (float) $stat->defensive_rating : null;
    }

    public function netRating(string|int $teamId, int $season): ?float
    {
        $stat = $this->teamSeasonStat($teamId, $season);

        return $stat?->net_rating !== null ? (float) $stat->net_rating : null;
    }

    public function pace(string|int $teamId, int $season): ?float
    {
        $stat = $this->teamSeasonStat($teamId, $season);

        return $stat?->pace !== null ? (float) $stat->pace : null;
    }

    /**
     * @return array{offensive_rating: ?float, defensive_rating: ?float, net_rating: ?float, pace: ?float, possessions_per_game: ?float}
     */
    public function efficiencySnapshot(string|int $teamId, int $season): array
    {
        $stat = $this->teamSeasonStat($teamId, $season);

        return [
            'offensive_rating' => $stat?->offensive_rating !== null ? (float) $stat->offensive_rating : null,
            'defensive_rating' => $stat?->defensive_rating !== null ? (float) $stat->defensive_rating : null,
            'net_rating' => $stat?->net_rating !== null ? (float) $stat->net_rating : null,
            'pace' => $stat?->pace !== null ? (float) $stat->pace : null,
            'possessions_per_game' => $stat?->possessions_per_game !== null ? (float) $stat->possessions_per_game : null,
        ];
    }

    public function leagueMedianDefensiveRating(int $season): ?float
    {
        $ratings = WnbaTeamSeasonStat::query()
            ->where('season', $season)
            ->whereNotNull('defensive_rating')
            ->orderBy('defensive_rating')
            ->pluck('defensive_rating')
            ->map(fn ($v) => (float) $v)
            ->values();

        if ($ratings->isEmpty()) {
            return null;
        }

        $count = $ratings->count();
        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return $ratings[$mid];
        }

        return ($ratings[$mid - 1] + $ratings[$mid]) / 2;
    }

    /**
     * Pure lookup from opponent DRtg vs league median — no box-score math.
     * Lower DRtg = better defense = more difficult for the offense.
     */
    public function matchupDifficulty(string|int|null $opponentTeamId, int $season): ?string
    {
        if ($opponentTeamId === null || $opponentTeamId === '') {
            return null;
        }

        $oppDrtg = $this->defensiveRating($opponentTeamId, $season);
        $median = $this->leagueMedianDefensiveRating($season);
        if ($oppDrtg === null || $median === null) {
            return null;
        }

        if ($oppDrtg < $median - self::MATCHUP_DIFFICULTY_BAND) {
            return 'difficult';
        }
        if ($oppDrtg > $median + self::MATCHUP_DIFFICULTY_BAND) {
            return 'favorable';
        }

        return 'neutral';
    }

    public function matchupSummary(string|int $team1Id, string|int $team2Id, int $season): ?WnbaMatchupSummary
    {
        $team1 = TeamForeignKeyResolver::resolveTeam($team1Id);
        $team2 = TeamForeignKeyResolver::resolveTeam($team2Id);
        $a = (string) ($team1?->team_id ?? $team1Id);
        $b = (string) ($team2?->team_id ?? $team2Id);
        $ids = [$a, $b];
        sort($ids);

        return WnbaMatchupSummary::query()
            ->where('season', $season)
            ->where('team_a_id', $ids[0])
            ->where('team_b_id', $ids[1])
            ->first();
    }

    /**
     * @return array{games: int, wins: int, losses: int, points_for_avg: ?float, points_against_avg: ?float}|null
     */
    public function teamTrendWindow(string|int $teamId, int $season, string $window): ?array
    {
        $keys = TeamForeignKeyResolver::foreignKeysForReference($teamId);
        $row = WnbaTeamPerformanceTrend::query()
            ->where('season', $season)
            ->where('window', $window)
            ->whereIn('team_id', $keys)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'games' => $row->games,
            'wins' => $row->wins,
            'losses' => $row->losses,
            'points_for_avg' => $row->points_for_avg,
            'points_against_avg' => $row->points_against_avg,
            'pace_avg' => $row->pace_avg,
            'offensive_rating' => $row->offensive_rating,
            'defensive_rating' => $row->defensive_rating,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function playerVsDefense(int $playerId, int $season): array
    {
        return WnbaPlayerVsDefense::query()
            ->where('player_id', $playerId)
            ->where('season', $season)
            ->get()
            ->keyBy('defense_bucket')
            ->map(fn (WnbaPlayerVsDefense $row) => [
                'games' => $row->games,
                'minutes_avg' => $row->minutes_avg,
                'points_avg' => $row->points_avg,
                'rebounds_avg' => $row->rebounds_avg,
                'assists_avg' => $row->assists_avg,
                'fg_pct' => $row->fg_pct,
                'three_pct' => $row->three_pct,
                'ts_pct' => $row->ts_pct,
                'usage_pct_avg' => $row->usage_pct_avg,
            ])
            ->all();
    }

    /**
     * Strong = elite+good buckets; weak = average+poor.
     *
     * @return array{games: int, points_avg: ?float, rebounds_avg: ?float, assists_avg: ?float}
     */
    public function playerVsDefenseStrength(int $playerId, int $season, string $strength): array
    {
        $buckets = $strength === 'strong'
            ? [AggregateComputationService::DEFENSE_BUCKET_ELITE, AggregateComputationService::DEFENSE_BUCKET_GOOD]
            : [AggregateComputationService::DEFENSE_BUCKET_AVERAGE, AggregateComputationService::DEFENSE_BUCKET_POOR];

        $rows = WnbaPlayerVsDefense::query()
            ->where('player_id', $playerId)
            ->where('season', $season)
            ->whereIn('defense_bucket', $buckets)
            ->get();

        return $this->combineVsDefenseRows($rows);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function playerTrends(int $playerId, int $season): array
    {
        return WnbaPlayerPerformanceTrend::query()
            ->where('player_id', $playerId)
            ->where('season', $season)
            ->get()
            ->keyBy('window')
            ->map(fn (WnbaPlayerPerformanceTrend $row) => [
                'games' => $row->games,
                'minutes_avg' => $row->minutes_avg,
                'points_avg' => $row->points_avg,
                'rebounds_avg' => $row->rebounds_avg,
                'assists_avg' => $row->assists_avg,
                'fg_pct' => $row->fg_pct,
                'ts_pct' => $row->ts_pct,
                'points_slope' => $row->points_slope,
                'rebounds_slope' => $row->rebounds_slope,
                'assists_slope' => $row->assists_slope,
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function teamTrends(string|int $teamId, int $season): array
    {
        $keys = TeamForeignKeyResolver::foreignKeysForReference($teamId);

        return WnbaTeamPerformanceTrend::query()
            ->whereIn('team_id', $keys)
            ->where('season', $season)
            ->get()
            ->keyBy('window')
            ->map(fn (WnbaTeamPerformanceTrend $row) => [
                'games' => $row->games,
                'wins' => $row->wins,
                'losses' => $row->losses,
                'points_for_avg' => $row->points_for_avg,
                'points_against_avg' => $row->points_against_avg,
                'pace_avg' => $row->pace_avg,
                'offensive_rating' => $row->offensive_rating,
                'defensive_rating' => $row->defensive_rating,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, WnbaPlayerVsDefense>  $rows
     * @return array{games: int, points_avg: ?float, rebounds_avg: ?float, assists_avg: ?float}
     */
    private function combineVsDefenseRows(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [
                'games' => 0,
                'points_avg' => null,
                'rebounds_avg' => null,
                'assists_avg' => null,
            ];
        }

        $games = (int) $rows->sum('games');
        if ($games === 0) {
            return [
                'games' => 0,
                'points_avg' => null,
                'rebounds_avg' => null,
                'assists_avg' => null,
            ];
        }

        $weighted = fn (string $field) => $rows->sum(fn ($r) => ((float) ($r->{$field} ?? 0)) * $r->games) / $games;

        return [
            'games' => $games,
            'points_avg' => round($weighted('points_avg'), 2),
            'rebounds_avg' => round($weighted('rebounds_avg'), 2),
            'assists_avg' => round($weighted('assists_avg'), 2),
        ];
    }
}
