<?php

namespace App\Services\WNBA\Analytics;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GameAnalyticsService
{
    private const CACHE_TTL = 1800; // 30 minutes

    private const WNBA_GAME_MINUTES = 40;

    /**
     * Get comprehensive game analysis and predictions
     */
    public function getGameAnalysis(int $gameId): array
    {
        $cacheKey = "game_analysis_{$gameId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($gameId) {
            try {
                $game = WnbaGame::with(['homeTeamStats', 'awayTeamStats'])->find($gameId);

                if (! $game) {
                    return $this->getEmptyAnalysis();
                }

                return [
                    'game_info' => $this->getGameInfo($game),
                    'team_matchup' => $this->getTeamMatchupAnalysis($game),
                    'pace_projection' => $this->getPaceProjection($game),
                    'scoring_environment' => $this->getScoringEnvironment($game),
                    'key_factors' => $this->getKeyGameFactors($game),
                    'historical_matchups' => $this->getHistoricalMatchups($game),
                    'situational_factors' => $this->getSituationalFactors($game),
                    'betting_insights' => $this->getBettingInsights($game),
                ];
            } catch (\Exception $e) {
                Log::error('Error analyzing game', [
                    'game_id' => $gameId,
                    'error' => $e->getMessage(),
                ]);

                return $this->getEmptyAnalysis();
            }
        });
    }

    /**
     * Analyze live game flow and momentum
     */
    public function getLiveGameFlow(int $gameId): array
    {
        $cacheKey = "live_game_flow_{$gameId}";

        return Cache::remember($cacheKey, 300, function () use ($gameId) { // 5 min cache for live data
            $plays = WnbaPlay::where('game_id', $gameId)
                ->orderBy('period_number')
                ->orderBy('clock_display_value')
                ->get();

            if ($plays->isEmpty()) {
                return [];
            }

            return [
                'scoring_runs' => $this->identifyScoringRuns($plays),
                'momentum_shifts' => $this->identifyMomentumShifts($plays),
                'quarter_analysis' => $this->analyzeQuarterPerformance($plays),
                'clutch_moments' => $this->identifyClutchMoments($plays),
                'pace_by_quarter' => $this->calculatePaceByQuarter($plays),
                'shooting_streaks' => $this->identifyShootingStreaks($plays),
            ];
        });
    }

    /**
     * Get pre-game matchup predictions
     */
    public function getMatchupPredictions(int $homeTeamId, int $awayTeamId, int $season): array
    {
        $cacheKey = "matchup_predictions_{$homeTeamId}_{$awayTeamId}_{$season}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($homeTeamId, $awayTeamId, $season) {
            return [
                'pace_prediction' => $this->predictGamePace($homeTeamId, $awayTeamId, $season),
                'total_prediction' => $this->predictGameTotal($homeTeamId, $awayTeamId, $season),
                'spread_analysis' => $this->analyzeSpread($homeTeamId, $awayTeamId, $season),
                'style_matchup' => $this->analyzeStyleMatchup($homeTeamId, $awayTeamId, $season),
                'key_player_matchups' => $this->getKeyPlayerMatchups($homeTeamId, $awayTeamId, $season),
                'injury_impact' => $this->assessInjuryImpact($homeTeamId, $awayTeamId),
                'rest_advantage' => $this->calculateRestAdvantage($homeTeamId, $awayTeamId),
            ];
        });
    }

    /**
     * Analyze game environment factors
     */
    public function getGameEnvironmentFactors(int $gameId): array
    {
        $game = WnbaGame::find($gameId);

        if (! $game) {
            return [];
        }

        return [
            'venue_factors' => $this->getVenueFactors($game),
            'schedule_factors' => $this->getScheduleFactors($game),
            'weather_impact' => $this->getWeatherImpact($game),
            'crowd_factor' => $this->getCrowdFactor($game),
            'travel_fatigue' => $this->getTravelFatigue($game),
            'motivation_factors' => $this->getMotivationFactors($game),
        ];
    }

    /**
     * Calculate advanced game metrics
     */
    public function getAdvancedGameMetrics(int $gameId): array
    {
        $cacheKey = "advanced_game_metrics_{$gameId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($gameId) {
            $gameTeams = WnbaGameTeam::where('game_id', $gameId)->get();

            if ($gameTeams->count() !== 2) {
                return [];
            }

            $homeTeam = $gameTeams->where('home_away', 'home')->first();
            $awayTeam = $gameTeams->where('home_away', 'away')->first();

            if (! $homeTeam || ! $awayTeam) {
                return [];
            }

            return [
                'pace' => $this->calculateGamePace($homeTeam, $awayTeam),
                'efficiency' => $this->calculateGameEfficiency($homeTeam, $awayTeam),
                'four_factors' => $this->calculateFourFactors($homeTeam, $awayTeam),
                'game_flow' => $this->calculateGameFlow($gameId),
                'competitive_balance' => $this->calculateCompetitiveBalance($homeTeam, $awayTeam),
                'clutch_performance' => $this->calculateClutchPerformance($gameId),
            ];
        });
    }

    /**
     * Get comprehensive analytics for a game
     */
    public function getAnalytics(int $gameId): array
    {
        try {
            return [
                'game_id' => $gameId,
                'basic_stats' => $this->getBasicStats($gameId),
                'advanced_stats' => $this->getAdvancedStats($gameId),
                'team_comparison' => $this->getTeamComparison($gameId),
                'player_performances' => $this->getPlayerPerformances($gameId),
                'key_moments' => $this->getKeyMoments($gameId),
                'efficiency_metrics' => $this->getEfficiencyMetrics($gameId),
                'pace_analysis' => $this->getPaceAnalysis($gameId),
                'shooting_analysis' => $this->getShootingAnalysis($gameId),
                'rebounding_analysis' => $this->getReboundingAnalysis($gameId),
                'turnover_analysis' => $this->getTurnoverAnalysis($gameId),
                'clutch_analysis' => $this->getClutchAnalysis($gameId),
                'momentum_shifts' => $this->getMomentumShifts($gameId),
                'referee_impact' => $this->getRefereeImpact($gameId),
                'venue_factors' => $this->getVenueFactors($gameId),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get game analytics', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
            ]);

            return [
                'game_id' => $gameId,
                'error' => 'Failed to retrieve analytics',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get basic game statistics
     */
    public function getBasicStats(int $gameId): array
    {
        // Implementation of getBasicStats method
        // This method should return an array of basic game statistics
        return [];
    }

    // Private helper methods

    private function getGameInfo(WnbaGame $game): array
    {
        return [
            'game_id' => $game->id,
            'date' => $game->game_date,
            'season' => $game->season,
            'home_team_id' => $game->home_team_id,
            'away_team_id' => $game->away_team_id,
            'status' => $game->status ?? 'scheduled',
            'venue' => $game->venue ?? 'TBD',
        ];
    }

    private function getTeamMatchupAnalysis(WnbaGame $game): array
    {
        // Get recent performance for both teams
        $homeTeamStats = $this->getRecentTeamStats($game->home_team_id, $game->season);
        $awayTeamStats = $this->getRecentTeamStats($game->away_team_id, $game->season);

        return [
            'home_team' => $homeTeamStats,
            'away_team' => $awayTeamStats,
            'advantages' => $this->identifyTeamAdvantages($homeTeamStats, $awayTeamStats),
            'key_matchups' => $this->identifyKeyMatchups($homeTeamStats, $awayTeamStats),
        ];
    }

    private function getPaceProjection(WnbaGame $game): array
    {
        $homeTeamPace = $this->getTeamAveragePace($game->home_team_id, $game->season);
        $awayTeamPace = $this->getTeamAveragePace($game->away_team_id, $game->season);

        $projectedPace = ($homeTeamPace + $awayTeamPace) / 2;

        return [
            'home_team_pace' => $homeTeamPace,
            'away_team_pace' => $awayTeamPace,
            'projected_pace' => round($projectedPace, 1),
            'pace_rating' => $this->getPaceRating($projectedPace),
            'total_possessions_estimate' => round($projectedPace * 2, 0),
        ];
    }

    private function getScoringEnvironment(WnbaGame $game): array
    {
        $homeOffense = $this->getTeamOffensiveRating($game->home_team_id, $game->season);
        $homeDefense = $this->getTeamDefensiveRating($game->home_team_id, $game->season);
        $awayOffense = $this->getTeamOffensiveRating($game->away_team_id, $game->season);
        $awayDefense = $this->getTeamDefensiveRating($game->away_team_id, $game->season);

        $projectedHomeScore = ($homeOffense + $awayDefense) / 2;
        $projectedAwayScore = ($awayOffense + $homeDefense) / 2;
        $projectedTotal = $projectedHomeScore + $projectedAwayScore;

        return [
            'projected_home_score' => round($projectedHomeScore, 1),
            'projected_away_score' => round($projectedAwayScore, 1),
            'projected_total' => round($projectedTotal, 1),
            'scoring_environment' => $this->getScoringEnvironmentRating($projectedTotal),
            'offensive_advantage' => $this->getOffensiveAdvantage($homeOffense, $awayOffense),
            'defensive_advantage' => $this->getDefensiveAdvantage($homeDefense, $awayDefense),
        ];
    }

    private function getKeyGameFactors(WnbaGame $game): array
    {
        return [
            'rest_differential' => $this->calculateRestDifferential($game),
            'travel_factor' => $this->calculateTravelFactor($game),
            'home_court_advantage' => $this->calculateHomeCourtAdvantage($game),
            'injury_report' => $this->getInjuryReport($game),
            'motivation_level' => $this->assessMotivationLevel($game),
            'weather_conditions' => $this->getWeatherConditions($game),
        ];
    }

    private function getHistoricalMatchups(WnbaGame $game): array
    {
        $historicalGames = WnbaGame::where(function ($query) use ($game) {
            $query->where('home_team_id', $game->home_team_id)
                ->where('away_team_id', $game->away_team_id);
        })
            ->orWhere(function ($query) use ($game) {
                $query->where('home_team_id', $game->away_team_id)
                    ->where('away_team_id', $game->home_team_id);
            })
            ->where('season', $game->season)
            ->where('id', '!=', $game->id)
            ->with(['homeTeamStats', 'awayTeamStats'])
            ->orderBy('game_date', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_games' => $historicalGames->count(),
            'recent_results' => $this->formatRecentResults($historicalGames, $game),
            'average_total' => $this->calculateAverageTotal($historicalGames),
            'average_margin' => $this->calculateAverageMargin($historicalGames, $game),
            'trends' => $this->identifyMatchupTrends($historicalGames),
        ];
    }

    private function getSituationalFactors(WnbaGame $game): array
    {
        return [
            'back_to_back' => $this->isBackToBack($game),
            'days_rest' => $this->getDaysRest($game),
            'time_of_season' => $this->getTimeOfSeason($game),
            'playoff_implications' => $this->getPlayoffImplications($game),
            'rivalry_factor' => $this->getRivalryFactor($game),
            'revenge_game' => $this->isRevengeGame($game),
        ];
    }

    private function getBettingInsights(WnbaGame $game): array
    {
        return [
            'value_plays' => $this->identifyValuePlays($game),
            'sharp_money' => $this->getSharpMoneyIndicators($game),
            'public_betting' => $this->getPublicBettingTrends($game),
            'line_movement' => $this->getLineMovement($game),
            'weather_impact' => $this->getWeatherBettingImpact($game),
        ];
    }

    private function identifyScoringRuns(Collection $plays): array
    {
        // Analyze play-by-play for scoring runs
        $runs = [];
        $currentRun = null;

        foreach ($plays as $play) {
            if ($play->scoring_play) {
                // Logic to identify scoring runs
                // This would require more detailed play-by-play analysis
            }
        }

        return $runs;
    }

    private function identifyMomentumShifts(Collection $plays): array
    {
        // Identify significant momentum changes in the game
        return [];
    }

    private function analyzeQuarterPerformance(Collection $plays): array
    {
        $quarters = [];

        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterPlays = $plays->where('period_number', $quarter);

            $quarters[$quarter] = [
                'total_plays' => $quarterPlays->count(),
                'scoring_plays' => $quarterPlays->where('scoring_play', true)->count(),
                'pace_estimate' => $this->estimateQuarterPace($quarterPlays),
            ];
        }

        return $quarters;
    }

    private function identifyClutchMoments(Collection $plays): array
    {
        // Identify clutch moments (final 5 minutes, close game)
        return [];
    }

    private function calculatePaceByQuarter(Collection $plays): array
    {
        $paceByQuarter = [];

        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterPlays = $plays->where('period_number', $quarter);
            $paceByQuarter[$quarter] = $this->estimateQuarterPace($quarterPlays);
        }

        return $paceByQuarter;
    }

    private function identifyShootingStreaks(Collection $plays): array
    {
        // Identify hot/cold shooting streaks
        return [];
    }

    private function predictGamePace(int $homeTeamId, int $awayTeamId, int $season): array
    {
        $homeTeamPace = $this->getTeamAveragePace($homeTeamId, $season);
        $awayTeamPace = $this->getTeamAveragePace($awayTeamId, $season);

        $predictedPace = ($homeTeamPace + $awayTeamPace) / 2;

        return [
            'predicted_pace' => round($predictedPace, 1),
            'confidence' => $this->calculatePaceConfidence($homeTeamPace, $awayTeamPace),
            'factors' => $this->getPaceFactors($homeTeamId, $awayTeamId),
        ];
    }

    private function predictGameTotal(int $homeTeamId, int $awayTeamId, int $season): array
    {
        $homeOffense = $this->getTeamOffensiveRating($homeTeamId, $season);
        $homeDefense = $this->getTeamDefensiveRating($homeTeamId, $season);
        $awayOffense = $this->getTeamOffensiveRating($awayTeamId, $season);
        $awayDefense = $this->getTeamDefensiveRating($awayTeamId, $season);

        $projectedTotal = (($homeOffense + $awayDefense) / 2) + (($awayOffense + $homeDefense) / 2);

        return [
            'predicted_total' => round($projectedTotal, 1),
            'confidence' => $this->calculateTotalConfidence($homeOffense, $homeDefense, $awayOffense, $awayDefense),
            'over_under_lean' => $this->getOverUnderLean($projectedTotal),
        ];
    }

    private function analyzeSpread(int $homeTeamId, int $awayTeamId, int $season): array
    {
        $homeRating = $this->getTeamNetRating($homeTeamId, $season);
        $awayRating = $this->getTeamNetRating($awayTeamId, $season);
        $homeCourtAdvantage = 3.0; // Typical WNBA home court advantage

        $projectedSpread = ($homeRating - $awayRating) + $homeCourtAdvantage;

        return [
            'projected_spread' => round($projectedSpread, 1),
            'home_team_advantage' => $projectedSpread > 0,
            'confidence' => $this->calculateSpreadConfidence($homeRating, $awayRating),
        ];
    }

    private function analyzeStyleMatchup(int $homeTeamId, int $awayTeamId, int $season): array
    {
        return [
            'pace_matchup' => $this->comparePaceStyles($homeTeamId, $awayTeamId, $season),
            'offensive_styles' => $this->compareOffensiveStyles($homeTeamId, $awayTeamId, $season),
            'defensive_styles' => $this->compareDefensiveStyles($homeTeamId, $awayTeamId, $season),
            'rebounding_battle' => $this->compareRebounding($homeTeamId, $awayTeamId, $season),
        ];
    }

    private function getKeyPlayerMatchups(int $homeTeamId, int $awayTeamId, int $season): array
    {
        // This would require player-level analysis
        return [];
    }

    private function assessInjuryImpact(int $homeTeamId, int $awayTeamId): array
    {
        // This would require injury report data
        return [
            'home_team_injuries' => [],
            'away_team_injuries' => [],
            'impact_rating' => 'Low',
        ];
    }

    private function calculateRestAdvantage(int $homeTeamId, int $awayTeamId): array
    {
        // This would require schedule analysis
        return [
            'home_team_rest' => 1,
            'away_team_rest' => 1,
            'advantage' => 'Even',
        ];
    }

    // Additional helper methods for calculations

    private function getRecentTeamStats(int $teamId, int $season): array
    {
        $recentGames = WnbaGameTeam::where('team_id', $teamId)
            ->whereHas('game', function ($q) use ($season) {
                $q->where('season', $season);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($recentGames->isEmpty()) {
            return [];
        }

        return [
            'games_played' => $recentGames->count(),
            'wins' => $recentGames->where('team_winner', true)->count(),
            'losses' => $recentGames->where('team_winner', false)->count(),
            'ppg' => round($recentGames->avg('team_score'), 1),
            'opp_ppg' => round($recentGames->avg('opponent_team_score'), 1),
            'fg_pct' => $this->calculateFgPercentage($recentGames),
            'three_pct' => $this->calculateThreePointPercentage($recentGames),
        ];
    }

    private function getTeamAveragePace(int $teamId, int $season): float
    {
        // Calculate team's average pace
        $games = WnbaGameTeam::where('team_id', $teamId)
            ->whereHas('game', function ($q) use ($season) {
                $q->where('season', $season);
            })
            ->get();

        if ($games->isEmpty()) {
            return 75.0; // Default WNBA pace
        }

        $totalPace = 0;
        $gameCount = 0;

        foreach ($games as $game) {
            $possessions = $this->estimatePossessions($game);
            if ($possessions > 0) {
                $pace = ($possessions / self::WNBA_GAME_MINUTES) * self::WNBA_GAME_MINUTES;
                $totalPace += $pace;
                $gameCount++;
            }
        }

        return $gameCount > 0 ? $totalPace / $gameCount : 75.0;
    }

    private function getTeamOffensiveRating(int $teamId, int $season): float
    {
        // Calculate offensive rating (points per 100 possessions)
        return 100.0; // Placeholder
    }

    private function getTeamDefensiveRating(int $teamId, int $season): float
    {
        // Calculate defensive rating (opponent points per 100 possessions)
        return 100.0; // Placeholder
    }

    private function getTeamNetRating(int $teamId, int $season): float
    {
        return $this->getTeamOffensiveRating($teamId, $season) - $this->getTeamDefensiveRating($teamId, $season);
    }

    private function estimatePossessions(WnbaGameTeam $game): float
    {
        return $game->field_goals_attempted +
               (0.44 * $game->free_throws_attempted) -
               $game->offensive_rebounds +
               $game->turnovers;
    }

    private function calculateFgPercentage(Collection $games): float
    {
        $totalMade = $games->sum('field_goals_made');
        $totalAttempted = $games->sum('field_goals_attempted');

        return $totalAttempted > 0 ? round(($totalMade / $totalAttempted) * 100, 1) : 0;
    }

    private function calculateThreePointPercentage(Collection $games): float
    {
        $totalMade = $games->sum('three_point_field_goals_made');
        $totalAttempted = $games->sum('three_point_field_goals_attempted');

        return $totalAttempted > 0 ? round(($totalMade / $totalAttempted) * 100, 1) : 0;
    }

    private function getPaceRating(float $pace): string
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

    private function getScoringEnvironmentRating(float $total): string
    {
        if ($total >= 170) {
            return 'High Scoring';
        }
        if ($total >= 160) {
            return 'Above Average';
        }
        if ($total >= 150) {
            return 'Average';
        }
        if ($total >= 140) {
            return 'Below Average';
        }

        return 'Low Scoring';
    }

    private function getOffensiveAdvantage(float $homeOffense, float $awayOffense): string
    {
        $diff = $homeOffense - $awayOffense;
        if ($diff >= 5) {
            return 'Home';
        }
        if ($diff <= -5) {
            return 'Away';
        }

        return 'Even';
    }

    private function getDefensiveAdvantage(float $homeDefense, float $awayDefense): string
    {
        $diff = $awayDefense - $homeDefense; // Lower is better for defense
        if ($diff >= 5) {
            return 'Home';
        }
        if ($diff <= -5) {
            return 'Away';
        }

        return 'Even';
    }

    private function getEmptyAnalysis(): array
    {
        return [
            'game_info' => [],
            'team_matchup' => [],
            'pace_projection' => [],
            'scoring_environment' => [],
            'key_factors' => [],
            'historical_matchups' => [],
            'situational_factors' => [],
            'betting_insights' => [],
        ];
    }

    // Placeholder methods for additional functionality
    private function identifyTeamAdvantages($homeStats, $awayStats): array
    {
        return [];
    }

    private function identifyKeyMatchups($homeStats, $awayStats): array
    {
        return [];
    }

    private function calculateRestDifferential($game): int
    {
        return 0;
    }

    private function calculateTravelFactor($game): string
    {
        return 'None';
    }

    private function calculateHomeCourtAdvantage($game): float
    {
        return 3.0;
    }

    private function getInjuryReport($game): array
    {
        return [];
    }

    private function assessMotivationLevel($game): string
    {
        return 'Normal';
    }

    private function getWeatherConditions($game): array
    {
        return [];
    }

    private function formatRecentResults($games, $game): array
    {
        return [];
    }

    private function calculateAverageTotal($games): float
    {
        return 0;
    }

    private function calculateAverageMargin($games, $game): float
    {
        return 0;
    }

    private function identifyMatchupTrends($games): array
    {
        return [];
    }

    private function isBackToBack($game): bool
    {
        return false;
    }

    private function getDaysRest($game): int
    {
        return 1;
    }

    private function getTimeOfSeason($game): string
    {
        return 'Regular';
    }

    private function getPlayoffImplications($game): string
    {
        return 'None';
    }

    private function getRivalryFactor($game): string
    {
        return 'None';
    }

    private function isRevengeGame($game): bool
    {
        return false;
    }

    private function identifyValuePlays($game): array
    {
        return [];
    }

    private function getSharpMoneyIndicators($game): array
    {
        return [];
    }

    private function getPublicBettingTrends($game): array
    {
        return [];
    }

    private function getLineMovement($game): array
    {
        return [];
    }

    private function getWeatherBettingImpact($game): array
    {
        return [];
    }

    private function estimateQuarterPace($plays): float
    {
        return 0;
    }

    private function calculatePaceConfidence($homePace, $awayPace): string
    {
        return 'Medium';
    }

    private function getPaceFactors($homeTeamId, $awayTeamId): array
    {
        return [];
    }

    private function calculateTotalConfidence($homeO, $homeD, $awayO, $awayD): string
    {
        return 'Medium';
    }

    private function getOverUnderLean($total): string
    {
        return 'Even';
    }

    private function calculateSpreadConfidence($homeRating, $awayRating): string
    {
        return 'Medium';
    }

    private function comparePaceStyles($homeTeamId, $awayTeamId, $season): array
    {
        return [];
    }

    private function compareOffensiveStyles($homeTeamId, $awayTeamId, $season): array
    {
        return [];
    }

    private function compareDefensiveStyles($homeTeamId, $awayTeamId, $season): array
    {
        return [];
    }

    private function compareRebounding($homeTeamId, $awayTeamId, $season): array
    {
        return [];
    }

    private function getVenueFactors($game): array
    {
        return [];
    }

    private function getScheduleFactors($game): array
    {
        return [];
    }

    private function getWeatherImpact($game): array
    {
        return [];
    }

    private function getCrowdFactor($game): array
    {
        return [];
    }

    private function getTravelFatigue($game): array
    {
        return [];
    }

    private function getMotivationFactors($game): array
    {
        return [];
    }

    private function calculateGamePace($homeTeam, $awayTeam): array
    {
        return [];
    }

    private function calculateGameEfficiency($homeTeam, $awayTeam): array
    {
        return [];
    }

    private function calculateFourFactors($homeTeam, $awayTeam): array
    {
        return [];
    }

    private function calculateGameFlow($gameId): array
    {
        return [];
    }

    private function calculateCompetitiveBalance($homeTeam, $awayTeam): array
    {
        return [];
    }

    private function calculateClutchPerformance($gameId): array
    {
        return [];
    }
}
