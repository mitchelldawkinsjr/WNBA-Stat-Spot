import { browser } from '$app/environment';
import { env } from '$env/dynamic/public';

// Development fallback URLs - these should be set via environment variables in production
const API_URLS = {
    service: 'http://localhost:8080/api',
    hostInternal: 'http://localhost:8080/api',
    localhost: 'http://localhost:8080/api',
    localhostDefault: 'http://localhost:8080/api'
};

// Get API URL from environment variables with fallbacks
const getApiUrl = () => {
    // In production, use environment variable if set
    if (env.PUBLIC_API_URL) {
        return env.PUBLIC_API_URL;
    }

    // If we're in the browser and the current URL is not localhost, use relative URLs
    if (browser && typeof window !== 'undefined') {
        const currentHost = window.location.host;
        if (!currentHost.includes('localhost') && !currentHost.includes('127.0.0.1')) {
            // Production deployment - use relative URL
            return '/api';
        }
    }

    // Development mode - ALWAYS use localhost:8080 for both browser and server-side
    // This ensures the frontend always connects to the correct port
    return 'http://localhost:8080/api';
};

const API_URL = 'http://localhost:8080/api';

// Utility function to get user timezone information
export function getUserTimezone() {
    if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const now = new Date();
        const offset = now.getTimezoneOffset();
        const offsetHours = Math.floor(Math.abs(offset) / 60);
        const offsetMinutes = Math.abs(offset) % 60;
        const offsetSign = offset <= 0 ? '+' : '-';

        return {
            timezone,
            offset,
            offsetString: `UTC${offsetSign}${offsetHours.toString().padStart(2, '0')}:${offsetMinutes.toString().padStart(2, '0')}`,
            localTime: now.toLocaleString(),
            utcTime: now.toISOString(),
            dateString: now.toLocaleDateString(),
            timeString: now.toLocaleTimeString()
        };
    }

    return {
        timezone: 'UTC',
        offset: 0,
        offsetString: 'UTC+00:00',
        localTime: new Date().toString(),
        utcTime: new Date().toISOString(),
        dateString: new Date().toLocaleDateString(),
        timeString: new Date().toLocaleTimeString()
    };
}

// API Client configuration complete

export interface ApiResponse<T> {
    data: T;
    message: string;
}

export interface Team {
    id: number;
    team_id: string;
    team_name: string;
    team_location: string;
    team_abbreviation: string;
    team_display_name: string;
    team_uid: string;
    team_slug: string | null;
    team_logo: string;
    team_color: string;
    team_alternate_color: string;
    team_conference?: string;
    team_division?: string;
    team_founded?: string;
    created_at: string;
    updated_at: string;
}

export interface Player {
    id: number;
    athlete_id: string;
    athlete_display_name: string;
    athlete_short_name: string;
    athlete_jersey: string | null;
    athlete_headshot_href: string | null;
    athlete_headshot?: string | null;
    athlete_position_name: string | null;
    athlete_position_abbreviation: string | null;
    athlete_height?: string | null;
    athlete_weight?: number | null;
    athlete_experience?: string | null;
    athlete_college?: string | null;
    team_id?: string;
    created_at: string;
    updated_at: string;
    player_games?: PlayerGame[];
}

export interface PlayerGame {
    id: number;
    game_id: number;
    player_id: number;
    team_id: number;
    minutes: string | null;
    field_goals_made: number;
    field_goals_attempted: number;
    three_point_field_goals_made: number;
    three_point_field_goals_attempted: number;
    free_throws_made: number;
    free_throws_attempted: number;
    offensive_rebounds: number;
    defensive_rebounds: number;
    rebounds: number;
    assists: number;
    steals: number;
    blocks: number;
    turnovers: number;
    fouls: number;
    plus_minus: number;
    points: number;
    starter: boolean;
    ejected: boolean;
    did_not_play: boolean;
    reason: string | null;
    active: boolean;
    team?: Team;
    game?: Game;
}

export interface Game {
    id: number;
    game_id: string;
    season: string;
    season_type: string;
    game_date: string;
    game_date_time: string;
    home_team_id?: string;
    away_team_id?: string;
    home_team_score?: number | null;
    away_team_score?: number | null;
    venue_id: string | null;
    venue_name: string | null;
    venue_city: string | null;
    venue_state: string | null;
    venue_country: string | null;
    venue_capacity: number | null;
    venue_surface: string | null;
    venue_indoor: boolean | null;
    status_id: string | null;
    status_name: string | null;
    status_type: string | null;
    status_abbreviation: string | null;
    created_at: string;
    updated_at: string;
    home_team?: {
        id: number;
        team_id: string;
        name: string;
        abbreviation: string;
        logo: string;
        score: number | null;
        winner: boolean;
    } | null;
    away_team?: {
        id: number;
        team_id: string;
        name: string;
        abbreviation: string;
        logo: string;
        score: number | null;
        winner: boolean;
    } | null;
    final_score?: {
        home: number | null;
        away: number | null;
        final: boolean;
    } | null;
}

export interface Stats extends PlayerGame {
    player?: Player;
    team?: Team;
    game?: Game;
}

// WNBA Analytics Interfaces
export interface PlayerPropPrediction {
    prediction: number;
    confidence: number;
    over_probability: number;
    under_probability: number;
    recommendation: string;
    expected_value: number;
}

export interface Prediction {
    id?: number;
    player_id: string;
    player_name: string;
    player_position?: string;
    stat: string;
    line: number;
    original_line?: number;
    predicted_value: number;
    confidence: number;
    probability_over?: number;
    probability_under?: number;
    recommendation: string;
    expected_value: number;
    reasoning?: string;
    data_source?: string;
    line_source?: string;
    odds_data?: {
        line?: number;
        over_odds?: number;
        under_odds?: number;
        available?: boolean;
        source?: string;
        bookmaker_over?: string;
        bookmaker_under?: string;
        last_update?: string;
        event_id?: string;
        commence_time?: string;
        total_bookmakers?: number;
    };
    created_at: string;
}

export interface PropBet {
    id?: number;
    player_id: string;
    player_name: string;
    team_abbreviation: string;
    stat_type: string;
    line: number;
    over_odds: number;
    under_odds: number;
    sportsbook: string;
    game_date: string;
}

export interface PropScannerBet {
    player_id: string;
    player_name: string;
    player_position: string;
    stat_type: string;
    suggested_line: number;
    original_line?: number;
    predicted_value: number;
    confidence: number;
    recommendation: 'over' | 'under' | 'avoid';
    expected_value: number;
    probability_over: number;
    probability_under: number;
    recent_form: number;
    season_average: number;
    last_5_games_avg?: number;
    home_away_factor?: number;
    matchup_difficulty: string;
    injury_risk: string;
    betting_value: 'excellent' | 'good' | 'fair' | 'poor';
    reasoning?: string;
    data_source?: string;
    line_source?: string;
    odds_data?: {
        line?: number;
        over_odds?: number;
        under_odds?: number;
        available?: boolean;
        source?: string;
        bookmaker_over?: string;
        bookmaker_under?: string;
        last_update?: string;
        event_id?: string;
        commence_time?: string;
        total_bookmakers?: number;
    };
    created_at?: string;
}

export interface TodaysProp {
    player_id: string;
    player_name: string;
    team_abbreviation: string;
    opponent: string;
    game_time: string;
    stat_type: string;
    suggested_line: number;
    predicted_value: number;
    confidence: number;
    recommendation: 'over' | 'under' | 'avoid';
    expected_value: number;
    probability_over: number;
    probability_under: number;
    recent_form: number;
    season_average: number;
    matchup_difficulty: string;
    betting_value: 'excellent' | 'good' | 'fair' | 'poor';
    reasoning: string;
    espn_line?: number;
    espn_odds?: {
        over: number;
        under: number;
    };
}

// Data Aggregator Interfaces
export interface AggregatedPlayerData {
    player_info: {
        player_id: number;
        athlete_id: string;
        name: string;
        position: string;
        team_id: number;
        team_name: string;
    };
    season_stats: {
        games_played: number;
        averages: Record<string, number>;
        totals: Record<string, number>;
        percentages: Record<string, number>;
    };
    game_log: Array<{
        game_id: number;
        date: string;
        opponent: string;
        home_away: string;
        minutes: number;
        points: number;
        rebounds: number;
        assists: number;
        steals: number;
        blocks: number;
        turnovers: number;
        fg_made_attempted: string;
        three_pt_made_attempted: string;
        ft_made_attempted: string;
        plus_minus: number;
        starter: boolean;
    }>;
    performance_trends: Record<string, number>;
    situational_stats: {
        home: Record<string, any>;
        away: Record<string, any>;
        vs_strong_defense: Record<string, any>;
        vs_weak_defense: Record<string, any>;
        back_to_back: Record<string, any>;
        rest_days: Record<string, any>;
    };
    advanced_metrics: {
        usage_rate: number;
        true_shooting_pct: number;
        effective_fg_pct: number;
        assist_turnover_ratio: number;
        per_36_stats: Record<string, any>;
        player_efficiency_rating: number;
    };
    consistency_metrics: {
        points_consistency: number;
        rebounds_consistency: number;
        assists_consistency: number;
        overall_consistency: number;
    };
    data_quality: {
        sample_size: number;
        data_completeness: number;
        recency_score: number;
        quality_score: number;
    };
}

export interface AggregatedTeamData {
    team_info: Record<string, any>;
    season_stats: Record<string, any>;
    game_log: Array<Record<string, any>>;
    offensive_metrics: Record<string, any>;
    defensive_metrics: Record<string, any>;
    pace_metrics: Record<string, any>;
    situational_performance: Record<string, any>;
    strength_of_schedule: Record<string, any>;
    recent_form: Record<string, any>;
}

export interface AggregatedGameData {
    game_info: Record<string, any>;
    team_stats: Record<string, any>;
    player_stats: Record<string, any>;
    play_by_play: Record<string, any>;
    game_flow: Record<string, any>;
    key_moments: Record<string, any>;
    pace_analysis: Record<string, any>;
    efficiency_metrics: Record<string, any>;
    competitive_balance: Record<string, any>;
}

export interface AggregatedMatchupData {
    matchup_history: Record<string, any>;
    head_to_head_stats: Record<string, any>;
    recent_meetings: Array<Record<string, any>>;
    style_comparison: Record<string, any>;
    key_player_matchups: Array<Record<string, any>>;
    trends: Record<string, any>;
    prediction_factors: Record<string, any>;
}

export interface AggregatedLeagueData {
    league_averages: Record<string, any>;
    team_rankings: Record<string, any>;
    player_leaders: Record<string, any>;
    pace_trends: Record<string, any>;
    scoring_trends: Record<string, any>;
    efficiency_trends: Record<string, any>;
    defensive_trends: Record<string, any>;
    league_context: Record<string, any>;
}

export interface AggregatedPropData {
    stat_distribution: Record<string, any>;
    historical_performance: Record<string, any>;
    situational_analysis: Record<string, any>;
    opponent_impact: Record<string, any>;
    trend_analysis: Record<string, any>;
    consistency_metrics: Record<string, any>;
    outlier_analysis: Record<string, any>;
    prediction_inputs: Record<string, any>;
}

export interface PlayerAnalytics {
    player_id: number;
    analytics: {
        recent_form: {
            games_analyzed: number;
            date_range: {
                from: string;
                to: string;
            };
            averages: Record<string, number>;
            trends: Record<string, number>;
            consistency: number;
            game_log: Array<{
                date: string;
                opponent: string;
                minutes: string;
                points: number;
                rebounds: number;
                assists: number;
                steals: number;
                blocks: number;
                turnovers: number;
                fg_made_attempted: string;
                three_pt_made_attempted: string;
                ft_made_attempted: string;
            }>;
        };
        per_36_stats?: any;
        advanced_metrics?: any;
        home_away_performance?: any;
        home_away_splits?: {
            home: Record<string, any>;
            away: Record<string, any>;
        };
        shooting_efficiency?: any;
    };
    generated_at: string;
}

export interface TeamAnalytics {
    team_id: number;
    analytics: {
        performance_metrics?: any;
        shooting_trends?: any;
        opponent_analysis?: any;
        defensive_metrics?: any;
        strength_of_schedule?: any;
    };
    generated_at: string;
}

export interface GameAnalytics {
    game_id: number;
    analytics: {
        game_analysis?: any;
        live_game_flow?: any;
        environment_factors?: any;
        advanced_metrics?: any;
    };
    generated_at: string;
}

export interface BettingRecommendation {
    player_id: string;
    stat: string;
    line: number;
    recommendation: 'over' | 'under' | 'avoid';
    confidence: number;
    expected_value: number;
    reasoning: string;
}

export interface CacheStats {
    total_keys: number;
    memory_usage: any[];
    hit_rate: number;
    key_distribution: {
        player: number;
        team: number;
        game: number;
        prediction: number;
        league: number;
        analytics: number;
    };
    expiration_analysis: {
        expiring_soon: number;
        expiring_medium: number;
        expiring_later: number;
        no_expiration: number;
    };
}

export interface ModelValidation {
    overall_accuracy: number;
    stat_accuracies: Record<string, number>;
    recent_performance: Array<{
        date: string;
        accuracy: number;
    }>;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    message: string;
}

export interface TeamPlayersResponse {
    data: Player[];
    meta: {
        total: number;
        team: Team;
    };
}

// Simple in-memory cache for API responses
const apiCache = new Map<string, { data: any; timestamp: number; ttl: number }>();

const CACHE_TTL = {
    short: 5 * 60 * 1000,    // 5 minutes
    medium: 30 * 60 * 1000,  // 30 minutes
    long: 60 * 60 * 1000,    // 1 hour
};

function getCacheKey(url: string, options?: RequestInit): string {
    const method = options?.method || 'GET';
    const body = options?.body || '';
    return `${method}:${url}:${body}`;
}

function getFromCache(key: string): any | null {
    const cached = apiCache.get(key);
    if (!cached) return null;

    if (Date.now() - cached.timestamp > cached.ttl) {
        apiCache.delete(key);
        return null;
    }

    return cached.data;
}

function setCache(key: string, data: any, ttl: number): void {
    apiCache.set(key, {
        data,
        timestamp: Date.now(),
        ttl
    });
}

async function fetchApi<T>(endpoint: string, options?: RequestInit & { cacheTtl?: keyof typeof CACHE_TTL }): Promise<T> {
    const url = `${getApiUrl()}${endpoint}`;
    const cacheKey = getCacheKey(url, options);
    const cacheTtl = options?.cacheTtl ? CACHE_TTL[options.cacheTtl] : CACHE_TTL.short;

    // Check cache for GET requests
    if (!options?.method || options.method === 'GET') {
        const cached = getFromCache(cacheKey);
        if (cached) {
            return cached;
        }
    }

    // Remove our custom cacheTtl property before passing to fetch
    const { cacheTtl: _, ...fetchOptions } = options || {};

    const config: RequestInit = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        ...fetchOptions,
    };

    try {
        const response = await fetch(url, config);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
        }

        const data = await response.json();

        // Cache successful GET requests
        if (!options?.method || options.method === 'GET') {
            setCache(cacheKey, data, cacheTtl);
        }

        return data;
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Unknown error';

        // API request failed - check network connection

        throw error;
    }
}

export const api = {
    // Connection testing for Docker debugging
    test: {
        connection: async () => {
            const results = [];

            for (const [name, url] of Object.entries(API_URLS)) {
                try {
                    const response = await fetch(`${url}/health`, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' }
                    });

                    const result = {
                        name,
                        url,
                        status: response.status,
                        ok: response.ok,
                        data: response.ok ? await response.json() : null
                    };

                    results.push(result);
                } catch (error) {
                    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
                    const result = {
                        name,
                        url,
                        error: errorMessage,
                        ok: false
                    };

                    results.push(result);
                }
            }

            return results;
        }
    },
    // Health check for debugging container connectivity
    health: {
        check: () => fetchApi<{ status: string; timestamp: string; message: string }>('/health', { cacheTtl: 'short' })
    },
    teams: {
        getAll: (params?: { search?: string }) => {
            const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
            return fetchApi<ApiResponse<Team[]>>(`/teams${query}`, { cacheTtl: 'medium' });
        },
        getSummary: () => fetchApi<ApiResponse<Team[]>>('/teams/summary', { cacheTtl: 'long' }),
        clearCache: () => fetchApi<ApiResponse<any>>('/teams/clear-cache', { method: 'POST' }),
        getById: (teamId: string) => fetchApi<ApiResponse<Team>>(`/teams/${teamId}`, { cacheTtl: 'medium' }),
        getPlayers: (teamId: string) => fetchApi<TeamPlayersResponse>(`/teams/${teamId}/players`, { cacheTtl: 'medium' }),
    },
    players: {
        getAll: (params?: {
            page?: number;
            per_page?: number;
            search?: string;
            team?: string;
            position?: string;
        }) => {
            const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
            return fetchApi<PaginatedResponse<Player>>(`/players${query}`, { cacheTtl: 'medium' });
        },
        getSummary: () => fetchApi<ApiResponse<Player[]>>('/players/summary', { cacheTtl: 'long' }),
        getById: (id: string) => fetchApi<ApiResponse<Player>>(`/players/${id}`, { cacheTtl: 'medium' }),
        clearCache: () => fetchApi<ApiResponse<any>>('/players/clear-cache', { method: 'POST' }),
    },
    games: {
        getAll: () => fetchApi<ApiResponse<Game[]>>('/games', { cacheTtl: 'medium' }),
    },
    stats: {
        getAll: () => fetchApi<ApiResponse<Stats[]>>('/stats', { cacheTtl: 'medium' }),
    },
    wnba: {
        predictions: {
            getPlayerProps: (playerId: string, stats: string[]) =>
                fetchApi<{ success: boolean; data: { predictions: Record<string, PlayerPropPrediction> } }>('/wnba/predictions/props', {
                    method: 'POST',
                    body: JSON.stringify({ player_id: playerId, stats })
                }),
            getBettingRecommendations: (filters?: any) =>
                fetchApi<{ success: boolean; data: { recommendations: BettingRecommendation[] } }>('/wnba/predictions/betting', {
                    method: 'POST',
                    body: JSON.stringify(filters || {})
                }),
            generate: (data: { player_id: string; stat: string; line: number }) =>
                fetchApi<{ success: boolean; data: Prediction }>('/wnba/predictions/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    cacheTtl: 'medium'
                }),
            generatePrediction: (data: { player_id: string; stat: string; line: number }) =>
                fetchApi<{ success: boolean; data: Prediction }>('/wnba/predictions/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    cacheTtl: 'medium'
                }),
            getPropBets: () =>
                fetchApi<{ success: boolean; data: PropBet[] }>('/wnba/predictions/prop-bets', { cacheTtl: 'short' }),
            getTodaysBest: (timezone?: string) => {
                // Get user's timezone if not provided
                const userTimezone = timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
                const params = new URLSearchParams();
                params.append('timezone', userTimezone);

                return fetchApi<{ success: boolean; data: TodaysProp[] }>(`/wnba/predictions/todays-best?${params.toString()}`, { cacheTtl: 'short' });
            },
            getTodaysBestProps: (timezone?: string) => {
                // Get user's timezone if not provided
                const userTimezone = timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
                const params = new URLSearchParams();
                params.append('timezone', userTimezone);

                return fetchApi<{ success: boolean; data: TodaysProp[] }>(`/wnba/predictions/todays-best?${params.toString()}`, { cacheTtl: 'short' });
            },
        },
        analytics: {
            getPlayer: (playerId: string, options?: { season?: number; last_n_games?: number }) => {
                const params = new URLSearchParams();
                if (options?.season) params.append('season', options.season.toString());
                if (options?.last_n_games) params.append('last_n_games', options.last_n_games.toString());

                const queryString = params.toString();
                const endpoint = `/wnba/analytics/player/${playerId}${queryString ? `?${queryString}` : ''}`;

                return fetchApi<{ success: boolean; data: PlayerAnalytics }>(endpoint, { cacheTtl: 'medium' });
            },
            getTeam: (teamId: string, options?: { season?: number; last_n_games?: number }) => {
                const params = new URLSearchParams();
                if (options?.season) params.append('season', options.season.toString());
                if (options?.last_n_games) params.append('last_n_games', options.last_n_games.toString());

                const queryString = params.toString();
                const endpoint = `/wnba/analytics/team/${teamId}${queryString ? `?${queryString}` : ''}`;

                return fetchApi<{ success: boolean; data: TeamAnalytics }>(endpoint, { cacheTtl: 'medium' });
            },
            getGame: (gameId: string) => {
                return fetchApi<{ success: boolean; data: GameAnalytics }>(`/wnba/analytics/game/${gameId}`, { cacheTtl: 'medium' });
            },
        },
        validation: {
            getSummary: (params?: { stat_type?: string; player_category?: string; season?: number; validation_type?: string }) => {
                const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
                return fetchApi<{ success: boolean; data: any }>(`/wnba/validation${query}`, { cacheTtl: 'long' });
            },
        },
        betting: {
            getAnalytics: (params?: { timeframe?: string; bet_type?: string }) => {
                const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
                return fetchApi<{ success: boolean; data: any }>(`/wnba/betting/analytics${query}`, { cacheTtl: 'medium' });
            },
        },
        dataQuality: {
            getMetrics: (params?: { timeframe?: string; source?: string }) => {
                const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
                return fetchApi<{ success: boolean; data: any }>(`/wnba/data-quality/metrics${query}`, { cacheTtl: 'short' });
            },
        },
        monteCarlo: {
            runSimulation: (params: { player_id: string; stat_type: string; simulations: number; confidence_level: number; scenario: string }) => {
                return fetchApi<{ success: boolean; data: any }>(`/wnba/monte-carlo/run`, {
                    method: 'POST',
                    body: JSON.stringify(params)
                });
            },
        },
        propScanner: {
            scanAllPlayers: () => {
                return fetchApi<{ data: PropScannerBet[]; message: string }>('/wnba/prop-scanner/scan-all', { cacheTtl: 'short' });
            },
            scanPlayer: (playerId: string) => {
                return fetchApi<{ data: PropScannerBet[]; message: string }>(`/wnba/prop-scanner/scan-player/${playerId}`, { cacheTtl: 'short' });
            },
        },
        cache: {
            getStats: () => fetchApi<{ success: boolean; data: any }>('/wnba/cache/stats', { cacheTtl: 'short' }),
        },

        // Testing & Validation API
        testing: {
            testPlayerAccuracy: (params: { player_id: string; stat_type: string; test_games?: number; betting_lines?: number[] }) =>
                fetchApi<{ success: boolean; data: PredictionTestResult }>('/wnba/testing/player-accuracy', {
                    method: 'POST',
                    body: JSON.stringify(params)
                }),

            runBulkTesting: (params: { player_ids?: string[]; stat_types?: string[]; test_games?: number }) =>
                fetchApi<{ success: boolean; data: BulkTestResult }>('/wnba/testing/bulk-testing', {
                    method: 'POST',
                    body: JSON.stringify(params)
                }),

            getHistoricalTests: (params?: { limit?: number; offset?: number }) => {
                const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
                return fetchApi<{ success: boolean; data: HistoricalTestResult[] }>(`/wnba/testing/historical${query}`, { cacheTtl: 'medium' });
            },

            startHistoricalTesting: (params: HistoricalTestingParams) =>
                fetchApi<{ success: boolean; data: any }>('/wnba/testing/historical/start', {
                    method: 'POST',
                    body: JSON.stringify(params)
                }),

            getHistoricalResults: (params?: HistoricalResultsParams) => {
                const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
                return fetchApi<{ success: boolean; data: { results: HistoricalTestResult[]; analytics: HistoricalTestAnalytics; filters_applied: any } }>(`/wnba/testing/historical/results${query}`, { cacheTtl: 'medium' });
            },

            getHistoricalTestingStatus: () =>
                fetchApi<{ success: boolean; data: HistoricalTestingStatus }>('/wnba/testing/historical/status', { cacheTtl: 'short' }),

            getHistoricalLeaderboard: (params?: LeaderboardParams) => {
                const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
                return fetchApi<{ success: boolean; data: HistoricalTestAnalytics }>(`/wnba/testing/historical/leaderboard${query}`, { cacheTtl: 'medium' });
            },
        },

        // Data Import/Update API
        dataImport: {
            // Full import (incremental)
            importAll: () => fetchApi<{ success: boolean; message: string; data: any }>('/wnba/data/import', {
                method: 'POST'
            }),

            // Force import (overwrites existing)
            forceImportAll: () => fetchApi<{ success: boolean; message: string; data: any }>('/wnba/data/import/force', {
                method: 'POST'
            }),

            // Get import status
            getStatus: () => fetchApi<{ success: boolean; data: any }>('/wnba/data/import/status'),

            // Get data summary
            getSummary: () => fetchApi<{ success: boolean; data: any }>('/wnba/data/stats/summary', { cacheTtl: 'short' }),

            // Individual imports
            importTeams: () => fetchApi<{ success: boolean; message: string; data: any }>('/wnba/data/import/teams', {
                method: 'POST'
            }),

            importPlayers: () => fetchApi<{ success: boolean; message: string; data: any }>('/wnba/data/import/players', {
                method: 'POST'
            }),

            importGames: () => fetchApi<{ success: boolean; message: string; data: any }>('/wnba/data/import/games', {
                method: 'POST'
            }),

            importPlayerStats: () => fetchApi<{ success: boolean; message: string; data: any }>('/wnba/data/import/stats', {
                method: 'POST'
            }),
        },
    },
    // The Odds API methods
    odds: {
        // Sports and Events
        getSports: () => fetchApi<{ success: boolean; data: OddsApiSport[]; count: number }>('/odds/sports', { cacheTtl: 'long' }),
        getWnbaOdds: (params?: any) => fetchApi<{ success: boolean; data: OddsApiEvent[]; count: number; filters: any }>(`/odds/wnba`, params),
        getWnbaEvents: (params?: any) => fetchApi<{ success: boolean; data: OddsApiEvent[]; count: number }>(`/odds/wnba/events`, params),

        // Player Props (based on The Odds API documentation)
        getWnbaPlayerProps: (params?: {
            markets?: string[] | string;
            bookmakers?: string[] | string;
            player_name?: string;
            regions?: string;
            oddsFormat?: string;
        }) => {
            const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
            return fetchApi<{ success: boolean; data: OddsApiPlayerProp[]; count: number; filters: any }>(`/odds/wnba/props${query}`, { cacheTtl: 'short' });
        },

        getPlayerPropMarkets: () => fetchApi<{ success: boolean; data: OddsApiPlayerProp[] }>('/odds/wnba/props/markets'),

        getBestPlayerPropOdds: (params: {
            player_name: string;
            stat_type: string;
            line?: number;
        }) => {
            const query = `?${new URLSearchParams(params as any).toString()}`;
            return fetchApi<{ success: boolean; data: OddsApiPlayerProp }>(`/odds/wnba/props/best${query}`, { cacheTtl: 'short' });
        },

        getPlayerPropsAnalysis: (params: {
            player_name: string;
            markets?: string[] | string;
        }) => {
            const query = `?${new URLSearchParams(params as any).toString()}`;
            return fetchApi<{ success: boolean; data: OddsApiPlayerProp[] }>(`/odds/wnba/props/analysis${query}`, { cacheTtl: 'short' });
        },

        getEventPlayerProps: (eventId: string, params?: {
            markets?: string[] | string;
            bookmakers?: string[] | string;
            player_name?: string;
            regions?: string;
            oddsFormat?: string;
        }) => {
            const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
            return fetchApi<{ success: boolean; data: OddsApiPlayerProp[] }>(`/odds/wnba/events/${eventId}/props${query}`, { cacheTtl: 'short' });
        },

        // General Odds
        getPlayerOdds: (params?: any) => {
            const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
            return fetchApi<{ success: boolean; data: OddsApiPlayerProp | null }>(`/odds/player${query}`, { cacheTtl: 'short' });
        },
        getHistoricalOdds: (params?: any) => {
            const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
            return fetchApi<{ success: boolean; data: OddsApiEvent[] }>(`/odds/historical${query}`, { cacheTtl: 'medium' });
        },
        getBestOdds: (params?: any) => {
            const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
            return fetchApi<{ success: boolean; data: BestOddsComparison[] }>(`/odds/best${query}`, { cacheTtl: 'short' });
        },
        getLiveOdds: (params?: any) => {
            const query = params ? `?${new URLSearchParams(params as any).toString()}` : '';
            return fetchApi<{ success: boolean; data: OddsApiEvent[] }>(`/odds/live${query}`, { cacheTtl: 'short' });
        },
        getUsageStats: () => fetchApi<{ success: boolean; data: OddsApiUsageStats }>('/odds/usage', { cacheTtl: 'short' }),
        clearCache: () => fetchApi<{ success: boolean; message: string }>('/odds/clear-cache', { method: 'POST' }),
    },
};

export interface PredictionTestResult {
    player: {
        athlete_id: string;
        name: string;
        position: string;
    };
    test_parameters: {
        stat_type: string;
        test_games: number;
        betting_lines: number[];
        season_average: number;
    };
    actual_results: Array<{
        game_number: number;
        date: string;
        actual_value: number;
    }>;
    line_tests: Array<{
        line: number;
        predicted_value: number;
        confidence: number;
        probability_over: number;
        probability_under: number;
        recommendation: string;
        predictions: Array<{
            game_number: number;
            actual_value: number;
            actual_result: string;
            predicted_result: string;
            correct: boolean;
            line: number;
        }>;
        correct_predictions: number;
        total_predictions: number;
        accuracy_percentage: number;
    }>;
    overall_performance: {
        total_predictions: number;
        correct_predictions: number;
        accuracy_percentage: number;
        by_line: Array<{
            line: number;
            accuracy: number;
        }>;
    };
    insights: string[];
    tested_at: string;
}

export interface BulkTestResult {
    results: Array<{
        player: {
            athlete_id: string;
            name: string;
            position: string;
        };
        accuracy: number;
        total_predictions: number;
        correct_predictions: number;
    }>;
    overall_stats: {
        total_predictions: number;
        correct_predictions: number;
        players_tested: number;
        average_accuracy: number;
    };
    tested_at: string;
}

// Historical Testing Interfaces
export interface HistoricalTestResult {
    id: number;
    test_batch_id: string;
    test_type: string;
    player_id: string;
    player_name: string;
    player_position: string | null;
    stat_type: string;
    test_games: number;
    betting_lines: number[];
    season_average: number;
    total_predictions: number;
    correct_predictions: number;
    accuracy_percentage: number;
    confidence_score: number | null;
    line_results: HistoricalLineResult[];
    actual_game_results: HistoricalGameResult[];
    insights: string[] | null;
    best_line_accuracy: number | null;
    worst_line_accuracy: number | null;
    average_line_accuracy: number | null;
    volatility_score: number | null;
    sample_size: number;
    data_quality_score: number | null;
    tested_at: string;
    test_version: string;
    created_at: string;
    updated_at: string;
}

export interface HistoricalLineResult {
    line: number;
    predicted_value: number;
    confidence: number;
    probability_over: number;
    probability_under: number;
    recommendation: string;
    predictions: HistoricalPrediction[];
    correct_predictions: number;
    total_predictions: number;
    accuracy_percentage: number;
}

export interface HistoricalPrediction {
    game_number: number;
    actual_value: number;
    actual_result: string;
    predicted_result: string;
    correct: boolean;
    line: number;
}

export interface HistoricalGameResult {
    game_number: number;
    date: string;
    actual_value: number;
}

export interface HistoricalTestAnalytics {
    top_performers: HistoricalTestResult[];
    stat_performance: Array<{
        stat_type: string;
        avg_accuracy: number;
        test_count: number;
        best_accuracy: number;
        worst_accuracy: number;
    }>;
    player_rankings: Array<{
        player_id: string;
        player_name: string;
        player_position: string | null;
        stat_type: string;
        avg_accuracy: number;
        test_count: number;
        best_accuracy: number;
        avg_sample_size: number;
    }>;
    accuracy_trends: Array<{
        test_date: string;
        avg_accuracy: number;
        test_count: number;
    }>;
    summary_stats: {
        total_tests: number;
        unique_players: number;
        avg_accuracy_by_stat: Record<string, {
            stat_type: string;
            avg_accuracy: number;
            test_count: number;
        }>;
        accuracy_distribution: {
            excellent: number;
            good: number;
            fair: number;
            poor: number;
        };
    };
}

export interface HistoricalTestingStatus {
    recent_batches: Array<{
        test_batch_id: string;
        test_type: string;
        total_tests: number;
        avg_accuracy: number;
        started_at: string;
        completed_at: string;
    }>;
    overall_stats: {
        total_tests_run: number;
        average_accuracy: number;
        best_accuracy: number;
        worst_accuracy: number;
        total_players_tested: number;
        last_test_run: string | null;
    };
    status: string;
}

export interface HistoricalTestingParams {
    stat_types?: string[];
    min_games?: number;
    test_games?: number;
    player_limit?: number;
}

export interface HistoricalResultsParams {
    stat_type?: string;
    player_id?: string;
    min_accuracy?: number;
    limit?: number;
    sort_by?: string;
    sort_order?: string;
}

export interface LeaderboardParams {
    stat_type?: string;
    limit?: number;
}

// The Odds API Interfaces
export interface OddsApiSport {
    key: string;
    group: string;
    title: string;
    description: string;
    active: boolean;
    has_outrights: boolean;
}

export interface OddsApiEvent {
    id: string;
    sport_key: string;
    sport_title: string;
    commence_time: string;
    home_team: string;
    away_team: string;
    bookmakers: OddsApiBookmaker[];
}

export interface OddsApiBookmaker {
    key: string;
    title: string;
    last_update: string;
    markets: OddsApiMarket[];
}

export interface OddsApiMarket {
    key: string;
    outcomes: OddsApiOutcome[];
}

export interface OddsApiOutcome {
    name: string;
    price: number;
    point?: number;
    description?: string;
}

export interface OddsApiPlayerProp {
    player_name: string;
    stat_type: string;
    line: number;
    event_id: string;
    commence_time: string;
    home_team: string;
    away_team: string;
    bookmakers: Array<{
        bookmaker: string;
        bookmaker_key: string;
        name: string;
        price: number;
        point?: number;
        last_update: string;
    }>;
}

export interface OddsApiUsageStats {
    requests_today: number;
    requests_this_month: number;
    monthly_limit: number;
    daily_target: number;
    monthly_usage_percent: number;
    daily_usage_percent: number;
    last_request: string | null;
    can_make_request: boolean;
    requests_remaining_today: number;
    requests_remaining_month: number;
    status: 'normal' | 'warning' | 'critical' | 'daily_limit_reached' | 'approaching_daily_limit';
}

export interface BestOddsComparison {
    event_id: string;
    home_team: string;
    away_team: string;
    commence_time: string;
    player_name?: string;
    stat_type?: string;
    line?: number;
    best_odds: Array<{
        bookmaker: string;
        odds: number;
        last_update: string;
    }>;
}
