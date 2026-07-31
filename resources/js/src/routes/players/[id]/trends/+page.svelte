<script lang="ts">
    import { onDestroy, onMount } from 'svelte';
    import {
        playerAnalytics,
        gameStatsChartData,
        shootingEfficiencyData,
        homeAwayComparison,
    } from '$lib/stores/playerAnalytics';
    import { playerProfile } from '$lib/stores/playerProfile';
    import { api } from '$lib/api/client';
    import { pageLoading, trackPageLoad } from '$lib/stores/pageLoading';
    import PlayerStatsChart from '$lib/components/charts/PlayerStatsChart.svelte';
    import ShootingEfficiencyChart from '$lib/components/charts/ShootingEfficiencyChart.svelte';
    import HomeAwayComparisonChart from '$lib/components/charts/HomeAwayComparisonChart.svelte';
    import PlayerHitRateCard from '$lib/components/PlayerHitRateCard.svelte';
    import { page } from '$app/stores';

    let chartStat: 'points' | 'rebounds' | 'assists' = 'points';
    let loading = false;
    let error: string | null = null;
    let loadedKey = '';

    const doneLoading = trackPageLoad();
    onDestroy(doneLoading);

    const chartStatLabels = {
        points: 'Points per Game',
        rebounds: 'Rebounds per Game',
        assists: 'Assists per Game',
    } as const;

    $: profile = $playerProfile;
    $: playerId = $page.params.id;
    $: season = profile.season;
    $: athleteId = profile.player?.athlete_id;
    $: chartData = $gameStatsChartData.map((game) => ({
        date: game.date,
        value: game[chartStat] ?? 0,
    }));

    async function loadAnalytics() {
        if (!athleteId) return;
        const key = `${athleteId}:${season}`;
        if (key === loadedKey) return;
        const isInitial = loadedKey === '';
        if (!isInitial) pageLoading.start();
        loadedKey = key;

        loading = true;
        error = null;
        playerAnalytics.reset();
        try {
            const hasAnalytics = await playerAnalytics.fetchAnalytics(athleteId, { season });
            if (!hasAnalytics) {
                const gamelog = await api.players.getGamelog(athleteId, { season, last_n_games: 50 });
                if (gamelog.success && gamelog.data?.games?.length) {
                    playerAnalytics.setFromGamelog(gamelog.data.games);
                }
            }
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load analytics';
        } finally {
            loading = false;
            if (isInitial) doneLoading();
            else pageLoading.stop();
        }
    }

    onMount(loadAnalytics);
    $: if (athleteId && season) loadAnalytics();
</script>

<svelte:head>
    <title>{profile.player?.athlete_display_name || 'Player'} Trends | WNBA Stat Spot</title>
</svelte:head>

{#if loading || $playerAnalytics.loading}
    <!-- Global BrandLoadingScreen covers the viewport -->
{:else if error || $playerAnalytics.error}
    <div class="alert alert-danger" role="alert">
        <strong>Error:</strong> {error || $playerAnalytics.error}
    </div>
{:else}
    <div class="row g-3">
        {#if athleteId}
            <div class="col-12">
                <PlayerHitRateCard
                    playerId={athleteId}
                    {season}
                    seasonStats={profile.seasonStats}
                />
            </div>
        {/if}

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Shooting statistics</h5>
                </div>
                <div class="card-body">
                    {#if $playerAnalytics.shootingEfficiency}
                        <div class="row g-3">
                            {#each Object.entries($playerAnalytics.shootingEfficiency) as [stat, value]}
                                {#if typeof value === 'number'}
                                    <div class="col-md-6">
                                        <div class="bg-light p-3 rounded d-flex justify-content-between">
                                            <span class="text-muted small text-capitalize">{stat.replace(/_/g, ' ')}</span>
                                            <span class="fw-bold">
                                                {value.toFixed(1)}{#if stat.includes('percentage')}%{/if}
                                            </span>
                                        </div>
                                    </div>
                                {/if}
                            {/each}
                        </div>
                    {:else}
                        <p class="text-muted mb-0 text-center py-3">No shooting efficiency data</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Home vs away</h5>
                </div>
                <div class="card-body">
                    {#if $playerAnalytics.homeAwayPerformance}
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <h6 class="text-success mb-3">
                                    Home ({$playerAnalytics.homeAwayPerformance.home.games} games)
                                </h6>
                                {#each Object.entries($playerAnalytics.homeAwayPerformance.home.stats || {}) as [stat, value]}
                                    {#if typeof value === 'number' && ['points', 'rebounds', 'assists'].includes(stat)}
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small text-capitalize">{stat}</span>
                                            <span class="fw-bold">{value.toFixed(1)}</span>
                                        </div>
                                    {/if}
                                {/each}
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">
                                    Away ({$playerAnalytics.homeAwayPerformance.away.games} games)
                                </h6>
                                {#each Object.entries($playerAnalytics.homeAwayPerformance.away.stats || {}) as [stat, value]}
                                    {#if typeof value === 'number' && ['points', 'rebounds', 'assists'].includes(stat)}
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small text-capitalize">{stat}</span>
                                            <span class="fw-bold">{value.toFixed(1)}</span>
                                        </div>
                                    {/if}
                                {/each}
                            </div>
                        </div>
                    {:else}
                        <p class="text-muted mb-0 text-center py-3">No home/away data</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-0">Game trend</h5>
                        <small class="text-muted">Per-game for selected stat</small>
                    </div>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" class:active={chartStat === 'points'} on:click={() => (chartStat = 'points')}>PTS</button>
                        <button type="button" class="btn btn-outline-primary" class:active={chartStat === 'rebounds'} on:click={() => (chartStat = 'rebounds')}>REB</button>
                        <button type="button" class="btn btn-outline-primary" class:active={chartStat === 'assists'} on:click={() => (chartStat = 'assists')}>AST</button>
                    </div>
                </div>
                <div class="card-body">
                    {#if chartData.length > 0}
                        <PlayerStatsChart data={chartData} statName={chartStatLabels[chartStat]} />
                    {:else}
                        <p class="text-muted mb-0 text-center py-3">No game statistics</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Shooting efficiency</h5>
                </div>
                <div class="card-body">
                    {#if $shootingEfficiencyData}
                        <ShootingEfficiencyChart data={$shootingEfficiencyData} />
                    {:else}
                        <p class="text-muted mb-0 text-center py-3">No shooting chart data</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Home vs away chart</h5>
                </div>
                <div class="card-body">
                    {#if $homeAwayComparison}
                        <HomeAwayComparisonChart data={$homeAwayComparison} />
                    {:else}
                        <p class="text-muted mb-0 text-center py-3">No comparison chart data</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent form</h5>
                </div>
                <div class="card-body">
                    {#if $playerAnalytics.recentForm?.averages}
                        <p class="text-muted small mb-3">
                            Last {$playerAnalytics.recentForm.games_analyzed} games
                        </p>
                        <div class="row g-3">
                            {#each Object.entries($playerAnalytics.recentForm.averages) as [stat, value]}
                                <div class="col-md-6">
                                    <div class="bg-light p-3 rounded d-flex justify-content-between">
                                        <span class="text-muted small text-capitalize">{stat.replace(/_/g, ' ')}</span>
                                        <span class="fw-bold">
                                            {typeof value === 'number' ? value.toFixed(1) : value}
                                            {#if stat.includes('pct')}%{/if}
                                        </span>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {:else}
                        <p class="text-muted mb-0 text-center py-3">No recent form data</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>
{/if}
