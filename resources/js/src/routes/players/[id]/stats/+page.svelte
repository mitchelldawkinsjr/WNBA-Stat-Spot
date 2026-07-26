<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api, type AggregatedPlayerData } from '$lib/api/client';
    import { playerProfile } from '$lib/stores/playerProfile';

    let aggregatedData: AggregatedPlayerData | null = null;
    let loading = true;
    let error = '';
    let showAdvanced = true;
    let showSituational = false;
    let loadedKey = '';

    $: profile = $playerProfile;
    $: playerId = $page.params.id;
    $: season = profile.season;

    async function loadStats() {
        if (!playerId) return;
        const key = `${playerId}:${season}`;
        if (key === loadedKey) return;
        loadedKey = key;

        loading = true;
        error = '';
        try {
            const dataResponse = await api.players.getAggregatedData(playerId, {
                season,
                last_n_games: 20,
            });
            if (!dataResponse.success || !dataResponse.data) {
                throw new Error(dataResponse.message || 'Failed to load aggregated player data');
            }
            aggregatedData = dataResponse.data;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load player data';
            aggregatedData = null;
        } finally {
            loading = false;
        }
    }

    onMount(loadStats);
    $: if (playerId && season) loadStats();

    function formatNumber(value: number): string {
        return Number.isFinite(value) ? value.toFixed(1) : '—';
    }

    function formatPercentage(value: number): string {
        return Number.isFinite(value) ? `${value.toFixed(1)}%` : '—';
    }
</script>

<svelte:head>
    <title>{profile.player?.athlete_display_name || 'Player'} Stats | WNBA Stat Spot</title>
</svelte:head>

{#if loading}
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Loading season stats...</p>
        </div>
    </div>
{:else if error}
    <div class="alert alert-danger" role="alert"><strong>Error:</strong> {error}</div>
{:else if aggregatedData}
    {#if aggregatedData.season_stats.games_played === 0}
        <div class="alert alert-info" role="alert">
            No game statistics found for {season}. Confirm stats import has run and the season matches your data.
        </div>
    {/if}

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Season statistics</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <h6 class="text-muted mb-3">Averages</h6>
                    {#each Object.entries(aggregatedData.season_stats.averages) as [stat, value]}
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-capitalize">{stat.replace(/_/g, ' ')}</span>
                            <strong>{formatNumber(value)}</strong>
                        </div>
                    {/each}
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted mb-3">Shooting %</h6>
                    {#each Object.entries(aggregatedData.season_stats.percentages) as [stat, value]}
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-capitalize">{stat.replace(/_/g, ' ').replace('pct', '%')}</span>
                            <strong>{formatPercentage(value)}</strong>
                        </div>
                    {/each}
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted mb-3">Consistency</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Points</span>
                        <strong>{formatPercentage(aggregatedData.consistency_metrics.points_consistency)}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Rebounds</span>
                        <strong>{formatPercentage(aggregatedData.consistency_metrics.rebounds_consistency)}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Assists</span>
                        <strong>{formatPercentage(aggregatedData.consistency_metrics.assists_consistency)}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Overall</span>
                        <strong>{formatPercentage(aggregatedData.consistency_metrics.overall_consistency)}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Advanced metrics</h5>
            <button class="btn btn-sm btn-outline-secondary" type="button" on:click={() => (showAdvanced = !showAdvanced)}>
                {showAdvanced ? 'Hide' : 'Show'}
            </button>
        </div>
        {#if showAdvanced}
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatPercentage(aggregatedData.advanced_metrics.usage_rate)}</div>
                            <div class="text-muted small">Usage rate</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatPercentage(aggregatedData.advanced_metrics.true_shooting_pct)}</div>
                            <div class="text-muted small">True shooting %</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatPercentage(aggregatedData.advanced_metrics.effective_fg_pct)}</div>
                            <div class="text-muted small">Effective FG %</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatNumber(aggregatedData.advanced_metrics.assist_turnover_ratio)}</div>
                            <div class="text-muted small">AST/TO</div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Performance trends</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                {#each Object.entries(aggregatedData.performance_trends) as [stat, trend]}
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="mb-1 {trend > 0 ? 'text-success' : trend < 0 ? 'text-danger' : 'text-muted'}">
                                {trend > 0 ? '↗' : trend < 0 ? '↘' : '→'} {Math.abs(trend).toFixed(3)}
                            </h4>
                            <small class="text-muted">{stat.replace(/_/g, ' ').toUpperCase()}</small>
                        </div>
                    </div>
                {/each}
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Situational performance</h5>
            <button class="btn btn-sm btn-outline-secondary" type="button" on:click={() => (showSituational = !showSituational)}>
                {showSituational ? 'Hide' : 'Show'}
            </button>
        </div>
        {#if showSituational}
            <div class="card-body">
                <div class="row g-4 mb-2">
                    <div class="col-md-6">
                        <h6 class="text-success"><i class="fas fa-home me-1"></i> Home</h6>
                        {#if aggregatedData.situational_stats.home && Object.keys(aggregatedData.situational_stats.home).length > 0}
                            {#each Object.entries(aggregatedData.situational_stats.home) as [stat, value]}
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-capitalize">{stat.replace(/_/g, ' ')}</span>
                                    <strong>{typeof value === 'number' ? formatNumber(value) : value}</strong>
                                </div>
                            {/each}
                        {:else}
                            <p class="text-muted small mb-0">No home data</p>
                        {/if}
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary"><i class="fas fa-plane me-1"></i> Away</h6>
                        {#if aggregatedData.situational_stats.away && Object.keys(aggregatedData.situational_stats.away).length > 0}
                            {#each Object.entries(aggregatedData.situational_stats.away) as [stat, value]}
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-capitalize">{stat.replace(/_/g, ' ')}</span>
                                    <strong>{typeof value === 'number' ? formatNumber(value) : value}</strong>
                                </div>
                            {/each}
                        {:else}
                            <p class="text-muted small mb-0">No away data</p>
                        {/if}
                    </div>
                </div>
            </div>
        {/if}
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Data quality</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="fw-bold fs-4 text-info">{aggregatedData.data_quality.sample_size}</div>
                    <div class="text-muted small">Sample size</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-bold fs-4 text-success">{formatPercentage(aggregatedData.data_quality.data_completeness * 100)}</div>
                    <div class="text-muted small">Completeness</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-bold fs-4 text-warning">{formatPercentage(aggregatedData.data_quality.recency_score * 100)}</div>
                    <div class="text-muted small">Recency</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-bold fs-4 text-primary">{formatPercentage(aggregatedData.data_quality.quality_score * 100)}</div>
                    <div class="text-muted small">Overall</div>
                </div>
            </div>
        </div>
    </div>
{/if}
