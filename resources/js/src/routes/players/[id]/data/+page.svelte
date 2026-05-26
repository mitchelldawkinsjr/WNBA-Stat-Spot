<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import type { Player, AggregatedPlayerData } from '$lib/api/client';

    let player: Player | null = null;
    let aggregatedData: AggregatedPlayerData | null = null;
    let loading = true;
    let error = '';

    $: playerId = $page.params.id;

    onMount(async () => {
        if (!playerId) return;

        try {
            const [playerResponse, dataResponse] = await Promise.all([
                api.players.getById(playerId),
                // Same endpoint as api.wnba.data.getPlayerData; use players.* so deep `wnba.data` is never required.
                api.players.getAggregatedData(playerId, { last_n_games: 20 }),
            ]);

            player = playerResponse.data;
            if (!dataResponse.success || !dataResponse.data) {
                throw new Error(dataResponse.message || 'Failed to load aggregated player data');
            }
            aggregatedData = dataResponse.data;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load player data';
        } finally {
            loading = false;
        }
    });

    function formatNumber(value: number): string {
        return value.toFixed(1);
    }

    function formatPercentage(value: number): string {
        return value.toFixed(1) + '%';
    }
</script>

<svelte:head>
    <title>{player?.athlete_display_name || 'Player'} Comprehensive Data | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <a href="/players/{playerId}" class="btn btn-outline-primary">
                            ← Back to Player
                        </a>
                    </div>
                    <h4 class="page-title">Comprehensive Player Data</h4>
                </div>
            </div>
        </div>

        {#if loading}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 mb-0">Loading comprehensive player data...</p>
                        </div>
                    </div>
                </div>
            </div>
        {:else if error}
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <strong>Error:</strong> {error}
                    </div>
                </div>
            </div>
        {:else if player && aggregatedData}
            {#if aggregatedData.season_stats.games_played === 0}
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-info mb-0" role="alert">
                            No game statistics were found for this player for the active season. Confirm stats import has run and
                            <code>WNBA_CURRENT_SEASON</code> matches your data.
                        </div>
                    </div>
                </div>
            {/if}
            <!-- Player Info Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                        {#if player.athlete_headshot_href}
                                            <img src={player.athlete_headshot_href} alt={player.athlete_display_name} class="rounded-circle" style="width: 64px; height: 64px; object-fit: cover;" />
                                        {:else}
                                            <i class="fas fa-user text-primary fs-24"></i>
                                        {/if}
                                    </div>
                                </div>
                                <div class="col">
                                    <h3 class="mb-1">{player.athlete_display_name}</h3>
                                    <p class="text-muted mb-2">Comprehensive Data Analysis</p>
                                    <div class="row g-3">
                                        <div class="col-auto">
                                            <span class="badge bg-primary">#{player.athlete_jersey || 'N/A'}</span>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-secondary">{player.athlete_position_name || 'Position N/A'}</span>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-info">{aggregatedData.season_stats.games_played} Games</span>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-success">Quality: {formatPercentage(aggregatedData.data_quality.quality_score * 100)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Season Statistics -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Season Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <h6 class="text-muted mb-3">Averages</h6>
                                    {#each Object.entries(aggregatedData.season_stats.averages) as [stat, value]}
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-capitalize">{stat.replace('_', ' ')}</span>
                                            <strong>{formatNumber(value)}</strong>
                                        </div>
                                    {/each}
                                </div>
                                <div class="col-md-3">
                                    <h6 class="text-muted mb-3">Shooting %</h6>
                                    {#each Object.entries(aggregatedData.season_stats.percentages) as [stat, value]}
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-capitalize">{stat.replace('_', ' ').replace('pct', '%')}</span>
                                            <strong>{formatPercentage(value)}</strong>
                                        </div>
                                    {/each}
                                </div>
                                <div class="col-md-3">
                                    <h6 class="text-muted mb-3">Advanced Metrics</h6>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Usage Rate</span>
                                        <strong>{formatPercentage(aggregatedData.advanced_metrics.usage_rate)}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>True Shooting %</span>
                                        <strong>{formatPercentage(aggregatedData.advanced_metrics.true_shooting_pct)}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Effective FG %</span>
                                        <strong>{formatPercentage(aggregatedData.advanced_metrics.effective_fg_pct)}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Assist/TO Ratio</span>
                                        <strong>{formatNumber(aggregatedData.advanced_metrics.assist_turnover_ratio)}</strong>
                                    </div>
                                </div>
                                <div class="col-md-3">
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
                </div>
            </div>

            <!-- Performance Trends -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Performance Trends</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                {#each Object.entries(aggregatedData.performance_trends) as [stat, trend]}
                                    <div class="col-md-3 col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <h4 class="mb-1 {trend > 0 ? 'text-success' : trend < 0 ? 'text-danger' : 'text-muted'}">
                                                {trend > 0 ? '↗' : trend < 0 ? '↘' : '→'} {Math.abs(trend).toFixed(3)}
                                            </h4>
                                            <small class="text-muted">{stat.replace('_', ' ').toUpperCase()} TREND</small>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Situational Performance -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Situational Performance</h5>
                        </div>
                        <div class="card-body">
                            <!-- Home vs Away Performance -->
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 bg-light">
                                        <h6 class="mb-3 text-success d-flex align-items-center">
                                            <i class="fas fa-home me-2"></i>
                                            Home Performance
                                        </h6>
                                        {#if aggregatedData.situational_stats.home && Object.keys(aggregatedData.situational_stats.home).length > 0}
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <tbody>
                                                        {#each Object.entries(aggregatedData.situational_stats.home) as [stat, value]}
                                                            <tr>
                                                                <td class="border-0 fw-medium">{stat.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</td>
                                                                <td class="border-0 text-end"><strong>{typeof value === 'number' ? formatNumber(value) : value}</strong></td>
                                                            </tr>
                                                        {/each}
                                                    </tbody>
                                                </table>
                                            </div>
                                        {:else}
                                            <div class="text-center py-3">
                                                <i class="fas fa-chart-line text-muted fs-24 mb-2"></i>
                                                <p class="text-muted mb-0">No home games data available</p>
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 bg-light">
                                        <h6 class="mb-3 text-primary d-flex align-items-center">
                                            <i class="fas fa-plane me-2"></i>
                                            Away Performance
                                        </h6>
                                        {#if aggregatedData.situational_stats.away && Object.keys(aggregatedData.situational_stats.away).length > 0}
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <tbody>
                                                        {#each Object.entries(aggregatedData.situational_stats.away) as [stat, value]}
                                                            <tr>
                                                                <td class="border-0 fw-medium">{stat.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</td>
                                                                <td class="border-0 text-end"><strong>{typeof value === 'number' ? formatNumber(value) : value}</strong></td>
                                                            </tr>
                                                        {/each}
                                                    </tbody>
                                                </table>
                                            </div>
                                        {:else}
                                            <div class="text-center py-3">
                                                <i class="fas fa-chart-line text-muted fs-24 mb-2"></i>
                                                <p class="text-muted mb-0">No away games data available</p>
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            </div>

                            <!-- Other Situational Stats (only show if data exists) -->
                            {#if (aggregatedData.situational_stats.vs_strong_defense && Object.keys(aggregatedData.situational_stats.vs_strong_defense).length > 0) ||
                                 (aggregatedData.situational_stats.vs_weak_defense && Object.keys(aggregatedData.situational_stats.vs_weak_defense).length > 0) ||
                                 (aggregatedData.situational_stats.back_to_back && Object.keys(aggregatedData.situational_stats.back_to_back).length > 0) ||
                                 (aggregatedData.situational_stats.rest_days && Object.keys(aggregatedData.situational_stats.rest_days).length > 0)}
                                <hr class="my-4">
                                <h6 class="text-muted mb-3">Additional Situational Analysis</h6>
                                <div class="row g-4">
                                    {#each Object.entries(aggregatedData.situational_stats) as [situation, stats]}
                                        {#if situation !== 'home' && situation !== 'away' && stats && Object.keys(stats).length > 0}
                                            <div class="col-md-6">
                                                <div class="border rounded p-3">
                                                    <h6 class="mb-3 text-info">{situation.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm mb-0">
                                                            <tbody>
                                                                {#each Object.entries(stats) as [stat, value]}
                                                                    <tr>
                                                                        <td class="border-0">{stat.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</td>
                                                                        <td class="border-0 text-end"><strong>{typeof value === 'number' ? formatNumber(value) : value}</strong></td>
                                                                    </tr>
                                                                {/each}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        {/if}
                                    {/each}
                                </div>
                            {:else}
                                <div class="text-center py-4">
                                    <i class="fas fa-info-circle text-muted fs-24 mb-2"></i>
                                    <p class="text-muted mb-0">Additional situational analysis will be available with more game data</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Quality Assessment -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Data Quality Assessment</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3 col-6">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h4 class="mb-1 text-info">{aggregatedData.data_quality.sample_size}</h4>
                                        <small class="text-muted">SAMPLE SIZE</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h4 class="mb-1 text-success">{formatPercentage(aggregatedData.data_quality.data_completeness * 100)}</h4>
                                        <small class="text-muted">COMPLETENESS</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h4 class="mb-1 text-warning">{formatPercentage(aggregatedData.data_quality.recency_score * 100)}</h4>
                                        <small class="text-muted">RECENCY</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h4 class="mb-1 text-primary">{formatPercentage(aggregatedData.data_quality.quality_score * 100)}</h4>
                                        <small class="text-muted">OVERALL QUALITY</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Game Log -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Game Log</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Opponent</th>
                                            <th>H/A</th>
                                            <th>MIN</th>
                                            <th>PTS</th>
                                            <th>REB</th>
                                            <th>AST</th>
                                            <th>STL</th>
                                            <th>BLK</th>
                                            <th>TO</th>
                                            <th>FG</th>
                                            <th>3PT</th>
                                            <th>FT</th>
                                            <th>+/-</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each aggregatedData.game_log.slice(0, 10) as game}
                                            <tr>
                                                <td><small>{new Date(game.date).toLocaleDateString()}</small></td>
                                                <td><small>{game.opponent}</small></td>
                                                <td><span class="badge {game.home_away === 'home' ? 'bg-success' : 'bg-secondary'}">{game.home_away.toUpperCase()}</span></td>
                                                <td><small>{game.minutes}</small></td>
                                                <td><strong>{game.points}</strong></td>
                                                <td>{game.rebounds}</td>
                                                <td>{game.assists}</td>
                                                <td>{game.steals}</td>
                                                <td>{game.blocks}</td>
                                                <td>{game.turnovers}</td>
                                                <td><small>{game.fg_made_attempted}</small></td>
                                                <td><small>{game.three_pt_made_attempted}</small></td>
                                                <td><small>{game.ft_made_attempted}</small></td>
                                                <td class="{game.plus_minus > 0 ? 'text-success' : game.plus_minus < 0 ? 'text-danger' : 'text-muted'}">
                                                    {game.plus_minus > 0 ? '+' : ''}{game.plus_minus}
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
        {:else}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                <i class="fas fa-user-slash text-muted fs-24"></i>
                            </div>
                            <h5 class="mb-2">Player Not Found</h5>
                            <p class="text-muted mb-0">The requested player data could not be loaded.</p>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>
