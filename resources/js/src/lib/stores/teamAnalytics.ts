import { writable, derived } from 'svelte/store';
import type { Writable, Readable } from 'svelte/store';
import { api } from '$lib/api/client';

export interface GameResult {
    date: string;
    opponent: string;
    points_scored: number;
    points_allowed: number;
    result: 'W' | 'L';
    home_away: 'home' | 'away';
}

export interface TeamAnalyticsState {
    loading: boolean;
    error: string | null;
    gameResults: GameResult[];
    seasonStats: {
        wins: number;
        losses: number;
        win_percentage: number;
        points_per_game: number;
        points_allowed_per_game: number;
        streak: number;
        streak_type: 'W' | 'L';
    } | null;
    advancedMetrics: {
        offensive_rating: number;
        defensive_rating: number;
        net_rating: number;
        pace: number;
        true_shooting_percentage: number;
    } | null;
    homeAwaySplits: {
        home: {
            wins: number;
            losses: number;
            points_per_game: number;
            points_allowed_per_game: number;
        };
        away: {
            wins: number;
            losses: number;
            points_per_game: number;
            points_allowed_per_game: number;
        };
    } | null;
}

function createTeamAnalyticsStore() {
    const { subscribe, set, update } = writable<TeamAnalyticsState>({
        loading: false,
        error: null,
        gameResults: [],
        seasonStats: null,
        advancedMetrics: null,
        homeAwaySplits: null
    });

    return {
        subscribe,
        fetchAnalytics: async (teamId: string) => {
            update(state => ({ ...state, loading: true, error: null }));

            try {
                const response = await api.wnba.analytics.getTeam(teamId);
                const analytics = response.data;

                update(state => ({
                    ...state,
                    loading: false,
                    gameResults: analytics.game_results || [],
                    seasonStats: analytics.season_stats || null,
                    advancedMetrics: analytics.advanced_metrics || null,
                    homeAwaySplits: analytics.home_away_splits || null
                }));
            } catch (error) {
                update(state => ({
                    ...state,
                    loading: false,
                    error: error instanceof Error ? error.message : 'Failed to fetch team analytics'
                }));
            }
        },
        reset: () => {
            set({
                loading: false,
                error: null,
                gameResults: [],
                seasonStats: null,
                advancedMetrics: null,
                homeAwaySplits: null
            });
        }
    };
}

export const teamAnalytics = createTeamAnalyticsStore();

// Derived stores for specific analytics
export const gameResultsChartData = derived(
    teamAnalytics,
    $teamAnalytics => {
        if (!$teamAnalytics.gameResults.length) return [];

        return $teamAnalytics.gameResults.map(game => ({
            date: game.date,
            points_scored: game.points_scored,
            points_allowed: game.points_allowed,
            result: game.result,
            home_away: game.home_away
        }));
    }
);

export const seasonStatsData = derived(
    teamAnalytics,
    $teamAnalytics => {
        if (!$teamAnalytics.seasonStats) return null;

        return {
            record: `${$teamAnalytics.seasonStats.wins}-${$teamAnalytics.seasonStats.losses}`,
            winPercentage: $teamAnalytics.seasonStats.win_percentage,
            pointsPerGame: $teamAnalytics.seasonStats.points_per_game,
            pointsAllowedPerGame: $teamAnalytics.seasonStats.points_allowed_per_game,
            streak: `${$teamAnalytics.seasonStats.streak}${$teamAnalytics.seasonStats.streak_type}`
        };
    }
);

export const advancedMetricsData = derived(
    teamAnalytics,
    $teamAnalytics => {
        if (!$teamAnalytics.advancedMetrics) return null;

        return {
            offensiveRating: $teamAnalytics.advancedMetrics.offensive_rating,
            defensiveRating: $teamAnalytics.advancedMetrics.defensive_rating,
            netRating: $teamAnalytics.advancedMetrics.net_rating,
            pace: $teamAnalytics.advancedMetrics.pace,
            trueShootingPercentage: $teamAnalytics.advancedMetrics.true_shooting_percentage
        };
    }
);
