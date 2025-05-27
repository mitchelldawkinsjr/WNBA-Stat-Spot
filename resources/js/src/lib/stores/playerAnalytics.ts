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
        fetchAnalytics: async (playerId: string) => {
            update(state => ({ ...state, loading: true, error: null }));

            try {
                const response = await api.wnba.analytics.getPlayer(playerId);
                const analytics = response.data;

                update(state => ({
                    ...state,
                    loading: false,
                    gameStats: analytics.game_stats || [],
                    recentForm: analytics.analytics.recent_form || null,
                    per36Stats: analytics.analytics.per_36_stats || null,
                    advancedMetrics: analytics.analytics.advanced_metrics || null,
                    shootingEfficiency: analytics.analytics.shooting_efficiency || null,
                    homeAwayPerformance: analytics.analytics.home_away_performance || null
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
            fgPercentage: $playerAnalytics.shootingEfficiency.fg_percentage || 0,
            threePercentage: $playerAnalytics.shootingEfficiency.three_percentage || 0,
            ftPercentage: $playerAnalytics.shootingEfficiency.ft_percentage || 0,
            efgPercentage: $playerAnalytics.shootingEfficiency.efg_percentage || 0,
            tsPercentage: $playerAnalytics.shootingEfficiency.ts_percentage || 0
        };
    }
);

export const homeAwayComparison = derived(
    playerAnalytics,
    $playerAnalytics => {
        if (!$playerAnalytics.homeAwayPerformance) return null;

        return {
            home: $playerAnalytics.homeAwayPerformance.home || {},
            away: $playerAnalytics.homeAwayPerformance.away || {}
        };
    }
);
