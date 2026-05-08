<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BettingAnalyticsController extends Controller
{
    use ApiResponseTrait, CacheHelper;

    /**
     * Get comprehensive betting analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $this->validateRequest($request->all(), [
                'timeframe' => 'nullable|string|in:7d,30d,90d,season',
                'bet_type' => 'nullable|string|in:all,player_props,team_totals,spreads,moneylines'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }

        try {
            $timeframe = $request->input('timeframe', '30d');
            $betType = $request->input('bet_type', 'all');

            $cacheKey = "analytics_{$timeframe}_{$betType}";

            $analytics = $this->getCached($cacheKey, function () use ($timeframe, $betType) {
                return $this->generateBettingAnalytics($timeframe, $betType);
            }, 900);

            return $this->successResponse($analytics, 'Betting analytics retrieved successfully');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving betting analytics');
        }
    }

    /**
     * Generate betting analytics data
     */
    private function generateBettingAnalytics(string $timeframe, string $betType): array
    {
        // Adjust data based on timeframe
        $timeframeMultiplier = match($timeframe) {
            '7d' => 0.2,
            '30d' => 1.0,
            '90d' => 3.0,
            'season' => 12.0,
            default => 1.0
        };

        $baseBets = (int) floor(247 * $timeframeMultiplier / 12);
        $baseWinning = (int) floor($baseBets * 0.632);
        $baseWagered = $baseBets * 100;
        $baseProfit = (int) floor($baseWagered * 0.087);

        return [
            'overall_performance' => [
                'total_bets' => $baseBets,
                'winning_bets' => $baseWinning,
                'win_rate' => $baseWinning / max($baseBets, 1),
                'total_wagered' => $baseWagered,
                'total_profit' => $baseProfit,
                'roi' => $baseProfit / max($baseWagered, 1),
                'average_odds' => -108,
                'sharpe_ratio' => 1.42
            ],
            'monthly_performance' => [
                ['month' => 'Jan 2025', 'bets' => (int) floor(89 * $timeframeMultiplier / 12), 'profit' => (int) floor(890 * $timeframeMultiplier / 12), 'roi' => 0.095],
                ['month' => 'Dec 2024', 'bets' => (int) floor(76 * $timeframeMultiplier / 12), 'profit' => (int) floor(456 * $timeframeMultiplier / 12), 'roi' => 0.062],
                ['month' => 'Nov 2024', 'bets' => (int) floor(82 * $timeframeMultiplier / 12), 'profit' => (int) floor(799 * $timeframeMultiplier / 12), 'roi' => 0.098]
            ],
            'bet_type_performance' => [
                'player_props' => [
                    'bets' => $betType === 'player_props' ? $baseBets : (int) floor($baseBets * 0.63),
                    'win_rate' => 0.641,
                    'roi' => 0.092,
                    'avg_ev' => 0.034
                ],
                'team_totals' => [
                    'bets' => $betType === 'team_totals' ? $baseBets : (int) floor($baseBets * 0.18),
                    'win_rate' => 0.622,
                    'roi' => 0.078,
                    'avg_ev' => 0.028
                ],
                'spreads' => [
                    'bets' => $betType === 'spreads' ? $baseBets : (int) floor($baseBets * 0.13),
                    'win_rate' => 0.594,
                    'roi' => 0.045,
                    'avg_ev' => 0.019
                ],
                'moneylines' => [
                    'bets' => $betType === 'moneylines' ? $baseBets : (int) floor($baseBets * 0.06),
                    'win_rate' => 0.571,
                    'roi' => 0.023,
                    'avg_ev' => 0.012
                ]
            ],
            'top_opportunities' => [
                [
                    'player' => "A'ja Wilson",
                    'stat' => 'Points',
                    'line' => 22.5,
                    'our_prob' => 0.68,
                    'book_odds' => -115,
                    'ev' => 0.087,
                    'kelly' => 0.034,
                    'confidence' => 'High'
                ],
                [
                    'player' => "Breanna Stewart",
                    'stat' => 'Rebounds',
                    'line' => 8.5,
                    'our_prob' => 0.72,
                    'book_odds' => -110,
                    'ev' => 0.076,
                    'kelly' => 0.029,
                    'confidence' => 'High'
                ],
                [
                    'player' => "Sabrina Ionescu",
                    'stat' => 'Assists',
                    'line' => 6.5,
                    'our_prob' => 0.65,
                    'book_odds' => -105,
                    'ev' => 0.054,
                    'kelly' => 0.021,
                    'confidence' => 'Medium'
                ]
            ],
            'bankroll_simulation' => [
                'starting_bankroll' => 10000,
                'current_bankroll' => 10000 + $baseProfit,
                'max_drawdown' => -0.087,
                'max_bankroll' => 10000 + $baseProfit + (int) floor($baseProfit * 0.2),
                'volatility' => 0.156,
                'risk_of_ruin' => 0.003
            ],
            'generated_at' => now()->toISOString(),
            'timeframe' => $timeframe,
            'bet_type_filter' => $betType
        ];
    }
}
