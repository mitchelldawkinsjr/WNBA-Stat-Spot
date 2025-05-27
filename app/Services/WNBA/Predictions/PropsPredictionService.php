<?php

namespace App\Services\WNBA\Predictions;

use App\Models\WnbaPlayerGame;
use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayer;
use App\Models\WnbaTeam;
use App\Services\WNBA\Analytics\PlayerAnalyticsService;
use App\Services\WNBA\Math\BayesianCalculator;
use App\Services\WNBA\Math\MonteCarloSimulator;
use App\Services\WNBA\Math\PoissonCalculator;
use App\Services\WNBA\Data\DataAggregatorService;
use App\Services\WNBA\Analytics\StatisticalEngineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PropsPredictionService
{
    private PredictionEngine $predictionEngine;
    private PlayerAnalyticsService $playerAnalytics;
    private DataAggregatorService $dataAggregator;
    private \App\Services\WNBA\Predictions\StatisticalEngineService $statisticalEngine;

    public function __construct(
        PredictionEngine $predictionEngine,
        PlayerAnalyticsService $playerAnalytics,
        DataAggregatorService $dataAggregator,
        \App\Services\WNBA\Predictions\StatisticalEngineService $statisticalEngine
    ) {
        $this->predictionEngine = $predictionEngine;
        $this->playerAnalytics = $playerAnalytics;
        $this->dataAggregator = $dataAggregator;
        $this->statisticalEngine = $statisticalEngine;
    }

    /**
     * Main prediction method - returns full prediction object
     */
    public function predictProp(int $playerId, int $gameId, string $propType, float $lineValue): array
    {
        $cacheKey = "prop_prediction_{$playerId}_{$gameId}_{$propType}_{$lineValue}";

        return Cache::remember($cacheKey, 1800, function() use ($playerId, $gameId, $propType, $lineValue) {
            // Get player and game context
            $player = WnbaPlayer::find($playerId);
            $game = WnbaGame::find($gameId);

            if (!$player || !$game) {
                throw new \InvalidArgumentException('Invalid player or game ID');
            }

            // Get game context
            $gameContext = $this->getGameContext($gameId, $playerId);

            // Get player profile
            $playerProfile = $this->getPlayerProfile($playerId, $propType);

            // Calculate prediction based on prop type
            $prediction = $this->calculatePropPrediction($playerId, $gameId, $propType, $lineValue, $gameContext, $playerProfile);

            // Calculate confidence
            $confidence = $this->calculateConfidence($prediction['data_quality']);

            // Calculate expected value
            $expectedValue = $this->calculateExpectedValue(
                $prediction['over_probability'],
                $gameContext['odds'] ?? ['over' => -110, 'under' => -110]
            );

            return [
                'prediction_id' => uniqid('wnba_prop_'),
                'player' => [
                    'id' => $player->id,
                    'athlete_id' => $player->athlete_id,
                    'name' => $player->athlete_display_name,
                    'position' => $player->athlete_position_abbreviation,
                    'team_id' => $gameContext['player_team_id']
                ],
                'game_context' => $gameContext,
                'prop_details' => [
                    'type' => $propType,
                    'line_value' => $lineValue,
                    'category' => $this->getPropCategory($propType)
                ],
                'prediction' => [
                    'predicted_value' => $prediction['predicted_value'],
                    'over_probability' => $prediction['over_probability'],
                    'under_probability' => 1 - $prediction['over_probability'],
                    'confidence_score' => $confidence,
                    'expected_value' => $expectedValue,
                    'recommendation' => $this->getRecommendation($prediction['over_probability'], $expectedValue, $confidence)
                ],
                'contributing_factors' => $prediction['factors'],
                'statistical_basis' => $prediction['statistical_basis'],
                'model_info' => [
                    'version' => '1.0',
                    'model_type' => $prediction['model_type'],
                    'data_points_used' => $prediction['data_points'],
                    'last_updated' => now()->toISOString()
                ]
            ];
        });
    }

    /**
     * Generate predictions for all available props for a player/game
     */
    public function predictAllProps(int $playerId, int $gameId): array
    {
        $availableProps = [
            'points' => [15.5, 18.5, 21.5],
            'rebounds' => [6.5, 8.5, 10.5],
            'assists' => [3.5, 5.5, 7.5],
            'three_point_field_goals_made' => [1.5, 2.5, 3.5],
            'steals' => [0.5, 1.5, 2.5],
            'blocks' => [0.5, 1.5, 2.5],
            'turnovers' => [2.5, 3.5, 4.5],
            'minutes' => [25.5, 30.5, 35.5]
        ];

        $predictions = [];
        foreach ($availableProps as $propType => $lines) {
            foreach ($lines as $line) {
                try {
                    $predictions[$propType][] = $this->predictProp($playerId, $gameId, $propType, $line);
                } catch (\Exception $e) {
                    // Log error and continue
                    Log::warning("Failed to predict {$propType} {$line} for player {$playerId}: " . $e->getMessage());
                }
            }
        }

        return $predictions;
    }

    /**
     * Bulk prediction for multiple players in a game
     */
    public function predictGameProps(int $gameId): array
    {
        $game = WnbaGame::with('playerGames.player')->find($gameId);

        if (!$game) {
            throw new \InvalidArgumentException('Invalid game ID');
        }

        $predictions = [];
        foreach ($game->playerGames as $playerGame) {
            if ($playerGame->minutes > 10) { // Only predict for players with significant minutes
                $predictions[$playerGame->player->athlete_display_name] = $this->predictAllProps(
                    $playerGame->player_id,
                    $gameId
                );
            }
        }

        return $predictions;
    }

    /**
     * Predict points for a player in a game
     */
    public function predictPoints(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('points', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict rebounds for a player in a game
     */
    public function predictRebounds(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('rebounds', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict assists for a player in a game
     */
    public function predictAssists(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('assists', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict steals for a player in a game
     */
    public function predictSteals(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('steals', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict blocks for a player in a game
     */
    public function predictBlocks(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('blocks', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict turnovers for a player in a game
     */
    public function predictTurnovers(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('turnovers', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict field goals made for a player in a game
     */
    public function predictFieldGoalsMade(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('field_goals_made', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict field goals attempted for a player in a game
     */
    public function predictFieldGoalsAttempted(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('field_goals_attempted', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict three pointers made for a player in a game
     */
    public function predictThreePointersMade(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('three_pointers_made', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict three pointers attempted for a player in a game
     */
    public function predictThreePointersAttempted(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('three_pointers_attempted', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict free throws made for a player in a game
     */
    public function predictFreeThrowsMade(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('free_throws_made', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict free throws attempted for a player in a game
     */
    public function predictFreeThrowsAttempted(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('free_throws_attempted', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict minutes played for a player in a game
     */
    public function predictMinutesPlayed(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('minutes_played', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict plus minus for a player in a game
     */
    public function predictPlusMinus(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('plus_minus', $playerId, $gameId, $lineValue);
    }

    /**
     * Predict personal fouls for a player in a game
     */
    public function predictPersonalFouls(int $playerId, int $gameId, float $lineValue = null): array
    {
        return $this->predictionEngine->predict('personal_fouls', $playerId, $gameId, $lineValue);
    }

    /**
     * Calculate prediction confidence based on data quality
     */
    public function calculateConfidence(array $dataQuality): float
    {
        $factors = [
            'sample_size' => min(1.0, $dataQuality['games_count'] / 15), // Full confidence at 15+ games
            'recency' => min(1.0, $dataQuality['days_since_last_game'] <= 7 ? 1.0 : 0.8),
            'consistency' => $dataQuality['consistency_score'] / 100,
            'injury_status' => $dataQuality['injury_free'] ? 1.0 : 0.7,
            'minutes_stability' => min(1.0, $dataQuality['minutes_variance'] <= 5 ? 1.0 : 0.8)
        ];

        $weights = [
            'sample_size' => 0.3,
            'recency' => 0.2,
            'consistency' => 0.2,
            'injury_status' => 0.15,
            'minutes_stability' => 0.15
        ];

        $confidence = 0;
        foreach ($factors as $factor => $value) {
            $confidence += $value * $weights[$factor];
        }

        return round($confidence, 3);
    }

    /**
     * Calculate expected value for betting recommendation
     */
    public function calculateExpectedValue(float $probability, array $odds, float $stake = 100): array
    {
        $overOdds = $odds['over'] ?? -110;
        $underOdds = $odds['under'] ?? -110;

        // Convert American odds to decimal
        $overDecimal = $overOdds > 0 ? ($overOdds / 100) + 1 : (100 / abs($overOdds)) + 1;
        $underDecimal = $underOdds > 0 ? ($underOdds / 100) + 1 : (100 / abs($underOdds)) + 1;

        // Calculate expected values
        $overEV = ($probability * ($overDecimal - 1) * $stake) - ((1 - $probability) * $stake);
        $underEV = ((1 - $probability) * ($underDecimal - 1) * $stake) - ($probability * $stake);

        return [
            'over_bet' => round($overEV, 2),
            'under_bet' => round($underEV, 2),
            'best_bet' => $overEV > $underEV ? 'over' : 'under',
            'edge' => round(max($overEV, $underEV), 2)
        ];
    }

    // Private helper methods

    private function getGameContext(int $gameId, int $playerId): array
    {
        $game = WnbaGame::with('gameTeams.team')->find($gameId);
        $playerGame = WnbaPlayerGame::where('game_id', $gameId)
            ->where('player_id', $playerId)
            ->first();

        if (!$game || !$playerGame) {
            throw new \InvalidArgumentException('Game or player game not found');
        }

        $playerTeam = $playerGame->team_id;
        $opponentTeam = $game->gameTeams->where('team_id', '!=', $playerTeam)->first();

        return [
            'game_id' => $gameId,
            'game_date' => $game->game_date,
            'season_type' => $game->season_type,
            'player_team_id' => $playerTeam,
            'opponent_team_id' => $opponentTeam->team_id ?? null,
            'home_away' => $this->getHomeAway($game, $playerTeam),
            'rest_days' => $this->calculateRestDays($playerId, $game->game_date),
            'pace_factor' => $this->calculatePaceFactor($playerTeam, $opponentTeam->team_id ?? null),
            'opponent_defense_rating' => $this->getTeamDefensiveRating($opponentTeam->team_id ?? null),
            'projected_minutes' => $this->projectMinutes($playerId, $gameId)
        ];
    }

    private function getPlayerProfile(int $playerId, string $statType): array
    {
        $seasonGames = WnbaPlayerGame::where('player_id', $playerId)
            ->whereHas('game', function($query) {
                $query->where('season', 2025);
            })
            ->get();

        if ($seasonGames->isEmpty()) {
            return $this->getEmptyPlayerProfile();
        }

        $statValues = $seasonGames->pluck($statType)->toArray();

        return [
            'season_average' => round($seasonGames->avg($statType), 2),
            'games_played' => $seasonGames->count(),
            'variance' => $this->calculateVariance($statValues),
            'std_dev' => $this->calculateStandardDeviation($statValues),
            'consistency_score' => $this->calculateConsistencyScore($statValues),
            'trend' => $this->calculateTrend($statValues),
            'percentiles' => $this->calculatePercentiles($statValues)
        ];
    }

    private function calculatePropPrediction(int $playerId, int $gameId, string $propType, float $lineValue, array $gameContext, array $playerProfile): array
    {
        switch ($propType) {
            case 'points':
                $prediction = $this->predictPoints($playerId, $gameId);
                break;
            case 'rebounds':
                $prediction = $this->predictRebounds($playerId, $gameId);
                break;
            case 'assists':
                $prediction = $this->predictAssists($playerId, $gameId);
                break;
            case 'three_point_field_goals_made':
                $prediction = $this->predictThreePointersMade($playerId, $gameId);
                break;
            case 'steals':
                $prediction = $this->predictSteals($playerId, $gameId);
                break;
            case 'blocks':
                $prediction = $this->predictBlocks($playerId, $gameId);
                break;
            case 'minutes':
                $prediction = $this->predictMinutesPlayed($playerId, $gameId);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported prop type: {$propType}");
        }

        // Calculate over probability based on distribution type
        $overProbability = $this->calculateOverProbability($prediction, $lineValue);

        return [
            'predicted_value' => $prediction['prediction']['adjusted_value'],
            'over_probability' => $overProbability,
            'model_type' => $prediction['prediction']['distribution'],
            'factors' => $prediction['factors'],
            'statistical_basis' => $playerProfile,
            'data_points' => $playerProfile['games_played'],
            'data_quality' => $this->assessDataQuality($playerProfile, $gameContext)
        ];
    }

    private function calculateOverProbability(array $prediction, float $lineValue): float
    {
        $distributionType = $prediction['prediction']['distribution'];
        $predictedValue = $prediction['prediction']['adjusted_value'];

        switch ($distributionType) {
            case 'poisson':
                return $this->statisticalEngine->calculatePoissonOverProbability(
                    $prediction['prediction']['base_value'],
                    $lineValue
                );

            case 'binomial':
                $n = $prediction['prediction']['n'] ?? 10;
                $p = $prediction['prediction']['p'] ?? 0.5;
                return $this->statisticalEngine->calculateBinomialOverProbability(
                    ['n' => $n, 'p' => $p],
                    $lineValue
                );

            case 'normal':
                $mean = $prediction['prediction']['base_value'];
                $stdDev = $prediction['prediction']['std_dev'] ?? 1;
                return $this->statisticalEngine->calculateNormalOverProbability(
                    ['mean' => $mean, 'std_dev' => $stdDev],
                    $lineValue
                );

            default:
                // Fallback to simple comparison
                return $predictedValue > $lineValue ? 0.6 : 0.4;
        }
    }

    private function applyContextualAdjustments(float $baseValue, array $gameContext, array $weights): float
    {
        $adjustments = [
            'pace' => $gameContext['pace_factor'] ?? 1.0,
            'defense' => 1 - (($gameContext['opponent_defense_rating'] - 100) / 100 * 0.1),
            'rest' => $this->getRestAdjustment($gameContext['rest_days']),
            'home_court' => $this->getHomeCourtAdjustment($gameContext['home_away'])
        ];

        $adjustedValue = $baseValue;
        foreach ($adjustments as $type => $factor) {
            $weight = $weights[$type . '_weight'] ?? 0;
            $adjustedValue *= (1 + ($factor - 1) * $weight);
        }

        return $adjustedValue;
    }

    private function getRestAdjustment(int $restDays): float
    {
        if ($restDays === 0) return 0.95; // Back-to-back
        if ($restDays === 1) return 0.98; // One day rest
        if ($restDays >= 3) return 1.02; // Well rested
        return 1.0; // Normal rest
    }

    private function getHomeCourtAdjustment(string $homeAway): float
    {
        return $homeAway === 'home' ? 1.03 : 0.97;
    }

    private function calculateVariance(array $values): float
    {
        if (empty($values)) return 0;

        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        return array_sum($squaredDiffs) / count($values);
    }

    private function calculateStandardDeviation(array $values): float
    {
        return sqrt($this->calculateVariance($values));
    }

    private function calculateConsistencyScore(array $values): float
    {
        if (empty($values)) return 0;

        $mean = array_sum($values) / count($values);
        $coefficientOfVariation = $mean > 0 ? $this->calculateStandardDeviation($values) / $mean : 0;

        return round((1 - min(1, $coefficientOfVariation)) * 100, 1);
    }

    private function calculateTrend(array $values): float
    {
        $n = count($values);
        if ($n < 2) return 0;

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

    private function calculatePercentiles(array $values): array
    {
        if (empty($values)) return [];

        sort($values);
        $count = count($values);

        return [
            '10th' => $values[intval($count * 0.1)],
            '25th' => $values[intval($count * 0.25)],
            '50th' => $values[intval($count * 0.5)],
            '75th' => $values[intval($count * 0.75)],
            '90th' => $values[intval($count * 0.9)]
        ];
    }

    private function getEmptyPrediction(string $statType): array
    {
        return [
            'predicted_value' => 0,
            'distribution_type' => 'unknown',
            'confidence' => 0,
            'error' => "Insufficient data for {$statType} prediction"
        ];
    }

    private function getEmptyPlayerProfile(): array
    {
        return [
            'season_average' => 0,
            'games_played' => 0,
            'variance' => 0,
            'std_dev' => 0,
            'consistency_score' => 0,
            'trend' => 0,
            'percentiles' => []
        ];
    }

    private function getPropCategory(string $propType): string
    {
        $categories = [
            'points' => 'scoring',
            'rebounds' => 'rebounding',
            'assists' => 'playmaking',
            'three_point_field_goals_made' => 'shooting',
            'steals' => 'defense',
            'blocks' => 'defense',
            'turnovers' => 'ball_handling',
            'minutes' => 'playing_time'
        ];

        return $categories[$propType] ?? 'other';
    }

    private function getRecommendation(float $overProbability, array $expectedValue, float $confidence): array
    {
        $bestBet = $expectedValue['best_bet'];
        $edge = $expectedValue['edge'];

        if ($confidence < 0.6) {
            return ['action' => 'pass', 'reason' => 'Low confidence in prediction'];
        }

        if ($edge < 2) {
            return ['action' => 'pass', 'reason' => 'Insufficient edge'];
        }

        $strength = $edge > 5 ? 'strong' : 'moderate';

        return [
            'action' => 'bet',
            'side' => $bestBet,
            'strength' => $strength,
            'edge' => $edge,
            'confidence' => $confidence
        ];
    }

    private function assessDataQuality(array $playerProfile, array $gameContext): array
    {
        return [
            'games_count' => $playerProfile['games_played'],
            'days_since_last_game' => $this->getDaysSinceLastGame($gameContext['game_date']),
            'consistency_score' => $playerProfile['consistency_score'],
            'injury_free' => true, // Would need injury data
            'minutes_variance' => $playerProfile['std_dev']
        ];
    }

    // Placeholder methods that would need full implementation
    private function getHomeAway($game, $teamId): string { return 'home'; }
    private function calculateRestDays($playerId, $gameDate): int { return 2; }
    private function calculatePaceFactor($team1, $team2): float { return 1.0; }
    private function getTeamDefensiveRating($teamId): float { return 100.0; }
    private function projectMinutes($playerId, $gameId): float { return 30.0; }
    private function getOpponentReboundingRate($teamId): float { return 0.0; }
    private function getTeamAssistRate($teamId): float { return 0.5; }
    private function getOpponentThreePointDefense($teamId): float { return 0.0; }
    private function getOpponentTurnoverRate($teamId): float { return 0.0; }
    private function getInjuryAdjustment($playerId): float { return 1.0; }
    private function getGameImportance($gameId): float { return 1.0; }
    private function getFoulTroubleRisk($playerId): float { return 1.0; }
    private function getDaysSinceLastGame($gameDate): int { return 2; }
}
