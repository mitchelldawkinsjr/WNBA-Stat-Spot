<script lang="ts">
    import { onMount } from 'svelte';
    import DefaultLayout from '$lib/layouts/DefaultLayout.svelte';
    import { api, type PredictionAccuracyDashboard } from '$lib/api/client';

    let dashboard: PredictionAccuracyDashboard | null = null;
    let loading = true;
    let error = '';

    onMount(async () => {
        await loadAccuracy();
    });

    async function loadAccuracy() {
        try {
            loading = true;
            error = '';
            const response = await api.wnba.predictions.getAccuracy('America/New_York');
            dashboard = response.data ?? null;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load prediction accuracy';
            dashboard = null;
        } finally {
            loading = false;
        }
    }

    function formatPct(value: number | null | undefined): string {
        if (value == null) return '—';
        return `${value.toFixed(1)}%`;
    }

    function formatNum(value: number | null | undefined, digits = 1): string {
        if (value == null) return '—';
        return value.toFixed(digits);
    }

    function pctClass(value: number | null | undefined): string {
        if (value == null) return 'text-muted';
        if (value >= 55) return 'text-success';
        if (value >= 50) return 'text-warning';
        return 'text-danger';
    }

    function formatStat(statType: string): string {
        return statType.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
    }

    $: topProp = dashboard?.top_prop_of_day ?? null;
</script>

<svelte:head>
    <title>Model Accuracy | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row mb-4">
            <div class="col-12">
                <div class="page-title-box d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h4 class="page-title mb-1">Model Accuracy</h4>
                        <p class="text-muted mb-0">
                            Running accuracy for tracked final-score projections and prop recommendations.
                            Metrics update once games are final.
                        </p>
                    </div>
                    <button class="btn btn-outline-primary" on:click={loadAccuracy} disabled={loading}>
                        {#if loading}
                            <span class="spinner-border spinner-border-sm me-2"></span>
                        {/if}
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        {#if loading && !dashboard}
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 mb-0 text-muted">Loading tracked prediction accuracy…</p>
                </div>
            </div>
        {:else if error}
            <div class="alert alert-danger" role="alert">{error}</div>
        {:else if dashboard}
            <!-- Top prop of the day -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-primary">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                <div>
                                    <p class="ds-section-label mb-1">Top Prop of the Day</p>
                                    {#if topProp}
                                        <h2 class="ds-headline-md mb-1">{topProp.player_name}</h2>
                                        <p class="text-muted mb-2">
                                            {topProp.team_abbreviation ?? ''}
                                            {#if topProp.opponent}
                                                vs {topProp.opponent}
                                            {/if}
                                        </p>
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            <span class="badge bg-{topProp.recommendation === 'over' ? 'success' : 'danger'}">
                                                {topProp.recommendation?.toUpperCase()} {topProp.suggested_line}
                                            </span>
                                            <span class="badge bg-primary-subtle text-primary-emphasis">
                                                {formatStat(topProp.stat_type)}
                                            </span>
                                            <span class="text-muted small">
                                                Predicted {topProp.predicted_value}
                                                · EV {formatNum(topProp.expected_value)}
                                                · Conf {formatNum(topProp.confidence, 0)}%
                                            </span>
                                        </div>
                                        {#if topProp.reasoning}
                                            <p class="mt-3 mb-0 text-muted">{topProp.reasoning}</p>
                                        {/if}
                                    {:else}
                                        <h2 class="ds-headline-md mb-1">No top prop yet</h2>
                                        <p class="text-muted mb-0">
                                            Open Today's Best Props or wait for today's slate to generate a tracked pick.
                                        </p>
                                    {/if}
                                </div>
                                <a href="/reports/todays-props" class="btn btn-primary">View All Props</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accuracy summary -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <p class="text-muted mb-1">Winner Pick Accuracy</p>
                            <h2 class="{pctClass(dashboard.game_scores.winner_accuracy)} mb-0">
                                {formatPct(dashboard.game_scores.winner_accuracy)}
                            </h2>
                            <small class="text-muted">
                                {dashboard.game_scores.winner_correct}/{dashboard.game_scores.graded} graded games
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <p class="text-muted mb-1">Total Within 5 Pts</p>
                            <h2 class="{pctClass(dashboard.game_scores.total_within_5_pct)} mb-0">
                                {formatPct(dashboard.game_scores.total_within_5_pct)}
                            </h2>
                            <small class="text-muted">
                                Avg total error {formatNum(dashboard.game_scores.avg_total_error)}
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <p class="text-muted mb-1">Prop Accuracy</p>
                            <h2 class="{pctClass(dashboard.props.accuracy)} mb-0">
                                {formatPct(dashboard.props.accuracy)}
                            </h2>
                            <small class="text-muted">
                                {dashboard.props.correct}/{dashboard.props.graded} graded props
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <p class="text-muted mb-1">Top Prop Hit Rate</p>
                            <h2 class="{pctClass(dashboard.props.top_prop_accuracy)} mb-0">
                                {formatPct(dashboard.props.top_prop_accuracy)}
                            </h2>
                            <small class="text-muted">
                                {dashboard.props.top_prop_correct}/{dashboard.props.top_prop_graded} daily tops
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header border-0">
                            <h5 class="card-title mb-0">Final Score Projections</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <div class="ds-score-card">
                                        <p class="ds-section-label mb-0">Pending</p>
                                        <p class="mb-0 fw-bold fs-4">{dashboard.game_scores.pending}</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="ds-score-card">
                                        <p class="ds-section-label mb-0">Spread Direction</p>
                                        <p class="mb-0 fw-bold fs-4 {pctClass(dashboard.game_scores.spread_direction_accuracy)}">
                                            {formatPct(dashboard.game_scores.spread_direction_accuracy)}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="ds-score-card">
                                        <p class="ds-section-label mb-0">Avg Home Error</p>
                                        <p class="mb-0 fw-bold fs-4">{formatNum(dashboard.game_scores.avg_home_score_error)}</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="ds-score-card">
                                        <p class="ds-section-label mb-0">Avg Away Error</p>
                                        <p class="mb-0 fw-bold fs-4">{formatNum(dashboard.game_scores.avg_away_score_error)}</p>
                                    </div>
                                </div>
                            </div>

                            {#if dashboard.game_scores.recent.length > 0}
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Matchup</th>
                                                <th>Projected</th>
                                                <th>Actual</th>
                                                <th>Winner</th>
                                                <th>Total Err</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each dashboard.game_scores.recent as row}
                                                <tr>
                                                    <td>
                                                        <a href="/games/{row.game_id}">{row.matchup}</a>
                                                    </td>
                                                    <td class="small">{row.predicted}</td>
                                                    <td class="small">{row.actual}</td>
                                                    <td>
                                                        {#if row.winner_correct}
                                                            <span class="badge bg-success-subtle text-success">Hit</span>
                                                        {:else}
                                                            <span class="badge bg-danger-subtle text-danger">Miss</span>
                                                        {/if}
                                                    </td>
                                                    <td>{row.total_error}</td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            {:else}
                                <p class="text-muted mb-0">
                                    No graded score projections yet. Predictions are stored when previews or today's props run,
                                    then graded after games finish.
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header border-0">
                            <h5 class="card-title mb-0">Prop Recommendations</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <div class="ds-score-card">
                                        <p class="ds-section-label mb-0">Pending</p>
                                        <p class="mb-0 fw-bold fs-4">{dashboard.props.pending}</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="ds-score-card">
                                        <p class="ds-section-label mb-0">Graded</p>
                                        <p class="mb-0 fw-bold fs-4">{dashboard.props.graded}</p>
                                    </div>
                                </div>
                            </div>

                            {#if dashboard.props.by_stat.length > 0}
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Stat</th>
                                                <th>Graded</th>
                                                <th>Accuracy</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each dashboard.props.by_stat as row}
                                                <tr>
                                                    <td>{formatStat(row.stat_type)}</td>
                                                    <td>{row.graded}</td>
                                                    <td class={pctClass(row.accuracy)}>{formatPct(row.accuracy)}</td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            {:else}
                                <p class="text-muted mb-0">
                                    No graded props yet. Today's best props are tracked automatically and scored after box scores land.
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-muted small mb-0">
                Last updated {new Date(dashboard.updated_at).toLocaleString('en-US', { timeZone: 'America/New_York' })} ET
            </p>
        {/if}
    </div>
</DefaultLayout>
