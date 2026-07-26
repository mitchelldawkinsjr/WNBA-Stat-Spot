<script lang="ts">
    import { onMount } from 'svelte';
    import DefaultLayout from '$lib/layouts/DefaultLayout.svelte';
    import {
        api,
        type ChampionReportDetail,
        type ChampionReportListItem,
    } from '$lib/api/client';

    let reports: ChampionReportListItem[] = [];
    let championVersion = '';
    let selected: ChampionReportDetail | null = null;
    let loading = true;
    let detailLoading = false;
    let error = '';

    onMount(async () => {
        await loadReports();
    });

    async function loadReports() {
        try {
            loading = true;
            error = '';
            const response = await api.wnba.predictions.getChampionReports(50);
            reports = response.data?.reports ?? [];
            championVersion = response.data?.champion?.version ?? '';
            if (reports.length > 0 && !selected) {
                await openReport(reports[0].report_uuid);
            }
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load champion reports';
        } finally {
            loading = false;
        }
    }

    async function openReport(uuid: string) {
        try {
            detailLoading = true;
            const response = await api.wnba.predictions.getChampionReport(uuid);
            selected = response.data ?? null;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load report detail';
            selected = null;
        } finally {
            detailLoading = false;
        }
    }

    function formatDelta(value: number | null | undefined): string {
        if (value == null) return '—';
        const sign = value > 0 ? '+' : '';
        return `${sign}${value.toFixed(4)}`;
    }

    function formatWhen(value: string | null | undefined): string {
        if (!value) return '—';
        return new Date(value).toLocaleString();
    }
</script>

<svelte:head>
    <title>Model Feedback | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row mb-4">
            <div class="col-12">
                <div class="page-title-box d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h4 class="page-title mb-1">Model Feedback</h4>
                        <p class="text-muted mb-0">
                            Champion promotion reports from the automatic prediction feedback loop —
                            what changed, why it promoted, and holdout metrics.
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        {#if championVersion}
                            <span class="badge bg-primary-subtle text-primary fs-6">
                                Champion {championVersion}
                            </span>
                        {/if}
                        <button class="btn btn-outline-primary" on:click={loadReports} disabled={loading}>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {#if error}
            <div class="alert alert-danger">{error}</div>
        {/if}

        {#if loading && reports.length === 0}
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 mb-0 text-muted">Loading champion reports…</p>
                </div>
            </div>
        {:else if reports.length === 0}
            <div class="card">
                <div class="card-body text-center py-5">
                    <h5 class="mb-2">No champion promotions yet</h5>
                    <p class="text-muted mb-0">
                        Reports appear automatically when the nightly feedback job promotes a new
                        champion. Non-promotions stay on the feedback-run audit log only.
                    </p>
                </div>
            </div>
        {:else}
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Promotion history</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            {#each reports as report}
                                <button
                                    type="button"
                                    class="list-group-item list-group-item-action text-start {selected?.report_uuid === report.report_uuid ? 'active' : ''}"
                                    on:click={() => openReport(report.report_uuid)}
                                >
                                    <div class="fw-semibold mb-1">{report.headline}</div>
                                    <div class="small {selected?.report_uuid === report.report_uuid ? 'text-white-50' : 'text-muted'}">
                                        {report.from_version} → {report.to_version}
                                        · Brier Δ {formatDelta(report.brier_delta)}
                                        · {formatWhen(report.promoted_at)}
                                    </div>
                                </button>
                            {/each}
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Report detail</h5>
                            {#if detailLoading}
                                <span class="spinner-border spinner-border-sm text-primary"></span>
                            {/if}
                        </div>
                        <div class="card-body">
                            {#if !selected}
                                <p class="text-muted mb-0">Select a report to inspect.</p>
                            {:else}
                                <h5 class="mb-3">{selected.headline}</h5>
                                <pre class="bg-light border rounded p-3 small mb-4" style="white-space: pre-wrap;">{selected.summary_markdown}</pre>

                                <h6 class="mb-2">Parameter changes</h6>
                                {#if !selected.changes?.length}
                                    <p class="text-muted small">No parameter diffs.</p>
                                {:else}
                                    <div class="table-responsive mb-4">
                                        <table class="table table-sm align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Path</th>
                                                    <th>From</th>
                                                    <th>To</th>
                                                    <th>Why</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {#each selected.changes as change}
                                                    <tr>
                                                        <td><code>{change.path}</code></td>
                                                        <td>{String(change.from)}</td>
                                                        <td>{String(change.to)}</td>
                                                        <td class="small text-muted">{change.why ?? '—'}</td>
                                                    </tr>
                                                {/each}
                                            </tbody>
                                        </table>
                                    </div>
                                {/if}

                                {#if selected.calibration_buckets?.length}
                                    <h6 class="mb-2">Calibration buckets</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Bucket</th>
                                                    <th>Predicted</th>
                                                    <th>Observed</th>
                                                    <th>Count</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {#each selected.calibration_buckets as bucket}
                                                    <tr>
                                                        <td>{bucket.bucket}</td>
                                                        <td>{(bucket.predicted * 100).toFixed(1)}%</td>
                                                        <td>{(bucket.observed * 100).toFixed(1)}%</td>
                                                        <td>{bucket.count}</td>
                                                    </tr>
                                                {/each}
                                            </tbody>
                                        </table>
                                    </div>
                                {/if}
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>
