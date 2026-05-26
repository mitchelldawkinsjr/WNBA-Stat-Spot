<?php

namespace App\Utils;

use Illuminate\Support\Collection;

class StatisticsCalculator
{
    /**
     * WNBA player-game rows store 3PA/3PM as three_point_field_goals_* — keep outgoing keys as three_pointers_* for callers.
     */
    private static function avgRounded(Collection $games, string $column, int $precision = 1): float
    {
        $value = $games->avg($column);

        return round((float) ($value ?? 0), $precision);
    }

    /**
     * Calculate basic statistical averages for player stats
     */
    public static function calculatePlayerAverages(Collection $games): array
    {
        if ($games->isEmpty()) {
            return self::getEmptyPlayerStats();
        }

        return [
            'points' => self::avgRounded($games, 'points'),
            'rebounds' => self::avgRounded($games, 'rebounds'),
            'assists' => self::avgRounded($games, 'assists'),
            'steals' => self::avgRounded($games, 'steals'),
            'blocks' => self::avgRounded($games, 'blocks'),
            'turnovers' => self::avgRounded($games, 'turnovers'),
            'minutes' => self::avgRounded($games, 'minutes'),
            'field_goals_made' => self::avgRounded($games, 'field_goals_made'),
            'field_goals_attempted' => self::avgRounded($games, 'field_goals_attempted'),
            'three_pointers_made' => self::avgRounded($games, 'three_point_field_goals_made'),
            'three_pointers_attempted' => self::avgRounded($games, 'three_point_field_goals_attempted'),
            'free_throws_made' => self::avgRounded($games, 'free_throws_made'),
            'free_throws_attempted' => self::avgRounded($games, 'free_throws_attempted'),
        ];
    }

    /**
     * Calculate shooting percentages
     */
    public static function calculateShootingPercentages(Collection $games): array
    {
        if ($games->isEmpty()) {
            return [
                'field_goal_percentage' => 0,
                'three_point_percentage' => 0,
                'free_throw_percentage' => 0,
            ];
        }

        $totalFgMade = $games->sum('field_goals_made');
        $totalFgAttempted = $games->sum('field_goals_attempted');
        $total3pMade = $games->sum('three_point_field_goals_made');
        $total3pAttempted = $games->sum('three_point_field_goals_attempted');
        $totalFtMade = $games->sum('free_throws_made');
        $totalFtAttempted = $games->sum('free_throws_attempted');

        return [
            'field_goal_percentage' => $totalFgAttempted > 0 
                ? round(($totalFgMade / $totalFgAttempted) * 100, 1) 
                : 0,
            'three_point_percentage' => $total3pAttempted > 0 
                ? round(($total3pMade / $total3pAttempted) * 100, 1) 
                : 0,
            'free_throw_percentage' => $totalFtAttempted > 0 
                ? round(($totalFtMade / $totalFtAttempted) * 100, 1) 
                : 0,
        ];
    }

    /**
     * Calculate team statistics
     */
    public static function calculateTeamStats(Collection $games): array
    {
        if ($games->isEmpty()) {
            return self::getEmptyTeamStats();
        }

        $wins = $games->where('result', 'W')->count();
        $losses = $games->where('result', 'L')->count();
        $totalGames = $games->count();

        return [
            'games_played' => $totalGames,
            'wins' => $wins,
            'losses' => $losses,
            'win_percentage' => $totalGames > 0 ? round(($wins / $totalGames) * 100, 1) : 0,
            'points_for' => round($games->avg('points_for'), 1),
            'points_against' => round($games->avg('points_against'), 1),
            'point_differential' => round($games->avg('point_differential'), 1),
        ];
    }

    /**
     * Calculate home vs away splits
     */
    public static function calculateHomeAwaySplits(Collection $games): array
    {
        $homeGames = $games->where('home_away', 'home');
        $awayGames = $games->where('home_away', 'away');

        return [
            'home' => [
                'games' => $homeGames->count(),
                'wins' => $homeGames->where('result', 'W')->count(),
                'losses' => $homeGames->where('result', 'L')->count(),
                'points' => round($homeGames->avg('points'), 1),
            ],
            'away' => [
                'games' => $awayGames->count(),
                'wins' => $awayGames->where('result', 'W')->count(),
                'losses' => $awayGames->where('result', 'L')->count(),
                'points' => round($awayGames->avg('points'), 1),
            ],
        ];
    }

    /**
     * Calculate streak information
     */
    public static function calculateStreak(Collection $games): array
    {
        if ($games->isEmpty()) {
            return [
                'current_streak' => 0,
                'streak_type' => null,
                'longest_win_streak' => 0,
                'longest_loss_streak' => 0,
            ];
        }

        $sortedGames = $games->sortByDesc('game_date');
        $latestResult = $sortedGames->first()->result ?? null;
        
        $currentStreak = 0;
        $currentStreakType = $latestResult;
        
        foreach ($sortedGames as $game) {
            if ($game->result === $currentStreakType) {
                $currentStreak++;
            } else {
                break;
            }
        }

        return [
            'current_streak' => $currentStreak,
            'streak_type' => $currentStreakType,
            'longest_win_streak' => self::calculateLongestStreak($games, 'W'),
            'longest_loss_streak' => self::calculateLongestStreak($games, 'L'),
        ];
    }

    /**
     * Calculate efficiency metrics
     */
    public static function calculateEfficiencyMetrics(Collection $games): array
    {
        if ($games->isEmpty()) {
            return [
                'true_shooting_percentage' => 0,
                'effective_field_goal_percentage' => 0,
                'player_efficiency_rating' => 0,
            ];
        }

        // True Shooting Percentage = Points / (2 * (FGA + 0.44 * FTA))
        $points = $games->sum('points');
        $fga = $games->sum('field_goals_attempted');
        $fta = $games->sum('free_throws_attempted');
        
        $tsPossessions = 2 * ($fga + (0.44 * $fta));
        $trueShootingPercentage = $tsPossessions > 0 ? round(($points / $tsPossessions) * 100, 1) : 0;

        // Effective Field Goal Percentage = (FGM + 0.5 * 3PM) / FGA
        $fgm = $games->sum('field_goals_made');
        $threePm = $games->sum('three_point_field_goals_made');
        
        $effectiveFgPercentage = $fga > 0 ? round((($fgm + (0.5 * $threePm)) / $fga) * 100, 1) : 0;

        return [
            'true_shooting_percentage' => $trueShootingPercentage,
            'effective_field_goal_percentage' => $effectiveFgPercentage,
            'usage_rate' => self::calculateUsageRate($games),
        ];
    }

    /**
     * Get empty player stats structure
     */
    private static function getEmptyPlayerStats(): array
    {
        return [
            'points' => 0,
            'rebounds' => 0,
            'assists' => 0,
            'steals' => 0,
            'blocks' => 0,
            'turnovers' => 0,
            'minutes' => 0,
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
            'three_pointers_made' => 0,
            'three_pointers_attempted' => 0,
            'free_throws_made' => 0,
            'free_throws_attempted' => 0,
        ];
    }

    /**
     * Get empty team stats structure
     */
    private static function getEmptyTeamStats(): array
    {
        return [
            'games_played' => 0,
            'wins' => 0,
            'losses' => 0,
            'win_percentage' => 0,
            'points_for' => 0,
            'points_against' => 0,
            'point_differential' => 0,
        ];
    }

    /**
     * Calculate longest streak of a specific type
     */
    private static function calculateLongestStreak(Collection $games, string $resultType): int
    {
        $sortedGames = $games->sortBy('game_date');
        $longestStreak = 0;
        $currentStreak = 0;

        foreach ($sortedGames as $game) {
            if ($game->result === $resultType) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return $longestStreak;
    }

    /**
     * Calculate usage rate (simplified version)
     */
    private static function calculateUsageRate(Collection $games): float
    {
        if ($games->isEmpty()) {
            return 0;
        }

        // Simplified usage rate calculation
        $totalMinutes = $games->sum('minutes');
        $totalGames = $games->count();
        
        return $totalGames > 0 ? round($totalMinutes / $totalGames, 1) : 0;
    }
}