<?php

namespace App\Services\WNBA\Predictions;

use App\Models\WnbaPlayerGame;
use App\Models\WnbaGame;
use App\Models\WnbaPlayer;
use App\Services\WNBA\Analytics\PlayerAnalyticsService;
use App\Services\WNBA\Data\DataAggregatorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PredictionEngine
{
    private StatisticalEngineService $statisticalEngine;
    private PlayerAnalyticsService $playerAnalytics;
    private DataAggregatorService $dataAggregator;

    public function __construct(
        StatisticalEngineService $statisticalEngine,
        PlayerAnalyticsService $playerAnalytics,
        DataAggregatorService $dataAggregator
    ) {
        $this->statisticalEngine = $statisticalEngine;
        $this->playerAnalytics = $playerAnalytics;
        $this->dataAggregator = $dataAggregator;
    }

    /**
     * Generate prediction for any stat type
     */
    public function predict(string $statType, int $playerId, int $gameId, float $lineValue = null): array
    {
        $cacheKey = "prediction_{$statType}_{$playerId}_{$gameId}";

        return Cache::remember($cacheKey, 1800, function() use ($statType, $playerId, $gameId, $lineValue) {
            try {
                // Get player and game context
                $player = WnbaPlayer::find($playerId);
                $game = WnbaGame::find($gameId);

                if (!$player || !$game) {
                    throw new \InvalidArgumentException('Invalid player or game ID');
                }

                // Get historical data
                $historicalData = $this->dataAggregator->getPropData($playerId, $statType);

                // Get game context
                $gameContext = $this->playerAnalytics->getGameContext($gameId, $playerId);

                // Calculate base prediction
                $basePrediction = $this->calculateBasePrediction($statType, $historicalData, $gameContext);

                // Apply adjustments
                $adjustedPrediction = $this->applyAdjustments($basePrediction, $gameContext);

                // Calculate probabilities if line value provided
                $probabilities = $lineValue ?
                    $this->calculateProbabilities($adjustedPrediction, $lineValue) :
                    null;

                return [
                    'prediction_id' => uniqid('wnba_pred_'),
                    'player' => [
                        'id' => $player->id,
                        'name' => $player->athlete_display_name,
                        'position' => $player->athlete_position_abbreviation,
                        'team_id' => $gameContext['player_team_id']
                    ],
                    'game_context' => $gameContext,
                    'stat_type' => $statType,
                    'prediction' => [
                        'base_value' => $basePrediction['value'],
                        'adjusted_value' => $adjustedPrediction['value'],
                        'confidence' => $adjustedPrediction['confidence'],
                        'distribution' => $adjustedPrediction['distribution']
                    ],
                    'probabilities' => $probabilities,
                    'factors' => [
                        'historical' => $historicalData['stat_distribution'],
                        'situational' => $gameContext,
                        'adjustments' => $adjustedPrediction['adjustments']
                    ],
                    'metadata' => [
                        'version' => '2.0',
                        'generated_at' => now()->toISOString(),
                        'data_points' => $historicalData['games_played']
                    ]
                ];
            } catch (\Exception $e) {
                Log::error('Prediction failed', [
                    'stat_type' => $statType,
                    'player_id' => $playerId,
                    'game_id' => $gameId,
                    'error' => $e->getMessage()
                ]);
                return $this->getEmptyPrediction($statType);
            }
        });
    }

    /**
     * Calculate base prediction using historical data
     */
    private function calculateBasePrediction(string $statType, array $historicalData, array $gameContext): array
    {
        $distribution = $historicalData['stat_distribution'];

        // Determine appropriate distribution type
        $distributionType = $this->determineDistributionType($distribution);

        // Calculate base value using appropriate method
        $baseValue = match($distributionType) {
            'poisson' => $this->calculatePoissonBase($distribution),
            'normal' => $this->calculateNormalBase($distribution),
            'binomial' => $this->calculateBinomialBase($distribution),
            default => $distribution['mean'] ?? 0
        };

        return [
            'value' => $baseValue,
            'distribution_type' => $distributionType,
            'confidence' => $this->calculateBaseConfidence($distribution),
            'distribution' => $distribution
        ];
    }

    /**
     * Apply contextual adjustments to base prediction
     */
    private function applyAdjustments(array $basePrediction, array $gameContext): array
    {
        $adjustments = [
            'pace' => $this->calculatePaceAdjustment($gameContext),
            'rest' => $this->calculateRestAdjustment($gameContext),
            'opponent' => $this->calculateOpponentAdjustment($gameContext),
            'situation' => $this->calculateSituationalAdjustment($gameContext)
        ];

        $adjustedValue = $basePrediction['value'];
        foreach ($adjustments as $adjustment) {
            $adjustedValue *= $adjustment['factor'];
        }

        return [
            'value' => $adjustedValue,
            'confidence' => $this->calculateAdjustedConfidence($basePrediction['confidence'], $adjustments),
            'distribution' => $this->adjustDistribution($basePrediction['distribution'], $adjustments),
            'adjustments' => $adjustments
        ];
    }

    /**
     * Calculate probabilities for over/under scenarios
     */
    private function calculateProbabilities(array $prediction, float $lineValue): array
    {
        $distribution = $prediction['distribution'];
        $value = $prediction['value'];

        return [
            'over' => $this->statisticalEngine->calculateOverProbability($distribution, $lineValue),
            'under' => 1 - $this->statisticalEngine->calculateOverProbability($distribution, $lineValue),
            'line_value' => $lineValue,
            'predicted_value' => $value,
            'edge' => $this->calculateEdge($value, $lineValue)
        ];
    }

    // Helper methods
    private function determineDistributionType(array $distribution): string
    {
        // Analyze distribution shape to determine appropriate type
        $shape = $this->statisticalEngine->analyzeDistributionShape($distribution['values']);

        return match(true) {
            $shape['symmetry'] === 'symmetric' => 'normal',
            $shape['tail_heaviness'] === 'heavy_tailed' => 'poisson',
            $distribution['max'] <= 1 => 'binomial',
            default => 'normal'
        };
    }

    private function calculatePoissonBase(array $distribution): float
    {
        return $distribution['mean'] ?? 0;
    }

    private function calculateNormalBase(array $distribution): float
    {
        return $distribution['mean'] ?? 0;
    }

    private function calculateBinomialBase(array $distribution): float
    {
        return $distribution['mean'] ?? 0;
    }

    private function calculateBaseConfidence(array $distribution): float
    {
        return $this->statisticalEngine->calculateConfidence([
            'sample_size' => $distribution['count'] ?? 0,
            'variance' => $distribution['variance'] ?? 0,
            'consistency' => $distribution['consistency_score'] ?? 0
        ]);
    }

    private function calculatePaceAdjustment(array $gameContext): array
    {
        $paceFactor = $gameContext['pace_factor'] ?? 1.0;
        return [
            'factor' => $paceFactor,
            'description' => 'Game pace adjustment'
        ];
    }

    private function calculateRestAdjustment(array $gameContext): array
    {
        $restDays = $gameContext['rest_days'] ?? 0;
        $factor = match(true) {
            $restDays <= 1 => 0.9,  // Back-to-back
            $restDays >= 4 => 1.1,  // Well rested
            default => 1.0
        };
        return [
            'factor' => $factor,
            'description' => 'Rest days adjustment'
        ];
    }

    private function calculateOpponentAdjustment(array $gameContext): array
    {
        $defenseRating = $gameContext['opponent_defense_rating'] ?? 100;
        $factor = 1 + (($defenseRating - 100) / 1000);
        return [
            'factor' => $factor,
            'description' => 'Opponent strength adjustment'
        ];
    }

    private function calculateSituationalAdjustment(array $gameContext): array
    {
        $factor = 1.0;
        $description = 'Situational factors';

        if ($gameContext['home_away'] === 'home') {
            $factor *= 1.05;
            $description .= ' (Home court advantage)';
        }

        return [
            'factor' => $factor,
            'description' => $description
        ];
    }

    private function calculateAdjustedConfidence(float $baseConfidence, array $adjustments): float
    {
        $adjustmentFactors = array_map(fn($adj) => $adj['factor'], $adjustments);
        $adjustmentImpact = array_sum($adjustmentFactors) / count($adjustmentFactors);

        return $baseConfidence * (1 - abs(1 - $adjustmentImpact) * 0.2);
    }

    private function adjustDistribution(array $distribution, array $adjustments): array
    {
        $adjustedDistribution = $distribution;
        $adjustmentFactor = array_product(array_map(fn($adj) => $adj['factor'], $adjustments));

        if (isset($adjustedDistribution['mean'])) {
            $adjustedDistribution['mean'] *= $adjustmentFactor;
        }
        if (isset($adjustedDistribution['median'])) {
            $adjustedDistribution['median'] *= $adjustmentFactor;
        }

        return $adjustedDistribution;
    }

    private function calculateEdge(float $predicted, float $line): float
    {
        return abs($predicted - $line) / $line;
    }

    private function getEmptyPrediction(string $statType): array
    {
        return [
            'prediction_id' => uniqid('wnba_pred_'),
            'stat_type' => $statType,
            'prediction' => [
                'base_value' => 0,
                'adjusted_value' => 0,
                'confidence' => 0,
                'distribution' => []
            ],
            'probabilities' => null,
            'factors' => [],
            'metadata' => [
                'version' => '2.0',
                'generated_at' => now()->toISOString(),
                'data_points' => 0,
                'error' => 'Insufficient data for prediction'
            ]
        ];
    }
}
