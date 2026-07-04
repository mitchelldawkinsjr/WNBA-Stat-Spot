<?php

namespace App\Services\WNBA\Analytics;

use App\Models\WnbaGameTeam;
use App\Models\WnbaGame;
use App\Models\WnbaPlayerGame;
use App\Services\WNBA\Data\Support\TeamForeignKeyResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TeamAnalyticsService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const WNBA_GAME_MINUTES = 40;

    /**
     * Get comprehensive team performance metrics
     */
    public function getTeamPerformanceMetrics(int|string $teamId, int $season, ?int $lastNGames = null): array
    {
        $cacheKey = "team_performance_{$teamId}_{$season}_" . ($lastNGames ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($teamId, $season, $lastNGames) {
            try {
                $games = $this->getTeamGames($teamId, $season, $lastNGames);

                if ($games->isEmpty()) {
                    return $this->getEmptyMetrics();
                }

                return [
                    'basic_stats' => $this->calculateBasicTeamStats($games),
                    'advanced_stats' => $this->calculateAdvancedTeamStats($games),
                    'pace_metrics' => $this->calculatePaceMetrics($teamId, $games),
                    'efficiency_ratings' => $this->calculateEfficiencyRatings($games),
                    'home_away_splits' => $this->calculateHomeAwaySplits($games),
                    'recent_form' => $this->calculateRecentForm($games),
                    'opponent_strength' => $this->calculateOpponentStrength($games),
                    'clutch_performance' => $this->calculateClutchPerformance($teamId, $games),
                ];
            } catch (\Exception $e) {
                Log::error('Error calculating team performance metrics', [
                    'team_id' => $teamId,
                    'season' => $season,
                    'error' => $e->getMessage()
                ]);
                return $this->getEmptyMetrics();
            }
        });
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
            'games_analyzed' => $gameCount
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
                'efficiency_grade' => 'N/A'
            ];
        }

        $avgOffensiveRating = $totalOffensiveRating / $gameCount;
        $avgDefensiveRating = $totalDefensiveRating / $gameCount;
        $netRating = $avgOffensiveRating - $avgDefensiveRating;

        return [
            'offensive_rating' => round($avgOffensiveRating, 2),
            'defensive_rating' => round($avgDefensiveRating, 2),
            'net_rating' => round($netRating, 2),
            'efficiency_grade' => $this->getEfficiencyGrade($netRating)
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
                    'point_differential' => $game->team_score - $game->opponent_team_score
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
                'defensive_efficiency' => $this->calculateDefensiveEfficiency($games)
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
                'games_analyzed' => $gameCount
            ];
        });
    }

    /**
     * Get comprehensive analytics for a team
     */
    public function getAnalytics(int $teamId): array
    {
        try {
            return [
                'team_id' => $teamId,
                'basic_stats' => $this->getBasicStats($teamId),
                'advanced_stats' => $this->getAdvancedStats($teamId),
                'offensive_stats' => $this->getOffensiveStats($teamId),
                'defensive_stats' => $this->getDefensiveStats($teamId),
                'efficiency_metrics' => $this->getEfficiencyMetrics($teamId),
                'pace_and_tempo' => $this->getPaceAndTempo($teamId),
                'clutch_performance' => $this->getClutchPerformance($teamId),
                'home_away_splits' => $this->getHomeAwaySplits($teamId),
                'monthly_performance' => $this->getMonthlyPerformance($teamId),
                'strength_of_schedule' => $this->getStrengthOfSchedule($teamId),
                'injury_impact' => $this->getInjuryImpact($teamId),
                'roster_analysis' => $this->getRosterAnalysis($teamId),
                'recent_form' => $this->getRecentForm($teamId),
                'head_to_head' => $this->getHeadToHeadRecords($teamId)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get team analytics', [
                'team_id' => $teamId,
                'error' => $e->getMessage()
            ]);

            return [
                'team_id' => $teamId,
                'error' => 'Failed to retrieve analytics',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get basic team statistics
     */
    public function getBasicStats(int $teamId): array
    {
        // Implementation of getBasicStats method
    }

    // Private helper methods

    private function getTeamGames(int|string $teamId, int $season, ?int $lastNGames = null): Collection
    {
        $teamKeys = TeamForeignKeyResolver::foreignKeysForReference($teamId);

        $query = WnbaGameTeam::whereIn('team_id', $teamKeys)
            ->whereHas('game', function ($q) use ($season) {
                $q->where('season', $season);
            })
            ->with('game')
            ->orderBy('created_at', 'desc');

        if ($lastNGames) {
            $query->limit($lastNGames);
        }

        return $query->get();
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
            ]
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
            ]
        ];
    }

    private function calculateOpponentStrength(Collection $games): array
    {
        // This would require additional opponent data analysis
        return [
            'avg_opponent_record' => 0.500,
            'strength_of_schedule' => 'Average',
            'quality_wins' => 0,
            'bad_losses' => 0
        ];
    }

    private function calculateClutchPerformance(int $teamId, Collection $games): array
    {
        // This would require play-by-play data for clutch situations
        return [
            'close_game_record' => '0-0',
            'clutch_fg_percentage' => 0,
            'clutch_scoring_avg' => 0,
            'games_decided_by_5_or_less' => 0
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
        if ($totalGames === 0) return 0;

        $avgSteals = $games->avg('steals');
        $avgBlocks = $games->avg('blocks');
        $avgDefReb = $games->avg('defensive_rebounds');

        return round($avgSteals + $avgBlocks + $avgDefReb, 2);
    }

    private function calculateDefensiveEfficiency(Collection $games): float
    {
        $totalGames = $games->count();
        if ($totalGames === 0) return 0;

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
        if ($pace >= 85) return 'Very Fast';
        if ($pace >= 80) return 'Fast';
        if ($pace >= 75) return 'Average';
        if ($pace >= 70) return 'Slow';
        return 'Very Slow';
    }

    private function getEfficiencyGrade(float $netRating): string
    {
        if ($netRating >= 10) return 'Elite';
        if ($netRating >= 5) return 'Very Good';
        if ($netRating >= 0) return 'Good';
        if ($netRating >= -5) return 'Below Average';
        return 'Poor';
    }

    private function getScheduleDifficulty(float $sosRating): string
    {
        if ($sosRating >= 0.600) return 'Very Difficult';
        if ($sosRating >= 0.550) return 'Difficult';
        if ($sosRating >= 0.450) return 'Average';
        if ($sosRating >= 0.400) return 'Easy';
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
