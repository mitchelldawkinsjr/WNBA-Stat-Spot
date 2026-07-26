<?php

namespace App\Services\WNBA\Analytics;

use App\Models\WnbaGameTeam;
use App\Services\WNBA\Data\Support\TeamForeignKeyResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TeamAnalyticsService
{
    private const CACHE_TTL = 3600; // 1 hour

    private const WNBA_GAME_MINUTES = 40;

    public function __construct(
        private AggregateStatsReader $aggregates,
    ) {}

    /**
     * Get comprehensive team performance metrics
     */
    public function getTeamPerformanceMetrics(int|string $teamId, int $season, ?int $lastNGames = null): array
    {
        $cacheKey = "team_performance_v2_{$teamId}_{$season}_".($lastNGames ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($teamId, $season, $lastNGames) {
            try {
                $games = $this->getTeamGames($teamId, $season, $lastNGames);

                if ($games->isEmpty() && $this->aggregates->teamSeasonStat($teamId, $season) === null) {
                    return $this->getEmptyMetrics();
                }

                $window = match (true) {
                    $lastNGames !== null && $lastNGames <= 5 => 'l5',
                    $lastNGames !== null && $lastNGames <= 10 => 'l10',
                    default => 'season',
                };
                $trend = $this->aggregates->teamTrendWindow($teamId, $season, $window);
                $seasonStat = $this->aggregates->teamSeasonStat($teamId, $season);
                $efficiency = $this->efficiencyFromAggregates($seasonStat, $trend);
                $pace = $this->paceFromAggregates($seasonStat, $trend);

                return [
                    'basic_stats' => $games->isNotEmpty()
                        ? $this->calculateBasicTeamStats($games)
                        : $this->basicStatsFromSeasonStat($seasonStat),
                    'advanced_stats' => $games->isNotEmpty()
                        ? $this->calculateAdvancedTeamStats($games)
                        : [],
                    'pace_metrics' => $pace,
                    'efficiency_ratings' => $efficiency,
                    'home_away_splits' => $games->isNotEmpty()
                        ? $this->calculateHomeAwaySplits($games)
                        : ($seasonStat?->splits ?? []),
                    'recent_form' => $this->recentFormFromTrends($teamId, $season),
                    'opponent_strength' => $games->isNotEmpty()
                        ? $this->calculateOpponentStrength($games)
                        : [],
                    'clutch_performance' => $games->isNotEmpty()
                        ? $this->calculateClutchPerformance($teamId, $games)
                        : [],
                ];
            } catch (\Exception $e) {
                Log::error('Error calculating team performance metrics', [
                    'team_id' => $teamId,
                    'season' => $season,
                    'error' => $e->getMessage(),
                ]);

                return $this->getEmptyMetrics();
            }
        });
    }

    /**
     * @return array{offensive_rating: ?float, defensive_rating: ?float, net_rating: ?float, efficiency_grade: string}
     */
    private function efficiencyFromAggregates(?\App\Models\WnbaTeamSeasonStat $seasonStat, ?array $trend): array
    {
        $off = $trend['offensive_rating'] ?? $seasonStat?->offensive_rating;
        $def = $trend['defensive_rating'] ?? $seasonStat?->defensive_rating;
        $net = ($off !== null && $def !== null) ? round((float) $off - (float) $def, 2) : ($seasonStat?->net_rating);

        return [
            'offensive_rating' => $off !== null ? (float) $off : null,
            'defensive_rating' => $def !== null ? (float) $def : null,
            'net_rating' => $net !== null ? (float) $net : null,
            'efficiency_grade' => $net !== null ? $this->getEfficiencyGrade((float) $net) : 'N/A',
        ];
    }

    /**
     * @return array{pace: ?float, possessions_per_game: ?float, tempo_rating: string, games_analyzed: int|null}
     */
    private function paceFromAggregates(?\App\Models\WnbaTeamSeasonStat $seasonStat, ?array $trend): array
    {
        $pace = $trend['pace_avg'] ?? $seasonStat?->pace;
        $poss = $seasonStat?->possessions_per_game ?? $pace;

        return [
            'pace' => $pace !== null ? (float) $pace : null,
            'possessions_per_game' => $poss !== null ? (float) $poss : null,
            'tempo_rating' => $pace !== null ? $this->getTempoRating((float) $pace) : 'Unknown',
            'games_analyzed' => $trend['games'] ?? $seasonStat?->games_played,
        ];
    }

    private function basicStatsFromSeasonStat(?\App\Models\WnbaTeamSeasonStat $stat): array
    {
        if ($stat === null) {
            return [];
        }

        $gp = max(1, (int) $stat->games_played);

        return [
            'wins' => $stat->wins,
            'losses' => $stat->losses,
            'win_percentage' => round($stat->wins / $gp, 3),
            'points_per_game' => $stat->points_for_avg,
            'points_allowed_per_game' => $stat->points_against_avg,
            'point_differential' => ($stat->points_for_avg !== null && $stat->points_against_avg !== null)
                ? round((float) $stat->points_for_avg - (float) $stat->points_against_avg, 2)
                : null,
        ];
    }

    private function recentFormFromTrends(int|string $teamId, int $season): array
    {
        $l5 = $this->aggregates->teamTrendWindow($teamId, $season, 'l5');
        $l10 = $this->aggregates->teamTrendWindow($teamId, $season, 'l10');

        return [
            'last_5' => $l5,
            'last_10' => $l10,
            'trends' => $this->aggregates->teamTrends($teamId, $season),
        ];
    }

    /**
     * Calculate team pace and tempo metrics
     */
    public function calculatePaceMetrics(int $teamId, Collection $games): array
    {
        $totalPossessions = 0;
        $totalMinutes = 0;
        $gameCount = 0;

        foreach ($games as $game) {
            $possessions = $this->estimatePossessions($game);
            if ($possessions > 0) {
                $totalPossessions += $possessions;
                $totalMinutes += self::WNBA_GAME_MINUTES;
                $gameCount++;
            }
        }

        if ($gameCount === 0) {
            return ['pace' => 0, 'possessions_per_game' => 0, 'tempo_rating' => 'Unknown'];
        }

        $pace = ($totalPossessions / $totalMinutes) * self::WNBA_GAME_MINUTES;
        $possessionsPerGame = $totalPossessions / $gameCount;

        return [
            'pace' => round($pace, 2),
            'possessions_per_game' => round($possessionsPerGame, 2),
            'tempo_rating' => $this->getTempoRating($pace),
            'games_analyzed' => $gameCount,
        ];
    }

    /**
     * Calculate offensive and defensive efficiency ratings
     */
    public function calculateEfficiencyRatings(Collection $games): array
    {
        $totalOffensiveRating = 0;
        $totalDefensiveRating = 0;
        $gameCount = 0;

        foreach ($games as $game) {
            $possessions = $this->estimatePossessions($game);
            if ($possessions > 0) {
                $offensiveRating = ($game->team_score / $possessions) * 100;
                $defensiveRating = ($game->opponent_team_score / $possessions) * 100;

                $totalOffensiveRating += $offensiveRating;
                $totalDefensiveRating += $defensiveRating;
                $gameCount++;
            }
        }

        if ($gameCount === 0) {
            return [
                'offensive_rating' => 0,
                'defensive_rating' => 0,
                'net_rating' => 0,
                'efficiency_grade' => 'N/A',
            ];
        }

        $avgOffensiveRating = $totalOffensiveRating / $gameCount;
        $avgDefensiveRating = $totalDefensiveRating / $gameCount;
        $netRating = $avgOffensiveRating - $avgDefensiveRating;

        return [
            'offensive_rating' => round($avgOffensiveRating, 2),
            'defensive_rating' => round($avgDefensiveRating, 2),
            'net_rating' => round($netRating, 2),
            'efficiency_grade' => $this->getEfficiencyGrade($netRating),
        ];
    }

    /**
     * Calculate team shooting and scoring trends
     */
    public function getShootingTrends(int|string $teamId, int $season, int $lastNGames = 10): array
    {
        $cacheKey = "team_shooting_trends_{$teamId}_{$season}_{$lastNGames}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($teamId, $season, $lastNGames) {
            $games = $this->getTeamGames($teamId, $season, $lastNGames);

            if ($games->isEmpty()) {
                return [];
            }

            $trends = [];
            foreach ($games as $index => $game) {
                $trends[] = [
                    'game_number' => $index + 1,
                    'date' => $game->game->game_date ?? null,
                    'fg_percentage' => $game->field_goals_attempted > 0
                        ? round(($game->field_goals_made / $game->field_goals_attempted) * 100, 1)
                        : 0,
                    'three_point_percentage' => $game->three_point_field_goals_attempted > 0
                        ? round(($game->three_point_field_goals_made / $game->three_point_field_goals_attempted) * 100, 1)
                        : 0,
                    'ft_percentage' => $game->free_throws_attempted > 0
                        ? round(($game->free_throws_made / $game->free_throws_attempted) * 100, 1)
                        : 0,
                    'points' => $game->team_score,
                    'opponent_points' => $game->opponent_team_score,
                    'point_differential' => $game->team_score - $game->opponent_team_score,
                ];
            }

            return $trends;
        });
    }

    /**
     * Analyze team performance against specific opponent types
     */
    public function getOpponentAnalysis(int|string $teamId, int $season): array
    {
        $cacheKey = "team_opponent_analysis_{$teamId}_{$season}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($teamId, $season) {
            $games = $this->getTeamGames($teamId, $season);

            $analysis = [
                'vs_winning_teams' => ['wins' => 0, 'losses' => 0, 'avg_score' => 0, 'avg_allowed' => 0],
                'vs_losing_teams' => ['wins' => 0, 'losses' => 0, 'avg_score' => 0, 'avg_allowed' => 0],
                'vs_top_defenses' => ['wins' => 0, 'losses' => 0, 'avg_score' => 0, 'avg_allowed' => 0],
                'vs_top_offenses' => ['wins' => 0, 'losses' => 0, 'avg_score' => 0, 'avg_allowed' => 0],
            ];

            // This would require additional logic to categorize opponents
            // For now, return basic structure
            return $analysis;
        });
    }

    /**
     * Get team defensive metrics and rankings
     */
    public function getDefensiveMetrics(int|string $teamId, int $season): array
    {
        $cacheKey = "team_defensive_metrics_{$teamId}_{$season}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($teamId, $season) {
            $games = $this->getTeamGames($teamId, $season);

            if ($games->isEmpty()) {
                return [];
            }

            $totalGames = $games->count();
            $totalSteals = $games->sum('steals');
            $totalBlocks = $games->sum('blocks');
            $totalTurnoversForced = $games->sum('turnovers'); // Opponent turnovers
            $totalPointsAllowed = $games->sum('opponent_team_score');
            $totalFgAllowed = 0; // Would need opponent shooting data

            return [
                'steals_per_game' => round($totalSteals / $totalGames, 2),
                'blocks_per_game' => round($totalBlocks / $totalGames, 2),
                'turnovers_forced_per_game' => round($totalTurnoversForced / $totalGames, 2),
                'points_allowed_per_game' => round($totalPointsAllowed / $totalGames, 2),
                'defensive_stops' => $this->calculateDefensiveStops($games),
                'defensive_efficiency' => $this->calculateDefensiveEfficiency($games),
            ];
        });
    }

    /**
     * Calculate team strength of schedule
     */
    public function getStrengthOfSchedule(int|string $teamId, int $season): array
    {
        $cacheKey = "team_sos_{$teamId}_{$season}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($teamId, $season) {
            $games = $this->getTeamGames($teamId, $season);

            if ($games->isEmpty()) {
                return ['sos_rating' => 0, 'difficulty' => 'Unknown'];
            }

            // Calculate based on opponent win percentages
            $totalOpponentWinPct = 0;
            $gameCount = 0;

            foreach ($games as $game) {
                $opponentWinPct = $this->getOpponentWinPercentage($game->opponent_team_id, $season);
                if ($opponentWinPct !== null) {
                    $totalOpponentWinPct += $opponentWinPct;
                    $gameCount++;
                }
            }

            if ($gameCount === 0) {
                return ['sos_rating' => 0, 'difficulty' => 'Unknown'];
            }

            $avgOpponentWinPct = $totalOpponentWinPct / $gameCount;

            return [
                'sos_rating' => round($avgOpponentWinPct, 3),
                'difficulty' => $this->getScheduleDifficulty($avgOpponentWinPct),
                'games_analyzed' => $gameCount,
            ];
        });
    }

    /**
     * Get comprehensive analytics for a team (shape expected by the team profile UI).
     */
    public function getAnalytics(int|string $teamId, ?int $season = null): array
    {
        $season = $season ?? (int) config('wnba.seasons.current_season');

        try {
            $games = $this->getTeamGames($teamId, $season);
            $seasonStat = $this->aggregates->teamSeasonStat($teamId, $season);
            $efficiency = $this->aggregates->efficiencySnapshot($teamId, $season);

            if ($games->isEmpty() && $seasonStat === null) {
                return $this->emptyAnalyticsResponse($teamId, $season);
            }

            $basic = $games->isNotEmpty()
                ? $this->calculateBasicTeamStats($games)
                : $this->basicStatsFromSeasonStat($seasonStat);

            $streak = $this->calculateWinLossStreak($games);
            $homeAway = $this->normalizeHomeAwaySplits(
                $games->isNotEmpty()
                    ? $this->calculateHomeAwaySplits($games)
                    : ($seasonStat?->splits ?? [])
            );

            return [
                'team_id' => $teamId,
                'season' => $season,
                'game_results' => $this->formatGameResults($games),
                'season_stats' => [
                    'wins' => (int) ($basic['wins'] ?? $seasonStat?->wins ?? 0),
                    'losses' => (int) ($basic['losses'] ?? $seasonStat?->losses ?? 0),
                    'win_percentage' => (float) ($basic['win_percentage'] ?? 0),
                    'points_per_game' => $basic['points_per_game'] ?? $seasonStat?->points_for_avg,
                    'points_allowed_per_game' => $basic['points_allowed_per_game'] ?? $seasonStat?->points_against_avg,
                    'streak' => $streak['streak'],
                    'streak_type' => $streak['streak_type'],
                ],
                'advanced_metrics' => [
                    'offensive_rating' => $efficiency['offensive_rating'],
                    'defensive_rating' => $efficiency['defensive_rating'],
                    'net_rating' => $efficiency['net_rating'],
                    'pace' => $efficiency['pace'],
                    'true_shooting_percentage' => $seasonStat?->ts_pct !== null
                        ? round((float) $seasonStat->ts_pct * 100, 1)
                        : ($games->isNotEmpty() ? $this->calculateTrueShootingPercentage($games) : null),
                ],
                'home_away_splits' => $homeAway,
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get team analytics', [
                'team_id' => $teamId,
                'season' => $season,
                'error' => $e->getMessage(),
            ]);

            return [
                'team_id' => $teamId,
                'season' => $season,
                'error' => 'Failed to retrieve analytics',
                'message' => $e->getMessage(),
            ];
        }
    }

    // Private helper methods

    private function getTeamGames(int|string $teamId, int $season, ?int $lastNGames = null): Collection
    {
        $teamKeys = TeamForeignKeyResolver::foreignKeysForReference($teamId);

        $query = WnbaGameTeam::whereIn('team_id', $teamKeys)
            ->whereHas('game', function ($q) use ($season) {
                $q->where('season', $season);
            })
            // Schedule import seeds 0-0 placeholder rows for future games.
            ->whereRaw('(wnba_game_teams.team_score + wnba_game_teams.opponent_team_score) > 0')
            ->with(['game', 'opponentTeam'])
            ->join('wnba_games', 'wnba_games.id', '=', 'wnba_game_teams.game_id')
            ->select('wnba_game_teams.*')
            ->orderByDesc('wnba_games.game_date');

        if ($lastNGames) {
            $query->limit($lastNGames);
        }

        return $query->get();
    }

    /**
     * @return list<array{date: ?string, opponent: string, points_scored: int, points_allowed: int, result: 'W'|'L', home_away: string}>
     */
    private function formatGameResults(Collection $games): array
    {
        return $games->map(function (WnbaGameTeam $game) {
            $opponent = $game->opponentTeam;

            return [
                'date' => $game->game?->game_date?->format('Y-m-d'),
                'opponent' => $opponent?->team_abbreviation
                    ?? $opponent?->team_display_name
                    ?? (string) $game->opponent_team_id,
                'points_scored' => (int) $game->team_score,
                'points_allowed' => (int) $game->opponent_team_score,
                'result' => $game->team_winner ? 'W' : 'L',
                'home_away' => (string) ($game->home_away ?? 'home'),
            ];
        })->values()->all();
    }

    /**
     * @return array{streak: int, streak_type: 'W'|'L'}
     */
    private function calculateWinLossStreak(Collection $games): array
    {
        if ($games->isEmpty()) {
            return ['streak' => 0, 'streak_type' => 'W'];
        }

        $first = $games->first();
        $streakType = $first->team_winner ? 'W' : 'L';
        $streak = 0;

        foreach ($games as $game) {
            $result = $game->team_winner ? 'W' : 'L';
            if ($result !== $streakType) {
                break;
            }
            $streak++;
        }

        return ['streak' => $streak, 'streak_type' => $streakType];
    }

    /**
     * Normalize home/away splits to the UI field names.
     *
     * @param  array<string, mixed>  $splits
     * @return array{home: array{wins: int, losses: int, points_per_game: float, points_allowed_per_game: float}, away: array{wins: int, losses: int, points_per_game: float, points_allowed_per_game: float}}|null
     */
    private function normalizeHomeAwaySplits(array $splits): ?array
    {
        if ($splits === [] || (! isset($splits['home']) && ! isset($splits['away']))) {
            return null;
        }

        return [
            'home' => $this->normalizeSplitSide($splits['home'] ?? []),
            'away' => $this->normalizeSplitSide($splits['away'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $side
     * @return array{wins: int, losses: int, points_per_game: float, points_allowed_per_game: float}
     */
    private function normalizeSplitSide(array $side): array
    {
        $wins = (int) ($side['wins'] ?? 0);
        $games = (int) ($side['games'] ?? 0);
        $losses = isset($side['losses'])
            ? (int) $side['losses']
            : max(0, $games - $wins);

        return [
            'wins' => $wins,
            'losses' => $losses,
            'points_per_game' => (float) ($side['points_per_game'] ?? $side['ppg'] ?? $side['points_for_avg'] ?? 0),
            'points_allowed_per_game' => (float) ($side['points_allowed_per_game'] ?? $side['opp_ppg'] ?? $side['points_against_avg'] ?? 0),
        ];
    }

    /**
     * @return array{team_id: int|string, season: int, game_results: list<empty>, season_stats: null, advanced_metrics: null, home_away_splits: null, generated_at: string}
     */
    private function emptyAnalyticsResponse(int|string $teamId, int $season): array
    {
        return [
            'team_id' => $teamId,
            'season' => $season,
            'game_results' => [],
            'season_stats' => null,
            'advanced_metrics' => null,
            'home_away_splits' => null,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function calculateBasicTeamStats(Collection $games): array
    {
        $totalGames = $games->count();

        return [
            'games_played' => $totalGames,
            'wins' => $games->where('team_winner', true)->count(),
            'losses' => $games->where('team_winner', false)->count(),
            'win_percentage' => $totalGames > 0 ? round($games->where('team_winner', true)->count() / $totalGames, 3) : 0,
            'points_per_game' => round($games->avg('team_score'), 2),
            'points_allowed_per_game' => round($games->avg('opponent_team_score'), 2),
            'point_differential' => round($games->avg('team_score') - $games->avg('opponent_team_score'), 2),
            'field_goal_percentage' => $this->calculateTeamFgPercentage($games),
            'three_point_percentage' => $this->calculateTeamThreePointPercentage($games),
            'free_throw_percentage' => $this->calculateTeamFtPercentage($games),
            'rebounds_per_game' => round($games->avg('rebounds'), 2),
            'assists_per_game' => round($games->avg('assists'), 2),
            'turnovers_per_game' => round($games->avg('turnovers'), 2),
        ];
    }

    private function calculateAdvancedTeamStats(Collection $games): array
    {
        $totalGames = $games->count();

        if ($totalGames === 0) {
            return [];
        }

        return [
            'effective_fg_percentage' => $this->calculateEffectiveFgPercentage($games),
            'true_shooting_percentage' => $this->calculateTrueShootingPercentage($games),
            'assist_to_turnover_ratio' => $this->calculateAssistToTurnoverRatio($games),
            'rebound_percentage' => $this->calculateReboundPercentage($games),
            'steal_percentage' => $this->calculateStealPercentage($games),
            'block_percentage' => $this->calculateBlockPercentage($games),
        ];
    }

    private function calculateHomeAwaySplits(Collection $games): array
    {
        $homeGames = $games->where('home_away', 'home');
        $awayGames = $games->where('home_away', 'away');

        return [
            'home' => [
                'games' => $homeGames->count(),
                'wins' => $homeGames->where('team_winner', true)->count(),
                'losses' => $homeGames->where('team_winner', false)->count(),
                'win_pct' => $homeGames->count() > 0 ? round($homeGames->where('team_winner', true)->count() / $homeGames->count(), 3) : 0,
                'ppg' => round($homeGames->avg('team_score'), 2),
                'opp_ppg' => round($homeGames->avg('opponent_team_score'), 2),
            ],
            'away' => [
                'games' => $awayGames->count(),
                'wins' => $awayGames->where('team_winner', true)->count(),
                'losses' => $awayGames->where('team_winner', false)->count(),
                'win_pct' => $awayGames->count() > 0 ? round($awayGames->where('team_winner', true)->count() / $awayGames->count(), 3) : 0,
                'ppg' => round($awayGames->avg('team_score'), 2),
                'opp_ppg' => round($awayGames->avg('opponent_team_score'), 2),
            ],
        ];
    }

    private function calculateRecentForm(Collection $games): array
    {
        $last5Games = $games->take(5);
        $last10Games = $games->take(10);

        return [
            'last_5' => [
                'wins' => $last5Games->where('team_winner', true)->count(),
                'losses' => $last5Games->where('team_winner', false)->count(),
                'ppg' => round($last5Games->avg('team_score'), 2),
                'opp_ppg' => round($last5Games->avg('opponent_team_score'), 2),
            ],
            'last_10' => [
                'wins' => $last10Games->where('team_winner', true)->count(),
                'losses' => $last10Games->where('team_winner', false)->count(),
                'ppg' => round($last10Games->avg('team_score'), 2),
                'opp_ppg' => round($last10Games->avg('opponent_team_score'), 2),
            ],
        ];
    }

    private function calculateOpponentStrength(Collection $games): array
    {
        // This would require additional opponent data analysis
        return [
            'avg_opponent_record' => 0.500,
            'strength_of_schedule' => 'Average',
            'quality_wins' => 0,
            'bad_losses' => 0,
        ];
    }

    private function calculateClutchPerformance(int $teamId, Collection $games): array
    {
        // This would require play-by-play data for clutch situations
        return [
            'close_game_record' => '0-0',
            'clutch_fg_percentage' => 0,
            'clutch_scoring_avg' => 0,
            'games_decided_by_5_or_less' => 0,
        ];
    }

    private function estimatePossessions(WnbaGameTeam $game): float
    {
        // Estimate possessions using the formula:
        // Possessions ≈ FGA + 0.44 * FTA - ORB + TO
        return $game->field_goals_attempted +
               (0.44 * $game->free_throws_attempted) -
               $game->offensive_rebounds +
               $game->turnovers;
    }

    private function calculateTeamFgPercentage(Collection $games): float
    {
        $totalMade = $games->sum('field_goals_made');
        $totalAttempted = $games->sum('field_goals_attempted');

        return $totalAttempted > 0 ? round(($totalMade / $totalAttempted) * 100, 1) : 0;
    }

    private function calculateTeamThreePointPercentage(Collection $games): float
    {
        $totalMade = $games->sum('three_point_field_goals_made');
        $totalAttempted = $games->sum('three_point_field_goals_attempted');

        return $totalAttempted > 0 ? round(($totalMade / $totalAttempted) * 100, 1) : 0;
    }

    private function calculateTeamFtPercentage(Collection $games): float
    {
        $totalMade = $games->sum('free_throws_made');
        $totalAttempted = $games->sum('free_throws_attempted');

        return $totalAttempted > 0 ? round(($totalMade / $totalAttempted) * 100, 1) : 0;
    }

    private function calculateEffectiveFgPercentage(Collection $games): float
    {
        $totalFgMade = $games->sum('field_goals_made');
        $totalThreeMade = $games->sum('three_point_field_goals_made');
        $totalFgAttempted = $games->sum('field_goals_attempted');

        if ($totalFgAttempted === 0) {
            return 0;
        }

        return round((($totalFgMade + 0.5 * $totalThreeMade) / $totalFgAttempted) * 100, 1);
    }

    private function calculateTrueShootingPercentage(Collection $games): float
    {
        $totalPoints = $games->sum('team_score');
        $totalFgAttempted = $games->sum('field_goals_attempted');
        $totalFtAttempted = $games->sum('free_throws_attempted');

        $totalShootingAttempts = 2 * ($totalFgAttempted + 0.44 * $totalFtAttempted);

        if ($totalShootingAttempts === 0) {
            return 0;
        }

        return round(($totalPoints / $totalShootingAttempts) * 100, 1);
    }

    private function calculateAssistToTurnoverRatio(Collection $games): float
    {
        $totalAssists = $games->sum('assists');
        $totalTurnovers = $games->sum('turnovers');

        return $totalTurnovers > 0 ? round($totalAssists / $totalTurnovers, 2) : 0;
    }

    private function calculateReboundPercentage(Collection $games): float
    {
        // This would require opponent rebounding data for accurate calculation
        return 50.0; // Placeholder
    }

    private function calculateStealPercentage(Collection $games): float
    {
        // This would require opponent possession data
        return 0.0; // Placeholder
    }

    private function calculateBlockPercentage(Collection $games): float
    {
        // This would require opponent two-point attempt data
        return 0.0; // Placeholder
    }

    private function calculateDefensiveStops(Collection $games): float
    {
        // Simplified defensive stops calculation
        $totalGames = $games->count();
        if ($totalGames === 0) {
            return 0;
        }

        $avgSteals = $games->avg('steals');
        $avgBlocks = $games->avg('blocks');
        $avgDefReb = $games->avg('defensive_rebounds');

        return round($avgSteals + $avgBlocks + $avgDefReb, 2);
    }

    private function calculateDefensiveEfficiency(Collection $games): float
    {
        $totalGames = $games->count();
        if ($totalGames === 0) {
            return 0;
        }

        return round($games->avg('opponent_team_score'), 2);
    }

    private function getOpponentWinPercentage(int|string $opponentId, int $season): ?float
    {
        $opponentKeys = TeamForeignKeyResolver::foreignKeysForReference($opponentId);

        $opponentGames = WnbaGameTeam::whereIn('team_id', $opponentKeys)
            ->whereHas('game', function ($q) use ($season) {
                $q->where('season', $season);
            })
            ->get();

        if ($opponentGames->isEmpty()) {
            return null;
        }

        $wins = $opponentGames->where('team_winner', true)->count();

        return $wins / $opponentGames->count();
    }

    private function getTempoRating(float $pace): string
    {
        if ($pace >= 85) {
            return 'Very Fast';
        }
        if ($pace >= 80) {
            return 'Fast';
        }
        if ($pace >= 75) {
            return 'Average';
        }
        if ($pace >= 70) {
            return 'Slow';
        }

        return 'Very Slow';
    }

    private function getEfficiencyGrade(float $netRating): string
    {
        if ($netRating >= 10) {
            return 'Elite';
        }
        if ($netRating >= 5) {
            return 'Very Good';
        }
        if ($netRating >= 0) {
            return 'Good';
        }
        if ($netRating >= -5) {
            return 'Below Average';
        }

        return 'Poor';
    }

    private function getScheduleDifficulty(float $sosRating): string
    {
        if ($sosRating >= 0.600) {
            return 'Very Difficult';
        }
        if ($sosRating >= 0.550) {
            return 'Difficult';
        }
        if ($sosRating >= 0.450) {
            return 'Average';
        }
        if ($sosRating >= 0.400) {
            return 'Easy';
        }

        return 'Very Easy';
    }

    private function getEmptyMetrics(): array
    {
        return [
            'basic_stats' => [],
            'advanced_stats' => [],
            'pace_metrics' => [],
            'efficiency_ratings' => [],
            'home_away_splits' => [],
            'recent_form' => [],
            'opponent_strength' => [],
            'clutch_performance' => [],
        ];
    }
}
