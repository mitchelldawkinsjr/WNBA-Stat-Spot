<?php

namespace App\Services\WNBA\Data;

use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaTeam;
use App\Models\WnbaPlay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DataAggregatorService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const BATCH_SIZE = 1000;

    /**
     * Aggregate player performance data for analytics
     */
    public function aggregatePlayerData(int $playerId, ?int $season = null, ?int $lastNGames = null): array
    {
        $cacheKey = "player_data_v2_{$playerId}_{$season}_{$lastNGames}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($playerId, $season, $lastNGames) {
            try {
                $query = WnbaPlayerGame::query()
                    ->with(['game', 'team', 'player'])
                    ->select('wnba_player_games.*')
                    ->where('wnba_player_games.player_id', $playerId)
                    ->join('wnba_games', 'wnba_games.id', '=', 'wnba_player_games.game_id');

                if ($season) {
                    $query->where('wnba_games.season', $season);
                }

                $query->orderBy('wnba_games.game_date', 'desc');

                if ($lastNGames) {
                    $query->limit($lastNGames);
                }

                $games = $query->get();

                if ($games->isEmpty()) {
                    return $this->getEmptyPlayerData();
                }

                return [
                    'player_info' => $this->extractPlayerInfo($games->first()),
                    'season_stats' => $this->calculateSeasonStats($games),
                    'game_log' => $this->formatGameLog($games),
                    'performance_trends' => $this->calculatePerformanceTrends($games),
                    'situational_stats' => $this->calculateSituationalStats($games),
                    'advanced_metrics' => $this->calculateAdvancedPlayerMetrics($games),
                    'consistency_metrics' => $this->calculateConsistencyMetrics($games),
                    'data_quality' => $this->assessDataQuality($games),
                ];

            } catch (\Exception $e) {
                Log::error('Player data aggregation failed', [
                    'player_id' => $playerId,
                    'error' => $e->getMessage(),
                ]);

                return $this->getEmptyPlayerData();
            }
        });
    }

    /**
     * Aggregate team performance data
     */
    public function aggregateTeamData(int $teamId, ?int $season = null, ?int $lastNGames = null): array
    {
        $cacheKey = "team_data_{$teamId}_{$season}_{$lastNGames}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($teamId, $season, $lastNGames) {
            try {
                $query = WnbaGameTeam::with(['game', 'team', 'opponentTeam'])
                    ->where('team_id', $teamId);

                if ($season) {
                    $query->whereHas('game', function($q) use ($season) {
                        $q->where('season', $season);
                    });
                }

                $query->whereHas('game', function($q) {
                    $q->orderBy('game_date', 'desc');
                });

                if ($lastNGames) {
                    $query->limit($lastNGames);
                }

                $games = $query->get();

                if ($games->isEmpty()) {
                    return $this->getEmptyTeamData();
                }

                return [
                    'team_info' => $this->extractTeamInfo($games->first()),
                    'season_stats' => $this->calculateTeamSeasonStats($games),
                    'game_log' => $this->formatTeamGameLog($games),
                    'offensive_metrics' => $this->calculateOffensiveMetrics($games),
                    'defensive_metrics' => $this->calculateDefensiveMetrics($games),
                    'pace_metrics' => $this->calculatePaceMetrics($games),
                    'situational_performance' => $this->calculateTeamSituationalStats($games),
                    'strength_of_schedule' => $this->calculateStrengthOfSchedule($games),
                    'recent_form' => $this->calculateRecentForm($games)
                ];

            } catch (\Exception $e) {
                Log::error('Team data aggregation failed', [
                    'team_id' => $teamId,
                    'error' => $e->getMessage()
                ]);
                return $this->getEmptyTeamData();
            }
        });
    }

    /**
     * Aggregate game-level data for analysis
     */
    public function aggregateGameData(int $gameId): array
    {
        $cacheKey = "game_data_{$gameId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($gameId) {
            try {
                $game = WnbaGame::with(['gameTeams.team', 'playerGames.player', 'plays'])
                    ->find($gameId);

                if (!$game) {
                    return $this->getEmptyGameData();
                }

                return [
                    'game_info' => $this->extractGameInfo($game),
                    'team_stats' => $this->extractTeamGameStats($game),
                    'player_stats' => $this->extractPlayerGameStats($game),
                    'play_by_play' => $this->processPlayByPlay($game),
                    'game_flow' => $this->analyzeGameFlow($game),
                    'key_moments' => $this->identifyKeyMoments($game),
                    'pace_analysis' => $this->analyzeGamePace($game),
                    'efficiency_metrics' => $this->calculateGameEfficiency($game),
                    'competitive_balance' => $this->analyzeCompetitiveBalance($game)
                ];

            } catch (\Exception $e) {
                Log::error('Game data aggregation failed', [
                    'game_id' => $gameId,
                    'error' => $e->getMessage()
                ]);
                return $this->getEmptyGameData();
            }
        });
    }

    /**
     * Aggregate matchup data between two teams
     */
    public function aggregateMatchupData(int $team1Id, int $team2Id, ?int $season = null): array
    {
        $cacheKey = "matchup_data_{$team1Id}_{$team2Id}_{$season}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($team1Id, $team2Id, $season) {
            try {
                // Get head-to-head games
                $query = WnbaGame::whereHas('gameTeams', function($q) use ($team1Id) {
                    $q->where('team_id', $team1Id);
                })->whereHas('gameTeams', function($q) use ($team2Id) {
                    $q->where('team_id', $team2Id);
                });

                if ($season) {
                    $query->where('season', $season);
                }

                $games = $query->with(['gameTeams.team', 'playerGames.player'])
                    ->orderBy('game_date', 'desc')
                    ->get();

                return [
                    'matchup_history' => $this->analyzeMatchupHistory($games, $team1Id, $team2Id),
                    'head_to_head_stats' => $this->calculateHeadToHeadStats($games, $team1Id, $team2Id),
                    'recent_meetings' => $this->formatRecentMeetings($games, $team1Id, $team2Id),
                    'style_comparison' => $this->compareTeamStyles($team1Id, $team2Id, $season),
                    'key_player_matchups' => $this->identifyKeyPlayerMatchups($team1Id, $team2Id, $season),
                    'trends' => $this->analyzeMatchupTrends($games, $team1Id, $team2Id),
                    'prediction_factors' => $this->extractPredictionFactors($team1Id, $team2Id, $season)
                ];

            } catch (\Exception $e) {
                Log::error('Matchup data aggregation failed', [
                    'team1_id' => $team1Id,
                    'team2_id' => $team2Id,
                    'error' => $e->getMessage()
                ]);
                return $this->getEmptyMatchupData();
            }
        });
    }

    /**
     * Aggregate league-wide statistics
     */
    public function aggregateLeagueData(int $season): array
    {
        $cacheKey = "league_data_{$season}";

        return Cache::remember($cacheKey, self::CACHE_TTL * 2, function() use ($season) {
            try {
                return [
                    'league_averages' => $this->calculateLeagueAverages($season),
                    'team_rankings' => $this->calculateTeamRankings($season),
                    'player_leaders' => $this->calculatePlayerLeaders($season),
                    'pace_trends' => $this->analyzePaceTrends($season),
                    'scoring_trends' => $this->analyzeScoringTrends($season),
                    'efficiency_trends' => $this->analyzeEfficiencyTrends($season),
                    'defensive_trends' => $this->analyzeDefensiveTrends($season),
                    'league_context' => $this->getLeagueContext($season)
                ];

            } catch (\Exception $e) {
                Log::error('League data aggregation failed', [
                    'season' => $season,
                    'error' => $e->getMessage()
                ]);
                return $this->getEmptyLeagueData();
            }
        });
    }

    /**
     * Aggregate data for prop betting analysis
     */
    public function aggregatePropData(int $playerId, string $statType, ?int $season = null): array
    {
        $cacheKey = "prop_data_{$playerId}_{$statType}_{$season}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($playerId, $statType, $season) {
            try {
                $query = WnbaPlayerGame::where('player_id', $playerId);

                if ($season) {
                    $query->whereHas('game', function($q) use ($season) {
                        $q->where('season', $season);
                    });
                }

                $games = $query->with(['game.gameTeams', 'team'])
                    ->whereHas('game', function($q) {
                        $q->orderBy('game_date', 'desc');
                    })
                    ->get();

                if ($games->isEmpty()) {
                    return $this->getEmptyPropData();
                }

                $statValues = $games->pluck($statType)->filter()->toArray();

                return [
                    'stat_distribution' => $this->analyzeStatDistribution($statValues),
                    'historical_performance' => $this->analyzeHistoricalPerformance($games, $statType),
                    'situational_analysis' => $this->analyzeSituationalPerformance($games, $statType),
                    'opponent_impact' => $this->analyzeOpponentImpact($games, $statType),
                    'trend_analysis' => $this->analyzeTrends($statValues),
                    'consistency_metrics' => $this->calculateConsistencyMetricsForProp($statValues),
                    'outlier_analysis' => $this->analyzeOutliers($statValues),
                    'prediction_inputs' => $this->preparePredictionInputs($games, $statType),
                    'games_played' => $games->count()
                ];

            } catch (\Exception $e) {
                Log::error('Prop data aggregation failed', [
                    'player_id' => $playerId,
                    'stat_type' => $statType,
                    'error' => $e->getMessage()
                ]);
                return $this->getEmptyPropData();
            }
        });
    }

    // Private helper methods

    private function extractPlayerInfo($playerGame): array
    {
        return [
            'player_id' => $playerGame->player->id,
            'athlete_id' => $playerGame->player->athlete_id,
            'name' => $playerGame->player->athlete_display_name,
            'position' => $playerGame->player->athlete_position_abbreviation,
            'team_id' => $playerGame->team_id,
            'team_name' => $playerGame->team->team_display_name ?? 'Unknown'
        ];
    }

    private function calculateSeasonStats($games): array
    {
        $avg = static function ($collection, string $attr, int $precision = 1): float {
            return round((float) ($collection->avg($attr) ?? 0), $precision);
        };

        return [
            'games_played' => $games->count(),
            'averages' => [
                'points' => $avg($games, 'points'),
                'rebounds' => $avg($games, 'rebounds'),
                'assists' => $avg($games, 'assists'),
                'steals' => $avg($games, 'steals'),
                'blocks' => $avg($games, 'blocks'),
                'turnovers' => $avg($games, 'turnovers'),
                'minutes' => $avg($games, 'minutes'),
                'field_goals_made' => $avg($games, 'field_goals_made'),
                'field_goals_attempted' => $avg($games, 'field_goals_attempted'),
                'three_point_made' => $avg($games, 'three_point_field_goals_made'),
                'three_point_attempted' => $avg($games, 'three_point_field_goals_attempted'),
                'free_throws_made' => $avg($games, 'free_throws_made'),
                'free_throws_attempted' => $avg($games, 'free_throws_attempted'),
            ],
            'totals' => [
                'points' => $games->sum('points'),
                'rebounds' => $games->sum('rebounds'),
                'assists' => $games->sum('assists'),
                'steals' => $games->sum('steals'),
                'blocks' => $games->sum('blocks'),
                'turnovers' => $games->sum('turnovers'),
                'minutes' => $games->sum('minutes')
            ],
            'percentages' => [
                'field_goal_pct' => $this->calculatePercentage($games->sum('field_goals_made'), $games->sum('field_goals_attempted')),
                'three_point_pct' => $this->calculatePercentage($games->sum('three_point_field_goals_made'), $games->sum('three_point_field_goals_attempted')),
                'free_throw_pct' => $this->calculatePercentage($games->sum('free_throws_made'), $games->sum('free_throws_attempted'))
            ]
        ];
    }

    private function formatGameLog($games): array
    {
        return $games->map(function ($game) {
            $date = optional(optional($game->game)->game_date)->format('Y-m-d') ?? '';

            return [
                'game_id' => $game->game_id,
                'date' => $date,
                'opponent' => $this->getOpponentName($game),
                'home_away' => $this->getHomeAway($game),
                'minutes' => $game->minutes,
                'points' => $game->points,
                'rebounds' => $game->rebounds,
                'assists' => $game->assists,
                'steals' => $game->steals,
                'blocks' => $game->blocks,
                'turnovers' => $game->turnovers,
                'fg_made_attempted' => $game->field_goals_made . '/' . $game->field_goals_attempted,
                'three_pt_made_attempted' => $game->three_point_field_goals_made . '/' . $game->three_point_field_goals_attempted,
                'ft_made_attempted' => $game->free_throws_made . '/' . $game->free_throws_attempted,
                'plus_minus' => $game->plus_minus,
                'starter' => $game->starter
            ];
        })->toArray();
    }

    private function calculatePerformanceTrends($games): array
    {
        $gameArray = $games->sortBy('game.game_date')->values();

        return [
            'points_trend' => $this->calculateTrend($gameArray->pluck('points')->toArray()),
            'rebounds_trend' => $this->calculateTrend($gameArray->pluck('rebounds')->toArray()),
            'assists_trend' => $this->calculateTrend($gameArray->pluck('assists')->toArray()),
            'minutes_trend' => $this->calculateTrend($gameArray->pluck('minutes')->toArray()),
            'efficiency_trend' => $this->calculateEfficiencyTrend($gameArray)
        ];
    }

    private function calculateSituationalStats($games): array
    {
        $homeGames = $games->filter(function($game) {
            return $this->getHomeAway($game) === 'home';
        });

        $awayGames = $games->filter(function($game) {
            return $this->getHomeAway($game) === 'away';
        });

        return [
            'home' => $this->calculateAverageStats($homeGames),
            'away' => $this->calculateAverageStats($awayGames),
            'vs_strong_defense' => $this->calculateVsStrongDefense($games),
            'vs_weak_defense' => $this->calculateVsWeakDefense($games),
            'back_to_back' => $this->calculateBackToBackStats($games),
            'rest_days' => $this->calculateRestDayStats($games)
        ];
    }

    private function calculateAdvancedPlayerMetrics($games): array
    {
        return [
            'usage_rate' => $this->calculateUsageRate($games),
            'true_shooting_pct' => $this->calculateTrueShootingPct($games),
            'effective_fg_pct' => $this->calculateEffectiveFGPct($games),
            'assist_turnover_ratio' => $this->calculateAssistTurnoverRatio($games),
            'per_36_stats' => $this->calculatePer36Stats($games),
            'player_efficiency_rating' => $this->calculatePER($games)
        ];
    }

    private function calculateConsistencyMetrics($games): array
    {
        $points = $games->pluck('points')->toArray();
        $rebounds = $games->pluck('rebounds')->toArray();
        $assists = $games->pluck('assists')->toArray();

        return [
            'points_consistency' => $this->calculateConsistency($points),
            'rebounds_consistency' => $this->calculateConsistency($rebounds),
            'assists_consistency' => $this->calculateConsistency($assists),
            'overall_consistency' => $this->calculateOverallConsistency($games)
        ];
    }

    private function assessDataQuality($games): array
    {
        $totalGames = $games->count();
        $gamesWithMinutes = $games->where('minutes', '>', 0)->count();
        $recentGames = $games->where('game.game_date', '>=', Carbon::now()->subDays(30))->count();

        return [
            'sample_size' => $totalGames,
            'data_completeness' => $totalGames > 0 ? $gamesWithMinutes / $totalGames : 0,
            'recency_score' => $totalGames > 0 ? $recentGames / min(10, $totalGames) : 0,
            'quality_score' => $this->calculateQualityScore($totalGames, $gamesWithMinutes, $recentGames)
        ];
    }

    private function calculateTrend($values): float
    {
        if (empty($values) || count($values) < 2) return 0;

        $n = count($values);
        $x = range(1, $n);
        $sumX = array_sum($x);
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $values[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        if ($n * $sumX2 - $sumX * $sumX == 0) return 0;

        return ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    }

    private function calculateConsistency(array $values): float
    {
        if (empty($values)) return 0;

        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);

        $coefficientOfVariation = $mean > 0 ? sqrt($variance) / $mean : 0;
        return round((1 - min(1, $coefficientOfVariation)) * 100, 1);
    }

    private function calculatePercentage(int $made, int $attempted): float
    {
        return $attempted > 0 ? round(($made / $attempted) * 100, 1) : 0;
    }

    private function calculateAverageStats($games): array
    {
        if ($games->isEmpty()) {
            return [];
        }

        return [
            'games' => $games->count(),
            'points' => round($games->avg('points'), 1),
            'rebounds' => round($games->avg('rebounds'), 1),
            'assists' => round($games->avg('assists'), 1),
            'minutes' => round($games->avg('minutes'), 1)
        ];
    }

    private function getOpponentName($game): string
    {
        // Get the game team data for this player's team using external game_id
        $gameTeam = WnbaGameTeam::whereHas('game', function($query) use ($game) {
                $query->where('game_id', $game->game->game_id);
            })
            ->where('team_id', $game->team_id)
            ->with('opponentTeam')
            ->first();

        if ($gameTeam && $gameTeam->opponentTeam) {
            return $gameTeam->opponentTeam->team_abbreviation ?? $gameTeam->opponentTeam->team_display_name ?? 'Unknown';
        }

        return 'Unknown';
    }

    private function getHomeAway($game): string
    {
        // Get the game team data for this player's team to determine home/away status using external game_id
        $gameTeam = WnbaGameTeam::whereHas('game', function($query) use ($game) {
                $query->where('game_id', $game->game->game_id);
            })
            ->where('team_id', $game->team_id)
            ->first();

        if ($gameTeam) {
            return $gameTeam->home_away ?? 'home';
        }

        return 'home';
    }

    // Placeholder methods for complex calculations
    private function calculateEfficiencyTrend($games): float { return 0; }
    private function calculateVsStrongDefense($games): array { return []; }
    private function calculateVsWeakDefense($games): array { return []; }
    private function calculateBackToBackStats($games): array { return []; }
    private function calculateRestDayStats($games): array { return []; }
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
    private function calculateTrueShootingPct($games): float { return 0; }
    private function calculateEffectiveFGPct($games): float { return 0; }
    private function calculateAssistTurnoverRatio($games): float { return 0; }
    private function calculatePer36Stats($games): array { return []; }
    private function calculatePER($games): float { return 0; }
    private function calculateOverallConsistency($games): float { return 0; }
    private function calculateQualityScore($total, $withMinutes, $recent): float { return 0.8; }

    // Team-related methods
    private function extractTeamInfo($gameTeam): array { return []; }
    private function calculateTeamSeasonStats($games): array { return []; }
    private function formatTeamGameLog($games): array { return []; }
    private function calculateOffensiveMetrics($games): array { return []; }
    private function calculateDefensiveMetrics($games): array { return []; }
    private function calculatePaceMetrics($games): array { return []; }
    private function calculateTeamSituationalStats($games): array { return []; }
    private function calculateStrengthOfSchedule($games): array { return []; }
    private function calculateRecentForm($games): array { return []; }

    // Game-related methods
    private function extractGameInfo($game): array { return []; }
    private function extractTeamGameStats($game): array { return []; }
    private function extractPlayerGameStats($game): array { return []; }
    private function processPlayByPlay($game): array { return []; }
    private function analyzeGameFlow($game): array { return []; }
    private function identifyKeyMoments($game): array { return []; }
    private function analyzeGamePace($game): array { return []; }
    private function calculateGameEfficiency($game): array { return []; }
    private function analyzeCompetitiveBalance($game): array { return []; }

    // Matchup-related methods
    private function analyzeMatchupHistory($games, $team1Id, $team2Id): array { return []; }
    private function calculateHeadToHeadStats($games, $team1Id, $team2Id): array { return []; }
    private function formatRecentMeetings($games, $team1Id, $team2Id): array { return []; }
    private function compareTeamStyles($team1Id, $team2Id, $season): array { return []; }
    private function identifyKeyPlayerMatchups($team1Id, $team2Id, $season): array { return []; }
    private function analyzeMatchupTrends($games, $team1Id, $team2Id): array { return []; }
    private function extractPredictionFactors($team1Id, $team2Id, $season): array { return []; }

    // League-related methods
    private function calculateLeagueAverages($season): array { return []; }
    private function calculateTeamRankings($season): array { return []; }
    private function calculatePlayerLeaders($season): array { return []; }
    private function analyzePaceTrends($season): array { return []; }
    private function analyzeScoringTrends($season): array { return []; }
    private function analyzeEfficiencyTrends($season): array { return []; }
    private function analyzeDefensiveTrends($season): array { return []; }
    private function getLeagueContext($season): array { return []; }

    // Prop-related methods
    private function analyzeStatDistribution($values): array
    {
        if (empty($values)) {
            return [
                'mean' => 0,
                'median' => 0,
                'std_dev' => 0,
                'variance' => 0,
                'min' => 0,
                'max' => 0,
                'count' => 0,
                'values' => [],
                'percentiles' => []
            ];
        }

        $count = count($values);
        $mean = array_sum($values) / $count;

        // Calculate variance and standard deviation
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance = $variance / $count;
        $stdDev = sqrt($variance);

        // Sort for percentiles and median
        sort($values);
        $median = $count % 2 === 0
            ? ($values[$count/2 - 1] + $values[$count/2]) / 2
            : $values[floor($count/2)];

        return [
            'mean' => round($mean, 2),
            'median' => round($median, 2),
            'std_dev' => round($stdDev, 2),
            'variance' => round($variance, 2),
            'min' => min($values),
            'max' => max($values),
            'count' => $count,
            'values' => $values,
            'percentiles' => $this->calculatePercentiles($values)
        ];
    }

    private function analyzeHistoricalPerformance($games, $statType): array
    {
        if ($games->isEmpty()) {
            return [
                'season_average' => 0,
                'recent_average' => 0,
                'games_played' => 0,
                'consistency_score' => 0
            ];
        }

        $statValues = $games->pluck($statType)->filter()->toArray();
        $seasonAverage = !empty($statValues) ? array_sum($statValues) / count($statValues) : 0;

        // Get recent 5 games average
        $recentGames = $games->take(5);
        $recentValues = $recentGames->pluck($statType)->filter()->toArray();
        $recentAverage = !empty($recentValues) ? array_sum($recentValues) / count($recentValues) : 0;

        // Calculate consistency (inverse of coefficient of variation)
        $consistency = 0;
        if (!empty($statValues) && $seasonAverage > 0) {
            $variance = 0;
            foreach ($statValues as $value) {
                $variance += pow($value - $seasonAverage, 2);
            }
            $stdDev = sqrt($variance / count($statValues));
            $coefficientOfVariation = $stdDev / $seasonAverage;
            $consistency = max(0, 1 - $coefficientOfVariation); // Higher is more consistent
        }

        return [
            'season_average' => round($seasonAverage, 2),
            'recent_average' => round($recentAverage, 2),
            'games_played' => $games->count(),
            'consistency_score' => round($consistency, 3),
            'trend' => $this->calculateTrend($statValues)
        ];
    }

    private function analyzeSituationalPerformance($games, $statType): array
    {
        if ($games->isEmpty()) {
            return [
                'home' => ['average' => 0, 'games' => 0],
                'away' => ['average' => 0, 'games' => 0],
                'vs_top_teams' => ['average' => 0, 'games' => 0],
                'back_to_back' => ['average' => 0, 'games' => 0]
            ];
        }

        $homeGames = $games->filter(function($game) {
            return $this->getHomeAway($game) === 'home';
        });

        $awayGames = $games->filter(function($game) {
            return $this->getHomeAway($game) === 'away';
        });

        return [
            'home' => [
                'average' => $homeGames->isEmpty() ? 0 : round($homeGames->avg($statType), 2),
                'games' => $homeGames->count()
            ],
            'away' => [
                'average' => $awayGames->isEmpty() ? 0 : round($awayGames->avg($statType), 2),
                'games' => $awayGames->count()
            ],
            'vs_top_teams' => [
                'average' => 0, // Would need opponent strength data
                'games' => 0
            ],
            'back_to_back' => [
                'average' => 0, // Would need game scheduling data
                'games' => 0
            ]
        ];
    }

    private function analyzeOpponentImpact($games, $statType): array
    {
        if ($games->isEmpty()) {
            return [
                'vs_strong_defense' => ['average' => 0, 'games' => 0],
                'vs_weak_defense' => ['average' => 0, 'games' => 0],
                'opponent_adjustment' => 1.0
            ];
        }

        // For now, return basic structure - would need opponent defensive ratings
        return [
            'vs_strong_defense' => [
                'average' => round($games->avg($statType), 2),
                'games' => $games->count()
            ],
            'vs_weak_defense' => [
                'average' => round($games->avg($statType), 2),
                'games' => $games->count()
            ],
            'opponent_adjustment' => 1.0 // Neutral adjustment for now
        ];
    }

    private function analyzeTrends($values): array
    {
        if (empty($values) || count($values) < 3) {
            return [
                'direction' => 'stable',
                'slope' => 0,
                'strength' => 0,
                'recent_form' => 'average'
            ];
        }

        // Calculate linear trend
        $n = count($values);
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

        // Determine trend direction and strength
        $direction = abs($slope) < 0.1 ? 'stable' : ($slope > 0 ? 'improving' : 'declining');
        $strength = min(1, abs($slope) / (array_sum($values) / $n)); // Normalize by average

        // Recent form (last 3 games vs season average)
        $recentValues = array_slice($values, -3);
        $recentAvg = array_sum($recentValues) / count($recentValues);
        $seasonAvg = array_sum($values) / count($values);

        $recentForm = 'average';
        if ($recentAvg > $seasonAvg * 1.1) {
            $recentForm = 'hot';
        } elseif ($recentAvg < $seasonAvg * 0.9) {
            $recentForm = 'cold';
        }

        return [
            'direction' => $direction,
            'slope' => round($slope, 3),
            'strength' => round($strength, 3),
            'recent_form' => $recentForm
        ];
    }

    private function calculateConsistencyMetricsForProp($values): array
    {
        if (empty($values)) {
            return [
                'coefficient_of_variation' => 0,
                'consistency_score' => 0,
                'volatility' => 'unknown'
            ];
        }

        $mean = array_sum($values) / count($values);
        $variance = 0;

        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        $stdDev = sqrt($variance / count($values));
        $coefficientOfVariation = $mean > 0 ? $stdDev / $mean : 0;
        $consistencyScore = max(0, 1 - $coefficientOfVariation);

        $volatility = 'low';
        if ($coefficientOfVariation > 0.5) {
            $volatility = 'high';
        } elseif ($coefficientOfVariation > 0.3) {
            $volatility = 'medium';
        }

        return [
            'coefficient_of_variation' => round($coefficientOfVariation, 3),
            'consistency_score' => round($consistencyScore, 3),
            'volatility' => $volatility
        ];
    }

    private function analyzeOutliers($values): array
    {
        if (empty($values) || count($values) < 4) {
            return [
                'outliers' => [],
                'outlier_count' => 0,
                'outlier_percentage' => 0
            ];
        }

        sort($values);
        $q1 = $this->calculatePercentile($values, 25);
        $q3 = $this->calculatePercentile($values, 75);
        $iqr = $q3 - $q1;

        $lowerBound = $q1 - 1.5 * $iqr;
        $upperBound = $q3 + 1.5 * $iqr;

        $outliers = array_filter($values, function($value) use ($lowerBound, $upperBound) {
            return $value < $lowerBound || $value > $upperBound;
        });

        return [
            'outliers' => array_values($outliers),
            'outlier_count' => count($outliers),
            'outlier_percentage' => round((count($outliers) / count($values)) * 100, 1),
            'lower_bound' => round($lowerBound, 2),
            'upper_bound' => round($upperBound, 2)
        ];
    }

    private function preparePredictionInputs($games, $statType): array
    {
        if ($games->isEmpty()) {
            return [
                'feature_vector' => [],
                'target_values' => [],
                'weights' => []
            ];
        }

        $statValues = $games->pluck($statType)->filter()->toArray();
        $minutes = $games->pluck('minutes')->filter()->toArray();

        // Create feature vector for prediction model
        $features = [];
        foreach ($games as $index => $game) {
            $features[] = [
                'minutes' => $game->minutes ?? 0,
                'home_away' => $this->getHomeAway($game) === 'home' ? 1 : 0,
                'rest_days' => 1, // Would calculate from game dates
                'game_number' => $index + 1,
                'season_progress' => ($index + 1) / $games->count()
            ];
        }

        // Calculate recency weights (more recent games weighted higher)
        $weights = [];
        $totalGames = count($statValues);
        for ($i = 0; $i < $totalGames; $i++) {
            $weights[] = ($i + 1) / $totalGames; // Linear weighting
        }

        return [
            'feature_vector' => $features,
            'target_values' => $statValues,
            'weights' => $weights,
            'sample_size' => count($statValues)
        ];
    }

    // Helper method for percentile calculation
    private function calculatePercentile($values, $percentile): float
    {
        if (empty($values)) return 0;

        sort($values);
        $count = count($values);
        $index = ($percentile / 100) * ($count - 1);

        // Ensure index is within bounds
        if ($index < 0) {
            return $values[0];
        }
        if ($index >= $count) {
            return $values[$count - 1];
        }

        if (floor($index) == $index) {
            return $values[(int)$index];
        } else {
            $lowerIndex = (int)floor($index);
            $upperIndex = (int)ceil($index);

            // Ensure both indices are within bounds
            $lowerIndex = max(0, min($lowerIndex, $count - 1));
            $upperIndex = max(0, min($upperIndex, $count - 1));

            $lower = $values[$lowerIndex];
            $upper = $values[$upperIndex];
            return $lower + ($upper - $lower) * ($index - floor($index));
        }
    }

    // Helper method for calculating percentiles array
    private function calculatePercentiles($values): array
    {
        if (empty($values)) return [];

        return [
            '10th' => $this->calculatePercentile($values, 10),
            '25th' => $this->calculatePercentile($values, 25),
            '50th' => $this->calculatePercentile($values, 50),
            '75th' => $this->calculatePercentile($values, 75),
            '90th' => $this->calculatePercentile($values, 90)
        ];
    }

    // Empty data methods
    private function getEmptyPlayerData(): array
    {
        return [
            'player_info' => [
                'player_id' => 0,
                'athlete_id' => '',
                'name' => '',
                'position' => '',
                'team_id' => 0,
                'team_name' => '',
            ],
            'season_stats' => [
                'games_played' => 0,
                'averages' => [
                    'points' => 0,
                    'rebounds' => 0,
                    'assists' => 0,
                    'steals' => 0,
                    'blocks' => 0,
                    'turnovers' => 0,
                    'minutes' => 0,
                    'field_goals_made' => 0,
                    'field_goals_attempted' => 0,
                    'three_point_made' => 0,
                    'three_point_attempted' => 0,
                    'free_throws_made' => 0,
                    'free_throws_attempted' => 0,
                ],
                'totals' => [
                    'points' => 0,
                    'rebounds' => 0,
                    'assists' => 0,
                    'steals' => 0,
                    'blocks' => 0,
                    'turnovers' => 0,
                    'minutes' => 0,
                ],
                'percentages' => [
                    'field_goal_pct' => 0,
                    'three_point_pct' => 0,
                    'free_throw_pct' => 0,
                ],
            ],
            'game_log' => [],
            'performance_trends' => [
                'points_trend' => 0,
                'rebounds_trend' => 0,
                'assists_trend' => 0,
                'minutes_trend' => 0,
                'efficiency_trend' => 0,
            ],
            'situational_stats' => [
                'home' => [],
                'away' => [],
                'vs_strong_defense' => [],
                'vs_weak_defense' => [],
                'back_to_back' => [],
                'rest_days' => [],
            ],
            'advanced_metrics' => [
                'usage_rate' => 0,
                'true_shooting_pct' => 0,
                'effective_fg_pct' => 0,
                'assist_turnover_ratio' => 0,
                'per_36_stats' => [],
                'player_efficiency_rating' => 0,
            ],
            'consistency_metrics' => [
                'points_consistency' => 0,
                'rebounds_consistency' => 0,
                'assists_consistency' => 0,
                'overall_consistency' => 0,
            ],
            'data_quality' => [
                'sample_size' => 0,
                'data_completeness' => 0,
                'recency_score' => 0,
                'quality_score' => 0,
            ],
        ];
    }

    private function getEmptyTeamData(): array { return []; }
    private function getEmptyGameData(): array { return []; }
    private function getEmptyMatchupData(): array { return []; }
    private function getEmptyLeagueData(): array { return []; }
    private function getEmptyPropData(): array
    {
        return [
            'stat_distribution' => [
                'mean' => 0,
                'median' => 0,
                'std_dev' => 0,
                'variance' => 0,
                'min' => 0,
                'max' => 0,
                'count' => 0,
                'values' => [],
                'percentiles' => []
            ],
            'historical_performance' => [
                'season_average' => 0,
                'recent_average' => 0,
                'games_played' => 0,
                'consistency_score' => 0
            ],
            'situational_analysis' => [
                'home' => ['average' => 0, 'games' => 0],
                'away' => ['average' => 0, 'games' => 0]
            ],
            'opponent_impact' => [
                'opponent_adjustment' => 1.0
            ],
            'trend_analysis' => [
                'direction' => 'stable',
                'slope' => 0,
                'recent_form' => 'average'
            ],
            'consistency_metrics' => [
                'coefficient_of_variation' => 0,
                'consistency_score' => 0,
                'volatility' => 'unknown'
            ],
            'outlier_analysis' => [
                'outliers' => [],
                'outlier_count' => 0
            ],
            'prediction_inputs' => [
                'feature_vector' => [],
                'target_values' => [],
                'sample_size' => 0
            ]
        ];
    }
}
