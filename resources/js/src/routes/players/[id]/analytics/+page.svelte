<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { playerAnalytics, gameStatsChartData, shootingEfficiencyData, homeAwayComparison } from '$lib/stores/playerAnalytics';
    import PlayerStatsChart from '$lib/components/charts/PlayerStatsChart.svelte';
    import ShootingEfficiencyChart from '$lib/components/charts/ShootingEfficiencyChart.svelte';
    import HomeAwayComparisonChart from '$lib/components/charts/HomeAwayComparisonChart.svelte';
    import LoadingSpinner from '$lib/components/LoadingSpinner.svelte';
    import ErrorMessage from '$lib/components/ErrorMessage.svelte';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import { api } from '$lib/api/client';

    const urlPlayerId = $page.params.id;
    let playerInfo: any = null;
    let analyticsSeason = 2026;
    let loading = true;
    let error: string | null = null;
    let chartStat: 'points' | 'rebounds' | 'assists' = 'points';

    const chartStatLabels: Record<typeof chartStat, string> = {
        points: 'Points per Game',
        rebounds: 'Rebounds per Game',
        assists: 'Assists per Game',
    };

    // Transform game stats data for chart component
    $: chartData = $gameStatsChartData.map(game => ({
        date: game.date,
        value: game[chartStat] ?? 0,
    }));

    async function loadAnalytics(season = analyticsSeason) {
        if (!playerInfo?.athlete_id) {
            return;
        }

        playerAnalytics.reset();
        const hasAnalytics = await playerAnalytics.fetchAnalytics(playerInfo.athlete_id, { season });

        if (!hasAnalytics) {
            const gamelog = await api.players.getGamelog(playerInfo.athlete_id, { season, last_n_games: 50 });
            if (gamelog.success && gamelog.data?.games?.length) {
                playerAnalytics.setFromGamelog(gamelog.data.games);
            }
        }
    }

    async function changeAnalyticsSeason(season: number) {
        analyticsSeason = season;
        loading = true;
        try {
            await loadAnalytics(season);
        } finally {
            loading = false;
        }
    }

    onMount(async () => {
        try {
            loading = true;
            error = null;

            const playerResponse = await api.players.getById(urlPlayerId);
            playerInfo = playerResponse.data;

            if (playerInfo?.athlete_id) {
                await loadAnalytics(analyticsSeason);
            } else {
                throw new Error('Player ID not found');
            }

        } catch (err) {
            console.error('Error loading player analytics:', err);
            error = err instanceof Error ? err.message : 'Failed to load player data';
        } finally {
            loading = false;
        }
    });
</script>

<svelte:head>
    <title>{playerInfo?.athlete_display_name || 'Player'} Analytics | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        {#if loading || $playerAnalytics.loading}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 mb-0">Loading player analytics...</p>
                        </div>
                    </div>
                </div>
            </div>
        {:else if error || $playerAnalytics.error}
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> {error || $playerAnalytics.error || 'Unknown error'}
                    </div>
                </div>
            </div>
        {:else if playerInfo}
            <!-- Page Title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h4 class="page-title">Player Analytics</h4>
                            <p class="text-muted mb-0">Comprehensive performance analysis and statistics</p>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Season">
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                class:active={analyticsSeason === 2026}
                                disabled={loading || $playerAnalytics.loading}
                                on:click={() => changeAnalyticsSeason(2026)}
                            >2026</button>
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                class:active={analyticsSeason === 2025}
                                disabled={loading || $playerAnalytics.loading}
                                on:click={() => changeAnalyticsSeason(2025)}
                            >2025</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Player Header Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                {#if playerInfo.athlete_headshot_href}
                                    <div class="avatar-lg me-3">
                                        <img
                                            src={playerInfo.athlete_headshot_href}
                                            alt={playerInfo.athlete_display_name}
                                            class="avatar-lg rounded-circle"
                                        />
                                    </div>
                                {/if}
                                <div class="flex-grow-1">
                                    <h3 class="mb-1">{playerInfo.athlete_display_name}</h3>
                                    <p class="text-muted mb-0">
                                        <span class="badge bg-primary me-2">#{playerInfo.athlete_jersey}</span>
                                        {playerInfo.athlete_position_name}
                                    </p>
                                </div>
                                <div class="text-end">
                                    <a href="/players/{urlPlayerId}" class="btn btn-outline-primary">
                                        <i class="fas fa-arrow-left me-1"></i>
                                        Back to Player
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Grid -->
            <div class="row">
                <!-- Shooting Statistics (moved up) -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-percentage me-2"></i>
                                Shooting Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            {#if $playerAnalytics.shootingEfficiency}
                                <div class="row g-3">
                                    {#each Object.entries($playerAnalytics.shootingEfficiency) as [stat, value]}
                                        {#if typeof value === 'number'}
                                            <div class="col-md-6">
                                                <div class="bg-light p-3 rounded">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="text-muted small text-capitalize">{stat.replace(/_/g, ' ')}</span>
                                                        <span class="fw-bold">
                                                            {value.toFixed(1)}{#if stat.includes('percentage')}%{/if}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        {/if}
                                    {/each}
                                </div>
                            {:else}
                                <div class="text-center py-4">
                                    <i class="fas fa-percentage text-muted fs-48 mb-3"></i>
                                    <p class="text-muted mb-0">No shooting efficiency data available</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Home vs Away Statistics (moved up) -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                Home vs Away Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            {#if $playerAnalytics.homeAwayPerformance}
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="border-end pe-3">
                                            <h6 class="text-success mb-3">
                                                <i class="fas fa-home me-1"></i>
                                                Home ({$playerAnalytics.homeAwayPerformance.home.games} games)
                                            </h6>
                                            <div class="space-y-2">
                                                {#each Object.entries($playerAnalytics.homeAwayPerformance.home.stats || {}) as [stat, value]}
                                                    {#if typeof value === 'number' && ['points', 'rebounds', 'assists'].includes(stat)}
                                                        <div class="d-flex justify-content-between mb-2">
                                                            <span class="text-muted small text-capitalize">{stat}:</span>
                                                            <span class="fw-bold">{value.toFixed(1)}</span>
                                                        </div>
                                                    {/if}
                                                {/each}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ps-3">
                                            <h6 class="text-primary mb-3">
                                                <i class="fas fa-plane me-1"></i>
                                                Away ({$playerAnalytics.homeAwayPerformance.away.games} games)
                                            </h6>
                                            <div class="space-y-2">
                                                {#each Object.entries($playerAnalytics.homeAwayPerformance.away.stats || {}) as [stat, value]}
                                                    {#if typeof value === 'number' && ['points', 'rebounds', 'assists'].includes(stat)}
                                                        <div class="d-flex justify-content-between mb-2">
                                                            <span class="text-muted small text-capitalize">{stat}:</span>
                                                            <span class="fw-bold">{value.toFixed(1)}</span>
                                                        </div>
                                                    {/if}
                                                {/each}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            {:else}
                                <div class="text-center py-4">
                                    <i class="fas fa-map-marker-alt text-muted fs-48 mb-3"></i>
                                    <p class="text-muted mb-0">No home/away performance data available</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Game Statistics Chart -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Game Statistics
                                </h5>
                                <small class="text-muted">Per-game trend for selected stat</small>
                            </div>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Chart stat">
                                <button type="button" class="btn btn-outline-primary" class:active={chartStat === 'points'} on:click={() => chartStat = 'points'}>PTS</button>
                                <button type="button" class="btn btn-outline-primary" class:active={chartStat === 'rebounds'} on:click={() => chartStat = 'rebounds'}>REB</button>
                                <button type="button" class="btn btn-outline-primary" class:active={chartStat === 'assists'} on:click={() => chartStat = 'assists'}>AST</button>
                            </div>
                        </div>
                        <div class="card-body">
                            {#if chartData.length > 0}
                                <PlayerStatsChart data={chartData} statName={chartStatLabels[chartStat]} />
                            {:else}
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-line text-muted fs-48 mb-3"></i>
                                    <p class="text-muted mb-0">No game statistics available</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Shooting Efficiency Chart -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bullseye me-2"></i>
                                Shooting Efficiency
                            </h5>
                        </div>
                        <div class="card-body">
                            {#if $shootingEfficiencyData}
                                <ShootingEfficiencyChart data={$shootingEfficiencyData} />
                            {:else}
                                <div class="text-center py-4">
                                    <i class="fas fa-bullseye text-muted fs-48 mb-3"></i>
                                    <p class="text-muted mb-0">No shooting efficiency data available</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Home vs Away Comparison Chart -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-home me-2"></i>
                                Home vs Away Performance
                            </h5>
                        </div>
                        <div class="card-body">
                            {#if $homeAwayComparison}
                                <HomeAwayComparisonChart data={$homeAwayComparison} />
                            {:else}
                                <div class="text-center py-4">
                                    <i class="fas fa-home text-muted fs-48 mb-3"></i>
                                    <p class="text-muted mb-0">No home/away comparison data available</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Recent Form -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-trending-up me-2"></i>
                                Recent Form
                            </h5>
                        </div>
                        <div class="card-body">
                            {#if $playerAnalytics.recentForm && $playerAnalytics.recentForm.averages}
                                <div class="mb-3">
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-calendar me-1"></i>
                                        Last {$playerAnalytics.recentForm.games_analyzed} games
                                        {#if $playerAnalytics.recentForm.game_log && $playerAnalytics.recentForm.game_log.length > 0}
                                            ({$playerAnalytics.recentForm.game_log[$playerAnalytics.recentForm.game_log.length - 1].date} to {$playerAnalytics.recentForm.game_log[0].date})
                                        {/if}
                                    </p>
                                </div>
                                <div class="row g-3">
                                    {#each Object.entries($playerAnalytics.recentForm.averages) as [stat, value]}
                                        <div class="col-md-6">
                                            <div class="bg-light p-3 rounded">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-muted small text-capitalize">{stat.replace(/_/g, ' ')}</span>
                                                    <span class="fw-bold">
                                                        {typeof value === 'number' ? value.toFixed(1) : value}
                                                        {#if stat.includes('pct')}%{/if}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            {:else}
                                <div class="text-center py-4">
                                    <i class="fas fa-trending-up text-muted fs-48 mb-3"></i>
                                    <p class="text-muted mb-0">No recent form data available</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {:else}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-user-slash text-muted fs-48 mb-3"></i>
                            <h5 class="text-muted">Player not found</h5>
                            <p class="text-muted mb-0">The requested player could not be found.</p>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>
