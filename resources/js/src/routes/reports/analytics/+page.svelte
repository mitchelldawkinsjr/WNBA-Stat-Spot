<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import type { Player, Team, PlayerAnalytics, TeamAnalytics, ModelValidation } from '$lib/api/client';

    let players: Player[] = [];
    let teams: Team[] = [];
    let selectedPlayer: string = '';
    let selectedTeam: string = '';
    let playerAnalytics: PlayerAnalytics | null = null;
    let teamAnalytics: TeamAnalytics | null = null;
    let modelValidation: ModelValidation | null = null;
    let loading = true;
    let analyticsLoading = false;
    let error = '';

    let validationData: any = null;

    // Filter options
    let selectedStatType = '';
    let selectedPlayerCategory = '';
    let selectedSeason = new Date().getFullYear();
    let selectedValidationType = '';

    const statTypes = [
        { value: 'points', label: 'Points' },
        { value: 'rebounds', label: 'Rebounds' },
        { value: 'assists', label: 'Assists' },
        { value: 'steals', label: 'Steals' },
        { value: 'blocks', label: 'Blocks' },
        { value: 'three_pointers', label: 'Three Pointers' }
    ];

    const playerCategories = [
        { value: 'star', label: 'Star Players' },
        { value: 'role', label: 'Role Players' },
        { value: 'rookie', label: 'Rookies' },
        { value: 'veteran', label: 'Veterans' }
    ];

    const validationTypes = [
        { value: 'accuracy', label: 'Accuracy Analysis' },
        { value: 'calibration', label: 'Calibration Analysis' },
        { value: 'bias', label: 'Bias Analysis' },
        { value: 'performance', label: 'Performance Analysis' }
    ];

    onMount(async () => {
        try {
            const [playersResponse, teamsResponse] = await Promise.all([
                api.players.getAll(),
                api.teams.getAll()
            ]);

            players = playersResponse.data.data;
            teams = teamsResponse.data.data;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load data';
        } finally {
            loading = false;
        }

        await loadValidationData();
    });

    async function loadValidationData() {
        try {
            loading = true;
            error = '';

            const params: any = {};
            if (selectedStatType) params.stat_type = selectedStatType;
            if (selectedPlayerCategory) params.player_category = selectedPlayerCategory;
            if (selectedSeason) params.season = selectedSeason;
            if (selectedValidationType) params.validation_type = selectedValidationType;

            const response = await api.wnba.validation.getSummary(params);
            validationData = response.data?.results || null;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load validation data';
            console.error('Validation data error:', err);
        } finally {
            loading = false;
        }
    }

    async function applyFilters() {
        await loadValidationData();
    }

    function clearFilters() {
        selectedStatType = '';
        selectedPlayerCategory = '';
        selectedSeason = new Date().getFullYear();
        selectedValidationType = '';
        loadValidationData();
    }

    function formatPercentage(value: number): string {
        return `${((value || 0) * 100).toFixed(1)}%`;
    }

    function formatNumber(value: number, decimals = 2): string {
        return (value || 0).toFixed(decimals);
    }

    function getPerformanceColor(value: number, threshold = 0.75): string {
        if (value >= threshold) return 'text-success';
        if (value >= threshold * 0.8) return 'text-warning';
        return 'text-danger';
    }

    function getBadgeClass(value: number, threshold = 0.75): string {
        if (value >= threshold) return 'bg-success-subtle text-success';
        if (value >= threshold * 0.8) return 'bg-warning-subtle text-warning';
        return 'bg-danger-subtle text-danger';
    }

    function getAccuracy(data: any): number {
        return (data as any).accuracy || 0;
    }

    function getSampleSize(data: any): number {
        return (data as any).sample_size || 0;
    }

    async function analyzePlayer() {
        if (!selectedPlayer) return;

        analyticsLoading = true;
        try {
            const response = await api.wnba.analytics.getPlayer(selectedPlayer);
            playerAnalytics = response.data;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to analyze player';
        } finally {
            analyticsLoading = false;
        }
    }

    async function analyzeTeam() {
        if (!selectedTeam) return;

        analyticsLoading = true;
        try {
            const response = await api.wnba.analytics.getTeam(selectedTeam);
            teamAnalytics = response.data;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to analyze team';
        } finally {
            analyticsLoading = false;
        }
    }
</script>

<svelte:head>
    <title>Analytics Report | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Analytics Report</h4>
                    <p class="text-muted mb-0">Model validation and performance analytics for WNBA predictions</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter text-primary me-2"></i>Analysis Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="stat-type" class="form-label">Statistic Type</label>
                                <select
                                    id="stat-type"
                                    bind:value={selectedStatType}
                                    class="form-select"
                                >
                                    <option value="">All Statistics</option>
                                    {#each statTypes as stat}
                                        <option value={stat.value}>{stat.label}</option>
                                    {/each}
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="player-category" class="form-label">Player Category</label>
                                <select
                                    id="player-category"
                                    bind:value={selectedPlayerCategory}
                                    class="form-select"
                                >
                                    <option value="">All Players</option>
                                    {#each playerCategories as category}
                                        <option value={category.value}>{category.label}</option>
                                    {/each}
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="season" class="form-label">Season</label>
                                <select
                                    id="season"
                                    bind:value={selectedSeason}
                                    class="form-select"
                                >
                                    <option value={2026}>2026</option>
                                    <option value={2025}>2025</option>
                                    <option value={2024}>2024</option>
                                    <option value={2023}>2023</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="validation-type" class="form-label">Analysis Type</label>
                                <select
                                    id="validation-type"
                                    bind:value={selectedValidationType}
                                    class="form-select"
                                >
                                    <option value="">All Types</option>
                                    {#each validationTypes as type}
                                        <option value={type.value}>{type.label}</option>
                                    {/each}
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button
                                        on:click={applyFilters}
                                        class="btn btn-primary flex-fill"
                                        disabled={loading}
                                    >
                                        {#if loading}
                                            <span class="spinner-border spinner-border-sm me-2"></span>
                                        {:else}
                                            <i class="fas fa-search me-2"></i>
                                        {/if}
                                        Analyze
                                    </button>
                                    <button
                                        on:click={clearFilters}
                                        class="btn btn-outline-secondary"
                                        disabled={loading}
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
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
            <!-- Overall Performance -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line text-primary me-2"></i>Overall Performance
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class={getPerformanceColor(validationData.overall_performance?.accuracy_score || 0)}>{formatPercentage(validationData.overall_performance?.accuracy_score || 0)}</h3>
                                        <p class="text-muted mb-0">Accuracy Score</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class={getPerformanceColor(validationData.overall_performance?.precision || 0)}>{formatPercentage(validationData.overall_performance?.precision || 0)}</h3>
                                        <p class="text-muted mb-0">Precision</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class={getPerformanceColor(validationData.overall_performance?.recall || 0)}>{formatPercentage(validationData.overall_performance?.recall || 0)}</h3>
                                        <p class="text-muted mb-0">Recall</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class={getPerformanceColor(validationData.overall_performance?.f1_score || 0)}>{formatPercentage(validationData.overall_performance?.f1_score || 0)}</h3>
                                        <p class="text-muted mb-0">F1 Score</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance by Category -->
            {#if validationData.performance_by_category}
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-users text-primary me-2"></i>Performance by Player Category
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Category</th>
                                                <th>Accuracy</th>
                                                <th>Sample Size</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each Object.entries(validationData.performance_by_category || {}) as [category, data]}
                                                <tr>
                                                    <td>
                                                        <span class="fw-medium text-capitalize">{category.replace('_', ' ')}</span>
                                                    </td>
                                                    <td>
                                                        <span class="{getPerformanceColor(getAccuracy(data))}">{formatPercentage(getAccuracy(data))}</span>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">{getSampleSize(data)}</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge {getBadgeClass(getAccuracy(data))}">
                                                            {getAccuracy(data) >= 0.75 ? 'Good' : getAccuracy(data) >= 0.6 ? 'Fair' : 'Poor'}
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
            {/if}

            <!-- Accuracy Metrics -->
            {#if validationData.accuracy_metrics}
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bullseye text-primary me-2"></i>Accuracy Metrics
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h4 class="text-primary">{formatNumber(validationData.accuracy_metrics.mean_absolute_error)}</h4>
                                            <p class="text-muted mb-0">Mean Absolute Error</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h4 class="text-info">{formatNumber(validationData.accuracy_metrics.root_mean_square_error)}</h4>
                                            <p class="text-muted mb-0">RMSE</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h4 class="text-warning">{formatNumber(validationData.accuracy_metrics.mean_absolute_percentage_error)}%</h4>
                                            <p class="text-muted mb-0">MAPE</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h4 class="text-success">{formatPercentage(validationData.accuracy_metrics.directional_accuracy)}</h4>
                                            <p class="text-muted mb-0">Directional Accuracy</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calibration Metrics -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-balance-scale text-primary me-2"></i>Calibration Metrics
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h4 class="text-primary">{formatPercentage(validationData.calibration_metrics?.calibration_score || 0)}</h4>
                                            <p class="text-muted mb-0">Calibration Score</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h4 class="text-info">{formatNumber(validationData.calibration_metrics?.brier_score || 0)}</h4>
                                            <p class="text-muted mb-0">Brier Score</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h4 class="text-warning">{formatPercentage(validationData.calibration_metrics?.reliability || 0)}</h4>
                                            <p class="text-muted mb-0">Reliability</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h4 class="text-success">{formatPercentage(validationData.calibration_metrics?.sharpness || 0)}</h4>
                                            <p class="text-muted mb-0">Sharpness</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Recommendations -->
            {#if validationData.recommendations}
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-lightbulb text-primary me-2"></i>Recommendations
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h6 class="text-danger">High Priority</h6>
                                        <ul class="list-unstyled">
                                            {#each (validationData.recommendations.high_priority || []) as rec}
                                                <li class="mb-2">
                                                    <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                                    {rec}
                                                </li>
                                            {/each}
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="text-warning">Medium Priority</h6>
                                        <ul class="list-unstyled">
                                            {#each (validationData.recommendations.medium_priority || []) as rec}
                                                <li class="mb-2">
                                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                    {rec}
                                                </li>
                                            {/each}
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="text-info">Low Priority</h6>
                                        <ul class="list-unstyled">
                                            {#each (validationData.recommendations.low_priority || []) as rec}
                                                <li class="mb-2">
                                                    <i class="fas fa-info-circle text-info me-2"></i>
                                                    {rec}
                                                </li>
                                            {/each}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Metadata -->
            {#if validationData.metadata}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle text-primary me-2"></i>Analysis Metadata
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Last Updated:</strong>
                                        <p class="text-muted mb-0">{new Date(validationData.metadata.last_updated).toLocaleString()}</p>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Sample Size:</strong>
                                        <p class="text-muted mb-0">{validationData.metadata.sample_size}</p>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Validation Period:</strong>
                                        <p class="text-muted mb-0">{validationData.metadata.validation_period}</p>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Model Version:</strong>
                                        <p class="text-muted mb-0">{validationData.metadata.model_version}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}
        {/if}
    </div>
</DefaultLayout>
