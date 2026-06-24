<?php

namespace App\Services\WNBA\Analytics;

use App\Models\WnbaGame;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Services\WNBA\Data\PlayerGamelogService;
use App\Utils\StatisticsCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PlayerAnalyticsService
{
    public function __construct(
        private PlayerGamelogService $gamelogService
    ) {}
    /**
     * Get player's recent performance trend (last N games)
     */
    public function getRecentForm(int $playerId, int $games = 10): array
    {
        $recentGames = WnbaPlayerGame::with(['game', 'team'])
            ->where('player_id', $playerId)
            ->whereHas('game', function ($query) {
                $query->orderBy('game_date', 'desc');
            })
            ->limit($games)
            ->get()
            ->sortByDesc('game.game_date')
            ->values();

        if ($recentGames->isEmpty()) {
            return $this->getEmptyFormData();
        }

        $stats = StatisticsCalculator::calculatePlayerAverages($recentGames);
        $trends = $this->calculateTrends($recentGames);

        $first = $recentGames->first();
        $last = $recentGames->last();
        $from = $last?->game?->game_date?->format('Y-m-d');
        $to = $first?->game?->game_date?->format('Y-m-d');

        return [
            'games_analyzed' => $recentGames->count(),
            'date_range' => ($from && $to) ? [
                'from' => $from,
                'to' => $to,
            ] : null,
            'averages' => $stats,
            'trends' => $trends,
            'consistency' => $this->calculateConsistency($recentGames),
            'game_log' => $this->formatGameLog($recentGames),
        ];
    }

    /**
     * Calculate per-36 minute normalized stats
     */
    public function calculatePer36Stats(int $playerId, ?string $dateRange = null): array
    {
        $query = WnbaPlayerGame::where('player_id', $playerId)
            ->where('minutes', '>', 0);

        if ($dateRange) {
            $dates = explode(' to ', $dateRange);
            if (count($dates) === 2) {
                $query->whereHas('game', function ($q) use ($dates) {
                    $q->whereBetween('game_date', [$dates[0], $dates[1]]);
                });
            }
        }

        $games = $query->get();

        if ($games->isEmpty()) {
            return $this->getEmptyPer36Data();
        }

        $totalMinutes = $games->sum('minutes');
        $per36Multiplier = 36 / ($totalMinutes / $games->count());

        return [
            'games_played' => $games->count(),
            'avg_minutes' => round($totalMinutes / $games->count(), 1),
            'per_36_stats' => $this->scaledPer36StatLine($games, $per36Multiplier),
            'efficiency_metrics' => StatisticsCalculator::calculateEfficiencyMetrics($games),
            'shooting_percentages' => StatisticsCalculator::calculateShootingPercentages($games),
        ];
    }

    /**
     * Scale per-game averages toward per-36 using multiplier = 36 / avg_minutes.
     */
    private function scaledPer36StatLine(Collection $games, float $multiplier): array
    {
        if ($games->isEmpty()) {
            return [];
        }

        $scale = static function (Collection $games, float $multiplier, string $attr): float {
            return round(((float) ($games->avg($attr) ?? 0)) * $multiplier, 1);
        };

        return [
            'points' => $scale($games, $multiplier, 'points'),
            'rebounds' => $scale($games, $multiplier, 'rebounds'),
            'assists' => $scale($games, $multiplier, 'assists'),
            'steals' => $scale($games, $multiplier, 'steals'),
            'blocks' => $scale($games, $multiplier, 'blocks'),
            'turnovers' => $scale($games, $multiplier, 'turnovers'),
            'field_goals_made' => $scale($games, $multiplier, 'field_goals_made'),
            'field_goals_attempted' => $scale($games, $multiplier, 'field_goals_attempted'),
            'three_point_field_goals_made' => $scale($games, $multiplier, 'three_point_field_goals_made'),
            'three_point_field_goals_attempted' => $scale($games, $multiplier, 'three_point_field_goals_attempted'),
            'free_throws_made' => $scale($games, $multiplier, 'free_throws_made'),
            'free_throws_attempted' => $scale($games, $multiplier, 'free_throws_attempted'),
        ];
    }

    /**
     * Get opponent-adjusted performance metrics
     */
    public function getOpponentAdjustedStats(int $playerId, int $opponentTeamId): array
    {
        // Get all games against this opponent
        $vsOpponentGames = WnbaPlayerGame::where('player_id', $playerId)
            ->whereHas('game.gameTeams', function ($query) use ($opponentTeamId) {
                $query->where('team_id', $opponentTeamId);
            })
            ->get();

        // Get all other games for comparison
        $otherGames = WnbaPlayerGame::where('player_id', $playerId)
            ->whereDoesntHave('game.gameTeams', function ($query) use ($opponentTeamId) {
                $query->where('team_id', $opponentTeamId);
            })
            ->get();

        return [
            'vs_opponent' => [
                'games' => $vsOpponentGames->count(),
                'stats' => $this->calculateAverageStats($vsOpponentGames),
            ],
            'vs_others' => [
                'games' => $otherGames->count(),
                'stats' => $this->calculateAverageStats($otherGames),
            ],
            'differential' => $this->calculateStatDifferential(
                $this->calculateAverageStats($vsOpponentGames),
                $this->calculateAverageStats($otherGames)
            ),
            'opponent_defensive_rating' => $this->getTeamDefensiveRating($opponentTeamId),
        ];
    }

    /**
     * Calculate usage rate and efficiency metrics
     */
    public function calculateAdvancedMetrics(int $playerId, ?int $gameId = null): array
    {
        $query = WnbaPlayerGame::where('player_id', $playerId);

        if ($gameId) {
            $query->where('game_id', $gameId);
            $playerGames = $query->get();
        } else {
            $playerGames = $query->whereHas('game', function ($q) {
                $q->where('season', (int) config('wnba.seasons.current_season'));
            })->get();
        }

        if ($playerGames->isEmpty()) {
            return $this->getEmptyAdvancedMetrics();
        }

        $metrics = [];
        foreach ($playerGames as $game) {
            $gameMetrics = $this->calculateSingleGameAdvancedMetrics($game);
            $metrics[] = $gameMetrics;
        }

        return [
            'usage_rate' => round(collect($metrics)->avg('usage_rate'), 2),
            'true_shooting_pct' => round(collect($metrics)->avg('true_shooting_pct'), 2),
            'effective_fg_pct' => round(collect($metrics)->avg('effective_fg_pct'), 2),
            'assist_turnover_ratio' => round(collect($metrics)->avg('assist_turnover_ratio'), 2),
            'rebounding_rate' => [
                'offensive' => round(collect($metrics)->avg('offensive_rebounding_rate'), 2),
                'defensive' => round(collect($metrics)->avg('defensive_rebounding_rate'), 2),
                'total' => round(collect($metrics)->avg('total_rebounding_rate'), 2),
            ],
            'player_efficiency_rating' => round(collect($metrics)->avg('player_efficiency_rating'), 2),
            'games_analyzed' => $playerGames->count(),
        ];
    }

    /**
     * Analyze performance vs different defensive ratings
     */
    public function analyzeVsDefensiveRatings(int $playerId): array
    {
        $games = WnbaPlayerGame::with(['game.gameTeams.team'])
            ->where('player_id', $playerId)
            ->get();

        $ratingBuckets = [
            'elite' => ['min' => 0, 'max' => 95, 'games' => [], 'label' => 'Elite Defense (< 95)'],
            'good' => ['min' => 95, 'max' => 105, 'games' => [], 'label' => 'Good Defense (95-105)'],
            'average' => ['min' => 105, 'max' => 115, 'games' => [], 'label' => 'Average Defense (105-115)'],
            'poor' => ['min' => 115, 'max' => 999, 'games' => [], 'label' => 'Poor Defense (> 115)'],
        ];

        foreach ($games as $game) {
            $opponentRating = $this->getOpponentDefensiveRating($game);

            foreach ($ratingBuckets as $key => &$bucket) {
                if ($opponentRating >= $bucket['min'] && $opponentRating < $bucket['max']) {
                    $bucket['games'][] = $game;
                    break;
                }
            }
        }

        $analysis = [];
        foreach ($ratingBuckets as $key => $bucket) {
            if (! empty($bucket['games'])) {
                $analysis[$key] = [
                    'label' => $bucket['label'],
                    'games_count' => count($bucket['games']),
                    'stats' => $this->calculateAverageStats(collect($bucket['games'])),
                    'performance_rating' => $this->calculatePerformanceRating(collect($bucket['games'])),
                ];
            }
        }

        return $analysis;
    }

    /**
     * Home vs Away performance splits
     */
    public function getHomeAwayPerformance(int $playerId): array
    {
        $playerGames = WnbaPlayerGame::with(['game.gameTeams'])
            ->where('player_id', $playerId)
            ->get();

        $homeGames = $playerGames->filter(function (WnbaPlayerGame $pg) {
            $row = $pg->game?->gameTeams?->firstWhere('team_id', $pg->team_id);

            return $row && $row->home_away === 'home';
        })->values();

        $awayGames = $playerGames->filter(function (WnbaPlayerGame $pg) {
            $row = $pg->game?->gameTeams?->firstWhere('team_id', $pg->team_id);

            return $row && $row->home_away === 'away';
        })->values();

        return [
            'home' => [
                'games' => $homeGames->count(),
                'stats' => $this->calculateAverageStats($homeGames),
                'win_percentage' => $this->calculateWinPercentage($homeGames),
            ],
            'away' => [
                'games' => $awayGames->count(),
                'stats' => $this->calculateAverageStats($awayGames),
                'win_percentage' => $this->calculateWinPercentage($awayGames),
            ],
            'differential' => $this->calculateStatDifferential(
                $this->calculateAverageStats($homeGames),
                $this->calculateAverageStats($awayGames)
            ),
        ];
    }

    /**
     * Rest vs fatigue impact analysis
     */
    public function analyzeRestImpact(int $playerId): array
    {
        $games = WnbaPlayerGame::with('game')
            ->where('player_id', $playerId)
            ->get()
            ->sortBy('game.game_date');

        $restAnalysis = [
            'back_to_back' => [],
            'one_day_rest' => [],
            'two_days_rest' => [],
            'three_plus_days_rest' => [],
        ];

        $previousGameDate = null;
        foreach ($games as $game) {
            if ($previousGameDate) {
                $restDays = Carbon::parse($game->game->game_date)
                    ->diffInDays(Carbon::parse($previousGameDate));

                if ($restDays === 1) {
                    $restAnalysis['back_to_back'][] = $game;
                } elseif ($restDays === 2) {
                    $restAnalysis['one_day_rest'][] = $game;
                } elseif ($restDays === 3) {
                    $restAnalysis['two_days_rest'][] = $game;
                } else {
                    $restAnalysis['three_plus_days_rest'][] = $game;
                }
            }
            $previousGameDate = $game->game->game_date;
        }

        $analysis = [];
        foreach ($restAnalysis as $restType => $gamesList) {
            if (! empty($gamesList)) {
                $analysis[$restType] = [
                    'games_count' => count($gamesList),
                    'stats' => $this->calculateAverageStats(collect($gamesList)),
                    'fatigue_impact' => $this->calculateFatigueImpact(collect($gamesList)),
                ];
            }
        }

        return $analysis;
    }

    /**
     * Performance in clutch situations (4th quarter from PBP data)
     */
    public function getClutchPerformance(int $playerId): array
    {
        // This would require play-by-play data analysis
        // For now, we'll use game-level data and approximate clutch performance
        $closeGames = WnbaPlayerGame::where('player_id', $playerId)
            ->whereHas('game.gameTeams', function ($query) {
                $query->whereRaw('ABS(team_score - opponent_team_score) <= 5');
            })
            ->get();

        $blowoutGames = WnbaPlayerGame::where('player_id', $playerId)
            ->whereHas('game.gameTeams', function ($query) {
                $query->whereRaw('ABS(team_score - opponent_team_score) > 15');
            })
            ->get();

        return [
            'close_games' => [
                'games_count' => $closeGames->count(),
                'stats' => $this->calculateAverageStats($closeGames),
                'definition' => 'Games decided by 5 points or less',
            ],
            'blowout_games' => [
                'games_count' => $blowoutGames->count(),
                'stats' => $this->calculateAverageStats($blowoutGames),
                'definition' => 'Games decided by more than 15 points',
            ],
            'clutch_factor' => $this->calculateClutchFactor($closeGames, $blowoutGames),
        ];
    }

    /**
     * Shooting efficiency analysis
     */
    public function getShootingEfficiency(int $playerId): array
    {
        $games = WnbaPlayerGame::where('player_id', $playerId)
            ->where('field_goals_attempted', '>', 0)
            ->get();

        if ($games->isEmpty()) {
            return $this->getEmptyShootingData();
        }

        return [
            'field_goal_percentage' => round(
                ($games->sum('field_goals_made') / $games->sum('field_goals_attempted')) * 100, 1
            ),
            'three_point_percentage' => round(
                ($games->sum('three_point_field_goals_made') / max(1, $games->sum('three_point_field_goals_attempted'))) * 100, 1
            ),
            'free_throw_percentage' => round(
                ($games->sum('free_throws_made') / max(1, $games->sum('free_throws_attempted'))) * 100, 1
            ),
            'true_shooting_percentage' => $this->calculateTrueShootingPercentage($games),
            'effective_field_goal_percentage' => $this->calculateEffectiveFieldGoalPercentage($games),
            'shot_distribution' => [
                'two_point_attempts_per_game' => round($games->avg('field_goals_attempted') - $games->avg('three_point_field_goals_attempted'), 1),
                'three_point_attempts_per_game' => round($games->avg('three_point_field_goals_attempted'), 1),
                'free_throw_attempts_per_game' => round($games->avg('free_throws_attempted'), 1),
                'three_point_rate' => round(($games->sum('three_point_field_goals_attempted') / $games->sum('field_goals_attempted')) * 100, 1),
            ],
            'shooting_consistency' => $this->calculateShootingConsistency($games),
        ];
    }

    /**
     * Rebounding rate calculations
     */
    public function getReboundingRates(int $playerId): array
    {
        $games = WnbaPlayerGame::where('player_id', $playerId)
            ->where('minutes', '>', 0)
            ->get();

        if ($games->isEmpty()) {
            return $this->getEmptyReboundingRates();
        }

        $totalRebounds = $games->sum('rebounds');
        $offensiveRebounds = $games->sum('offensive_rebounds');
        $defensiveRebounds = $games->sum('defensive_rebounds');
        $totalMinutes = $games->sum('minutes');

        return [
            'total_rebounding_rate' => round(($totalRebounds / max($totalMinutes, 1)) * 36, 2),
            'offensive_rebounding_rate' => round(($offensiveRebounds / max($totalMinutes, 1)) * 36, 2),
            'defensive_rebounding_rate' => round(($defensiveRebounds / max($totalMinutes, 1)) * 36, 2),
            'rebounding_percentage' => round(($totalRebounds / max($games->count(), 1)), 2),
            'games_analyzed' => $games->count(),
        ];
    }

    /**
     * Get game context for predictions
     */
    public function getGameContext(int $gameId, int $playerId): array
    {
        $game = WnbaGame::with('gameTeams.team')->find($gameId);
        $playerGame = WnbaPlayerGame::where('game_id', $gameId)
            ->where('player_id', $playerId)
            ->first();

        if (! $game || ! $playerGame) {
            // Return default context if game/player game not found
            return [
                'game_id' => $gameId,
                'game_date' => now(),
                'season_type' => 'Regular Season',
                'player_team_id' => null,
                'opponent_team_id' => null,
                'home_away' => 'home',
                'rest_days' => 2,
                'pace_factor' => 1.0,
                'opponent_defense_rating' => 100.0,
                'projected_minutes' => 30.0,
            ];
        }

        $playerTeam = $playerGame->team_id;
        $opponentTeam = $game->gameTeams->where('team_id', '!=', $playerTeam)->first();

        return [
            'game_id' => $gameId,
            'game_date' => $game->game_date,
            'season_type' => $game->season_type ?? 'Regular Season',
            'player_team_id' => $playerTeam,
            'opponent_team_id' => $opponentTeam->team_id ?? null,
            'home_away' => $this->getHomeAway($game, $playerTeam),
            'rest_days' => $this->calculateRestDays($playerId, $game->game_date),
            'pace_factor' => $this->calculatePaceFactor($playerTeam, $opponentTeam->team_id ?? null),
            'opponent_defense_rating' => $this->getTeamDefensiveRating($opponentTeam->team_id ?? null),
            'projected_minutes' => $this->projectMinutes($playerId, $gameId),
        ];
    }

    /**
     * Get comprehensive analytics for a player (DB-backed with live ESPN/Tank01 fallback).
     */
    public function getAnalytics(int|string $playerId, ?int $season = null): array
    {
        $season = $season ?? (int) config('wnba.seasons.current_season');

        try {
            $player = $this->resolvePlayer($playerId);
            $dbId = $player?->id ?? (is_numeric((string) $playerId) ? (int) $playerId : null);

            if ($dbId !== null && $this->seasonGamesQuery($dbId, $season)->exists()) {
                return $this->buildSeasonAnalytics($dbId, $season, 'database');
            }

            $externalId = $player
                ? (string) ($player->espn_athlete_id ?? $player->athlete_id)
                : (string) $playerId;

            $liveRows = $this->fetchLiveGamelogRows($externalId, $season);
            if ($liveRows !== []) {
                return $this->buildLiveAnalytics($liveRows, $dbId ?? $externalId, $season);
            }

            if ($dbId !== null) {
                return $this->buildSeasonAnalytics($dbId, $season, 'database', false);
            }

            return $this->emptyAnalyticsResponse($playerId, $season);
        } catch (\Throwable $e) {
            Log::error('Failed to get player analytics', [
                'player_id' => $playerId,
                'season' => $season,
                'error' => $e->getMessage(),
            ]);

            return [
                'player_id' => $playerId,
                'season' => $season,
                'error' => 'Failed to retrieve analytics',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @deprecated Use getAnalytics() — kept for callers passing only DB id.
     */
    public function getAnalyticsForDbPlayer(int $playerId): array
    {
        return $this->getAnalytics($playerId);
    }

    // Private helper methods

    private function getEmptyFormData(): array
    {
        return [
            'games_analyzed' => 0,
            'date_range' => null,
            'averages' => [],
            'trends' => [],
            'consistency' => 0,
            'game_log' => [],
        ];
    }

    private function calculateFormStats($games): array
    {
        return [
            'points' => round($games->avg('points'), 1),
            'rebounds' => round($games->avg('rebounds'), 1),
            'assists' => round($games->avg('assists'), 1),
            'steals' => round($games->avg('steals'), 1),
            'blocks' => round($games->avg('blocks'), 1),
            'turnovers' => round($games->avg('turnovers'), 1),
            'minutes' => round($games->avg('minutes'), 1),
            'field_goal_pct' => round(($games->sum('field_goals_made') / max(1, $games->sum('field_goals_attempted'))) * 100, 1),
            'three_point_pct' => round(($games->sum('three_point_field_goals_made') / max(1, $games->sum('three_point_field_goals_attempted'))) * 100, 1),
            'free_throw_pct' => round(($games->sum('free_throws_made') / max(1, $games->sum('free_throws_attempted'))) * 100, 1),
        ];
    }

    private function calculateTrends($games): array
    {
        $gameArray = $games->toArray();

        return [
            'points_trend' => $this->calculateLinearTrend(array_column($gameArray, 'points')),
            'rebounds_trend' => $this->calculateLinearTrend(array_column($gameArray, 'rebounds')),
            'assists_trend' => $this->calculateLinearTrend(array_column($gameArray, 'assists')),
            'minutes_trend' => $this->calculateLinearTrend(array_column($gameArray, 'minutes')),
        ];
    }

    private function calculateLinearTrend(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0;
        }

        $x = range(1, $n);
        $sumX = array_sum($x);
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $values[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        return round($slope, 3);
    }

    private function calculateConsistency($games): float
    {
        $points = $games->pluck('points')->toArray();
        if (empty($points)) {
            return 0;
        }

        $mean = array_sum($points) / count($points);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $points)) / count($points);

        $coefficientOfVariation = $mean > 0 ? sqrt($variance) / $mean : 0;

        return round((1 - min(1, $coefficientOfVariation)) * 100, 1);
    }

    private function formatGameLog($games): array
    {
        return $games->map(function ($game) {
            return [
                'date' => $game->game->game_date->format('Y-m-d'),
                'opponent' => $this->getOpponentName($game),
                'minutes' => $game->minutes,
                'points' => $game->points,
                'rebounds' => $game->rebounds,
                'assists' => $game->assists,
                'steals' => $game->steals,
                'blocks' => $game->blocks,
                'turnovers' => $game->turnovers,
                'fg_made_attempted' => $game->field_goals_made.'/'.$game->field_goals_attempted,
                'three_pt_made_attempted' => $game->three_point_field_goals_made.'/'.$game->three_point_field_goals_attempted,
                'ft_made_attempted' => $game->free_throws_made.'/'.$game->free_throws_attempted,
            ];
        })->toArray();
    }

    private function getOpponentName($game): string
    {
        // This would need to be implemented based on your game structure
        return 'Opponent'; // Placeholder
    }

    private function getEmptyPer36Data(): array
    {
        return [
            'games_played' => 0,
            'avg_minutes' => 0,
            'per_36_stats' => [],
            'efficiency_metrics' => [],
        ];
    }

    private function calculateEfficiencyMetrics($games): array
    {
        return [
            'true_shooting_pct' => $this->calculateTrueShootingPercentage($games),
            'effective_fg_pct' => $this->calculateEffectiveFieldGoalPercentage($games),
            'usage_rate' => $this->calculateUsageRate($games),
        ];
    }

    private function calculateTrueShootingPercentage($games): float
    {
        $totalPoints = $games->sum('points');
        $totalFGA = $games->sum('field_goals_attempted');
        $totalFTA = $games->sum('free_throws_attempted');

        if ($totalFGA + (0.44 * $totalFTA) == 0) {
            return 0;
        }

        return round(($totalPoints / (2 * ($totalFGA + (0.44 * $totalFTA)))) * 100, 1);
    }

    private function calculateEffectiveFieldGoalPercentage($games): float
    {
        $totalFGM = $games->sum('field_goals_made');
        $total3PM = $games->sum('three_point_field_goals_made');
        $totalFGA = $games->sum('field_goals_attempted');

        if ($totalFGA == 0) {
            return 0;
        }

        return round((($totalFGM + (0.5 * $total3PM)) / $totalFGA) * 100, 1);
    }

    private function calculateUsageRate($games): float
    {
        if ($games->isEmpty()) {
            return 0;
        }

        $totalPlayerFGA = $games->sum('field_goals_attempted');
        $totalPlayerFTA = $games->sum('free_throws_attempted');
        $totalPlayerTOV = $games->sum('turnovers');
        $totalPlayerMinutes = $games->sum('minutes');

        if ($totalPlayerMinutes == 0) {
            return 0;
        }

        // For simplified calculation without full team data, we'll estimate
        // Typical team values per game: ~80 FGA, ~20 FTA, ~15 TOV, ~240 team minutes
        $gamesCount = $games->count();
        $estimatedTeamFGA = $gamesCount * 80;
        $estimatedTeamFTA = $gamesCount * 20;
        $estimatedTeamTOV = $gamesCount * 15;
        $estimatedTeamMinutes = $gamesCount * 240;

        // Usage Rate = 100 * ((FGA + 0.44 * FTA + TOV) * (Team Minutes / 5)) / (Minutes * (Team FGA + 0.44 * Team FTA + Team TOV))
        $playerPossessions = $totalPlayerFGA + (0.44 * $totalPlayerFTA) + $totalPlayerTOV;
        $teamPossessions = $estimatedTeamFGA + (0.44 * $estimatedTeamFTA) + $estimatedTeamTOV;

        $usageRate = 100 * (($playerPossessions * ($estimatedTeamMinutes / 5)) / ($totalPlayerMinutes * $teamPossessions));

        return round($usageRate, 1);
    }

    private function calculateAverageStats($games): array
    {
        if ($games->isEmpty()) {
            return [];
        }

        return [
            'points' => round((float) ($games->avg('points') ?? 0), 1),
            'rebounds' => round((float) ($games->avg('rebounds') ?? 0), 1),
            'assists' => round((float) ($games->avg('assists') ?? 0), 1),
            'steals' => round((float) ($games->avg('steals') ?? 0), 1),
            'blocks' => round((float) ($games->avg('blocks') ?? 0), 1),
            'turnovers' => round((float) ($games->avg('turnovers') ?? 0), 1),
            'minutes' => round((float) ($games->avg('minutes') ?? 0), 1),
            'field_goal_pct' => round(($games->sum('field_goals_made') / max(1, $games->sum('field_goals_attempted'))) * 100, 1),
            'three_point_pct' => round(($games->sum('three_point_field_goals_made') / max(1, $games->sum('three_point_field_goals_attempted'))) * 100, 1),
            'free_throw_pct' => round(($games->sum('free_throws_made') / max(1, $games->sum('free_throws_attempted'))) * 100, 1),
        ];
    }

    private function calculateStatDifferential(array $stats1, array $stats2): array
    {
        $differential = [];
        foreach ($stats1 as $key => $value) {
            if (isset($stats2[$key])) {
                $differential[$key] = round($value - $stats2[$key], 1);
            }
        }

        return $differential;
    }

    private function getTeamDefensiveRating($teamId): float
    {
        // Implementation of getTeamDefensiveRating method
        return 100.0; // Placeholder
    }

    private function getEmptyAdvancedMetrics(): array
    {
        return [
            'usage_rate' => 0,
            'true_shooting_pct' => 0,
            'effective_fg_pct' => 0,
            'assist_turnover_ratio' => 0,
            'rebounding_rate' => ['offensive' => 0, 'defensive' => 0, 'total' => 0],
            'player_efficiency_rating' => 0,
            'games_analyzed' => 0,
        ];
    }

    private function calculateSingleGameAdvancedMetrics($game): array
    {
        return [
            'usage_rate' => 0, // Placeholder - needs team data
            'true_shooting_pct' => $this->calculateGameTrueShootingPct($game),
            'effective_fg_pct' => $this->calculateGameEffectiveFGPct($game),
            'assist_turnover_ratio' => $game->turnovers > 0 ? $game->assists / $game->turnovers : $game->assists,
            'offensive_rebounding_rate' => 0, // Placeholder
            'defensive_rebounding_rate' => 0, // Placeholder
            'total_rebounding_rate' => 0, // Placeholder
            'player_efficiency_rating' => $this->calculatePER($game),
        ];
    }

    private function calculateGameTrueShootingPct($game): float
    {
        $denominator = 2 * ($game->field_goals_attempted + (0.44 * $game->free_throws_attempted));

        return $denominator > 0 ? ($game->points / $denominator) * 100 : 0;
    }

    private function calculateGameEffectiveFGPct($game): float
    {
        return $game->field_goals_attempted > 0 ?
            (($game->field_goals_made + (0.5 * $game->three_point_field_goals_made)) / $game->field_goals_attempted) * 100 : 0;
    }

    private function calculatePER($game): float
    {
        // Simplified PER calculation
        $positiveActions = $game->points + $game->rebounds + $game->assists + $game->steals + $game->blocks;
        $negativeActions = $game->turnovers + ($game->field_goals_attempted - $game->field_goals_made) +
                          ($game->free_throws_attempted - $game->free_throws_made);

        return max(0, $positiveActions - $negativeActions);
    }

    private function getOpponentDefensiveRating($game): float
    {
        // Get opponent team's defensive rating
        return 100; // Placeholder
    }

    private function calculatePerformanceRating($games): float
    {
        if ($games->isEmpty()) {
            return 0;
        }

        return round($games->avg('points') + $games->avg('rebounds') + $games->avg('assists'), 1);
    }

    private function calculateWinPercentage($games): float
    {
        if ($games->isEmpty()) {
            return 0;
        }

        $wins = $games->filter(function ($game) {
            return $this->isWinningGame($game);
        })->count();

        return round(($wins / $games->count()) * 100, 1);
    }

    private function isWinningGame($game): bool
    {
        // This would need to check the game result
        return true; // Placeholder
    }

    private function calculateFatigueImpact($games): array
    {
        return [
            'minutes_impact' => round($games->avg('minutes'), 1),
            'efficiency_impact' => round($games->avg('points') / max(1, $games->avg('minutes')), 2),
        ];
    }

    private function calculateClutchFactor($closeGames, $blowoutGames): float
    {
        if ($closeGames->isEmpty() || $blowoutGames->isEmpty()) {
            return 0;
        }

        $closeAvg = $closeGames->avg('points');
        $blowoutAvg = $blowoutGames->avg('points');

        return $blowoutAvg > 0 ? round($closeAvg / $blowoutAvg, 2) : 0;
    }

    private function getEmptyShootingData(): array
    {
        return [
            'field_goal_percentage' => 0,
            'three_point_percentage' => 0,
            'free_throw_percentage' => 0,
            'true_shooting_percentage' => 0,
            'effective_field_goal_percentage' => 0,
            'shot_distribution' => [],
            'shooting_consistency' => 0,
        ];
    }

    private function calculateShootingConsistency($games): float
    {
        $fgPercentages = $games->map(function ($game) {
            $attempted = $this->statValue($game, 'field_goals_attempted');
            $made = $this->statValue($game, 'field_goals_made');

            return $attempted > 0 ? ($made / $attempted) * 100 : 0;
        })->filter(function ($pct) {
            return $pct > 0;
        });

        if ($fgPercentages->isEmpty()) {
            return 0;
        }

        $mean = $fgPercentages->avg();
        $variance = $fgPercentages->map(function ($pct) use ($mean) {
            return pow($pct - $mean, 2);
        })->avg();

        $coefficientOfVariation = $mean > 0 ? sqrt($variance) / $mean : 0;

        return round((1 - min(1, $coefficientOfVariation)) * 100, 1);
    }

    private function getEmptyReboundingRates(): array
    {
        return [
            'total_rebounding_rate' => 0,
            'offensive_rebounding_rate' => 0,
            'defensive_rebounding_rate' => 0,
            'rebounding_percentage' => 0,
            'games_analyzed' => 0,
        ];
    }

    private function calculateRestDays($playerId, $gameDate): int
    {
        // Implementation of calculateRestDays method
        return 2; // Placeholder
    }

    private function calculatePaceFactor($playerTeam, $opponentTeam): float
    {
        // Implementation of calculatePaceFactor method
        return 1.0; // Placeholder
    }

    private function projectMinutes($playerId, $gameId): float
    {
        // Implementation of projectMinutes method
        return 30.0; // Placeholder
    }

    private function getHomeAway($game, $teamId): string
    {
        // Implementation of getHomeAway method
        return 'home'; // Placeholder
    }

    private function resolvePlayer(int|string $playerId): ?WnbaPlayer
    {
        if (is_numeric((string) $playerId)) {
            $byId = WnbaPlayer::find((int) $playerId);
            if ($byId) {
                return $byId;
            }
        }

        return WnbaPlayer::findByExternalId((string) $playerId);
    }

    private function seasonGamesQuery(int $dbId, int $season, bool $filterSeason = true)
    {
        $query = WnbaPlayerGame::with(['game', 'team'])
            ->where('player_id', $dbId);

        if ($filterSeason) {
            $query->whereHas('game', function ($q) use ($season) {
                $q->where('season', (string) $season);
            });
        }

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLiveGamelogRows(string $externalId, int $season): array
    {
        try {
            $payload = $this->gamelogService->fetch($externalId, $season, 50);

            return array_values(array_filter(
                $payload['games'] ?? [],
                fn (array $game) => ($game['points'] ?? null) !== null || ($game['minutes'] ?? 0) > 0
            ));
        } catch (\Throwable $e) {
            Log::warning('Live gamelog fetch failed for player analytics', [
                'player_id' => $externalId,
                'season' => $season,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSeasonAnalytics(int $dbId, int $season, string $source, bool $filterSeason = true): array
    {
        $games = $this->seasonGamesQuery($dbId, $season, $filterSeason)
            ->get()
            ->sortByDesc(fn (WnbaPlayerGame $pg) => $pg->game?->game_date)
            ->values();

        $recent = $games->take(10);
        $averages = $this->calculateAverageStats($games);
        $shooting = $this->getShootingEfficiencyFromCollection($games);

        return [
            'player_id' => $dbId,
            'season' => $season,
            'source' => $source,
            'recent_form' => [
                'games_analyzed' => $recent->count(),
                'date_range' => $this->dateRangeFromDbGames($recent),
                'averages' => $this->calculateAverageStats($recent),
                'trends' => $this->calculateTrends($recent),
                'consistency' => $this->calculateConsistency($recent),
                'game_log' => $this->formatGameLog($recent),
            ],
            'opponent_adjusted_stats' => $this->getOpponentAdjustedStats($dbId, 1),
            'home_away_performance' => $this->getHomeAwayPerformanceFromCollection($games),
            'clutch_performance' => $this->getClutchPerformance($dbId),
            'shooting_efficiency' => $shooting,
            'rebounding_rates' => $this->getReboundingRates($dbId),
            'game_context' => $this->getGameContext(1, $dbId),
            'summary' => $this->buildSummary($games->count(), $averages, $shooting),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildLiveAnalytics(array $rows, int|string $playerId, int $season): array
    {
        $games = collect($rows);
        $recent = $games->take(10);
        $averages = $this->calculateAverageStats($games);
        $shooting = $this->getShootingEfficiencyFromCollection($games);

        return [
            'player_id' => $playerId,
            'season' => $season,
            'source' => 'espn',
            'recent_form' => [
                'games_analyzed' => $recent->count(),
                'date_range' => $this->dateRangeFromLiveRows($recent->all()),
                'averages' => $this->calculateAverageStats($recent),
                'trends' => $this->calculateTrends($recent),
                'consistency' => $this->calculateConsistency($recent),
                'game_log' => $this->formatLiveGameLog($recent->all()),
            ],
            'opponent_adjusted_stats' => $this->getEmptyOpponentAdjustedStats(),
            'home_away_performance' => $this->getHomeAwayPerformanceFromLiveRows($rows),
            'clutch_performance' => $this->getEmptyClutchPerformance(),
            'shooting_efficiency' => $shooting,
            'rebounding_rates' => $this->getEmptyReboundingRates(),
            'game_context' => $this->getDefaultGameContext(),
            'summary' => $this->buildSummary($games->count(), $averages, $shooting),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAnalyticsResponse(int|string $playerId, int $season): array
    {
        return [
            'player_id' => $playerId,
            'season' => $season,
            'source' => 'none',
            'recent_form' => $this->getEmptyFormData(),
            'opponent_adjusted_stats' => $this->getEmptyOpponentAdjustedStats(),
            'home_away_performance' => $this->getEmptyHomeAwayPerformance(),
            'clutch_performance' => $this->getEmptyClutchPerformance(),
            'shooting_efficiency' => $this->getEmptyShootingData(),
            'rebounding_rates' => $this->getEmptyReboundingRates(),
            'game_context' => $this->getDefaultGameContext(),
            'summary' => [
                'total_games' => 0,
                'avg_points' => 0,
                'avg_rebounds' => 0,
                'avg_assists' => 0,
                'field_goal_percentage' => 0,
                'three_point_percentage' => 0,
                'free_throw_percentage' => 0,
            ],
        ];
    }

    private function getShootingEfficiencyFromCollection(Collection $games): array
    {
        if ($games->isEmpty()) {
            return $this->getEmptyShootingData();
        }

        $attempted = (float) $games->sum('field_goals_attempted');
        if ($attempted <= 0) {
            return $this->getEmptyShootingData();
        }

        return [
            'field_goal_percentage' => round(($games->sum('field_goals_made') / $attempted) * 100, 1),
            'three_point_percentage' => round(
                ($games->sum('three_point_field_goals_made') / max(1, $games->sum('three_point_field_goals_attempted'))) * 100,
                1
            ),
            'free_throw_percentage' => round(
                ($games->sum('free_throws_made') / max(1, $games->sum('free_throws_attempted'))) * 100,
                1
            ),
            'true_shooting_percentage' => $this->calculateTrueShootingPercentage($games),
            'effective_field_goal_percentage' => $this->calculateEffectiveFieldGoalPercentage($games),
            'shot_distribution' => [
                'two_point_attempts_per_game' => round($games->avg('field_goals_attempted') - $games->avg('three_point_field_goals_attempted'), 1),
                'three_point_attempts_per_game' => round((float) $games->avg('three_point_field_goals_attempted'), 1),
                'free_throw_attempts_per_game' => round((float) $games->avg('free_throws_attempted'), 1),
                'three_point_rate' => round(($games->sum('three_point_field_goals_attempted') / max(1, $attempted)) * 100, 1),
            ],
            'shooting_consistency' => $this->calculateShootingConsistency($games),
        ];
    }

    private function getHomeAwayPerformanceFromCollection(Collection $games): array
    {
        if ($games->isEmpty()) {
            return $this->getEmptyHomeAwayPerformance();
        }

        $first = $games->first();
        if ($first instanceof WnbaPlayerGame) {
            $homeGames = $games->filter(function (WnbaPlayerGame $pg) {
                $row = $pg->game?->gameTeams?->firstWhere('team_id', $pg->team_id);

                return $row && $row->home_away === 'home';
            })->values();

            $awayGames = $games->filter(function (WnbaPlayerGame $pg) {
                $row = $pg->game?->gameTeams?->firstWhere('team_id', $pg->team_id);

                return $row && $row->home_away === 'away';
            })->values();

            return [
                'home' => [
                    'games' => $homeGames->count(),
                    'stats' => $this->calculateAverageStats($homeGames),
                    'win_percentage' => $this->calculateWinPercentage($homeGames),
                ],
                'away' => [
                    'games' => $awayGames->count(),
                    'stats' => $this->calculateAverageStats($awayGames),
                    'win_percentage' => $this->calculateWinPercentage($awayGames),
                ],
                'differential' => $this->calculateStatDifferential(
                    $this->calculateAverageStats($homeGames),
                    $this->calculateAverageStats($awayGames)
                ),
            ];
        }

        return $this->getHomeAwayPerformanceFromLiveRows($games->all());
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function getHomeAwayPerformanceFromLiveRows(array $rows): array
    {
        $games = collect($rows);
        $home = $games->filter(fn (array $g) => ($g['home_away'] ?? '') === 'home')->values();
        $away = $games->filter(fn (array $g) => ($g['home_away'] ?? '') === 'away')->values();

        return [
            'home' => [
                'games' => $home->count(),
                'stats' => $this->calculateAverageStats($home),
                'win_percentage' => 0,
            ],
            'away' => [
                'games' => $away->count(),
                'stats' => $this->calculateAverageStats($away),
                'win_percentage' => 0,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatLiveGameLog(array $rows): array
    {
        return array_map(function (array $game) {
            return [
                'date' => $game['game_date'] ?? null,
                'opponent' => $game['opponent_team_abbreviation'] ?? $game['opponent_team_name'] ?? 'Opponent',
                'minutes' => $game['minutes'] ?? 0,
                'points' => $game['points'] ?? 0,
                'rebounds' => $game['rebounds'] ?? 0,
                'assists' => $game['assists'] ?? 0,
                'steals' => $game['steals'] ?? 0,
                'blocks' => $game['blocks'] ?? 0,
                'turnovers' => $game['turnovers'] ?? 0,
                'fg_made_attempted' => ($game['field_goals_made'] ?? 0).'/'.($game['field_goals_attempted'] ?? 0),
                'three_pt_made_attempted' => ($game['three_point_field_goals_made'] ?? 0).'/'.($game['three_point_field_goals_attempted'] ?? 0),
                'ft_made_attempted' => ($game['free_throws_made'] ?? 0).'/'.($game['free_throws_attempted'] ?? 0),
            ];
        }, $rows);
    }

    private function dateRangeFromDbGames(Collection $games): ?array
    {
        $first = $games->first();
        $last = $games->last();
        $from = $last?->game?->game_date?->format('Y-m-d');
        $to = $first?->game?->game_date?->format('Y-m-d');

        return ($from && $to) ? ['from' => $from, 'to' => $to] : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function dateRangeFromLiveRows(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $dates = array_values(array_filter(array_column($rows, 'game_date')));
        if ($dates === []) {
            return null;
        }

        sort($dates);

        return ['from' => $dates[0], 'to' => $dates[count($dates) - 1]];
    }

    /**
     * @param  array<string, mixed>  $averages
     * @param  array<string, mixed>  $shooting
     * @return array<string, mixed>
     */
    private function buildSummary(int $totalGames, array $averages, array $shooting): array
    {
        return [
            'total_games' => $totalGames,
            'avg_points' => $averages['points'] ?? 0,
            'avg_rebounds' => $averages['rebounds'] ?? 0,
            'avg_assists' => $averages['assists'] ?? 0,
            'field_goal_percentage' => $shooting['field_goal_percentage'] ?? 0,
            'three_point_percentage' => $shooting['three_point_percentage'] ?? 0,
            'free_throw_percentage' => $shooting['free_throw_percentage'] ?? 0,
        ];
    }

    private function getEmptyHomeAwayPerformance(): array
    {
        return [
            'home' => ['games' => 0, 'stats' => [], 'win_percentage' => 0],
            'away' => ['games' => 0, 'stats' => [], 'win_percentage' => 0],
        ];
    }

    private function getEmptyOpponentAdjustedStats(): array
    {
        return [
            'vs_opponent' => ['games' => 0, 'stats' => []],
            'vs_others' => ['games' => 0, 'stats' => []],
            'differential' => [],
            'opponent_defensive_rating' => 100.0,
        ];
    }

    private function getEmptyClutchPerformance(): array
    {
        return [
            'clutch_games' => 0,
            'clutch_stats' => [],
            'non_clutch_stats' => [],
        ];
    }

    private function getDefaultGameContext(): array
    {
        return [
            'game_id' => null,
            'game_date' => null,
            'season_type' => 'Regular Season',
            'player_team_id' => null,
            'opponent_team_id' => null,
            'home_away' => 'home',
            'rest_days' => 2,
            'pace_factor' => 1.0,
            'opponent_defense_rating' => 100.0,
            'projected_minutes' => 30.0,
        ];
    }

    private function statValue(mixed $row, string $key): float
    {
        if (is_array($row)) {
            return (float) ($row[$key] ?? 0);
        }

        return (float) ($row->{$key} ?? 0);
    }
}
