<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import { Card, CardBody, CardHeader, CardTitle } from '@sveltestrap/sveltestrap';
    import LoadingError from './LoadingError.svelte';
    import BaseChart from './charts/BaseChart.svelte';
    import type { ChartData } from 'chart.js';

    interface TrendingPlayer {
        id: number;
        name: string;
        position: string;
        team: string;
        headshot?: string;
        recent_performance: {
            last_game: {
                points: number;
                rebounds: number;
                assists: number;
                date: string;
            };
            trend: 'up' | 'down' | 'stable';
            percentage_change: number;
        };
        prop_opportunities: number;
    }

    interface TodaysGame {
        id: string;
        home_team: string;
        away_team: string;
        game_time: string;
        prediction_confidence: number;
        top_prop: {
            player: string;
            stat: string;
            line: number;
            recommendation: 'over' | 'under';
            confidence: number;
        };
    }

    interface QuickStat {
        label: string;
        value: string;
        change: number;
        trend: 'up' | 'down' | 'stable';
        color: string;
    }

    let loading = true;
    let error: string | null = null;
    let trendingPlayers: TrendingPlayer[] = [];
    let todaysGames: TodaysGame[] = [];
    let quickStats: QuickStat[] = [];
    let userPreferences = {
        favoriteTeams: ['Las Vegas Aces', 'New York Liberty'],
        favoritePlayers: ['A\'ja Wilson', 'Sabrina Ionescu'],
        interests: ['predictions', 'props', 'analytics']
    };

    // Mock data for demonstration
    const mockTrendingPlayers: TrendingPlayer[] = [
        {
            id: 1,
            name: "A'ja Wilson",
            position: "C",
            team: "Las Vegas Aces",
            recent_performance: {
                last_game: { points: 28, rebounds: 12, assists: 4, date: "2024-06-15" },
                trend: "up",
                percentage_change: 15.3
            },
            prop_opportunities: 3
        },
        {
            id: 2,
            name: "Sabrina Ionescu",
            position: "PG", 
            team: "New York Liberty",
            recent_performance: {
                last_game: { points: 22, rebounds: 5, assists: 9, date: "2024-06-15" },
                trend: "up",
                percentage_change: 8.7
            },
            prop_opportunities: 2
        },
        {
            id: 3,
            name: "Breanna Stewart",
            position: "PF",
            team: "New York Liberty",
            recent_performance: {
                last_game: { points: 19, rebounds: 8, assists: 3, date: "2024-06-15" },
                trend: "stable",
                percentage_change: 2.1
            },
            prop_opportunities: 1
        }
    ];

    const mockTodaysGames: TodaysGame[] = [
        {
            id: "game1",
            home_team: "Las Vegas Aces",
            away_team: "New York Liberty",
            game_time: "8:00 PM ET",
            prediction_confidence: 73,
            top_prop: {
                player: "A'ja Wilson",
                stat: "Points",
                line: 24.5,
                recommendation: "over",
                confidence: 78
            }
        },
        {
            id: "game2",
            home_team: "Seattle Storm",
            away_team: "Phoenix Mercury",
            game_time: "10:00 PM ET",
            prediction_confidence: 65,
            top_prop: {
                player: "Jewell Loyd",
                stat: "3-Pointers",
                line: 2.5,
                recommendation: "over",
                confidence: 71
            }
        }
    ];

    const mockQuickStats: QuickStat[] = [
        { label: "Prop Accuracy", value: "73.2%", change: 2.1, trend: "up", color: "#10b981" },
        { label: "Model ROI", value: "+12.4%", change: 1.8, trend: "up", color: "#3b82f6" },
        { label: "Games Analyzed", value: "156", change: 0, trend: "stable", color: "#6b7280" },
        { label: "Active Props", value: "23", change: -2, trend: "down", color: "#f59e0b" }
    ];

    onMount(async () => {
        try {
            // In a real app, these would be actual API calls
            await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate loading
            
            trendingPlayers = mockTrendingPlayers;
            todaysGames = mockTodaysGames;
            quickStats = mockQuickStats;
        } catch (e) {
            error = e instanceof Error ? e.message : 'Failed to load dashboard data';
        } finally {
            loading = false;
        }
    });

    // Chart data for performance trends
    $: performanceChartData = {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [
            {
                label: 'Prediction Accuracy',
                data: [68, 71, 69, 75, 73, 78, 73],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'ROI %',
                data: [8.2, 9.1, 8.7, 11.2, 10.8, 12.1, 12.4],
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    } as ChartData<'line'>;

    function getTrendIcon(trend: string): string {
        switch (trend) {
            case 'up': return 'mdi-trending-up';
            case 'down': return 'mdi-trending-down';
            default: return 'mdi-trending-neutral';
        }
    }

    function getTrendColor(trend: string): string {
        switch (trend) {
            case 'up': return 'text-success';
            case 'down': return 'text-danger';
            default: return 'text-muted';
        }
    }

    function getConfidenceColor(confidence: number): string {
        if (confidence >= 75) return 'bg-success';
        if (confidence >= 60) return 'bg-warning';
        return 'bg-danger';
    }
</script>

<div class="personalized-dashboard">
    <LoadingError {loading} {error} loadingText="Loading your dashboard..." />

    {#if !loading && !error}
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="welcome-banner">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">Welcome back!</h2>
                            <p class="text-muted mb-0">Here's what's happening with your WNBA insights today.</p>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">Today</div>
                            <div class="fw-bold">{new Date().toLocaleDateString()}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            {#each quickStats as stat}
                <div class="col-6 col-lg-3 mb-3">
                    <Card class="stat-card h-100">
                        <CardBody>
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-1">{stat.label}</h6>
                                    <h4 class="mb-0" style="color: {stat.color}">{stat.value}</h4>
                                </div>
                                <div class="stat-trend {getTrendColor(stat.trend)}">
                                    <i class="mdi {getTrendIcon(stat.trend)}"></i>
                                </div>
                            </div>
                            {#if stat.change !== 0}
                                <div class="mt-2">
                                    <small class={getTrendColor(stat.trend)}>
                                        {stat.change > 0 ? '+' : ''}{stat.change.toFixed(1)}% from last week
                                    </small>
                                </div>
                            {/if}
                        </CardBody>
                    </Card>
                </div>
            {/each}
        </div>

        <div class="row">
            <!-- Today's Games -->
            <div class="col-lg-8 mb-4">
                <Card>
                    <CardHeader>
                        <div class="d-flex justify-content-between align-items-center">
                            <CardTitle class="mb-0">
                                <i class="mdi mdi-basketball me-2"></i>Today's Games
                            </CardTitle>
                            <a href="/games" class="btn btn-outline-primary btn-sm">View All</a>
                        </div>
                    </CardHeader>
                    <CardBody>
                        {#each todaysGames as game}
                            <div class="game-card mb-3">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="game-matchup">
                                            <div class="teams">
                                                <span class="away-team">{game.away_team}</span>
                                                <span class="vs">@</span>
                                                <span class="home-team">{game.home_team}</span>
                                            </div>
                                            <div class="game-time text-muted">{game.game_time}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="prediction-info">
                                            <div class="confidence-badge">
                                                <span class="badge {getConfidenceColor(game.prediction_confidence)}">
                                                    {game.prediction_confidence}% confidence
                                                </span>
                                            </div>
                                            <div class="top-prop mt-2">
                                                <strong>Top Prop:</strong> {game.top_prop.player} {game.top_prop.stat} 
                                                <span class="recommendation-badge">
                                                    {game.top_prop.recommendation.toUpperCase()} {game.top_prop.line}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </CardBody>
                </Card>
            </div>

            <!-- Trending Players -->
            <div class="col-lg-4 mb-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="mb-0">
                            <i class="mdi mdi-trending-up me-2"></i>Trending Players
                        </CardTitle>
                    </CardHeader>
                    <CardBody>
                        {#each trendingPlayers as player}
                            <div class="trending-player mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="player-info flex-grow-1">
                                        <div class="player-name fw-bold">{player.name}</div>
                                        <div class="player-details text-muted small">
                                            {player.position} • {player.team}
                                        </div>
                                        <div class="recent-stats mt-1">
                                            <small>
                                                Last: {player.recent_performance.last_game.points}pts, 
                                                {player.recent_performance.last_game.rebounds}reb, 
                                                {player.recent_performance.last_game.assists}ast
                                            </small>
                                        </div>
                                    </div>
                                    <div class="trend-indicator text-end">
                                        <div class={getTrendColor(player.recent_performance.trend)}>
                                            <i class="mdi {getTrendIcon(player.recent_performance.trend)}"></i>
                                            {player.recent_performance.percentage_change.toFixed(1)}%
                                        </div>
                                        <div class="prop-count">
                                            <span class="badge bg-primary">{player.prop_opportunities} props</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        {/each}
                        <div class="text-center mt-3">
                            <a href="/players" class="btn btn-outline-primary btn-sm">View All Players</a>
                        </div>
                    </CardBody>
                </Card>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <Card>
                    <CardHeader>
                        <CardTitle class="mb-0">
                            <i class="mdi mdi-chart-line me-2"></i>Your Performance This Week
                        </CardTitle>
                    </CardHeader>
                    <CardBody>
                        <BaseChart
                            chartType="line"
                            data={performanceChartData}
                            height="300px"
                        />
                    </CardBody>
                </Card>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <Card>
                    <CardHeader>
                        <CardTitle class="mb-0">
                            <i class="mdi mdi-lightning-bolt me-2"></i>Quick Actions
                        </CardTitle>
                    </CardHeader>
                    <CardBody>
                        <div class="row">
                            <div class="col-md-3 col-6 mb-2">
                                <a href="/reports/todays-props" class="quick-action-btn">
                                    <i class="mdi mdi-target"></i>
                                    <span>Today's Props</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <a href="/compare/players" class="quick-action-btn">
                                    <i class="mdi mdi-compare"></i>
                                    <span>Compare Players</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <a href="/advanced/prop-scanner" class="quick-action-btn">
                                    <i class="mdi mdi-radar"></i>
                                    <span>Prop Scanner</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <a href="/advanced/betting-analytics" class="quick-action-btn">
                                    <i class="mdi mdi-chart-bar"></i>
                                    <span>Analytics</span>
                                </a>
                            </div>
                        </div>
                    </CardBody>
                </Card>
            </div>
        </div>
    {/if}
</div>

<style>
    .welcome-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .stat-card {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        transition: transform 0.15s ease-in-out;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .stat-trend {
        font-size: 1.25rem;
    }

    .game-card {
        padding: 1rem;
        border: 1px solid #e3e6f0;
        border-radius: 0.375rem;
        background-color: #f8f9fa;
    }

    .game-matchup .teams {
        font-weight: 600;
        font-size: 1.1rem;
    }

    .vs {
        margin: 0 0.5rem;
        color: #6c757d;
    }

    .confidence-badge .badge {
        font-size: 0.75rem;
    }

    .recommendation-badge {
        background-color: #e3f2fd;
        color: #1976d2;
        padding: 0.125rem 0.375rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 0.25rem;
    }

    .trending-player {
        padding: 0.75rem 0;
        border-bottom: 1px solid #e9ecef;
    }

    .trending-player:last-child {
        border-bottom: none;
    }

    .prop-count .badge {
        font-size: 0.7rem;
    }

    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1rem;
        text-decoration: none;
        color: #495057;
        border: 2px solid #e9ecef;
        border-radius: 0.5rem;
        transition: all 0.15s ease-in-out;
    }

    .quick-action-btn:hover {
        color: #495057;
        border-color: #667eea;
        background-color: #f8f9fb;
        transform: translateY(-2px);
    }

    .quick-action-btn i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }

    .quick-action-btn span {
        font-size: 0.875rem;
        font-weight: 500;
        text-align: center;
    }
</style>