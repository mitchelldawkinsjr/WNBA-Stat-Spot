<?php

namespace App\Services\WNBA\Agents;

use App\Models\WnbaDataConflict;
use App\Models\WnbaGameTeam;
use App\Models\WnbaMatchupSummary;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaPlayerGameAdvanced;
use App\Models\WnbaPlayerPerformanceTrend;
use App\Models\WnbaPlayerSeasonStat;
use App\Models\WnbaPlayerVsDefense;
use App\Models\WnbaTeamPerformanceTrend;
use App\Models\WnbaTeamSeasonStat;
use App\Services\WNBA\Data\Support\TeamCatalog;
use Illuminate\Support\Collection;

/**
 * Analytics Agent core: precomputes season aggregates, per-game advanced
 * stats, matchup summaries, vs-defense splits, and performance trends from
 * validated canonical tables. Only this service writes the aggregate tables;
 * API controllers read them.
 *
 * Conventions: percentages are decimals 0..1, safe division returns null,
 * possessions are box-score estimates (FGA + 0.44*FTA - ORB + TOV).
 */
class AggregateComputationService
{
    public const FORMULA_VERSION = 'v1-box-estimate';

    private const TEAM_MINUTES_REGULATION = 200; // 5 players x 40 minutes

    /** Opponent team DRtg buckets (lower DRtg = better defense). */
    public const DEFENSE_BUCKET_ELITE = 'elite';

    public const DEFENSE_BUCKET_GOOD = 'good';

    public const DEFENSE_BUCKET_AVERAGE = 'average';

    public const DEFENSE_BUCKET_POOR = 'poor';

    private const TREND_WINDOWS = [
        'l5' => 5,
        'l10' => 10,
        'l20' => 20,
        'season' => null,
    ];

    private ?AgentRunReporter $reporter = null;

    public function __construct(
        private BoxScoreValidator $validator,
        private RankingsInsightsComputationService $rankingsInsights,
    ) {}

    public function setReporter(?AgentRunReporter $reporter): void
    {
        $this->reporter = $reporter;
        $this->rankingsInsights->setReporter($reporter);
    }

    public function computeSeason(int $season): void
    {
        $this->computePlayerGameAdvanced($season);
        $this->computePlayerSeasonStats($season);
        $this->computeTeamSeasonStats($season);
        // Vs-defense needs team season DRtg rows from the step above.
        $this->computePlayerVsDefense($season);
        $this->computePlayerPerformanceTrends($season);
        $this->computeTeamPerformanceTrends($season);
        $this->computeMatchupSummaries($season);
        // Rankings/insights consume the aggregate tables above.
        $this->rankingsInsights->computeSeason($season);
    }

    public function computePlayerGameAdvanced(int $season): int
    {
        $flaggedPlayers = $this->unresolvedEntityKeys('player');
        $computed = 0;

        WnbaPlayerGame::query()
            ->join('wnba_games as g', 'g.id', '=', 'wnba_player_games.game_id')
            ->where('g.season', $season)
            ->where('wnba_player_games.did_not_play', false)
            ->where(function ($query) {
                $query->whereNull('wnba_player_games.validation_status')
                    ->orWhere('wnba_player_games.validation_status', '!=', BoxScoreValidator::STATUS_INVALID);
            })
            ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_player_games.team_id'))
            ->select('wnba_player_games.*')
            ->orderBy('wnba_player_games.id')
            ->chunk(500, function (Collection $playerGames) use ($flaggedPlayers, &$computed) {
                $teamTotals = $this->teamTotalsForGames($playerGames->pluck('game_id')->unique()->all());

                foreach ($playerGames as $pg) {
                    if (in_array((string) $pg->player_id, $flaggedPlayers, true)) {
                        $this->reporter?->increment('entities_skipped_identity_conflict');

                        continue;
                    }

                    $minutes = $this->validator->parseMinutes($pg->minutes);
                    $shotAttempts = $pg->field_goals_attempted + 0.44 * $pg->free_throws_attempted;

                    WnbaPlayerGameAdvanced::updateOrCreate(
                        ['player_game_id' => $pg->id],
                        [
                            'game_id' => $pg->game_id,
                            'player_id' => $pg->player_id,
                            'minutes_decimal' => $minutes,
                            'ts_pct' => $this->safeDivide($pg->points, 2 * $shotAttempts),
                            'efg_pct' => $this->safeDivide(
                                $pg->field_goals_made + 0.5 * $pg->three_point_field_goals_made,
                                $pg->field_goals_attempted
                            ),
                            'usage_pct' => $this->usagePct($pg, $minutes, $teamTotals[$pg->game_id.'|'.$pg->team_id] ?? null),
                            'game_score' => $this->gameScore($pg),
                            'points_per_shot' => $this->safeDivide($pg->points, $shotAttempts),
                            'formula_version' => self::FORMULA_VERSION,
                            'computed_at' => now(),
                            'agent_run_id' => $this->reporter?->runId(),
                        ]
                    );
                    $computed++;
                }
            });

        $this->reporter?->increment('player_game_advanced_computed', $computed);

        return $computed;
    }

    public function computePlayerSeasonStats(int $season): int
    {
        $flaggedPlayers = $this->unresolvedEntityKeys('player');
        $computed = 0;

        $playerIds = WnbaPlayerGame::query()
            ->join('wnba_games as g', 'g.id', '=', 'wnba_player_games.game_id')
            ->where('g.season', $season)
            ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_player_games.team_id'))
            ->distinct()
            ->pluck('wnba_player_games.player_id');

        foreach ($playerIds as $playerId) {
            if (in_array((string) $playerId, $flaggedPlayers, true)) {
                $this->reporter?->increment('entities_skipped_identity_conflict');

                continue;
            }

            $games = WnbaPlayerGame::query()
                ->join('wnba_games as g', 'g.id', '=', 'wnba_player_games.game_id')
                ->leftJoin('wnba_game_teams as gt', function ($join) {
                    $join->on('gt.game_id', '=', 'wnba_player_games.game_id')
                        ->on('gt.team_id', '=', 'wnba_player_games.team_id');
                })
                ->where('wnba_player_games.player_id', $playerId)
                ->where('g.season', $season)
                ->where('wnba_player_games.did_not_play', false)
                ->where(function ($query) {
                    $query->whereNull('wnba_player_games.validation_status')
                        ->orWhere('wnba_player_games.validation_status', '!=', BoxScoreValidator::STATUS_INVALID);
                })
                ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_player_games.team_id'))
                ->select('wnba_player_games.*', 'gt.home_away')
                ->get();

            if ($games->isEmpty()) {
                continue;
            }

            $gp = $games->count();
            $minutesTotal = $games->sum(fn ($pg) => $this->validator->parseMinutes($pg->minutes) ?? 0.0);
            $totals = $this->sumCountingStats($games);
            $shotAttempts = $totals['fga'] + 0.44 * $totals['fta'];
            $latestLeagueGame = $games->sortByDesc('game_id')->first();

            WnbaPlayerSeasonStat::updateOrCreate(
                ['player_id' => $playerId, 'season' => $season],
                [
                    'team_id' => $latestLeagueGame->team_id,
                    'games_played' => $gp,
                    'games_started' => $games->where('starter', true)->count(),
                    'minutes_total' => round($minutesTotal, 1),
                    'points_total' => $totals['points'],
                    'rebounds_total' => $totals['rebounds'],
                    'offensive_rebounds_total' => $totals['oreb'],
                    'defensive_rebounds_total' => $totals['dreb'],
                    'assists_total' => $totals['assists'],
                    'steals_total' => $totals['steals'],
                    'blocks_total' => $totals['blocks'],
                    'turnovers_total' => $totals['turnovers'],
                    'fouls_total' => $totals['fouls'],
                    'field_goals_made_total' => $totals['fgm'],
                    'field_goals_attempted_total' => $totals['fga'],
                    'three_point_made_total' => $totals['tpm'],
                    'three_point_attempted_total' => $totals['tpa'],
                    'free_throws_made_total' => $totals['ftm'],
                    'free_throws_attempted_total' => $totals['fta'],
                    'points_avg' => round($totals['points'] / $gp, 2),
                    'rebounds_avg' => round($totals['rebounds'] / $gp, 2),
                    'assists_avg' => round($totals['assists'] / $gp, 2),
                    'steals_avg' => round($totals['steals'] / $gp, 2),
                    'blocks_avg' => round($totals['blocks'] / $gp, 2),
                    'turnovers_avg' => round($totals['turnovers'] / $gp, 2),
                    'minutes_avg' => round($minutesTotal / $gp, 2),
                    'fg_pct' => $this->safeDivide($totals['fgm'], $totals['fga']),
                    'three_pct' => $this->safeDivide($totals['tpm'], $totals['tpa']),
                    'ft_pct' => $this->safeDivide($totals['ftm'], $totals['fta']),
                    'efg_pct' => $this->safeDivide($totals['fgm'] + 0.5 * $totals['tpm'], $totals['fga']),
                    'ts_pct' => $this->safeDivide($totals['points'], 2 * $shotAttempts),
                    'splits' => [
                        'home' => $this->splitAverages($games->where('home_away', 'home')),
                        'away' => $this->splitAverages($games->where('home_away', 'away')),
                        'per_36' => $this->perMinutes($totals, $minutesTotal, 36),
                        'per_30' => $this->perMinutes($totals, $minutesTotal, 30),
                        'plus_minus_avg' => $this->plusMinusAvg($games),
                    ],
                    'formula_version' => self::FORMULA_VERSION,
                    'computed_at' => now(),
                    'agent_run_id' => $this->reporter?->runId(),
                ]
            );
            $computed++;
        }

        $this->reporter?->increment('player_season_stats_computed', $computed);

        return $computed;
    }

    public function computeTeamSeasonStats(int $season): int
    {
        $flaggedTeams = $this->unresolvedEntityKeys('team');
        $computed = 0;

        // Drop stale exhibition / All-Star season rows so rankings stay league-only.
        $excluded = TeamCatalog::excludedTeamIds();
        if ($excluded !== []) {
            WnbaTeamSeasonStat::query()
                ->where('season', $season)
                ->whereIn('team_id', $excluded)
                ->delete();
            WnbaTeamPerformanceTrend::query()
                ->where('season', $season)
                ->whereIn('team_id', $excluded)
                ->delete();
        }

        $teamIds = WnbaGameTeam::query()
            ->join('wnba_games as g', 'g.id', '=', 'wnba_game_teams.game_id')
            ->where('g.season', $season)
            ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_game_teams.team_id'))
            ->distinct()
            ->pluck('wnba_game_teams.team_id');

        foreach ($teamIds as $teamId) {
            if (in_array((string) $teamId, $flaggedTeams, true)) {
                $this->reporter?->increment('entities_skipped_identity_conflict');

                continue;
            }

            if (! TeamCatalog::isLeagueTeamId((string) $teamId)) {
                continue;
            }

            $rows = $this->completedTeamGames($teamId, $season);
            if ($rows->isEmpty()) {
                continue;
            }

            $opponentRows = $this->opponentRowsFor($rows);
            $gp = $rows->count();

            $totals = [
                'points' => $rows->sum('team_score'),
                'points_against' => $rows->sum('opponent_team_score'),
                'fgm' => $rows->sum('field_goals_made'),
                'fga' => $rows->sum('field_goals_attempted'),
                'tpm' => $rows->sum('three_point_field_goals_made'),
                'tpa' => $rows->sum('three_point_field_goals_attempted'),
                'ftm' => $rows->sum('free_throws_made'),
                'fta' => $rows->sum('free_throws_attempted'),
                'oreb' => $rows->sum('offensive_rebounds'),
                'dreb' => $rows->sum('defensive_rebounds'),
                'tov' => $rows->sum('turnovers'),
            ];
            $opp = [
                'fgm' => $opponentRows->sum('field_goals_made'),
                'fga' => $opponentRows->sum('field_goals_attempted'),
                'tpm' => $opponentRows->sum('three_point_field_goals_made'),
                'fta' => $opponentRows->sum('free_throws_attempted'),
                'ftm' => $opponentRows->sum('free_throws_made'),
                'oreb' => $opponentRows->sum('offensive_rebounds'),
                'dreb' => $opponentRows->sum('defensive_rebounds'),
                'tov' => $opponentRows->sum('turnovers'),
            ];

            $possessions = $totals['fga'] + 0.44 * $totals['fta'] - $totals['oreb'] + $totals['tov'];
            $oppPossessions = $opp['fga'] + 0.44 * $opp['fta'] - $opp['oreb'] + $opp['tov'];
            $possessionsPerGame = $gp > 0 ? $possessions / $gp : null;

            $offRating = $this->safeDivide($totals['points'] * 100, $possessions);
            $defRating = $this->safeDivide($totals['points_against'] * 100, $oppPossessions);

            WnbaTeamSeasonStat::updateOrCreate(
                ['team_id' => $teamId, 'season' => $season],
                [
                    'games_played' => $gp,
                    'wins' => $rows->where('team_winner', true)->count(),
                    'losses' => $rows->where('team_winner', false)->count(),
                    'points_for_avg' => round($totals['points'] / $gp, 2),
                    'points_against_avg' => round($totals['points_against'] / $gp, 2),
                    'pace' => $possessionsPerGame !== null ? round($possessionsPerGame, 2) : null,
                    'possessions_per_game' => $possessionsPerGame !== null ? round($possessionsPerGame, 2) : null,
                    'offensive_rating' => $offRating !== null ? round($offRating, 2) : null,
                    'defensive_rating' => $defRating !== null ? round($defRating, 2) : null,
                    'net_rating' => ($offRating !== null && $defRating !== null) ? round($offRating - $defRating, 2) : null,
                    'efg_pct' => $this->safeDivide($totals['fgm'] + 0.5 * $totals['tpm'], $totals['fga']),
                    'tov_pct' => $this->safeDivide($totals['tov'], $possessions),
                    'oreb_pct' => $this->safeDivide($totals['oreb'], $totals['oreb'] + $opp['dreb']),
                    'ft_rate' => $this->safeDivide($totals['fta'], $totals['fga']),
                    'opp_efg_pct' => $this->safeDivide($opp['fgm'] + 0.5 * $opp['tpm'], $opp['fga']),
                    'opp_tov_pct' => $this->safeDivide($opp['tov'], $oppPossessions),
                    'dreb_pct' => $this->safeDivide($totals['dreb'], $totals['dreb'] + $opp['oreb']),
                    'opp_ft_rate' => $this->safeDivide($opp['fta'], $opp['fga']),
                    'ts_pct' => $this->safeDivide($totals['points'], 2 * ($totals['fga'] + 0.44 * $totals['fta'])),
                    'three_rate' => $this->safeDivide($totals['tpa'], $totals['fga']),
                    'splits' => [
                        'home' => $this->teamSplit($rows->where('home_away', 'home')),
                        'away' => $this->teamSplit($rows->where('home_away', 'away')),
                    ],
                    'formula_version' => self::FORMULA_VERSION,
                    'computed_at' => now(),
                    'agent_run_id' => $this->reporter?->runId(),
                ]
            );
            $computed++;
        }

        $this->reporter?->increment('team_season_stats_computed', $computed);

        return $computed;
    }

    public function computeMatchupSummaries(int $season): int
    {
        $computed = 0;
        $excluded = TeamCatalog::excludedTeamIds();

        if ($excluded !== []) {
            WnbaMatchupSummary::query()
                ->where('season', $season)
                ->where(function ($query) use ($excluded) {
                    $query->whereIn('team_a_id', $excluded)
                        ->orWhereIn('team_b_id', $excluded);
                })
                ->delete();
        }

        $homeRows = WnbaGameTeam::query()
            ->join('wnba_games as g', 'g.id', '=', 'wnba_game_teams.game_id')
            ->where('g.season', $season)
            ->where('wnba_game_teams.home_away', 'home')
            ->whereRaw('(wnba_game_teams.team_score + wnba_game_teams.opponent_team_score) > 0')
            ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_game_teams.team_id'))
            ->when($excluded !== [], fn ($query) => $query->whereNotIn('wnba_game_teams.opponent_team_id', $excluded))
            ->select('wnba_game_teams.*', 'g.game_date')
            ->orderBy('g.game_date')
            ->get();

        $pairs = $homeRows->groupBy(function ($row) {
            $ids = [(string) $row->team_id, (string) $row->opponent_team_id];
            sort($ids);

            return implode('|', $ids);
        });

        foreach ($pairs as $key => $meetings) {
            [$teamA, $teamB] = explode('|', $key);

            $teamAWins = $meetings->filter(function ($row) use ($teamA) {
                $homeWon = $row->team_score > $row->opponent_team_score;

                return ((string) $row->team_id === $teamA) === $homeWon;
            })->count();

            $possessionsAvg = $meetings->avg(function ($row) {
                return $row->field_goals_attempted
                    + 0.44 * $row->free_throws_attempted
                    - $row->offensive_rebounds
                    + $row->turnovers;
            });

            WnbaMatchupSummary::updateOrCreate(
                ['season' => $season, 'team_a_id' => $teamA, 'team_b_id' => $teamB],
                [
                    'games_played' => $meetings->count(),
                    'team_a_wins' => $teamAWins,
                    'team_b_wins' => $meetings->count() - $teamAWins,
                    'avg_total_points' => round($meetings->avg(fn ($row) => $row->team_score + $row->opponent_team_score), 2),
                    'avg_margin' => round($meetings->avg(fn ($row) => abs($row->team_score - $row->opponent_team_score)), 2),
                    'avg_pace' => $possessionsAvg !== null ? round($possessionsAvg, 2) : null,
                    'last_meeting_date' => $meetings->last()->game_date,
                    'recent_meetings' => $meetings->sortByDesc('game_date')->take(5)->map(fn ($row) => [
                        'date' => (string) $row->game_date,
                        'home_team_id' => (string) $row->team_id,
                        'away_team_id' => (string) $row->opponent_team_id,
                        'home_score' => $row->team_score,
                        'away_score' => $row->opponent_team_score,
                    ])->values()->all(),
                    'formula_version' => self::FORMULA_VERSION,
                    'computed_at' => now(),
                    'agent_run_id' => $this->reporter?->runId(),
                ]
            );
            $computed++;
        }

        $this->reporter?->increment('matchup_summaries_computed', $computed);

        return $computed;
    }

    /**
     * Split each player's season games by opponent team DRtg bucket.
     */
    public function computePlayerVsDefense(int $season): int
    {
        $flaggedPlayers = $this->unresolvedEntityKeys('player');
        $teamDrtg = WnbaTeamSeasonStat::query()
            ->where('season', $season)
            ->whereNotNull('defensive_rating')
            ->pluck('defensive_rating', 'team_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        $usageByPlayerGame = WnbaPlayerGameAdvanced::query()
            ->whereIn('player_game_id', function ($query) use ($season) {
                $query->select('wnba_player_games.id')
                    ->from('wnba_player_games')
                    ->join('wnba_games as g', 'g.id', '=', 'wnba_player_games.game_id')
                    ->where('g.season', $season);
                TeamCatalog::wherePrimaryCompetition($query, 'wnba_player_games.team_id');
            })
            ->pluck('usage_pct', 'player_game_id')
            ->all();

        $computed = 0;

        $playerIds = WnbaPlayerGame::query()
            ->join('wnba_games as g', 'g.id', '=', 'wnba_player_games.game_id')
            ->where('g.season', $season)
            ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_player_games.team_id'))
            ->distinct()
            ->pluck('wnba_player_games.player_id');

        foreach ($playerIds as $playerId) {
            if (in_array((string) $playerId, $flaggedPlayers, true)) {
                $this->reporter?->increment('entities_skipped_identity_conflict');

                continue;
            }

            $games = WnbaPlayerGame::query()
                ->join('wnba_games as g', 'g.id', '=', 'wnba_player_games.game_id')
                ->join('wnba_game_teams as gt', function ($join) {
                    $join->on('gt.game_id', '=', 'wnba_player_games.game_id')
                        ->on('gt.team_id', '=', 'wnba_player_games.team_id');
                })
                ->where('wnba_player_games.player_id', $playerId)
                ->where('g.season', $season)
                ->where('wnba_player_games.did_not_play', false)
                ->where(function ($query) {
                    $query->whereNull('wnba_player_games.validation_status')
                        ->orWhere('wnba_player_games.validation_status', '!=', BoxScoreValidator::STATUS_INVALID);
                })
                ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_player_games.team_id'))
                ->select('wnba_player_games.*', 'gt.opponent_team_id')
                ->get();

            $byBucket = [];
            foreach ($games as $pg) {
                $oppId = (string) $pg->opponent_team_id;
                if (! isset($teamDrtg[$oppId])) {
                    continue;
                }
                $bucket = self::defenseBucket($teamDrtg[$oppId]);
                $byBucket[$bucket][] = $pg;
            }

            foreach ($byBucket as $bucket => $bucketGames) {
                $collection = collect($bucketGames);
                $gp = $collection->count();
                $minutesTotal = $collection->sum(fn ($pg) => $this->validator->parseMinutes($pg->minutes) ?? 0.0);
                $totals = $this->sumCountingStats($collection);
                $shotAttempts = $totals['fga'] + 0.44 * $totals['fta'];
                $usageValues = $collection
                    ->map(fn ($pg) => $usageByPlayerGame[$pg->id] ?? null)
                    ->filter(fn ($v) => $v !== null)
                    ->values();

                WnbaPlayerVsDefense::updateOrCreate(
                    ['player_id' => $playerId, 'season' => $season, 'defense_bucket' => $bucket],
                    [
                        'games' => $gp,
                        'minutes_avg' => $gp > 0 ? round($minutesTotal / $gp, 2) : null,
                        'points_avg' => $gp > 0 ? round($totals['points'] / $gp, 2) : null,
                        'rebounds_avg' => $gp > 0 ? round($totals['rebounds'] / $gp, 2) : null,
                        'assists_avg' => $gp > 0 ? round($totals['assists'] / $gp, 2) : null,
                        'fg_pct' => $this->safeDivide($totals['fgm'], $totals['fga']),
                        'three_pct' => $this->safeDivide($totals['tpm'], $totals['tpa']),
                        'ts_pct' => $this->safeDivide($totals['points'], 2 * $shotAttempts),
                        'usage_pct_avg' => $usageValues->isNotEmpty() ? round($usageValues->avg(), 4) : null,
                        'formula_version' => self::FORMULA_VERSION,
                        'computed_at' => now(),
                        'agent_run_id' => $this->reporter?->runId(),
                    ]
                );
                $computed++;
            }
        }

        $this->reporter?->increment('player_vs_defense_computed', $computed);

        return $computed;
    }

    public function computePlayerPerformanceTrends(int $season): int
    {
        $flaggedPlayers = $this->unresolvedEntityKeys('player');
        $computed = 0;

        $playerIds = WnbaPlayerGame::query()
            ->join('wnba_games as g', 'g.id', '=', 'wnba_player_games.game_id')
            ->where('g.season', $season)
            ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_player_games.team_id'))
            ->distinct()
            ->pluck('wnba_player_games.player_id');

        foreach ($playerIds as $playerId) {
            if (in_array((string) $playerId, $flaggedPlayers, true)) {
                $this->reporter?->increment('entities_skipped_identity_conflict');

                continue;
            }

            $games = WnbaPlayerGame::query()
                ->join('wnba_games as g', 'g.id', '=', 'wnba_player_games.game_id')
                ->where('wnba_player_games.player_id', $playerId)
                ->where('g.season', $season)
                ->where('wnba_player_games.did_not_play', false)
                ->where(function ($query) {
                    $query->whereNull('wnba_player_games.validation_status')
                        ->orWhere('wnba_player_games.validation_status', '!=', BoxScoreValidator::STATUS_INVALID);
                })
                ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_player_games.team_id'))
                ->select('wnba_player_games.*', 'g.game_date')
                ->orderBy('g.game_date')
                ->orderBy('wnba_player_games.id')
                ->get();

            if ($games->isEmpty()) {
                continue;
            }

            foreach (self::TREND_WINDOWS as $window => $limit) {
                $windowGames = $limit === null ? $games : $games->slice(-$limit)->values();
                if ($windowGames->isEmpty()) {
                    continue;
                }

                $gp = $windowGames->count();
                $minutes = $windowGames->map(fn ($pg) => $this->validator->parseMinutes($pg->minutes) ?? 0.0);
                $totals = $this->sumCountingStats($windowGames);
                $shotAttempts = $totals['fga'] + 0.44 * $totals['fta'];

                WnbaPlayerPerformanceTrend::updateOrCreate(
                    ['player_id' => $playerId, 'season' => $season, 'window' => $window],
                    [
                        'games' => $gp,
                        'minutes_avg' => round($minutes->avg(), 2),
                        'points_avg' => round($windowGames->avg('points'), 2),
                        'rebounds_avg' => round($windowGames->avg('rebounds'), 2),
                        'assists_avg' => round($windowGames->avg('assists'), 2),
                        'fg_pct' => $this->safeDivide($totals['fgm'], $totals['fga']),
                        'ts_pct' => $this->safeDivide($totals['points'], 2 * $shotAttempts),
                        'points_slope' => $this->linearSlope($windowGames->pluck('points')->all()),
                        'rebounds_slope' => $this->linearSlope($windowGames->pluck('rebounds')->all()),
                        'assists_slope' => $this->linearSlope($windowGames->pluck('assists')->all()),
                        'formula_version' => self::FORMULA_VERSION,
                        'computed_at' => now(),
                        'agent_run_id' => $this->reporter?->runId(),
                    ]
                );
                $computed++;
            }
        }

        $this->reporter?->increment('player_performance_trends_computed', $computed);

        return $computed;
    }

    public function computeTeamPerformanceTrends(int $season): int
    {
        $flaggedTeams = $this->unresolvedEntityKeys('team');
        $computed = 0;

        $teamIds = WnbaGameTeam::query()
            ->join('wnba_games as g', 'g.id', '=', 'wnba_game_teams.game_id')
            ->where('g.season', $season)
            ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_game_teams.team_id'))
            ->distinct()
            ->pluck('wnba_game_teams.team_id');

        foreach ($teamIds as $teamId) {
            if (in_array((string) $teamId, $flaggedTeams, true)) {
                $this->reporter?->increment('entities_skipped_identity_conflict');

                continue;
            }

            if (! TeamCatalog::isLeagueTeamId((string) $teamId)) {
                continue;
            }

            $rows = WnbaGameTeam::query()
                ->join('wnba_games as g', 'g.id', '=', 'wnba_game_teams.game_id')
                ->where('wnba_game_teams.team_id', $teamId)
                ->where('g.season', $season)
                ->whereRaw('(wnba_game_teams.team_score + wnba_game_teams.opponent_team_score) > 0')
                ->where(function ($query) {
                    $query->whereNull('wnba_game_teams.validation_status')
                        ->orWhere('wnba_game_teams.validation_status', '!=', BoxScoreValidator::STATUS_INVALID);
                })
                ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_game_teams.team_id'))
                ->select('wnba_game_teams.*', 'g.game_date')
                ->orderBy('g.game_date')
                ->orderBy('wnba_game_teams.id')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            foreach (self::TREND_WINDOWS as $window => $limit) {
                $windowRows = $limit === null ? $rows : $rows->slice(-$limit)->values();
                if ($windowRows->isEmpty()) {
                    continue;
                }

                $gp = $windowRows->count();
                $possessions = $windowRows->sum(fn ($row) => $this->estimatePossessionsFromRow($row));
                $oppPossessions = 0.0;
                $oppPoints = (float) $windowRows->sum('opponent_team_score');
                $points = (float) $windowRows->sum('team_score');

                // Opponent possessions approximated from the opposing box (same game).
                $opponentIds = $windowRows->pluck('opponent_team_id', 'game_id');
                $oppBoxes = WnbaGameTeam::query()
                    ->whereIn('game_id', $windowRows->pluck('game_id')->all())
                    ->get()
                    ->filter(fn ($row) => isset($opponentIds[$row->game_id])
                        && (string) $row->team_id === (string) $opponentIds[$row->game_id]);
                $oppPossessions = $oppBoxes->sum(fn ($row) => $this->estimatePossessionsFromRow($row));

                $offRating = $this->safeDivide($points * 100, $possessions);
                $defRating = $this->safeDivide($oppPoints * 100, $oppPossessions > 0 ? $oppPossessions : $possessions);

                WnbaTeamPerformanceTrend::updateOrCreate(
                    ['team_id' => $teamId, 'season' => $season, 'window' => $window],
                    [
                        'games' => $gp,
                        'wins' => $windowRows->where('team_winner', true)->count(),
                        'losses' => $windowRows->where('team_winner', false)->count(),
                        'points_for_avg' => round($points / $gp, 2),
                        'points_against_avg' => round($oppPoints / $gp, 2),
                        'pace_avg' => $gp > 0 ? round($possessions / $gp, 2) : null,
                        'offensive_rating' => $offRating !== null ? round($offRating, 2) : null,
                        'defensive_rating' => $defRating !== null ? round($defRating, 2) : null,
                        'formula_version' => self::FORMULA_VERSION,
                        'computed_at' => now(),
                        'agent_run_id' => $this->reporter?->runId(),
                    ]
                );
                $computed++;
            }
        }

        $this->reporter?->increment('team_performance_trends_computed', $computed);

        return $computed;
    }

    public static function defenseBucket(float $defensiveRating): string
    {
        if ($defensiveRating < 95) {
            return self::DEFENSE_BUCKET_ELITE;
        }
        if ($defensiveRating < 105) {
            return self::DEFENSE_BUCKET_GOOD;
        }
        if ($defensiveRating < 110) {
            return self::DEFENSE_BUCKET_AVERAGE;
        }

        return self::DEFENSE_BUCKET_POOR;
    }

    // ---------------------------------------------------------------- helpers

    private function safeDivide(float|int|null $numerator, float|int|null $denominator): ?float
    {
        if ($numerator === null || $denominator === null || (float) $denominator == 0.0) {
            return null;
        }

        return round($numerator / $denominator, 4);
    }

    /**
     * Ordinary least-squares slope of values vs 0-based index.
     *
     * @param  array<int, float|int|null>  $values
     */
    private function linearSlope(array $values): ?float
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumXX = 0.0;

        foreach ($values as $i => $y) {
            $x = (float) $i;
            $yv = (float) $y;
            $sumX += $x;
            $sumY += $yv;
            $sumXY += $x * $yv;
            $sumXX += $x * $x;
        }

        $denom = ($n * $sumXX) - ($sumX * $sumX);
        if ($denom == 0.0) {
            return null;
        }

        return round((($n * $sumXY) - ($sumX * $sumY)) / $denom, 4);
    }

    private function estimatePossessionsFromRow(WnbaGameTeam $row): float
    {
        return (float) $row->field_goals_attempted
            + 0.44 * (float) $row->free_throws_attempted
            - (float) $row->offensive_rebounds
            + (float) $row->turnovers;
    }

    /**
     * Box-score usage estimate:
     * (FGA + 0.44*FTA + TOV) * (TeamMinutes/5) / (MP * (TmFGA + 0.44*TmFTA + TmTOV))
     *
     * @param  array{fga: int, fta: int, tov: int}|null  $teamTotals
     */
    private function usagePct(WnbaPlayerGame $pg, ?float $minutes, ?array $teamTotals): ?float
    {
        if ($minutes === null || $minutes <= 0 || $teamTotals === null) {
            return null;
        }

        $teamPlays = $teamTotals['fga'] + 0.44 * $teamTotals['fta'] + $teamTotals['tov'];
        $playerPlays = $pg->field_goals_attempted + 0.44 * $pg->free_throws_attempted + $pg->turnovers;

        return $this->safeDivide(
            $playerPlays * (self::TEAM_MINUTES_REGULATION / 5),
            $minutes * $teamPlays
        );
    }

    private function gameScore(WnbaPlayerGame $pg): float
    {
        return round(
            $pg->points
            + 0.4 * $pg->field_goals_made
            - 0.7 * $pg->field_goals_attempted
            - 0.4 * ($pg->free_throws_attempted - $pg->free_throws_made)
            + 0.7 * $pg->offensive_rebounds
            + 0.3 * $pg->defensive_rebounds
            + $pg->steals
            + 0.7 * $pg->assists
            + 0.7 * $pg->blocks
            - 0.4 * $pg->fouls
            - $pg->turnovers,
            2
        );
    }

    /**
     * @param  array<int, int|string>  $gameIds
     * @return array<string, array{fga: int, fta: int, tov: int}>
     */
    private function teamTotalsForGames(array $gameIds): array
    {
        $rows = WnbaGameTeam::query()
            ->whereIn('game_id', $gameIds)
            ->get(['game_id', 'team_id', 'field_goals_attempted', 'free_throws_attempted', 'turnovers']);

        $totals = [];
        foreach ($rows as $row) {
            $totals[$row->game_id.'|'.$row->team_id] = [
                'fga' => (int) $row->field_goals_attempted,
                'fta' => (int) $row->free_throws_attempted,
                'tov' => (int) $row->turnovers,
            ];
        }

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    private function sumCountingStats(Collection $games): array
    {
        return [
            'points' => (int) $games->sum('points'),
            'rebounds' => (int) $games->sum('rebounds'),
            'oreb' => (int) $games->sum('offensive_rebounds'),
            'dreb' => (int) $games->sum('defensive_rebounds'),
            'assists' => (int) $games->sum('assists'),
            'steals' => (int) $games->sum('steals'),
            'blocks' => (int) $games->sum('blocks'),
            'turnovers' => (int) $games->sum('turnovers'),
            'fouls' => (int) $games->sum('fouls'),
            'fgm' => (int) $games->sum('field_goals_made'),
            'fga' => (int) $games->sum('field_goals_attempted'),
            'tpm' => (int) $games->sum('three_point_field_goals_made'),
            'tpa' => (int) $games->sum('three_point_field_goals_attempted'),
            'ftm' => (int) $games->sum('free_throws_made'),
            'fta' => (int) $games->sum('free_throws_attempted'),
        ];
    }

    /**
     * @return array{games: int, points: float|null, rebounds: float|null, assists: float|null, plus_minus: float|null}
     */
    private function splitAverages(Collection $games): array
    {
        $count = $games->count();

        return [
            'games' => $count,
            'points' => $count > 0 ? round($games->avg('points'), 2) : null,
            'rebounds' => $count > 0 ? round($games->avg('rebounds'), 2) : null,
            'assists' => $count > 0 ? round($games->avg('assists'), 2) : null,
            'plus_minus' => $this->plusMinusAvg($games),
        ];
    }

    /**
     * @param  array<string, int>  $totals
     * @return array<string, float|null>
     */
    private function perMinutes(array $totals, float $minutesTotal, int $perMinutes): array
    {
        if ($minutesTotal <= 0) {
            return [
                'points' => null,
                'rebounds' => null,
                'assists' => null,
                'steals' => null,
                'blocks' => null,
                'turnovers' => null,
            ];
        }

        $factor = $perMinutes / $minutesTotal;

        return [
            'points' => round($totals['points'] * $factor, 2),
            'rebounds' => round($totals['rebounds'] * $factor, 2),
            'assists' => round($totals['assists'] * $factor, 2),
            'steals' => round($totals['steals'] * $factor, 2),
            'blocks' => round($totals['blocks'] * $factor, 2),
            'turnovers' => round($totals['turnovers'] * $factor, 2),
        ];
    }

    private function plusMinusAvg(Collection $games): ?float
    {
        $withPm = $games->filter(fn ($pg) => $pg->plus_minus !== null);
        if ($withPm->isEmpty()) {
            return null;
        }

        return round((float) $withPm->avg('plus_minus'), 2);
    }

    /**
     * @return array{games: int, wins: int, points_for_avg: float|null, points_against_avg: float|null}
     */
    private function teamSplit(Collection $rows): array
    {
        $count = $rows->count();

        return [
            'games' => $count,
            'wins' => $rows->where('team_winner', true)->count(),
            'points_for_avg' => $count > 0 ? round($rows->avg('team_score'), 2) : null,
            'points_against_avg' => $count > 0 ? round($rows->avg('opponent_team_score'), 2) : null,
        ];
    }

    private function completedTeamGames(string $teamId, int $season): Collection
    {
        return WnbaGameTeam::query()
            ->join('wnba_games as g', 'g.id', '=', 'wnba_game_teams.game_id')
            ->where('wnba_game_teams.team_id', $teamId)
            ->where('g.season', $season)
            // Schedule import seeds 0-0 placeholder rows for future games.
            ->whereRaw('(wnba_game_teams.team_score + wnba_game_teams.opponent_team_score) > 0')
            ->where(function ($query) {
                $query->whereNull('wnba_game_teams.validation_status')
                    ->orWhere('wnba_game_teams.validation_status', '!=', BoxScoreValidator::STATUS_INVALID);
            })
            ->tap(fn ($query) => TeamCatalog::wherePrimaryCompetition($query, 'wnba_game_teams.team_id'))
            ->select('wnba_game_teams.*')
            ->get();
    }

    private function opponentRowsFor(Collection $rows): Collection
    {
        $keys = $rows->map(fn ($row) => $row->game_id.'|'.$row->opponent_team_id)->all();

        return WnbaGameTeam::query()
            ->whereIn('game_id', $rows->pluck('game_id')->unique()->all())
            ->get()
            ->filter(fn ($row) => in_array($row->game_id.'|'.$row->team_id, $keys, true))
            ->values();
    }

    /**
     * Entities with unresolved identity conflicts are excluded from aggregates
     * so a mis-mapped player or team cannot poison season averages.
     *
     * @return array<int, string>
     */
    private function unresolvedEntityKeys(string $entityType): array
    {
        return WnbaDataConflict::query()
            ->where('entity_type', $entityType)
            ->where('requires_review', true)
            ->whereNull('resolved_at')
            ->pluck('entity_key')
            ->map(fn ($key) => (string) $key)
            ->unique()
            ->values()
            ->all();
    }
}
