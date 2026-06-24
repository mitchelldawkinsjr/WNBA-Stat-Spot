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

function num(value: unknown): number {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function average(values: number[]): number {
    if (!values.length) return 0;
    return values.reduce((sum, value) => sum + value, 0) / values.length;
}

function buildAnalyticsFromGamelog(games: Array<Record<string, unknown>>) {
    const gameLog = games.map((game) => ({
        date: String(game.game_date ?? ''),
        opponent: String(game.opponent_team_abbreviation ?? game.opponent_team_name ?? 'Opponent'),
        minutes: num(game.minutes),
        points: num(game.points),
        rebounds: num(game.rebounds),
        assists: num(game.assists),
        steals: num(game.steals),
        blocks: num(game.blocks),
        turnovers: num(game.turnovers),
        fg_made_attempted: `${num(game.field_goals_made)}/${num(game.field_goals_attempted)}`,
        three_pt_made_attempted: `${num(game.three_point_field_goals_made)}/${num(game.three_point_field_goals_attempted)}`,
        ft_made_attempted: `${num(game.free_throws_made)}/${num(game.free_throws_attempted)}`,
    }));

    const recent = gameLog.slice(0, 10);
    const homeGames = games.filter((game) => game.home_away === 'home');
    const awayGames = games.filter((game) => game.home_away === 'away');

    const sum = (rows: Array<Record<string, unknown>>, key: string) =>
        rows.reduce((total, row) => total + num(row[key]), 0);

    const pct = (made: number, attempted: number) => (attempted > 0 ? (made / attempted) * 100 : 0);

    const fgMade = sum(games, 'field_goals_made');
    const fgAttempted = sum(games, 'field_goals_attempted');
    const tpm = sum(games, 'three_point_field_goals_made');
    const tpa = sum(games, 'three_point_field_goals_attempted');
    const ftm = sum(games, 'free_throws_made');
    const fta = sum(games, 'free_throws_attempted');
    const points = sum(games, 'points');

    const tsDenominator = 2 * (fgAttempted + 0.44 * fta);
    const efgDenominator = fgAttempted;

    const shootingEfficiency = {
        field_goal_percentage: Number(pct(fgMade, fgAttempted).toFixed(1)),
        three_point_percentage: Number(pct(tpm, tpa).toFixed(1)),
        free_throw_percentage: Number(pct(ftm, fta).toFixed(1)),
        true_shooting_percentage: Number((tsDenominator > 0 ? (points / tsDenominator) * 100 : 0).toFixed(1)),
        effective_field_goal_percentage: Number(
            (efgDenominator > 0 ? ((fgMade + 0.5 * tpm) / efgDenominator) * 100 : 0).toFixed(1)
        ),
    };

    const statsFor = (rows: Array<Record<string, unknown>>) => ({
        points: Number(average(rows.map((row) => num(row.points))).toFixed(1)),
        rebounds: Number(average(rows.map((row) => num(row.rebounds))).toFixed(1)),
        assists: Number(average(rows.map((row) => num(row.assists))).toFixed(1)),
        steals: Number(average(rows.map((row) => num(row.steals))).toFixed(1)),
        blocks: Number(average(rows.map((row) => num(row.blocks))).toFixed(1)),
        turnovers: Number(average(rows.map((row) => num(row.turnovers))).toFixed(1)),
        minutes: Number(average(rows.map((row) => num(row.minutes))).toFixed(1)),
    });

    return {
        gameStats: gameLog.map((game) => ({
            date: game.date,
            points: game.points,
            rebounds: game.rebounds,
            assists: game.assists,
            steals: game.steals,
            blocks: game.blocks,
            minutes: game.minutes,
            fg_made: num(game.fg_made_attempted.split('/')[0]),
            fg_attempted: num(game.fg_made_attempted.split('/')[1]),
            three_made: num(game.three_pt_made_attempted.split('/')[0]),
            three_attempted: num(game.three_pt_made_attempted.split('/')[1]),
            ft_made: num(game.ft_made_attempted.split('/')[0]),
            ft_attempted: num(game.ft_made_attempted.split('/')[1]),
        })),
        recentForm: {
            games_analyzed: recent.length,
            averages: statsFor(recent),
            game_log: recent,
        },
        shootingEfficiency,
        homeAwayPerformance: {
            home: {
                games: homeGames.length,
                stats: statsFor(homeGames),
            },
            away: {
                games: awayGames.length,
                stats: statsFor(awayGames),
            },
        },
    };
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

                if (analytics?.error) {
                    throw new Error(String(analytics.message ?? analytics.error));
                }

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

                return gameStats.length > 0;
            } catch (error) {
                update(state => ({
                    ...state,
                    loading: false,
                    error: error instanceof Error ? error.message : 'Failed to fetch player analytics'
                }));
                return false;
            }
        },
        setFromGamelog: (games: Array<Record<string, unknown>>) => {
            const built = buildAnalyticsFromGamelog(games);
            update(state => ({
                ...state,
                loading: false,
                error: null,
                gameStats: built.gameStats,
                recentForm: built.recentForm,
                shootingEfficiency: built.shootingEfficiency,
                homeAwayPerformance: built.homeAwayPerformance,
            }));
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
