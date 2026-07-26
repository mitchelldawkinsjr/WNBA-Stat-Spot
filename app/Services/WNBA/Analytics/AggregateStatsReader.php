<?php

namespace App\Services\WNBA\Analytics;

use App\Models\WnbaDailyInsight;
use App\Models\WnbaMatchupSummary;
use App\Models\WnbaPlayerGameAdvanced;
use App\Models\WnbaPlayerPercentileRank;
use App\Models\WnbaPlayerPerformanceTrend;
use App\Models\WnbaPlayerSeasonStat;
use App\Models\WnbaPlayerVsDefense;
use App\Models\WnbaTeamPercentileRank;
use App\Models\WnbaTeamPerformanceTrend;
use App\Models\WnbaTeamPowerRanking;
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

    /** Home-court boost (net-rating points) for matchup letter grades. */
    private const MATCHUP_HOME_COURT_BOOST = 2.5;

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

    public function playerSeasonStat(int $playerId, int $season): ?WnbaPlayerSeasonStat
    {
        return WnbaPlayerSeasonStat::query()
            ->where('player_id', $playerId)
            ->where('season', $season)
            ->first();
    }

    /**
     * Season-level advanced metrics from agent tables (null when not yet computed).
     *
     * @return array{
     *     usage_rate: float|null,
     *     true_shooting_pct: float|null,
     *     effective_fg_pct: float|null,
     *     assist_turnover_ratio: float|null,
     *     game_score_avg: float|null,
     *     plus_minus_avg: float|null,
     *     per_30_stats: array<string, float|null>,
     *     per_36_stats: array<string, float|null>,
     *     player_efficiency_rating: float|null
     * }|null
     */
    public function playerAdvancedMetrics(int $playerId, int $season): ?array
    {
        $seasonStat = $this->playerSeasonStat($playerId, $season);
        if ($seasonStat === null) {
            return null;
        }

        $advancedRows = WnbaPlayerGameAdvanced::query()
            ->join('wnba_games as g', 'g.id', '=', 'wnba_player_game_advanced.game_id')
            ->where('wnba_player_game_advanced.player_id', $playerId)
            ->where('g.season', $season)
            ->select('wnba_player_game_advanced.*')
            ->get();

        $usageAvg = $advancedRows->whereNotNull('usage_pct')->avg('usage_pct');
        $gameScoreAvg = $advancedRows->whereNotNull('game_score')->avg('game_score');

        $ast = (int) $seasonStat->assists_total;
        $tov = (int) $seasonStat->turnovers_total;
        $astTo = $tov > 0 ? round($ast / $tov, 2) : ($ast > 0 ? null : 0.0);

        $splits = is_array($seasonStat->splits) ? $seasonStat->splits : [];

        return [
            // Agent stores percentages as 0..1; API surfaces 0..100 for UI.
            'usage_rate' => $usageAvg !== null ? round((float) $usageAvg * 100, 1) : null,
            'true_shooting_pct' => $seasonStat->ts_pct !== null ? round((float) $seasonStat->ts_pct * 100, 1) : null,
            'effective_fg_pct' => $seasonStat->efg_pct !== null ? round((float) $seasonStat->efg_pct * 100, 1) : null,
            'assist_turnover_ratio' => $astTo,
            'game_score_avg' => $gameScoreAvg !== null ? round((float) $gameScoreAvg, 1) : null,
            'plus_minus_avg' => isset($splits['plus_minus_avg']) && $splits['plus_minus_avg'] !== null
                ? (float) $splits['plus_minus_avg']
                : null,
            'per_30_stats' => is_array($splits['per_30'] ?? null) ? $splits['per_30'] : [],
            'per_36_stats' => is_array($splits['per_36'] ?? null) ? $splits['per_36'] : [],
            // PER is out of scope for v1 box-estimate; keep null rather than a fake 0.
            'player_efficiency_rating' => null,
        ];
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
     * Latest (or dated) team power rankings for a season, ordered by rank.
     *
     * @return list<array<string, mixed>>
     */
    public function teamPowerRankings(int $season, ?string $asOfDate = null): array
    {
        $query = WnbaTeamPowerRanking::query()->where('season', $season);

        if ($asOfDate !== null && $asOfDate !== '') {
            $query->whereDate('as_of_date', $asOfDate);
        } else {
            $latest = WnbaTeamPowerRanking::query()
                ->where('season', $season)
                ->orderByDesc('as_of_date')
                ->value('as_of_date');

            if ($latest === null) {
                return [];
            }

            $query->whereDate('as_of_date', $latest);
        }

        return $query
            ->orderBy('rank')
            ->get()
            ->map(fn (WnbaTeamPowerRanking $row) => [
                'season' => $row->season,
                'as_of_date' => $row->as_of_date?->toDateString(),
                'team_id' => (string) $row->team_id,
                'rank' => $row->rank,
                'previous_rank' => $row->previous_rank,
                'rank_delta' => $row->rank_delta,
                'score' => $row->score,
                'components' => $row->components,
                'reason' => $row->reason,
                'formula_version' => $row->formula_version,
                'computed_at' => $row->computed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Daily insight cards for a season/date, highest priority first.
     *
     * @return list<array<string, mixed>>
     */
    public function dailyInsights(int $season, ?string $insightDate = null, int $limit = 15): array
    {
        $query = WnbaDailyInsight::query()->where('season', $season);

        if ($insightDate !== null && $insightDate !== '') {
            $query->whereDate('insight_date', $insightDate);
        } else {
            $latest = WnbaDailyInsight::query()
                ->where('season', $season)
                ->orderByDesc('insight_date')
                ->value('insight_date');

            if ($latest === null) {
                return [];
            }

            $query->whereDate('insight_date', $latest);
        }

        return $query
            ->orderByDesc('priority')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (WnbaDailyInsight $row) => [
                'season' => $row->season,
                'insight_date' => $row->insight_date?->toDateString(),
                'insight_type' => $row->insight_type,
                'entity_type' => $row->entity_type,
                'entity_id' => (string) $row->entity_id,
                'title' => $row->title,
                'body' => $row->body,
                'priority' => $row->priority,
                'payload' => $row->payload,
                'formula_version' => $row->formula_version,
                'computed_at' => $row->computed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function playerPercentiles(int $playerId, int $season): ?array
    {
        $row = WnbaPlayerPercentileRank::query()
            ->where('player_id', $playerId)
            ->where('season', $season)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'player_id' => $row->player_id,
            'season' => $row->season,
            'sample_size' => $row->sample_size,
            'points_pctl' => $row->points_pctl,
            'rebounds_pctl' => $row->rebounds_pctl,
            'assists_pctl' => $row->assists_pctl,
            'steals_pctl' => $row->steals_pctl,
            'blocks_pctl' => $row->blocks_pctl,
            'minutes_pctl' => $row->minutes_pctl,
            'ts_pct_pctl' => $row->ts_pct_pctl,
            'efg_pct_pctl' => $row->efg_pct_pctl,
            'formula_version' => $row->formula_version,
            'computed_at' => $row->computed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function teamPercentiles(string|int $teamId, int $season): ?array
    {
        $keys = TeamForeignKeyResolver::foreignKeysForReference($teamId);
        $row = WnbaTeamPercentileRank::query()
            ->where('season', $season)
            ->whereIn('team_id', $keys)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'team_id' => (string) $row->team_id,
            'season' => $row->season,
            'sample_size' => $row->sample_size,
            'offensive_rating_pctl' => $row->offensive_rating_pctl,
            'defensive_rating_pctl' => $row->defensive_rating_pctl,
            'net_rating_pctl' => $row->net_rating_pctl,
            'pace_pctl' => $row->pace_pctl,
            'efg_pct_pctl' => $row->efg_pct_pctl,
            'tov_pct_pctl' => $row->tov_pct_pctl,
            'formula_version' => $row->formula_version,
            'computed_at' => $row->computed_at?->toIso8601String(),
        ];
    }

    /**
     * Letter grade for home vs away based on net-rating edge (+ home-court boost).
     * Missing nets → null (never invent placeholders).
     *
     * @return array{grade: string, edge: float, summary: string, home_net: float, away_net: float}|null
     */
    public function matchupGrade(string|int $homeTeamId, string|int $awayTeamId, int $season): ?array
    {
        $homeNet = $this->netRating($homeTeamId, $season);
        $awayNet = $this->netRating($awayTeamId, $season);
        if ($homeNet === null || $awayNet === null) {
            return null;
        }

        $edge = round($homeNet - $awayNet + self::MATCHUP_HOME_COURT_BOOST, 1);

        if ($edge >= 8) {
            $grade = 'A';
        } elseif ($edge >= 4) {
            $grade = 'B';
        } elseif ($edge >= 0) {
            $grade = 'C';
        } elseif ($edge >= -4) {
            $grade = 'D';
        } else {
            $grade = 'F';
        }

        $favored = $edge >= 0 ? 'home' : 'away';
        $summary = sprintf(
            'Net-rating edge %+0.1f (home court %+0.1f) favors the %s side — grade %s.',
            $edge,
            self::MATCHUP_HOME_COURT_BOOST,
            $favored,
            $grade
        );

        return [
            'grade' => $grade,
            'edge' => $edge,
            'summary' => $summary,
            'home_net' => $homeNet,
            'away_net' => $awayNet,
        ];
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
