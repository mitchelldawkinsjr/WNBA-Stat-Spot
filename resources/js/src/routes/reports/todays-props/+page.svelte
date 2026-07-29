<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import type { TodaysProp } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import HitRateStrip from '$lib/components/HitRateStrip.svelte';
    import RecentVsLineBars from '$lib/components/RecentVsLineBars.svelte';

    let props: TodaysProp[] = [];
    let loading = true;
    let error = '';
    let serverMinAvgMinutes = 15;
    let filters = {
        stat_type: '',
        min_confidence: 0,
        min_expected_value: 0,
        min_avg_minutes: 15,
        recommendation: '',
        real_odds_only: false,
        sort_by: 'expected_value',
        sort_order: 'desc'
    };

    const minMinutesOptions = [
        { value: 0, label: 'Any (server gate)' },
        { value: 10, label: '10+ min' },
        { value: 15, label: '15+ min' },
        { value: 20, label: '20+ min' },
        { value: 25, label: '25+ min' },
        { value: 30, label: '30+ min' },
    ];

    const statTypes = [
        { value: '', label: 'All Stats' },
        { value: 'points', label: 'Points' },
        { value: 'rebounds', label: 'Rebounds' },
        { value: 'assists', label: 'Assists' },
        { value: 'steals', label: 'Steals' },
        { value: 'blocks', label: 'Blocks' }
    ];

    const recommendations = [
        { value: '', label: 'All Recommendations' },
        { value: 'over', label: 'Over' },
        { value: 'under', label: 'Under' },
        { value: 'avoid', label: 'Avoid' }
    ];

    const sortOptions = [
        { value: 'expected_value', label: 'Expected Value' },
        { value: 'l10_hit_rate', label: 'L10 Hit Rate' },
        { value: 'confidence', label: 'Confidence' },
        { value: 'predicted_value', label: 'Predicted Value' },
        { value: 'player_name', label: 'Player Name' },
        { value: 'game_time', label: 'Game Time' }
    ];

    function sortValue(prop: TodaysProp, key: string): number | string | null {
        if (key === 'l10_hit_rate') {
            return prop.hit_rates?.l10?.rate ?? -1;
        }
        if (key === 'expected_value') {
            return prop.expected_value ?? -999;
        }
        const val = prop[key as keyof TodaysProp];
        if (typeof val === 'number' || typeof val === 'string') return val;
        return null;
    }

    $: filteredProps = props.filter(prop => {
        if (filters.stat_type && prop.stat_type !== filters.stat_type) return false;

        const normalizedConfidence = prop.confidence <= 1 ? prop.confidence * 100 : prop.confidence;
        if (normalizedConfidence < filters.min_confidence) return false;

        if (filters.real_odds_only && !prop.odds_available) return false;

        if (filters.min_expected_value > 0) {
            if (prop.expected_value === null || prop.expected_value < filters.min_expected_value) return false;
        }
        if (filters.min_avg_minutes > 0) {
            const avgMin = prop.avg_minutes ?? 0;
            if (avgMin < Number(filters.min_avg_minutes)) return false;
        }
        if (filters.recommendation && prop.recommendation !== filters.recommendation) return false;
        return true;
    }).sort((a, b) => {
        const aVal = sortValue(a, filters.sort_by);
        const bVal = sortValue(b, filters.sort_by);
        if (aVal === bVal) return 0;
        if (aVal === null) return 1;
        if (bVal === null) return -1;

        if (filters.sort_order === 'desc') {
            return bVal > aVal ? 1 : -1;
        }
        return aVal > bVal ? 1 : -1;
    });

    async function loadTodaysProps() {
        try {
            loading = true;
            error = '';

            const response = await api.wnba.predictions.getTodaysBestProps();

            if (response.success) {
                props = response.data || [];
                if (response.gates?.min_avg_minutes != null) {
                    serverMinAvgMinutes = Number(response.gates.min_avg_minutes);
                    const current = Number(filters.min_avg_minutes);
                    if (current === 15 || current === 0) {
                        filters = { ...filters, min_avg_minutes: serverMinAvgMinutes };
                    }
                }
            } else {
                error = 'Failed to load today\'s props';
            }
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load today\'s props';
            console.error('Error loading today\'s props:', err);
        } finally {
            loading = false;
        }
    }

    function formatPercentage(value: number): string {
        if (value <= 1) {
            return `${(value * 100).toFixed(1)}%`;
        }
        return `${value.toFixed(1)}%`;
    }

    function formatNumber(value: number): string {
        return value.toFixed(1);
    }

    function formatEv(value: number | null | undefined): string {
        if (value === null || value === undefined) return '—';
        return `${value > 0 ? '+' : ''}${formatNumber(value)}%`;
    }

    function oppDefLabel(prop: TodaysProp): string {
        if (prop.opp_def_rank == null) return '';
        const basis = prop.opp_def_rank_basis === 'points_against' ? 'Pts all.' : 'DRtg';
        return `Opp ${basis} #${prop.opp_def_rank}`;
    }

    function getConfidenceColor(confidence: number): string {
        const normalizedConfidence = confidence <= 1 ? confidence : confidence / 100;
        if (normalizedConfidence >= 0.8) return 'success';
        if (normalizedConfidence >= 0.6) return 'warning';
        return 'danger';
    }

    function getRecommendationColor(recommendation: string): string {
        switch (recommendation) {
            case 'over': return 'success';
            case 'under': return 'primary';
            case 'avoid': return 'danger';
            default: return 'secondary';
        }
    }

    function getBettingValueColor(value: string): string {
        switch (value) {
            case 'excellent': return 'success';
            case 'good': return 'info';
            case 'fair': return 'warning';
            case 'poor': return 'danger';
            case 'research': return 'secondary';
            default: return 'secondary';
        }
    }

    function getMatchupDifficultyColor(difficulty: string): string {
        switch (difficulty.toLowerCase()) {
            case 'favorable': return 'success';
            case 'neutral': return 'warning';
            case 'difficult': return 'danger';
            default: return 'secondary';
        }
    }

    function clearFilters() {
        filters = {
            stat_type: '',
            min_confidence: 0,
            min_expected_value: 0,
            min_avg_minutes: serverMinAvgMinutes,
            recommendation: '',
            real_odds_only: false,
            sort_by: 'expected_value',
            sort_order: 'desc'
        };
    }

    onMount(() => {
        loadTodaysProps();
    });
</script>

<svelte:head>
    <title>Today's Best Props - WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <button
                            on:click={loadTodaysProps}
                            class="btn btn-outline-primary me-2"
                            disabled={loading}
                        >
                            {#if loading}
                                <span class="spinner-border spinner-border-sm me-1"></span>
                            {:else}
                                <i class="fas fa-sync me-1"></i>
                            {/if}
                            Refresh
                        </button>
                        <a href="/reports/predictions" class="btn btn-success">
                            <i class="fas fa-crystal-ball me-1"></i>Prediction Engine
                        </a>
                    </div>
                    <h4 class="page-title">Today's Best Props</h4>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter text-primary me-2"></i>Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label for="stat-filter" class="form-label">Statistic</label>
                                <select id="stat-filter" class="form-select" bind:value={filters.stat_type}>
                                    {#each statTypes as stat}
                                        <option value={stat.value}>{stat.label}</option>
                                    {/each}
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="confidence-filter" class="form-label">Min Confidence (%)</label>
                                <input
                                    id="confidence-filter"
                                    type="number"
                                    class="form-control"
                                    bind:value={filters.min_confidence}
                                    min="0"
                                    max="100"
                                    placeholder="0"
                                />
                            </div>
                            <div class="col-md-2">
                                <label for="ev-filter" class="form-label">Min Expected Value</label>
                                <input
                                    id="ev-filter"
                                    type="number"
                                    class="form-control"
                                    bind:value={filters.min_expected_value}
                                    min="0"
                                    step="0.1"
                                    placeholder="0"
                                />
                            </div>
                            <div class="col-md-2">
                                <label for="minutes-filter" class="form-label">Min Avg Minutes</label>
                                <select id="minutes-filter" class="form-select" bind:value={filters.min_avg_minutes}>
                                    {#each minMinutesOptions as opt}
                                        <option value={opt.value}>{opt.label}</option>
                                    {/each}
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="recommendation-filter" class="form-label">Recommendation</label>
                                <select id="recommendation-filter" class="form-select" bind:value={filters.recommendation}>
                                    {#each recommendations as rec}
                                        <option value={rec.value}>{rec.label}</option>
                                    {/each}
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="sort-filter" class="form-label">Sort By</label>
                                <select id="sort-filter" class="form-select" bind:value={filters.sort_by}>
                                    {#each sortOptions as sort}
                                        <option value={sort.value}>{sort.label}</option>
                                    {/each}
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="order-filter" class="form-label">Order</label>
                                <select id="order-filter" class="form-select" bind:value={filters.sort_order}>
                                    <option value="desc">Descending</option>
                                    <option value="asc">Ascending</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                                <div class="form-check mb-0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="real-odds-filter"
                                        bind:checked={filters.real_odds_only}
                                    />
                                    <label class="form-check-label" for="real-odds-filter">
                                        Real odds only
                                    </label>
                                </div>
                                <button
                                    on:click={clearFilters}
                                    class="btn btn-outline-secondary btn-sm"
                                >
                                    <i class="fas fa-times me-1"></i>Clear Filters
                                </button>
                                <span class="text-muted">
                                    Showing {filteredProps.length} of {props.length} props
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Props Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line text-success me-2"></i>
                                Today's Best Props ({filteredProps.length})
                            </h5>
                            <div class="text-muted">
                                <small>Last updated: {new Date().toLocaleString()}</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        {#if loading}
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 mb-0">Loading today's best props...</p>
                            </div>
                        {:else if error}
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Error:</strong> {error}
                                <button
                                    on:click={loadTodaysProps}
                                    class="btn btn-sm btn-outline-danger ms-2"
                                >
                                    Try Again
                                </button>
                            </div>
                        {:else if filteredProps.length === 0}
                            <div class="text-center py-5">
                                <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                    <i class="fas fa-search text-muted fs-24"></i>
                                </div>
                                <h5 class="mb-2">No Props Found</h5>
                                <p class="text-muted mb-3">
                                    {props.length === 0
                                        ? 'No WNBA games are scheduled for today, or all scheduled games have been completed. Today\'s props are only generated for games happening today that haven\'t finished yet.'
                                        : 'No props match your current filters. Try adjusting the filters above.'
                                    }
                                </p>
                                {#if props.length > 0}
                                    <button
                                        on:click={clearFilters}
                                        class="btn btn-primary"
                                    >
                                        <i class="fas fa-times me-2"></i>Clear Filters
                                    </button>
                                {/if}
                            </div>
                        {:else}
                            <div class="table-responsive">
                                <table class="table table-hover table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Player</th>
                                            <th>Game</th>
                                            <th>Stat</th>
                                            <th>Line</th>
                                            <th>Hit rates</th>
                                            <th>Recent</th>
                                            <th>Predicted</th>
                                            <th>Confidence</th>
                                            <th>Rec</th>
                                            <th>EV</th>
                                            <th>O/U</th>
                                            <th>Matchup</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each filteredProps as prop}
                                            <tr>
                                                <td>
                                                    <div>
                                                        <div class="fw-semibold">{prop.player_name}</div>
                                                        <small class="text-muted">{prop.team_abbreviation}</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="fw-medium">{prop.opponent}</div>
                                                        <small class="text-muted">{prop.game_time}</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info-subtle text-info text-capitalize">
                                                        {prop.stat_type}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-medium">{formatNumber(prop.suggested_line)}</div>
                                                    {#if prop.odds_available}
                                                        <span class="badge bg-success-subtle text-success">Live</span>
                                                    {:else}
                                                        <span class="badge bg-secondary-subtle text-secondary">Est. line</span>
                                                    {/if}
                                                </td>
                                                <td>
                                                    <HitRateStrip hitRates={prop.hit_rates} compact />
                                                </td>
                                                <td>
                                                    <RecentVsLineBars games={prop.recent_games} />
                                                </td>
                                                <td class="fw-bold text-primary">{formatNumber(prop.predicted_value)}</td>
                                                <td>
                                                    <span class="badge bg-{getConfidenceColor(prop.confidence)}-subtle text-{getConfidenceColor(prop.confidence)}">
                                                        {formatPercentage(prop.confidence)}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{getRecommendationColor(prop.recommendation)}-subtle text-{getRecommendationColor(prop.recommendation)} text-uppercase">
                                                        {prop.recommendation}
                                                    </span>
                                                </td>
                                                <td class="fw-medium {prop.expected_value !== null && prop.expected_value > 0 ? 'text-success' : prop.expected_value === null ? 'text-muted' : 'text-danger'}">
                                                    {formatEv(prop.expected_value)}
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div>O {formatPercentage(prop.probability_over)}</div>
                                                        <div>U {formatPercentage(prop.probability_under)}</div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{getMatchupDifficultyColor(prop.matchup_difficulty || 'neutral')}-subtle text-{getMatchupDifficultyColor(prop.matchup_difficulty || 'neutral')} text-capitalize">
                                                        {prop.matchup_difficulty || 'neutral'}
                                                    </span>
                                                    {#if oppDefLabel(prop)}
                                                        <div class="small text-muted mt-1">{oppDefLabel(prop)}</div>
                                                    {/if}
                                                </td>
                                                <td>
                                                    <span class="badge bg-{getBettingValueColor(prop.betting_value)}-subtle text-{getBettingValueColor(prop.betting_value)} text-capitalize">
                                                        {prop.betting_value}
                                                    </span>
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        {#if filteredProps.length > 0}
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar text-info me-2"></i>Summary Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h4 class="text-primary">{filteredProps.length}</h4>
                                        <p class="text-muted mb-0">Total Props</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h4 class="text-success">
                                            {formatPercentage(filteredProps.reduce((sum, p) => sum + p.confidence, 0) / filteredProps.length)}
                                        </h4>
                                        <p class="text-muted mb-0">Avg Confidence</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h4 class="text-info">
                                            {filteredProps.filter(p => p.expected_value !== null && p.expected_value > 0).length}
                                        </h4>
                                        <p class="text-muted mb-0">Positive EV Props</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h4 class="text-warning">
                                            {(() => {
                                                const evs = filteredProps
                                                    .map(p => p.expected_value)
                                                    .filter((v): v is number => v !== null);
                                                return evs.length ? formatNumber(Math.max(...evs)) + '%' : '—';
                                            })()}
                                        </h4>
                                        <p class="text-muted mb-0">Best Expected Value</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>
