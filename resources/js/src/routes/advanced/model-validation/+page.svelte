<script lang="ts">
    import { onMount } from 'svelte';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";

    let validationData: any = null;
    let loading = true;
    let error = '';
    let selectedTimeframe = '30d';
    let selectedStatType = '';
    let selectedModel = 'all';

    const timeframes = [
        { value: '7d', label: 'Last 7 Days' },
        { value: '30d', label: 'Last 30 Days' },
        { value: '90d', label: 'Last 90 Days' },
        { value: 'season', label: 'Current Season' }
    ];

    const statTypes = [
        { value: '', label: 'All Statistics' },
        { value: 'points', label: 'Points' },
        { value: 'rebounds', label: 'Rebounds' },
        { value: 'assists', label: 'Assists' },
        { value: 'steals', label: 'Steals' },
        { value: 'blocks', label: 'Blocks' }
    ];

    const models = [
        { value: 'all', label: 'All Models' },
        { value: 'poisson', label: 'Poisson Model' },
        { value: 'bayesian', label: 'Bayesian Model' },
        { value: 'monte_carlo', label: 'Monte Carlo' },
        { value: 'ensemble', label: 'Ensemble Model' }
    ];

    onMount(async () => {
        await loadValidationData();
    });

    async function loadValidationData() {
        try {
            loading = true;
            error = '';
            validationData = generateMockValidationData();
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load validation data';
            validationData = generateMockValidationData();
        } finally {
            loading = false;
        }
    }

    function generateMockValidationData() {
        return {
            overall_performance: {
                accuracy_score: 0.732,
                precision: 0.718,
                recall: 0.745,
                f1_score: 0.731,
                auc_roc: 0.789,
                log_loss: 0.542
            },
            calibration_metrics: {
                calibration_score: 0.89,
                brier_score: 0.21,
                reliability: 0.92,
                sharpness: 0.67,
                expected_calibration_error: 0.034
            },
            accuracy_by_stat: {
                points: { accuracy: 0.745, sample_size: 1250, confidence_interval: [0.721, 0.769] },
                rebounds: { accuracy: 0.698, sample_size: 1250, confidence_interval: [0.672, 0.724] },
                assists: { accuracy: 0.712, sample_size: 1250, confidence_interval: [0.687, 0.737] },
                steals: { accuracy: 0.681, sample_size: 1250, confidence_interval: [0.654, 0.708] },
                blocks: { accuracy: 0.663, sample_size: 1250, confidence_interval: [0.635, 0.691] }
            },
            performance_over_time: [
                { date: '2025-01-01', accuracy: 0.721, predictions: 45 },
                { date: '2025-01-02', accuracy: 0.734, predictions: 52 },
                { date: '2025-01-03', accuracy: 0.718, predictions: 48 },
                { date: '2025-01-04', accuracy: 0.742, predictions: 51 },
                { date: '2025-01-05', accuracy: 0.729, predictions: 49 }
            ],
            model_comparison: {
                poisson: { accuracy: 0.712, speed: 'Fast', complexity: 'Low' },
                bayesian: { accuracy: 0.734, speed: 'Medium', complexity: 'Medium' },
                monte_carlo: { accuracy: 0.748, speed: 'Slow', complexity: 'High' },
                ensemble: { accuracy: 0.756, speed: 'Medium', complexity: 'High' }
            },
            feature_importance: [
                { feature: 'Recent Form (5 games)', importance: 0.234 },
                { feature: 'Season Average', importance: 0.198 },
                { feature: 'Opponent Defense', importance: 0.156 },
                { feature: 'Home/Away', importance: 0.123 },
                { feature: 'Rest Days', importance: 0.089 },
                { feature: 'Game Pace', importance: 0.076 },
                { feature: 'Minutes Projection', importance: 0.067 },
                { feature: 'Injury Status', importance: 0.057 }
            ]
        };
    }

    async function applyFilters() {
        await loadValidationData();
    }

    function formatPercentage(value: number): string {
        return `${(value * 100).toFixed(1)}%`;
    }

    function formatNumber(value: number, decimals = 3): string {
        return value.toFixed(decimals);
    }

    function getPerformanceColor(value: number, threshold = 0.7): string {
        if (value >= threshold + 0.05) return 'text-success';
        if (value >= threshold) return 'text-warning';
        return 'text-danger';
    }

    function getBadgeClass(value: number, threshold = 0.7): string {
        if (value >= threshold + 0.05) return 'bg-success-subtle text-success';
        if (value >= threshold) return 'bg-warning-subtle text-warning';
        return 'bg-danger-subtle text-danger';
    }
</script>

<svelte:head>
    <title>Model Validation | Advanced Analytics | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <a href="/advanced" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Advanced
                        </a>
                    </div>
                    <h4 class="page-title">Model Validation</h4>
                    <p class="text-muted mb-0">Deep analysis of prediction model performance, accuracy metrics, and calibration</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-sliders-h text-primary me-2"></i>Analysis Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="timeframe" class="form-label">Timeframe</label>
                                <select
                                    id="timeframe"
                                    bind:value={selectedTimeframe}
                                    class="form-select"
                                >
                                    {#each timeframes as timeframe}
                                        <option value={timeframe.value}>{timeframe.label}</option>
                                    {/each}
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="stat-type" class="form-label">Statistic Type</label>
                                <select
                                    id="stat-type"
                                    bind:value={selectedStatType}
                                    class="form-select"
                                >
                                    {#each statTypes as stat}
                                        <option value={stat.value}>{stat.label}</option>
                                    {/each}
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="model" class="form-label">Model Type</label>
                                <select
                                    id="model"
                                    bind:value={selectedModel}
                                    class="form-select"
                                >
                                    {#each models as model}
                                        <option value={model.value}>{model.label}</option>
                                    {/each}
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button
                                    on:click={applyFilters}
                                    disabled={loading}
                                    class="btn btn-primary w-100"
                                >
                                    {#if loading}
                                        <span class="spinner-border spinner-border-sm me-2"></span>
                                    {:else}
                                        <i class="fas fa-search me-2"></i>
                                    {/if}
                                    Analyze
                                </button>
                            </div>
                        </div>
                    </div>
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
                            <p class="mt-2 mb-0">Analyzing model performance...</p>
                        </div>
                    </div>
                </div>
            </div>
        {:else if error}
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> {error}
                    </div>
                </div>
            </div>
        {:else if validationData}
            <!-- Overall Performance Metrics -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line text-primary me-2"></i>Overall Performance Metrics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="{getPerformanceColor(validationData.overall_performance.accuracy_score)}">{formatPercentage(validationData.overall_performance.accuracy_score)}</h3>
                                        <p class="text-muted mb-0">Accuracy</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="{getPerformanceColor(validationData.overall_performance.precision)}">{formatPercentage(validationData.overall_performance.precision)}</h3>
                                        <p class="text-muted mb-0">Precision</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="{getPerformanceColor(validationData.overall_performance.recall)}">{formatPercentage(validationData.overall_performance.recall)}</h3>
                                        <p class="text-muted mb-0">Recall</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="{getPerformanceColor(validationData.overall_performance.f1_score)}">{formatPercentage(validationData.overall_performance.f1_score)}</h3>
                                        <p class="text-muted mb-0">F1 Score</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="{getPerformanceColor(validationData.overall_performance.auc_roc)}">{formatNumber(validationData.overall_performance.auc_roc)}</h3>
                                        <p class="text-muted mb-0">AUC-ROC</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="text-info">{formatNumber(validationData.overall_performance.log_loss)}</h3>
                                        <p class="text-muted mb-0">Log Loss</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calibration Analysis -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-balance-scale text-primary me-2"></i>Calibration Analysis
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-center">
                                        <h4 class="text-success">{formatNumber(validationData.calibration_metrics.calibration_score)}</h4>
                                        <p class="text-muted mb-0">Calibration Score</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h4 class="text-info">{formatNumber(validationData.calibration_metrics.brier_score)}</h4>
                                        <p class="text-muted mb-0">Brier Score</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h4 class="text-warning">{formatPercentage(validationData.calibration_metrics.reliability)}</h4>
                                        <p class="text-muted mb-0">Reliability</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h4 class="text-primary">{formatPercentage(validationData.calibration_metrics.sharpness)}</h4>
                                        <p class="text-muted mb-0">Sharpness</p>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <h5 class="text-danger">{formatNumber(validationData.calibration_metrics.expected_calibration_error)}</h5>
                                <p class="text-muted mb-0">Expected Calibration Error</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accuracy by Statistic -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar text-primary me-2"></i>Accuracy by Statistic
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Statistic</th>
                                            <th>Accuracy</th>
                                            <th>Sample Size</th>
                                            <th>95% CI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each Object.entries(validationData.accuracy_by_stat) as [stat, data]}
                                            <tr>
                                                <td class="fw-medium text-capitalize">{stat}</td>
                                                <td>
                                                    <span class="{getBadgeClass(data.accuracy)}">{formatPercentage(data.accuracy)}</span>
                                                </td>
                                                <td class="text-muted">{data.sample_size}</td>
                                                <td class="text-muted small">
                                                    [{formatPercentage(data.confidence_interval[0])}, {formatPercentage(data.confidence_interval[1])}]
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

            <!-- Model Comparison -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-code-branch text-primary me-2"></i>Model Comparison
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Model Type</th>
                                            <th>Accuracy</th>
                                            <th>Speed</th>
                                            <th>Complexity</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each Object.entries(validationData.model_comparison) as [model, data]}
                                            <tr>
                                                <td class="fw-medium text-capitalize">{model.replace('_', ' ')}</td>
                                                <td>
                                                    <span class="{getBadgeClass(data.accuracy)}">{formatPercentage(data.accuracy)}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{data.speed === 'Fast' ? 'success' : data.speed === 'Medium' ? 'warning' : 'danger'}-subtle text-{data.speed === 'Fast' ? 'success' : data.speed === 'Medium' ? 'warning' : 'danger'}">
                                                        {data.speed}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{data.complexity === 'Low' ? 'success' : data.complexity === 'Medium' ? 'warning' : 'danger'}-subtle text-{data.complexity === 'Low' ? 'success' : data.complexity === 'Medium' ? 'warning' : 'danger'}">
                                                        {data.complexity}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{data.accuracy >= 0.75 ? 'success' : data.accuracy >= 0.7 ? 'warning' : 'danger'}-subtle text-{data.accuracy >= 0.75 ? 'success' : data.accuracy >= 0.7 ? 'warning' : 'danger'}">
                                                        {data.accuracy >= 0.75 ? 'Excellent' : data.accuracy >= 0.7 ? 'Good' : 'Needs Improvement'}
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

            <!-- Feature Importance -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-weight-hanging text-primary me-2"></i>Feature Importance
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                {#each validationData.feature_importance as feature, index}
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-medium">{feature.feature}</span>
                                            <span class="text-muted">{formatPercentage(feature.importance)}</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div
                                                class="progress-bar bg-{index < 2 ? 'success' : index < 4 ? 'warning' : 'info'}"
                                                style="width: {feature.importance * 100}%"
                                            ></div>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>
