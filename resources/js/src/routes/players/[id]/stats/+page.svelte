<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api, type AggregatedPlayerData } from '$lib/api/client';
    import { playerProfile } from '$lib/stores/playerProfile';

    type TrendWindow = {
        games?: number;
        minutes_avg?: number | null;
        points_avg?: number | null;
        rebounds_avg?: number | null;
        assists_avg?: number | null;
        fg_pct?: number | null;
        ts_pct?: number | null;
        points_slope?: number | null;
        rebounds_slope?: number | null;
        assists_slope?: number | null;
    };

    const TREND_WINDOW_ORDER = [
        { key: 'l5', label: 'L5' },
        { key: 'l10', label: 'L10' },
        { key: 'l20', label: 'L20' },
        { key: 'season', label: 'All' },
    ] as const;

    let aggregatedData: AggregatedPlayerData | null = null;
    let loading = true;
    let error = '';
    let showAdvanced = true;
    let showSituational = false;
    let loadedKey = '';

    $: profile = $playerProfile;
    $: playerId = $page.params.id;
    $: season = profile.season;
    $: advanced = aggregatedData?.advanced_metrics;
    $: trendWindows = normalizeTrendWindows(aggregatedData?.performance_trends);
    $: per30 = advanced?.per_30_stats ?? {};
    $: per36 = advanced?.per_36_stats ?? {};

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
                last_n_games: 50,
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

    function formatNumber(value: number | null | undefined, digits = 1): string {
        if (value == null || !Number.isFinite(value)) return '—';
        return value.toFixed(digits);
    }

    function formatPercentage(value: number | null | undefined): string {
        if (value == null || !Number.isFinite(value)) return '—';
        return `${value.toFixed(1)}%`;
    }

    function formatPlusMinus(value: number | null | undefined): string {
        if (value == null || !Number.isFinite(value)) return '—';
        const rounded = value.toFixed(1);
        return value > 0 ? `+${rounded}` : rounded;
    }

    function formatSlope(value: number | null | undefined): string {
        if (value == null || !Number.isFinite(value)) return '—';
        const arrow = value > 0 ? '↗' : value < 0 ? '↘' : '→';
        return `${arrow} ${Math.abs(value).toFixed(2)}`;
    }

    function slopeClass(value: number | null | undefined): string {
        if (value == null || !Number.isFinite(value) || value === 0) return 'text-muted';
        return value > 0 ? 'text-success' : 'text-danger';
    }

    function normalizeTrendWindows(
        trends: AggregatedPlayerData['performance_trends'] | undefined
    ): Array<{ key: string; label: string; data: TrendWindow }> {
        if (!trends || typeof trends !== 'object') return [];

        const entries = Object.entries(trends);
        if (entries.length === 0) return [];

        // Agent shape: { l5: {...}, l10: {...}, ... }
        const first = entries[0]?.[1];
        if (first && typeof first === 'object') {
            return TREND_WINDOW_ORDER.map(({ key, label }) => ({
                key,
                label,
                data: (trends as Record<string, TrendWindow>)[key] ?? {},
            })).filter((row) => Object.keys(row.data).length > 0);
        }

        // Legacy flat slopes → single "All" card
        const legacy = trends as Record<string, number>;
        return [
            {
                key: 'season',
                label: 'All',
                data: {
                    points_slope: legacy.points_trend,
                    rebounds_slope: legacy.rebounds_trend,
                    assists_slope: legacy.assists_trend,
                },
            },
        ];
    }

    function pctDisplay(value: number | null | undefined): string {
        if (value == null || !Number.isFinite(value)) return '—';
        // Agent stores 0..1; tolerate either scale
        const pct = value <= 1 ? value * 100 : value;
        return `${pct.toFixed(1)}%`;
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
                            <span class="text-capitalize">{stat === 'plus_minus' ? '+/−' : stat.replace(/_/g, ' ')}</span>
                            <strong>{stat === 'plus_minus' ? formatPlusMinus(value) : formatNumber(value)}</strong>
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
                {#if !advanced || (advanced.true_shooting_pct == null && advanced.usage_rate == null)}
                    <p class="text-muted small mb-3">
                        Advanced aggregates are empty. Run <code>php artisan app:wnba-agent analytics</code> for {season} to populate them.
                    </p>
                {/if}
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatPercentage(advanced?.true_shooting_pct)}</div>
                            <div class="text-muted small">True shooting %</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatPercentage(advanced?.effective_fg_pct)}</div>
                            <div class="text-muted small">Effective FG %</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatPercentage(advanced?.usage_rate)}</div>
                            <div class="text-muted small">Usage rate</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatNumber(advanced?.assist_turnover_ratio)}</div>
                            <div class="text-muted small">AST/TO</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatNumber(advanced?.game_score_avg)}</div>
                            <div class="text-muted small">Game score avg</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-bold fs-5">{formatPlusMinus(advanced?.plus_minus_avg)}</div>
                            <div class="text-muted small">+/− avg</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Per 30 minutes</h6>
                        {#if Object.keys(per30).length > 0}
                            {#each Object.entries(per30) as [stat, value]}
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-capitalize">{stat.replace(/_/g, ' ')}</span>
                                    <strong>{formatNumber(typeof value === 'number' ? value : null)}</strong>
                                </div>
                            {/each}
                        {:else}
                            <p class="text-muted small mb-0">No per-30 data yet.</p>
                        {/if}
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Per 36 minutes</h6>
                        {#if Object.keys(per36).length > 0}
                            {#each Object.entries(per36) as [stat, value]}
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-capitalize">{stat.replace(/_/g, ' ')}</span>
                                    <strong>{formatNumber(typeof value === 'number' ? value : null)}</strong>
                                </div>
                            {/each}
                        {:else}
                            <p class="text-muted small mb-0">No per-36 data yet.</p>
                        {/if}
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
            {#if trendWindows.length === 0}
                <p class="text-muted mb-0">
                    No performance trends yet. Run <code>php artisan app:wnba-agent analytics</code> for {season}.
                </p>
            {:else}
                <div class="row g-3">
                    {#each trendWindows as window}
                        <div class="col-md-6 col-xl-3">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">{window.label}</h6>
                                    <span class="text-muted small">{window.data.games ?? '—'} GP</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>PPG</span>
                                    <strong>{formatNumber(window.data.points_avg)}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>RPG</span>
                                    <strong>{formatNumber(window.data.rebounds_avg)}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>APG</span>
                                    <strong>{formatNumber(window.data.assists_avg)}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>TS%</span>
                                    <strong>{pctDisplay(window.data.ts_pct)}</strong>
                                </div>
                                <hr class="my-2" />
                                <div class="d-flex justify-content-between mb-1">
                                    <span>PTS trend</span>
                                    <strong class={slopeClass(window.data.points_slope)}>{formatSlope(window.data.points_slope)}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>REB trend</span>
                                    <strong class={slopeClass(window.data.rebounds_slope)}>{formatSlope(window.data.rebounds_slope)}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>AST trend</span>
                                    <strong class={slopeClass(window.data.assists_slope)}>{formatSlope(window.data.assists_slope)}</strong>
                                </div>
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
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
                                    <span class="text-capitalize">{stat === 'plus_minus' ? '+/−' : stat.replace(/_/g, ' ')}</span>
                                    <strong>
                                        {#if typeof value === 'number'}
                                            {stat === 'plus_minus' ? formatPlusMinus(value) : formatNumber(value)}
                                        {:else}
                                            {value}
                                        {/if}
                                    </strong>
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
                                    <span class="text-capitalize">{stat === 'plus_minus' ? '+/−' : stat.replace(/_/g, ' ')}</span>
                                    <strong>
                                        {#if typeof value === 'number'}
                                            {stat === 'plus_minus' ? formatPlusMinus(value) : formatNumber(value)}
                                        {:else}
                                            {value}
                                        {/if}
                                    </strong>
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
