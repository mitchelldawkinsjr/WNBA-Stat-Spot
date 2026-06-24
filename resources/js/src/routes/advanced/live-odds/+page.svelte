<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import type { OddsApiEvent, OddsApiPlayerProp, OddsApiUsageStats } from '$lib/api/client';

    let liveOdds: OddsApiEvent[] = [];
    let playerProps: OddsApiPlayerProp[] = [];
    let usageStats: OddsApiUsageStats | null = null;
    let loading = true;
    let error = '';
    let lastUpdate = '';
    let autoRefresh = true;
    let refreshInterval: number;

    // Filters
    let selectedMarkets = ['h2h', 'spreads', 'totals'];
    let selectedPropMarkets = ['player_points', 'player_rebounds', 'player_assists', 'player_threes'];
    let selectedBookmakers: string[] = [];
    let playerNameFilter = '';

    const availableMarkets = [
        { key: 'h2h', label: 'Moneyline' },
        { key: 'spreads', label: 'Point Spreads' },
        { key: 'totals', label: 'Totals (O/U)' }
    ];

    const availablePropMarkets = [
        { key: 'player_points', label: 'Player Points' },
        { key: 'player_rebounds', label: 'Player Rebounds' },
        { key: 'player_assists', label: 'Player Assists' },
        { key: 'player_threes', label: 'Player 3-Pointers' },
        { key: 'player_steals', label: 'Player Steals' },
        { key: 'player_blocks', label: 'Player Blocks' },
        { key: 'player_turnovers', label: 'Player Turnovers' },
        { key: 'player_points_rebounds', label: 'Points + Rebounds' },
        { key: 'player_points_assists', label: 'Points + Assists' },
        { key: 'player_rebounds_assists', label: 'Rebounds + Assists' },
        { key: 'player_points_rebounds_assists', label: 'Points + Rebounds + Assists' }
    ];

    const availableBookmakers = [
        'draftkings', 'fanduel', 'betmgm', 'caesars', 'pointsbet_us', 'unibet_us', 'betrivers'
    ];

    onMount(async () => {
        await loadData();
        if (autoRefresh) {
            startAutoRefresh();
        }
    });

    onDestroy(() => {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });

    async function loadData() {
        try {
            loading = true;
            error = '';

            // Initialize arrays to prevent undefined errors
            liveOdds = [];
            playerProps = [];
            usageStats = null;

            const [oddsResponse, propsResponse, statsResponse] = await Promise.allSettled([
                api.odds.getLiveOdds(),
                api.odds.getWnbaPlayerProps({
                    markets: selectedPropMarkets,
                    bookmakers: selectedBookmakers.length > 0 ? selectedBookmakers : undefined,
                    player_name: playerNameFilter || undefined
                }),
                api.odds.getUsageStats()
            ]);

            // Handle live odds response
            if (oddsResponse.status === 'fulfilled') {
                liveOdds = Array.isArray(oddsResponse.value.data) ? oddsResponse.value.data : [];
            } else {
                console.error('Failed to load live odds:', oddsResponse.reason);
            }

            // Handle player props response
            if (propsResponse.status === 'fulfilled') {
                playerProps = Array.isArray(propsResponse.value.data) ? propsResponse.value.data : [];
            } else {
                console.error('Failed to load player props:', propsResponse.reason);
            }

            // Handle usage stats response
            if (statsResponse.status === 'fulfilled') {
                usageStats = statsResponse.value.data;
            } else {
                console.error('Failed to load usage stats:', statsResponse.reason);
            }

            lastUpdate = new Date().toLocaleTimeString();

            // If all requests failed, show an error
            if (oddsResponse.status === 'rejected' && propsResponse.status === 'rejected' && statsResponse.status === 'rejected') {
                error = 'Failed to load odds data. Please check your API configuration.';
            }

        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load odds data';
            console.error('Failed to load odds:', err);

            // Ensure arrays are initialized even on error
            liveOdds = [];
            playerProps = [];
            usageStats = null;
        } finally {
            loading = false;
        }
    }

    function startAutoRefresh() {
        refreshInterval = setInterval(async () => {
            if (autoRefresh) {
                await loadData();
            }
        }, 30000); // Refresh every 30 seconds
    }

    function toggleAutoRefresh() {
        autoRefresh = !autoRefresh;
        if (autoRefresh) {
            startAutoRefresh();
        } else if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    }

    async function clearCache() {
        try {
            await api.odds.clearCache();
            await loadData();
        } catch (err) {
            console.error('Failed to clear cache:', err);
        }
    }

    function formatOdds(odds: number): string {
        return odds > 0 ? `+${odds}` : `${odds}`;
    }

    function formatTime(dateString: string): string {
        return new Date(dateString).toLocaleString();
    }

    function getOddsColor(odds: number): string {
        if (odds > 0) return 'text-success';
        if (odds < -200) return 'text-danger';
        return 'text-warning';
    }

    function getBestOdds(bookmakers: any[], market: string, outcome: string): { odds: number, bookmaker: string } | null {
        if (!Array.isArray(bookmakers)) return null;

        let bestOdds = outcome === 'over' ? -999999 : 999999;
        let bestBookmaker = '';

        for (const bookmaker of bookmakers) {
            if (!bookmaker.markets || !bookmaker.markets[market]) continue;

            for (const outcomeData of bookmaker.markets[market].outcomes || []) {
                if (outcomeData.name.toLowerCase().includes(outcome.toLowerCase())) {
                    if ((outcome === 'over' && outcomeData.price > bestOdds) ||
                        (outcome !== 'over' && outcomeData.price < bestOdds)) {
                        bestOdds = outcomeData.price;
                        bestBookmaker = bookmaker.title;
                    }
                }
            }
        }

        return bestBookmaker ? { odds: bestOdds, bookmaker: bestBookmaker } : null;
    }

    function getUsageStatusColor(status: string): string {
        switch (status) {
            case 'critical': return 'danger';
            case 'warning': return 'warning';
            case 'daily_limit_reached': return 'info';
            case 'approaching_daily_limit': return 'warning';
            default: return 'success';
        }
    }

    function getUsageAlertType(status: string): string {
        switch (status) {
            case 'critical': return 'danger';
            case 'warning': return 'warning';
            case 'daily_limit_reached': return 'info';
            case 'approaching_daily_limit': return 'warning';
            default: return 'info';
        }
    }

    function getUsageIcon(status: string): string {
        switch (status) {
            case 'critical': return 'exclamation-triangle';
            case 'warning': return 'exclamation-circle';
            case 'daily_limit_reached': return 'pause-circle';
            case 'approaching_daily_limit': return 'clock';
            default: return 'info-circle';
        }
    }

    function getUsageTitle(status: string): string {
        switch (status) {
            case 'critical': return 'Critical Usage Alert';
            case 'warning': return 'High Usage Warning';
            case 'daily_limit_reached': return 'Daily Limit Reached';
            case 'approaching_daily_limit': return 'Approaching Daily Limit';
            default: return 'Usage Notice';
        }
    }

    function getUsageMessage(stats: any): string {
        if (!stats) return '';

        switch (stats.status) {
            case 'critical':
                return `You've used ${stats.monthly_usage_percent}% of your monthly API limit. New requests are blocked to prevent overage.`;
            case 'warning':
                return `You've used ${stats.monthly_usage_percent}% of your monthly API limit. Consider reducing usage frequency.`;
            case 'daily_limit_reached':
                return `You've reached today's target of ${stats.daily_target} requests. Data will be served from cache until tomorrow.`;
            case 'approaching_daily_limit':
                return `You've used ${stats.daily_usage_percent}% of today's target. ${stats.requests_remaining_today} requests remaining.`;
            default:
                return 'API usage is within normal limits.';
        }
    }

    // Reactive statements to ensure arrays are always defined
    $: safeLiveOdds = Array.isArray(liveOdds) ? liveOdds : [];
    $: safePlayerProps = Array.isArray(playerProps) ? playerProps : [];
</script>

<svelte:head>
    <title>Live Odds | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="page-title">Live WNBA Odds</h4>
                            <p class="text-muted mb-0">Real-time betting odds powered by Tank01 (RapidAPI)</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button
                                on:click={toggleAutoRefresh}
                                class="btn btn-{autoRefresh ? 'success' : 'outline-secondary'} btn-sm"
                            >
                                <i class="fas fa-{autoRefresh ? 'pause' : 'play'} me-1"></i>
                                {autoRefresh ? 'Auto Refresh ON' : 'Auto Refresh OFF'}
                            </button>
                            <button
                                on:click={loadData}
                                class="btn btn-primary btn-sm"
                                disabled={loading}
                            >
                                {#if loading}
                                    <span class="spinner-border spinner-border-sm me-1"></span>
                                {:else}
                                    <i class="fas fa-sync me-1"></i>
                                {/if}
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {#if error}
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Notice:</strong> {error}
                        <br>
                        <small class="text-muted mt-2 d-block">
                            To use live odds, add your RapidAPI key for Tank01 WNBA to your .env file:
                            <br>
                            <code>RAPIDAPI_KEY=your_rapidapi_key_here</code>
                        </small>
                    </div>
                </div>
            </div>
        {/if}

        <!-- Enhanced Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-chart-line text-primary fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Live Games</h5>
                                <h3 class="text-primary mb-0">{safeLiveOdds.length}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-user text-success fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Player Props</h5>
                                <h3 class="text-success mb-0">{safePlayerProps.length}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-clock text-info fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Last Update</h5>
                                <h6 class="text-info mb-0">{lastUpdate || 'Never'}</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-{getUsageStatusColor(usageStats?.status)} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-database text-{getUsageStatusColor(usageStats?.status)} fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">API Usage</h5>
                                <h6 class="text-{getUsageStatusColor(usageStats?.status)} mb-0">
                                    {usageStats?.requests_today || 0}/{usageStats?.daily_target || 12} today
                                </h6>
                                <small class="text-muted">
                                    {usageStats?.requests_this_month || 0}/{usageStats?.monthly_limit || 500} this month
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Status Alert -->
        {#if usageStats?.status && usageStats.status !== 'normal'}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-{getUsageAlertType(usageStats.status)}" role="alert">
                        <i class="fas fa-{getUsageIcon(usageStats.status)} me-2"></i>
                        <strong>{getUsageTitle(usageStats.status)}:</strong> {getUsageMessage(usageStats)}
                        <div class="mt-2">
                            <div class="progress" style="height: 6px;">
                                <div
                                    class="progress-bar bg-{getUsageStatusColor(usageStats.status)}"
                                    style="width: {usageStats.monthly_usage_percent}%"
                                ></div>
                            </div>
                            <small class="text-muted">
                                Monthly usage: {usageStats.monthly_usage_percent}%
                                ({usageStats.requests_remaining_month} requests remaining)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        {/if}

        <!-- Live Game Odds -->
        {#if safeLiveOdds.length > 0}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-basketball-ball text-primary me-2"></i>Live Game Odds
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                {#each safeLiveOdds as game}
                                    <div class="col-lg-6 mb-3">
                                        <div class="card border">
                                            <div class="card-header bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0">{game.away_team} @ {game.home_team}</h6>
                                                    <small class="text-muted">{formatTime(game.commence_time)}</small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                {#if Array.isArray(game.bookmakers) && game.bookmakers.length > 0}
                                                    <!-- Moneyline -->
                                                    {@const h2hBest = getBestOdds(game.bookmakers, 'h2h', game.home_team)}
                                                    {@const h2hBestAway = getBestOdds(game.bookmakers, 'h2h', game.away_team)}
                                                    {#if h2hBest && h2hBestAway}
                                                        <div class="mb-2">
                                                            <small class="text-muted fw-bold">Moneyline (Best Odds)</small>
                                                            <div class="d-flex justify-content-between">
                                                                <span>{game.away_team}: <span class="{getOddsColor(h2hBestAway.odds)}">{formatOdds(h2hBestAway.odds)}</span></span>
                                                                <span>{game.home_team}: <span class="{getOddsColor(h2hBest.odds)}">{formatOdds(h2hBest.odds)}</span></span>
                                                            </div>
                                                        </div>
                                                    {/if}

                                                    <!-- Spreads -->
                                                    {@const spreadBest = getBestOdds(game.bookmakers, 'spreads', 'over')}
                                                    {#if spreadBest}
                                                        <div class="mb-2">
                                                            <small class="text-muted fw-bold">Point Spread</small>
                                                            <div class="text-center">
                                                                <span class="text-info">Available from {game.bookmakers.length} bookmaker(s)</span>
                                                            </div>
                                                        </div>
                                                    {/if}

                                                    <!-- Totals -->
                                                    {@const totalsBest = getBestOdds(game.bookmakers, 'totals', 'over')}
                                                    {#if totalsBest}
                                                        <div>
                                                            <small class="text-muted fw-bold">Total Points</small>
                                                            <div class="text-center">
                                                                <span class="text-info">O/U available from {game.bookmakers.length} bookmaker(s)</span>
                                                            </div>
                                                        </div>
                                                    {/if}
                                                {:else}
                                                    <div class="text-center text-muted">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        No odds available
                                                    </div>
                                                {/if}
                                            </div>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}

        <!-- Player Props -->
        {#if safePlayerProps.length > 0}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user text-success me-2"></i>Player Props
                                </h5>
                                <div class="d-flex gap-2">
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        placeholder="Filter by player name..."
                                        bind:value={playerNameFilter}
                                        on:input={loadData}
                                        style="width: 200px;"
                                    />
                                    <button on:click={clearCache} class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-trash me-1"></i>Clear Cache
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Player</th>
                                            <th>Stat</th>
                                            <th>Line</th>
                                            <th>Game</th>
                                            <th>Best Odds</th>
                                            <th>Bookmakers</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each safePlayerProps as prop}
                                            <tr>
                                                <td>
                                                    <div class="fw-medium">{prop.player_name}</div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary text-capitalize">
                                                        {prop.stat_type.replace('_', ' ')}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="fw-medium">{prop.line}</span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <small class="text-muted">{prop.away_team} @ {prop.home_team}</small>
                                                        <br>
                                                        <small class="text-muted">{formatTime(prop.commence_time)}</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    {#if Array.isArray(prop.bookmakers) && prop.bookmakers.length > 0}
                                                        {@const bestOver = prop.bookmakers.filter(b => b.name.toLowerCase().includes('over')).sort((a, b) => b.price - a.price)[0]}
                                                        {@const bestUnder = prop.bookmakers.filter(b => b.name.toLowerCase().includes('under')).sort((a, b) => a.price - b.price)[0]}
                                                        <div>
                                                            {#if bestOver}
                                                                <span class="text-success">O: {formatOdds(bestOver.price)}</span>
                                                            {/if}
                                                            {#if bestUnder}
                                                                <span class="text-danger ms-2">U: {formatOdds(bestUnder.price)}</span>
                                                            {/if}
                                                        </div>
                                                    {:else}
                                                        <span class="text-muted">No odds</span>
                                                    {/if}
                                                </td>
                                                <td>
                                                    <span class="badge bg-info-subtle text-info">
                                                        {Array.isArray(prop.bookmakers) ? prop.bookmakers.length : 0} available
                                                    </span>
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {:else if !loading}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-info-circle text-muted fs-48 mb-3"></i>
                            <h5 class="text-muted">No live odds available</h5>
                            <p class="text-muted mb-3">This could be because:</p>
                            <ul class="list-unstyled text-muted">
                                <li>• No WNBA games are currently scheduled</li>
                                <li>• Your RapidAPI key is not configured</li>
                                <li>• The API service is temporarily unavailable</li>
                            </ul>
                            <p class="text-muted mb-0">
                                <small>
                                    To configure your API key, add <code>ODDS_API_KEY=your_key</code> to your .env file
                                    <br>
                                    Get your API key at <a href="https://rapidapi.com/tank01/api/tank01-wnba-live-in-game-real-time-statistics-wnba" target="_blank">Tank01 WNBA on RapidAPI</a>
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        {/if}

        {#if loading}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 mb-0">Loading live odds...</p>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>
