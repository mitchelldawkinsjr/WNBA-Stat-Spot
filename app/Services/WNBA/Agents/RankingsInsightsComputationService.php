<?php

namespace App\Services\WNBA\Agents;

use App\Models\WnbaDailyInsight;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerPercentileRank;
use App\Models\WnbaPlayerPerformanceTrend;
use App\Models\WnbaPlayerSeasonStat;
use App\Models\WnbaTeam;
use App\Models\WnbaTeamPercentileRank;
use App\Models\WnbaTeamPerformanceTrend;
use App\Models\WnbaTeamPowerRanking;
use App\Models\WnbaTeamSeasonStat;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Analytics Agent extension: league percentiles, team power rankings, and
 * daily insight cards derived from precomputed aggregate tables.
 */
class RankingsInsightsComputationService
{
    public const FORMULA_VERSION = 'v1-rankings-insights';

    private const MIN_PLAYER_GAMES = 5;

    private const SURGE_FADE_PPG_DELTA = 4.0;

    private const MAX_DAILY_INSIGHTS = 15;

    private ?AgentRunReporter $reporter = null;

    public function setReporter(?AgentRunReporter $reporter): void
    {
        $this->reporter = $reporter;
    }

    public function computeSeason(int $season, ?Carbon $asOf = null): void
    {
        $asOf ??= Carbon::today();

        $this->computeTeamPercentileRanks($season);
        $this->computePlayerPercentileRanks($season);
        $this->computeTeamPowerRankings($season, $asOf);
        $this->computeDailyInsights($season, $asOf);
    }

    public function computeTeamPercentileRanks(int $season): int
    {
        $rows = WnbaTeamSeasonStat::query()
            ->where('season', $season)
            ->get();

        $computed = 0;
        foreach ($rows as $stat) {
            WnbaTeamPercentileRank::updateOrCreate(
                ['team_id' => $stat->team_id, 'season' => $season],
                [
                    'sample_size' => (int) $stat->games_played,
                    'offensive_rating_pctl' => $this->percentile($rows, 'offensive_rating', $stat->offensive_rating, true),
                    'defensive_rating_pctl' => $this->percentile($rows, 'defensive_rating', $stat->defensive_rating, false),
                    'net_rating_pctl' => $this->percentile($rows, 'net_rating', $stat->net_rating, true),
                    'pace_pctl' => $this->percentile($rows, 'pace', $stat->pace, true),
                    'efg_pct_pctl' => $this->percentile($rows, 'efg_pct', $stat->efg_pct, true),
                    'tov_pct_pctl' => $this->percentile($rows, 'tov_pct', $stat->tov_pct, false),
                    'formula_version' => self::FORMULA_VERSION,
                    'computed_at' => now(),
                    'agent_run_id' => $this->reporter?->runId(),
                ]
            );
            $computed++;
        }

        $this->reporter?->increment('team_percentile_ranks_computed', $computed);

        return $computed;
    }

    public function computePlayerPercentileRanks(int $season): int
    {
        $rows = WnbaPlayerSeasonStat::query()
            ->where('season', $season)
            ->where('games_played', '>=', self::MIN_PLAYER_GAMES)
            ->get();

        $computed = 0;
        foreach ($rows as $stat) {
            WnbaPlayerPercentileRank::updateOrCreate(
                ['player_id' => $stat->player_id, 'season' => $season],
                [
                    'sample_size' => (int) $stat->games_played,
                    'points_pctl' => $this->percentile($rows, 'points_avg', $stat->points_avg, true),
                    'rebounds_pctl' => $this->percentile($rows, 'rebounds_avg', $stat->rebounds_avg, true),
                    'assists_pctl' => $this->percentile($rows, 'assists_avg', $stat->assists_avg, true),
                    'steals_pctl' => $this->percentile($rows, 'steals_avg', $stat->steals_avg, true),
                    'blocks_pctl' => $this->percentile($rows, 'blocks_avg', $stat->blocks_avg, true),
                    'minutes_pctl' => $this->percentile($rows, 'minutes_avg', $stat->minutes_avg, true),
                    'ts_pct_pctl' => $this->percentile($rows, 'ts_pct', $stat->ts_pct, true),
                    'efg_pct_pctl' => $this->percentile($rows, 'efg_pct', $stat->efg_pct, true),
                    'formula_version' => self::FORMULA_VERSION,
                    'computed_at' => now(),
                    'agent_run_id' => $this->reporter?->runId(),
                ]
            );
            $computed++;
        }

        $this->reporter?->increment('player_percentile_ranks_computed', $computed);

        return $computed;
    }

    public function computeTeamPowerRankings(int $season, Carbon $asOf): int
    {
        $asOfDate = $asOf->toDateString();
        $seasonStats = WnbaTeamSeasonStat::query()
            ->where('season', $season)
            ->get()
            ->keyBy('team_id');

        if ($seasonStats->isEmpty()) {
            $this->reporter?->increment('team_power_rankings_computed', 0);

            return 0;
        }

        $trends = WnbaTeamPerformanceTrend::query()
            ->where('season', $season)
            ->whereIn('window', ['l5', 'l10'])
            ->get()
            ->groupBy('team_id');

        $priorDate = WnbaTeamPowerRanking::query()
            ->where('season', $season)
            ->where('as_of_date', '<', $asOfDate)
            ->orderByDesc('as_of_date')
            ->value('as_of_date');

        $previousRanks = $priorDate
            ? WnbaTeamPowerRanking::query()
                ->where('season', $season)
                ->whereDate('as_of_date', $priorDate)
                ->pluck('rank', 'team_id')
                ->all()
            : [];

        $scored = [];
        foreach ($seasonStats as $teamId => $stat) {
            $teamTrends = $trends->get($teamId, collect());
            $l5 = $teamTrends->firstWhere('window', 'l5');
            $l10 = $teamTrends->firstWhere('window', 'l10');
            $l5Net = $this->trendNet($l5);
            $l10Net = $this->trendNet($l10);

            $games = (int) $stat->wins + (int) $stat->losses;
            $winPct = $games > 0 ? ((int) $stat->wins / $games) : 0.5;
            $net = $stat->net_rating !== null ? (float) $stat->net_rating : 0.0;

            $score = $net * 0.5
                + ($l5Net ?? 0.0) * 0.3
                + ($l10Net ?? 0.0) * 0.1
                + ($winPct - 0.5) * 20 * 0.1;

            $scored[] = [
                'team_id' => (string) $teamId,
                'score' => round($score, 3),
                'components' => [
                    'net_rating' => $stat->net_rating !== null ? (float) $stat->net_rating : null,
                    'l5_net' => $l5Net,
                    'l10_net' => $l10Net,
                    'win_pct' => round($winPct, 4),
                    'weight_net' => 0.5,
                    'weight_l5_net' => 0.3,
                    'weight_l10_net' => 0.1,
                    'weight_win_pct' => 0.1,
                ],
                'reason' => $this->powerRankingReason($net, $l5Net, $winPct),
            ];
        }

        usort($scored, function (array $a, array $b) {
            if ($a['score'] === $b['score']) {
                return strcmp($a['team_id'], $b['team_id']);
            }

            return $b['score'] <=> $a['score'];
        });

        $computed = 0;
        foreach ($scored as $index => $row) {
            $rank = $index + 1;
            $previousRank = isset($previousRanks[$row['team_id']])
                ? (int) $previousRanks[$row['team_id']]
                : null;
            $rankDelta = $previousRank !== null ? $previousRank - $rank : null;

            $attributes = [
                'rank' => $rank,
                'previous_rank' => $previousRank,
                'rank_delta' => $rankDelta,
                'score' => $row['score'],
                'components' => $row['components'],
                'reason' => $row['reason'],
                'formula_version' => self::FORMULA_VERSION,
                'computed_at' => now(),
                'agent_run_id' => $this->reporter?->runId(),
            ];

            // whereDate avoids SQLite datetime-vs-date mismatches on the unique key.
            $existing = WnbaTeamPowerRanking::query()
                ->where('season', $season)
                ->whereDate('as_of_date', $asOfDate)
                ->where('team_id', $row['team_id'])
                ->first();

            if ($existing !== null) {
                $existing->update($attributes);
            } else {
                WnbaTeamPowerRanking::create([
                    'season' => $season,
                    'as_of_date' => $asOfDate,
                    'team_id' => $row['team_id'],
                    ...$attributes,
                ]);
            }
            $computed++;
        }

        $this->reporter?->increment('team_power_rankings_computed', $computed);

        return $computed;
    }

    public function computeDailyInsights(int $season, Carbon $asOf): int
    {
        $insightDate = $asOf->toDateString();

        WnbaDailyInsight::query()
            ->where('season', $season)
            ->whereDate('insight_date', $insightDate)
            ->delete();

        $candidates = [];

        $playerTrends = WnbaPlayerPerformanceTrend::query()
            ->where('season', $season)
            ->where('window', 'l5')
            ->get()
            ->keyBy('player_id');

        $playerStats = WnbaPlayerSeasonStat::query()
            ->where('season', $season)
            ->where('games_played', '>=', self::MIN_PLAYER_GAMES)
            ->whereIn('player_id', $playerTrends->keys())
            ->get()
            ->keyBy('player_id');

        $playerNames = WnbaPlayer::query()
            ->whereIn('id', $playerStats->keys())
            ->pluck('athlete_display_name', 'id');

        foreach ($playerStats as $playerId => $stat) {
            $trend = $playerTrends->get($playerId);
            if ($trend === null || $trend->points_avg === null || $stat->points_avg === null) {
                continue;
            }

            $delta = (float) $trend->points_avg - (float) $stat->points_avg;
            if ($delta >= self::SURGE_FADE_PPG_DELTA) {
                $name = $playerNames[$playerId] ?? 'Player';
                $candidates[] = [
                    'insight_type' => 'player_surge',
                    'entity_type' => 'player',
                    'entity_id' => (string) $playerId,
                    'title' => "{$name} surging",
                    'body' => sprintf(
                        '%s is averaging %.1f PPG over the last 5 games, %.1f above season average.',
                        $name,
                        (float) $trend->points_avg,
                        $delta
                    ),
                    'priority' => (int) min(100, 60 + round($delta * 5)),
                    'payload' => [
                        'l5_ppg' => (float) $trend->points_avg,
                        'season_ppg' => (float) $stat->points_avg,
                        'delta_ppg' => round($delta, 2),
                    ],
                ];
            } elseif ($delta <= -self::SURGE_FADE_PPG_DELTA) {
                $name = $playerNames[$playerId] ?? 'Player';
                $candidates[] = [
                    'insight_type' => 'player_fade',
                    'entity_type' => 'player',
                    'entity_id' => (string) $playerId,
                    'title' => "{$name} cooling off",
                    'body' => sprintf(
                        '%s is averaging %.1f PPG over the last 5 games, %.1f below season average.',
                        $name,
                        (float) $trend->points_avg,
                        abs($delta)
                    ),
                    'priority' => (int) min(100, 60 + round(abs($delta) * 5)),
                    'payload' => [
                        'l5_ppg' => (float) $trend->points_avg,
                        'season_ppg' => (float) $stat->points_avg,
                        'delta_ppg' => round($delta, 2),
                    ],
                ];
            }
        }

        $teamTrends = WnbaTeamPerformanceTrend::query()
            ->where('season', $season)
            ->where('window', 'l5')
            ->get();

        $teamSeason = WnbaTeamSeasonStat::query()
            ->where('season', $season)
            ->whereNotNull('net_rating')
            ->orderByDesc('net_rating')
            ->get();

        $teamNames = WnbaTeam::query()
            ->whereIn('team_id', $teamTrends->pluck('team_id')->merge($teamSeason->pluck('team_id'))->unique())
            ->get()
            ->keyBy('team_id');

        foreach ($teamTrends as $trend) {
            $wins = (int) $trend->wins;
            $losses = (int) $trend->losses;
            $label = $teamNames->get($trend->team_id)?->team_abbreviation
                ?? $teamNames->get($trend->team_id)?->team_display_name
                ?? (string) $trend->team_id;

            if ($wins >= 4) {
                $candidates[] = [
                    'insight_type' => 'team_hot',
                    'entity_type' => 'team',
                    'entity_id' => (string) $trend->team_id,
                    'title' => "{$label} on a heater",
                    'body' => sprintf('%s is %d-%d over the last 5 games.', $label, $wins, $losses),
                    'priority' => 50 + $wins * 8,
                    'payload' => [
                        'l5_wins' => $wins,
                        'l5_losses' => $losses,
                    ],
                ];
            } elseif ($wins <= 1 && ($wins + $losses) >= 3) {
                $candidates[] = [
                    'insight_type' => 'team_cold',
                    'entity_type' => 'team',
                    'entity_id' => (string) $trend->team_id,
                    'title' => "{$label} struggling",
                    'body' => sprintf('%s is %d-%d over the last 5 games.', $label, $wins, $losses),
                    'priority' => 50 + (5 - $wins) * 8,
                    'payload' => [
                        'l5_wins' => $wins,
                        'l5_losses' => $losses,
                    ],
                ];
            }
        }

        if ($teamSeason->isNotEmpty()) {
            $leader = $teamSeason->first();
            $trailer = $teamSeason->last();
            $leaderLabel = $teamNames->get($leader->team_id)?->team_abbreviation
                ?? (string) $leader->team_id;
            $trailerLabel = $teamNames->get($trailer->team_id)?->team_abbreviation
                ?? (string) $trailer->team_id;

            $candidates[] = [
                'insight_type' => 'net_rating_leader',
                'entity_type' => 'team',
                'entity_id' => (string) $leader->team_id,
                'title' => "{$leaderLabel} leads in net rating",
                'body' => sprintf(
                    '%s sits at %.1f net rating, best in the league.',
                    $leaderLabel,
                    (float) $leader->net_rating
                ),
                'priority' => 70,
                'payload' => ['net_rating' => (float) $leader->net_rating],
            ];

            if ($trailer->team_id !== $leader->team_id) {
                $candidates[] = [
                    'insight_type' => 'net_rating_trailer',
                    'entity_type' => 'team',
                    'entity_id' => (string) $trailer->team_id,
                    'title' => "{$trailerLabel} trails in net rating",
                    'body' => sprintf(
                        '%s sits at %.1f net rating, worst in the league.',
                        $trailerLabel,
                        (float) $trailer->net_rating
                    ),
                    'priority' => 65,
                    'payload' => ['net_rating' => (float) $trailer->net_rating],
                ];
            }
        }

        usort($candidates, fn (array $a, array $b) => $b['priority'] <=> $a['priority']);
        $selected = array_slice($candidates, 0, self::MAX_DAILY_INSIGHTS);

        $computed = 0;
        foreach ($selected as $insight) {
            WnbaDailyInsight::create([
                'season' => $season,
                'insight_date' => $insightDate,
                'insight_type' => $insight['insight_type'],
                'entity_type' => $insight['entity_type'],
                'entity_id' => $insight['entity_id'],
                'title' => $insight['title'],
                'body' => $insight['body'],
                'priority' => $insight['priority'],
                'payload' => $insight['payload'],
                'formula_version' => self::FORMULA_VERSION,
                'computed_at' => now(),
                'agent_run_id' => $this->reporter?->runId(),
            ]);
            $computed++;
        }

        $this->reporter?->increment('daily_insights_computed', $computed);

        return $computed;
    }

    /**
     * Average rank percentile: (below + 0.5 * equal) / n * 100.
     * When higherIsBetter is false (e.g. DRtg, TOV%), lower values rank higher.
     */
    private function percentile(Collection $rows, string $column, mixed $value, bool $higherIsBetter): ?float
    {
        if ($value === null) {
            return null;
        }

        $values = $rows->pluck($column)->filter(fn ($v) => $v !== null)->values();
        if ($values->isEmpty()) {
            return null;
        }

        $n = $values->count();
        $floatValue = (float) $value;

        if ($higherIsBetter) {
            $below = $values->filter(fn ($v) => (float) $v < $floatValue)->count();
            $equal = $values->filter(fn ($v) => (float) $v == $floatValue)->count();
        } else {
            $below = $values->filter(fn ($v) => (float) $v > $floatValue)->count();
            $equal = $values->filter(fn ($v) => (float) $v == $floatValue)->count();
        }

        return round(($below + 0.5 * $equal) / $n * 100, 1);
    }

    private function trendNet(?WnbaTeamPerformanceTrend $row): ?float
    {
        if ($row === null || $row->offensive_rating === null || $row->defensive_rating === null) {
            return null;
        }

        return round((float) $row->offensive_rating - (float) $row->defensive_rating, 2);
    }

    private function powerRankingReason(float $net, ?float $l5Net, float $winPct): string
    {
        $parts = [sprintf('Net %.1f', $net)];
        if ($l5Net !== null) {
            $parts[] = sprintf('L5 net %+.1f', $l5Net);
        }
        $parts[] = sprintf('Win%% %.0f', $winPct * 100);

        return implode(', ', $parts);
    }
}
