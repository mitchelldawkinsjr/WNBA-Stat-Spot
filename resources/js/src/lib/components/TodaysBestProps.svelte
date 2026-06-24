<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';

    interface TodaysProp {
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
        odds_api_line?: number;
        odds_api_odds?: {
            over: number;
            under: number;
        };
        odds_available?: boolean;
        odds_source?: string;
        bookmakers?: {
            over: string;
            under: string;
        };
    }

    let todaysProps: TodaysProp[] = [];
    let loading = true;
    let error: string | null = null;
    let lastUpdated: string | null = null;

    onMount(async () => {
        await loadTodaysProps();
        // Refresh every 30 minutes
        setInterval(loadTodaysProps, 30 * 60 * 1000);
    });

    async function loadTodaysProps() {
        try {
            loading = true;
            error = null;

            const response = await api.wnba.predictions.getTodaysBest('America/New_York');
            todaysProps = response.data ?? [];
            lastUpdated = new Date().toLocaleTimeString('en-US', { timeZone: 'America/New_York' });
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load today\'s props';
            todaysProps = [];
            console.error('Failed to load today\'s props:', err);
        } finally {
            loading = false;
        }
    }

    function getValueColor(value: string): string {
        switch (value) {
            case 'excellent': return 'text-success';
            case 'good': return 'text-info';
            case 'fair': return 'text-warning';
            case 'poor': return 'text-danger';
            default: return 'text-muted';
        }
    }

    function getRecommendationColor(rec: string): string {
        switch (rec) {
            case 'over': return 'text-success';
            case 'under': return 'text-danger';
            case 'avoid': return 'text-muted';
            default: return 'text-muted';
        }
    }

    function formatStat(statType: string): string {
        return statType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function formatPercentage(value: number): string {
        // Handle both decimal (0.65) and percentage (65) formats
        if (value <= 1) {
            // Decimal format - multiply by 100
            return `${(value * 100).toFixed(1)}%`;
        } else {
            // Already in percentage format - just format
            return `${value.toFixed(1)}%`;
        }
    }

    function getBadgeColor(value: string): string {
        switch(value) {
            case 'excellent': return 'success';
            case 'good': return 'info';
            case 'fair': return 'warning';
            case 'poor': return 'danger';
            default: return 'secondary';
        }
    }
</script>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">
            <i class="fas fa-fire text-danger me-2"></i>Today's Best Props
        </h4>
        <div class="d-flex align-items-center">
            {#if lastUpdated}
                <small class="text-muted me-3">Updated: {lastUpdated}</small>
            {/if}
            <a
                href="/reports/todays-props"
                class="btn btn-sm btn-success me-2"
            >
                <i class="fas fa-external-link-alt me-1"></i>
                View All Props
            </a>
            <button
                class="btn btn-sm btn-outline-primary"
                on:click={loadTodaysProps}
                disabled={loading}
            >
                {#if loading}
                    <span class="spinner-border spinner-border-sm me-1"></span>
                {:else}
                    <i class="fas fa-sync-alt me-1"></i>
                {/if}
                Refresh
            </button>
        </div>
    </div>
    <div class="card-body">
        {#if loading}
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 mb-0 text-muted">Analyzing today's games...</p>
            </div>
        {:else if error}
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                {error}
            </div>
        {:else if todaysProps.length === 0}
            <div class="text-center py-4">
                <i class="fas fa-calendar-times text-muted fs-48 mb-3"></i>
                <h6 class="text-muted">No Props Available Today</h6>
                <p class="text-muted mb-0">
                    No WNBA games are scheduled for today, or all scheduled games have been completed.
                    <br>
                    <small class="text-muted">
                        Today's props are only generated for games happening today that haven't finished yet.
                        Check back tomorrow for new games and prop opportunities!
                    </small>
                </p>
                <div class="mt-3">
                    <button
                        on:click={loadTodaysProps}
                        class="btn btn-outline-primary btn-sm me-2"
                    >
                        <i class="fas fa-sync me-1"></i>
                        Check Again
                    </button>
                    <a href="/reports/predictions" class="btn btn-primary btn-sm">
                        <i class="fas fa-crystal-ball me-1"></i>
                        Use Prediction Engine
                    </a>
                </div>
            </div>
        {:else}
            <div class="row g-3">
                {#each todaysProps.slice(0, 6) as prop}
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title mb-1 fw-bold">{prop.player_name}</h6>
                                        <small class="text-muted">{prop.team_abbreviation} {prop.opponent}</small>
                                    </div>
                                    <span class="badge bg-{getBadgeColor(prop.betting_value)} rounded-pill">
                                        {prop.betting_value}
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-capitalize">{prop.stat_type}</span>
                                        <small class="text-muted">{prop.game_time}</small>
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge bg-{prop.recommendation === 'over' ? 'success' : 'danger'} me-2">
                                            {prop.recommendation.toUpperCase()} {prop.suggested_line}
                                        </span>
                                        <small class="text-muted">Predicted: {prop.predicted_value}</small>
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold text-success">{formatPercentage(prop.probability_over)}</div>
                                            <small class="text-muted">Over</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold text-danger">{formatPercentage(prop.probability_under)}</div>
                                            <small class="text-muted">Under</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Expected Value:</small>
                                        <small class="fw-bold text-{prop.expected_value > 10 ? 'success' : 'warning'}">
                                            +{prop.expected_value.toFixed(1)}%
                                        </small>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Confidence:</small>
                                        <small class="fw-bold">{formatPercentage(prop.confidence)}</small>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Recent Form:</small>
                                        <small class="fw-bold">{prop.recent_form}</small>
                                    </div>
                                </div>

                                {#if prop.odds_api_line}
                                    <div class="border-top pt-2 mt-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Market Line:</small>
                                            <small class="fw-bold">{prop.odds_api_line}</small>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">O/U Odds:</small>
                                            <small>
                                                <span class="text-success">{(prop.odds_api_odds?.over ?? 0) > 0 ? '+' : ''}{prop.odds_api_odds?.over ?? 0}</span>
                                                /
                                                <span class="text-danger">{(prop.odds_api_odds?.under ?? 0) > 0 ? '+' : ''}{prop.odds_api_odds?.under ?? 0}</span>
                                            </small>
                                        </div>
                                        {#if prop.bookmakers}
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">Bookmakers:</small>
                                                <small class="text-info">{prop.bookmakers.over} / {prop.bookmakers.under}</small>
                                            </div>
                                        {/if}
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">Source:</small>
                                            <small class="badge bg-{prop.odds_available ? 'success' : 'warning'}-subtle text-{prop.odds_available ? 'success' : 'warning'}">
                                                {prop.odds_source === 'odds_api' ? 'Live Odds' : 'Estimated'}
                                            </small>
                                        </div>
                                    </div>
                                {:else}
                                    <div class="border-top pt-2 mt-2">
                                        <small class="text-muted fst-italic">Live odds not available</small>
                                    </div>
                                {/if}

                                <div class="mt-2">
                                    <small class="text-muted">{prop.reasoning}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>

            {#if todaysProps.length > 6}
                <div class="text-center mt-3">
                    <a href="/reports/todays-props" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-search me-1"></i>
                        View All {todaysProps.length} Props
                    </a>
                </div>
            {/if}
        {/if}
    </div>
</div>
