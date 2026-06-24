import { writable, derived } from 'svelte/store';
import type { Writable, Readable } from 'svelte/store';
import { api } from '$lib/api/client';

export interface GameStats {
    date: string;
    points: number;
    rebounds: number;
    assists: number;
    steals: number;
    blocks: number;
    minutes: number;
    fg_made: number;
    fg_attempted: number;
    three_made: number;
    three_attempted: number;
    ft_made: number;
    ft_attempted: number;
}

export interface PlayerAnalyticsState {
    loading: boolean;
    error: string | null;
    gameStats: GameStats[];
    recentForm: {
        games_analyzed: number;
        averages: Record<string, number>;
        game_log?: any[];
    } | null;
    per36Stats: Record<string, number> | null;
    advancedMetrics: Record<string, number> | null;
    shootingEfficiency: Record<string, number> | null;
    homeAwayPerformance: Record<string, Record<string, number>> | null;
}

function createPlayerAnalyticsStore() {
    const { subscribe, set, update } = writable<PlayerAnalyticsState>({
        loading: false,
        error: null,
        gameStats: [],
        recentForm: null,
        per36Stats: null,
        advancedMetrics: null,
        shootingEfficiency: null,
        homeAwayPerformance: null
    });

    return {
        subscribe,
        fetchAnalytics: async (playerId: string, options?: { season?: number }) => {
            update(state => ({ ...state, loading: true, error: null }));

            try {
                const response = await api.wnba.analytics.getPlayer(playerId, options);
                const analytics = response.data;

                // Convert game_log to gameStats format
                const gameStats = analytics.recent_form?.game_log?.map((game: any) => ({
                    date: game.date,
                    points: game.points || 0,
                    rebounds: game.rebounds || 0,
                    assists: game.assists || 0,
                    steals: game.steals || 0,
                    blocks: game.blocks || 0,
                    minutes: parseFloat(game.minutes) || 0,
                    fg_made: parseInt(game.fg_made_attempted?.split('/')[0]) || 0,
                    fg_attempted: parseInt(game.fg_made_attempted?.split('/')[1]) || 0,
                    three_made: parseInt(game.three_pt_made_attempted?.split('/')[0]) || 0,
                    three_attempted: parseInt(game.three_pt_made_attempted?.split('/')[1]) || 0,
                    ft_made: parseInt(game.ft_made_attempted?.split('/')[0]) || 0,
                    ft_attempted: parseInt(game.ft_made_attempted?.split('/')[1]) || 0,
                })) || [];

                update(state => ({
                    ...state,
                    loading: false,
                    gameStats: gameStats,
                    recentForm: analytics.recent_form || null,
                    per36Stats: analytics.per_36_stats || null,
                    advancedMetrics: analytics.advanced_metrics || null,
                    shootingEfficiency: analytics.shooting_efficiency || null,
                    homeAwayPerformance: analytics.home_away_performance || null
                }));
            } catch (error) {
                update(state => ({
                    ...state,
                    loading: false,
                    error: error instanceof Error ? error.message : 'Failed to fetch player analytics'
                }));
            }
        },
        reset: () => {
            set({
                loading: false,
                error: null,
                gameStats: [],
                recentForm: null,
                per36Stats: null,
                advancedMetrics: null,
                shootingEfficiency: null,
                homeAwayPerformance: null
            });
        }
    };
}

export const playerAnalytics = createPlayerAnalyticsStore();

// Derived stores for specific analytics
export const gameStatsChartData = derived(
    playerAnalytics,
    $playerAnalytics => {
        if (!$playerAnalytics.gameStats.length) return [];

        return $playerAnalytics.gameStats.map(game => ({
            date: game.date,
            points: game.points,
            rebounds: game.rebounds,
            assists: game.assists,
            steals: game.steals,
            blocks: game.blocks
        }));
    }
);

export const shootingEfficiencyData = derived(
    playerAnalytics,
    $playerAnalytics => {
        if (!$playerAnalytics.shootingEfficiency) return null;

        return {
            fgPercentage: $playerAnalytics.shootingEfficiency.field_goal_percentage || 0,
            threePercentage: $playerAnalytics.shootingEfficiency.three_point_percentage || 0,
            ftPercentage: $playerAnalytics.shootingEfficiency.free_throw_percentage || 0,
            efgPercentage: $playerAnalytics.shootingEfficiency.effective_field_goal_percentage || 0,
            tsPercentage: $playerAnalytics.shootingEfficiency.true_shooting_percentage || 0
        };
    }
);

export const homeAwayComparison = derived(
    playerAnalytics,
    $playerAnalytics => {
        if (!$playerAnalytics.homeAwayPerformance) return null;

        return {
            home: $playerAnalytics.homeAwayPerformance.home?.stats || {},
            away: $playerAnalytics.homeAwayPerformance.away?.stats || {}
        };
    }
);
