<?php

namespace App\Services\WNBA\Predictions;

use Illuminate\Support\Facades\Log;

class BettingRecommendationService
{
    private PredictionEngine $predictionEngine;

    public function __construct(PredictionEngine $predictionEngine)
    {
        $this->predictionEngine = $predictionEngine;
    }

    /**
     * Get betting recommendation for a specific prop
     */
    public function getRecommendation(
        int $playerId,
        string $statType,
        float $line,
        float $oddsOver,
        float $oddsUnder,
        ?int $gameId = null,
        ?int $season = null
    ): array {
        try {
            // Use a mock game ID if none provided
            $gameId = $gameId ?? 1;

            // Get prediction for the stat
            $prediction = $this->predictionEngine->predict($statType, $playerId, $gameId, $line);

            if (empty($prediction['probabilities'])) {
                return $this->getEmptyRecommendation($playerId, $statType, $line, $oddsOver, $oddsUnder);
            }

            $predictedValue = $prediction['prediction']['adjusted_value'];
            $confidence = $prediction['prediction']['confidence'];
            $overProbability = $prediction['probabilities']['over'];
            $underProbability = $prediction['probabilities']['under'];

            // Convert American odds to decimal
            $decimalOddsOver = $this->convertAmericanToDecimal($oddsOver);
            $decimalOddsUnder = $this->convertAmericanToDecimal($oddsUnder);

            // Calculate expected values
            $evOver = $this->calculateExpectedValue($overProbability, $decimalOddsOver);
            $evUnder = $this->calculateExpectedValue($underProbability, $decimalOddsUnder);

            // Determine recommendation
            $recommendation = $this->determineRecommendation($confidence, $evOver, $evUnder, $predictedValue, $line);

            return [
                'player_id' => $playerId,
                'stat_type' => $statType,
                'line' => $line,
                'predicted_value' => round($predictedValue, 2),
                'confidence' => round($confidence, 3),
                'over_probability' => round($overProbability, 3),
                'under_probability' => round($underProbability, 3),
                'expected_value_over' => round($evOver, 3),
                'expected_value_under' => round($evUnder, 3),
                'recommendation' => $recommendation['action'],
                'reasoning' => $recommendation['reasoning'],
                'odds' => [
                    'over' => $oddsOver,
                    'under' => $oddsUnder
                ],
                'generated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Error generating betting recommendation', [
                'error' => $e->getMessage(),
                'player_id' => $playerId,
                'stat_type' => $statType,
                'line' => $line
            ]);
            return $this->getEmptyRecommendation($playerId, $statType, $line, $oddsOver, $oddsUnder, $e->getMessage());
        }
    }

    /**
     * Convert American odds to decimal
     */
    private function convertAmericanToDecimal(float $americanOdds): float
    {
        return $americanOdds > 0 ? ($americanOdds / 100) + 1 : (100 / abs($americanOdds)) + 1;
    }

    /**
     * Calculate expected value
     */
    private function calculateExpectedValue(float $probability, float $decimalOdds): float
    {
        return ($probability * ($decimalOdds - 1)) - ((1 - $probability) * 1);
    }

    /**
     * Determine betting recommendation
     */
    private function determineRecommendation(
        float $confidence,
        float $evOver,
        float $evUnder,
        float $predictedValue,
        float $line
    ): array {
        $minConfidence = 0.7;
        $minEdge = 0.05;

        if ($confidence < $minConfidence) {
            return [
                'action' => 'avoid',
                'reasoning' => 'Insufficient confidence in prediction'
            ];
        }

        if ($evOver > $minEdge) {
            return [
                'action' => 'over',
                'reasoning' => sprintf(
                    'Strong edge on over: %.1f predicted vs %.1f line (EV: %.1f%%)',
                    $predictedValue,
                    $line,
                    $evOver * 100
                )
            ];
        }

        if ($evUnder > $minEdge) {
            return [
                'action' => 'under',
                'reasoning' => sprintf(
                    'Strong edge on under: %.1f predicted vs %.1f line (EV: %.1f%%)',
                    $predictedValue,
                    $line,
                    $evUnder * 100
                )
            ];
        }

        return [
            'action' => 'avoid',
            'reasoning' => 'No significant edge detected'
        ];
    }

    /**
     * Get empty recommendation
     */
    private function getEmptyRecommendation(
        int $playerId,
        string $statType,
        float $line,
        float $oddsOver,
        float $oddsUnder,
        ?string $error = null
    ): array {
        return [
            'player_id' => $playerId,
            'stat_type' => $statType,
            'line' => $line,
            'predicted_value' => $line,
            'confidence' => 0.5,
            'over_probability' => 0.5,
            'under_probability' => 0.5,
            'expected_value_over' => 0,
            'expected_value_under' => 0,
            'recommendation' => 'avoid',
            'reasoning' => $error ?? 'Insufficient data for reliable prediction',
            'odds' => [
                'over' => $oddsOver,
                'under' => $oddsUnder
            ],
            'generated_at' => now()->toISOString()
        ];
    }
}
