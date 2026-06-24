<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import type {
        HistoricalTestResult,
        HistoricalTestAnalytics,
        HistoricalTestingStatus,
        HistoricalTestingParams,
        HistoricalResultsParams,
        LeaderboardParams
    } from '$lib/api/client';

    // State management
    let activeTab = 'dashboard';
    let loading = false;
    let error = '';
    let mounted = false;

    // Dashboard data
    let testingStatus: HistoricalTestingStatus | null = null;
    let recentResults: HistoricalTestResult[] = [];
    let leaderboard: any[] = [];
    let analytics: HistoricalTestAnalytics | null = null;

    // Testing configuration
    let testingParams: HistoricalTestingParams = {
        stat_types: ['points', 'rebounds', 'assists'],
        min_games: 5,
        test_games: 3,
        player_limit: 20
    };

    // Filters
    let resultsFilters: HistoricalResultsParams = {
        stat_type: '',
        player_id: '',
        min_accuracy: undefined,
        limit: 50,
        sort_by: 'accuracy_percentage',
        sort_order: 'desc'
    };

    let leaderboardFilters: LeaderboardParams = {
        stat_type: '',
        limit: 20
    };

    // UI state
    let isTestingRunning = false;
    let testingProgress = '';
    let selectedTestResult: HistoricalTestResult | null = null;
    let showDetailModal = false;
    let selectedPlayerId = '';
    let selectedPlayerName = '';

    const statTypes = ['points', 'rebounds', 'assists', 'steals', 'blocks'];

    onMount(async () => {
        mounted = true;

        const playerParam = $page.url.searchParams.get('player');
        if (playerParam) {
            selectedPlayerId = playerParam;
            resultsFilters.player_id = playerParam;
            activeTab = 'results';
            await loadSelectedPlayer(playerParam);
        }

        await loadDashboardData();
    });

    async function loadSelectedPlayer(playerId: string) {
        try {
            const response = await api.players.getById(playerId);
            selectedPlayerName = response.data?.athlete_display_name ?? '';
        } catch {
            selectedPlayerName = '';
        }
    }

    async function loadDashboardData() {
        try {
            loading = true;
            error = '';

            const [statusResponse, resultsResponse, leaderboardResponse] = await Promise.allSettled([
                api.wnba.testing.getHistoricalTestingStatus(),
                api.wnba.testing.getHistoricalResults({
                    limit: 10,
                    sort_by: 'tested_at',
                    sort_order: 'desc',
                    ...(selectedPlayerId ? { player_id: selectedPlayerId } : {})
                }),
                api.wnba.testing.getHistoricalLeaderboard({ limit: 10 })
            ]);

            if (statusResponse.status === 'fulfilled') {
                testingStatus = statusResponse.value.data;
            }

            if (resultsResponse.status === 'fulfilled') {
                recentResults = resultsResponse.value.data.results;
                analytics = resultsResponse.value.data.analytics;
            } else {
                console.error('Results loading error:', resultsResponse.reason);
            }

            if (leaderboardResponse.status === 'fulfilled') {
                leaderboard = leaderboardResponse.value.data.leaderboard;
            } else {
                console.error('Leaderboard loading error:', leaderboardResponse.reason);
            }

            const failures = [statusResponse, resultsResponse, leaderboardResponse]
                .filter((response) => response.status === 'rejected');

            if (failures.length === 3) {
                throw failures[0].reason;
            }

        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load dashboard data';
            console.error('Dashboard loading error:', err);
        } finally {
            loading = false;
        }
    }

    async function startHistoricalTesting() {
        try {
            isTestingRunning = true;
            testingProgress = 'Starting historical testing job...';
            error = '';

            const response = await api.wnba.testing.startHistoricalTesting(testingParams);

            if (response.success) {
                testingProgress = `Testing job started! Estimated duration: ${response.data.estimated_duration}`;

                // Refresh status after a delay
                setTimeout(async () => {
                    await loadDashboardData();
                    isTestingRunning = false;
                    testingProgress = '';
                }, 3000);
            } else {
                throw new Error('Failed to start testing job');
            }

        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to start historical testing';
            isTestingRunning = false;
            testingProgress = '';
        }
    }

    async function loadResults() {
        try {
            loading = true;

            // Filter out empty string values
            const filteredParams: any = {};
            Object.entries(resultsFilters).forEach(([key, value]) => {
                if (value !== '' && value !== undefined && value !== null) {
                    filteredParams[key] = value;
                }
            });

            const response = await api.wnba.testing.getHistoricalResults(filteredParams);
            recentResults = response.data.results;
            analytics = response.data.analytics;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load results';
        } finally {
            loading = false;
        }
    }

    async function loadLeaderboard() {
        try {
            loading = true;

            // Filter out empty string values
            const filteredParams: any = {};
            Object.entries(leaderboardFilters).forEach(([key, value]) => {
                if (value !== '' && value !== undefined && value !== null) {
                    filteredParams[key] = value;
                }
            });

            const response = await api.wnba.testing.getHistoricalLeaderboard(filteredParams);
            leaderboard = response.data.leaderboard;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load leaderboard';
        } finally {
            loading = false;
        }
    }

    function getAccuracyBadgeClass(accuracy: number | string): string {
        const acc = safeNumber(accuracy);
        if (acc >= 85) return 'bg-success';
        if (acc >= 75) return 'bg-primary';
        if (acc >= 65) return 'bg-warning';
        return 'bg-danger';
    }

    function getPerformanceLevel(accuracy: number | string): string {
        const acc = safeNumber(accuracy);
        if (acc >= 85) return 'Excellent';
        if (acc >= 75) return 'Good';
        if (acc >= 65) return 'Fair';
        return 'Poor';
    }

    function formatDate(dateString: string): string {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Helper function to safely format numbers
    function safeToFixed(value: any, decimals: number = 1): string {
        const num = typeof value === 'string' ? parseFloat(value) : value;
        return (typeof num === 'number' && !isNaN(num)) ? num.toFixed(decimals) : '0';
    }

    // Helper function to safely get numeric value
    function safeNumber(value: any): number {
        const num = typeof value === 'string' ? parseFloat(value) : value;
        return (typeof num === 'number' && !isNaN(num)) ? num : 0;
    }

    // Show detailed test results
    function showTestDetails(result: HistoricalTestResult) {
        selectedTestResult = result;
        showDetailModal = true;
    }

    function closeDetailModal() {
        showDetailModal = false;
        selectedTestResult = null;
    }

    // Handle keyboard events
    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape' && showDetailModal) {
            closeDetailModal();
        }
    }

    // Reactive statements for filter changes
    $: if (mounted && resultsFilters && activeTab === 'results') {
        loadResults();
    }

    $: if (mounted && leaderboardFilters && activeTab === 'leaderboard') {
        loadLeaderboard();
    }
</script>

<svelte:head>
    <title>Historical Prediction Testing | WNBA Stat Spot</title>
</svelte:head>

<svelte:window on:keydown={handleKeydown} />

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Historical Prediction Testing</h4>
                    <p class="text-muted mb-0">Comprehensive testing and validation of prediction accuracy across historical data</p>
                </div>
            </div>
        </div>

        {#if selectedPlayerId}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info d-flex justify-content-between align-items-center mb-0" role="alert">
                        <div>
                            <i class="fas fa-user me-2"></i>
                            Showing prediction test results
                            {#if selectedPlayerName}
                                for <strong>{selectedPlayerName}</strong>
                            {:else}
                                for player <strong>{selectedPlayerId}</strong>
                            {/if}
                        </div>
                        <a href="/players/{selectedPlayerId}" class="btn btn-sm btn-outline-primary">
                            View Player Profile
                        </a>
                    </div>
                </div>
            </div>
        {/if}

        {#if error}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> {error}
                    </div>
                </div>
            </div>
        {/if}

        <!-- Navigation Tabs -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-pills nav-justified" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link {activeTab === 'dashboard' ? 'active' : ''}"
                                    on:click={() => activeTab = 'dashboard'}
                                    type="button"
                                >
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link {activeTab === 'testing' ? 'active' : ''}"
                                    on:click={() => activeTab = 'testing'}
                                    type="button"
                                >
                                    <i class="fas fa-play me-2"></i>Run Tests
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link {activeTab === 'results' ? 'active' : ''}"
                                    on:click={() => activeTab = 'results'}
                                    type="button"
                                >
                                    <i class="fas fa-chart-line me-2"></i>Results
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link {activeTab === 'leaderboard' ? 'active' : ''}"
                                    on:click={() => activeTab = 'leaderboard'}
                                    type="button"
                                >
                                    <i class="fas fa-trophy me-2"></i>Leaderboard
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link {activeTab === 'analytics' ? 'active' : ''}"
                                    on:click={() => activeTab = 'analytics'}
                                    type="button"
                                >
                                    <i class="fas fa-chart-bar me-2"></i>Analytics
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Tab -->
        {#if activeTab === 'dashboard'}
            <div class="row">
                <!-- Status Overview -->
                {#if testingStatus}
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-vial text-primary fs-18"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">Total Tests</h5>
                                        <h3 class="text-primary mb-0">{testingStatus.overall_stats.total_tests_run.toLocaleString()}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-percentage text-success fs-18"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">Avg Accuracy</h5>
                                        <h3 class="text-success mb-0">{safeToFixed(testingStatus?.overall_stats?.average_accuracy)}%</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-users text-info fs-18"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">Players Tested</h5>
                                        <h3 class="text-info mb-0">{testingStatus?.overall_stats?.total_players_tested || 0}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-star text-warning fs-18"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">Best Accuracy</h5>
                                        <h3 class="text-warning mb-0">{safeToFixed(testingStatus?.overall_stats?.best_accuracy)}%</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Recent Test Batches -->
                {#if testingStatus?.recent_batches && testingStatus.recent_batches.length > 0}
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history text-primary me-2"></i>Recent Test Batches
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Batch ID</th>
                                                <th>Type</th>
                                                <th>Tests</th>
                                                <th>Accuracy</th>
                                                <th>Started</th>
                                                <th>Completed</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each testingStatus.recent_batches as batch}
                                                <tr>
                                                    <td>
                                                        <code class="text-muted">{batch.test_batch_id.split('_').slice(-1)[0]}</code>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            {batch.test_type}
                                                        </span>
                                                    </td>
                                                    <td>{batch.total_tests}</td>
                                                    <td>
                                                        <span class="badge {getAccuracyBadgeClass(batch.avg_accuracy)}">
                                                            {safeToFixed(batch.avg_accuracy)}%
                                                        </span>
                                                    </td>
                                                    <td>{formatDate(batch.started_at)}</td>
                                                    <td>{formatDate(batch.completed_at)}</td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Top Performers Preview -->
                {#if leaderboard.length > 0}
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-trophy text-warning me-2"></i>Top Performers
                                    </h5>
                                    <button
                                        class="btn btn-sm btn-outline-primary"
                                        on:click={() => activeTab = 'leaderboard'}
                                    >
                                        View All
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Rank</th>
                                                <th>Player</th>
                                                <th>Stat</th>
                                                <th>Avg Accuracy</th>
                                                <th>Tests</th>
                                                <th>Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each leaderboard.slice(0, 5) as player, index}
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-warning text-dark">#{index + 1}</span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <h6 class="mb-0 fw-medium">{player.player_name}</h6>
                                                            <small class="text-muted">{player.player_position || 'N/A'}</small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info-subtle text-info">
                                                            {player.stat_type}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge {getAccuracyBadgeClass(player.avg_accuracy)}">
                                                            {safeToFixed(player.avg_accuracy)}%
                                                        </span>
                                                    </td>
                                                    <td>{player.test_count}</td>
                                                    <td>
                                                        <span class="text-muted">
                                                            {getPerformanceLevel(player.avg_accuracy)}
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
                {/if}
            </div>
        {/if}

        <!-- Testing Tab -->
        {#if activeTab === 'testing'}
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cogs text-primary me-2"></i>Testing Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Statistics to Test</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        {#each statTypes as stat}
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="stat-{stat}"
                                                    bind:group={testingParams.stat_types}
                                                    value={stat}
                                                />
                                                <label class="form-check-label" for="stat-{stat}">
                                                    {stat.charAt(0).toUpperCase() + stat.slice(1)}
                                                </label>
                                            </div>
                                        {/each}
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label for="min-games" class="form-label">Min Games Required</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="min-games"
                                        bind:value={testingParams.min_games}
                                        min="3"
                                        max="20"
                                    />
                                </div>

                                <div class="col-md-3">
                                    <label for="test-games" class="form-label">Games to Test</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="test-games"
                                        bind:value={testingParams.test_games}
                                        min="1"
                                        max="10"
                                    />
                                </div>

                                <div class="col-md-6">
                                    <label for="player-limit" class="form-label">Player Limit (optional)</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="player-limit"
                                        bind:value={testingParams.player_limit}
                                        min="1"
                                        max="100"
                                        placeholder="Leave empty for all players"
                                    />
                                    <div class="form-text">Limit testing to specific number of players for faster results</div>
                                </div>

                                <div class="col-12">
                                    <button
                                        class="btn btn-primary btn-lg"
                                        on:click={startHistoricalTesting}
                                        disabled={isTestingRunning || !testingParams.stat_types?.length}
                                    >
                                        {#if isTestingRunning}
                                            <span class="spinner-border spinner-border-sm me-2"></span>
                                            Running Tests...
                                        {:else}
                                            <i class="fas fa-play me-2"></i>
                                            Start Historical Testing
                                        {/if}
                                    </button>
                                </div>

                                {#if testingProgress}
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            {testingProgress}
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle text-info me-2"></i>Testing Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 class="fw-semibold">How it works:</h6>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Tests prediction accuracy against historical game data
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Generates multiple betting lines around season averages
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Compares predictions to actual game outcomes
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Provides detailed accuracy metrics and insights
                                    </li>
                                </ul>
                            </div>

                            <div class="mb-3">
                                <h6 class="fw-semibold">Estimated Test Count:</h6>
                                <p class="text-muted mb-0">
                                    {(testingParams.player_limit || 50) * (testingParams.stat_types?.length || 0)} tests
                                </p>
                            </div>

                            <div>
                                <h6 class="fw-semibold">Performance Grades:</h6>
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex justify-content-between">
                                        <span class="badge bg-success">85%+</span>
                                        <span class="text-muted">Excellent</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="badge bg-primary">75-84%</span>
                                        <span class="text-muted">Good</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="badge bg-warning">65-74%</span>
                                        <span class="text-muted">Fair</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="badge bg-danger">Below 65%</span>
                                        <span class="text-muted">Poor</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}

        <!-- Results Tab -->
        {#if activeTab === 'results'}
            <div class="row">
                <!-- Filters -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-filter text-primary me-2"></i>Filter Results
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="filter-stat" class="form-label">Statistic</label>
                                    <select class="form-select" id="filter-stat" bind:value={resultsFilters.stat_type}>
                                        <option value="">All Statistics</option>
                                        {#each statTypes as stat}
                                            <option value={stat}>{stat.charAt(0).toUpperCase() + stat.slice(1)}</option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="filter-accuracy" class="form-label">Min Accuracy (%)</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="filter-accuracy"
                                        bind:value={resultsFilters.min_accuracy}
                                        min="0"
                                        max="100"
                                        placeholder="Any accuracy"
                                    />
                                </div>

                                <div class="col-md-3">
                                    <label for="filter-sort" class="form-label">Sort By</label>
                                    <select class="form-select" id="filter-sort" bind:value={resultsFilters.sort_by}>
                                        <option value="accuracy_percentage">Accuracy</option>
                                        <option value="tested_at">Test Date</option>
                                        <option value="player_name">Player Name</option>
                                        <option value="stat_type">Statistic</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="filter-order" class="form-label">Order</label>
                                    <select class="form-select" id="filter-order" bind:value={resultsFilters.sort_order}>
                                        <option value="desc">Descending</option>
                                        <option value="asc">Ascending</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-table text-primary me-2"></i>Test Results ({recentResults.length})
                            </h5>
                        </div>
                        <div class="card-body">
                            {#if loading}
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 mb-0">Loading test results...</p>
                                </div>
                            {:else if recentResults.length === 0}
                                <div class="text-center py-5">
                                    <i class="fas fa-search text-muted fs-48 mb-3"></i>
                                    <h5 class="text-muted">No test results found</h5>
                                    <p class="text-muted mb-0">Try adjusting your filters or run some tests first</p>
                                </div>
                            {:else}
                                <div class="table-responsive">
                                    <table class="table table-hover table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Player</th>
                                                <th>Stat</th>
                                                <th>Accuracy</th>
                                                <th>Tests</th>
                                                <th>Sample Size</th>
                                                <th>Quality</th>
                                                <th>Tested</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each recentResults as result}
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <h6 class="mb-0 fw-medium">{result.player_name}</h6>
                                                            <small class="text-muted">{result.player_position || 'N/A'}</small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info-subtle text-info">
                                                            {result.stat_type}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge {getAccuracyBadgeClass(result.accuracy_percentage)}">
                                                            {safeToFixed(result.accuracy_percentage)}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">
                                                            {result.correct_predictions}/{result.total_predictions}
                                                        </span>
                                                    </td>
                                                    <td>{result.sample_size}</td>
                                                    <td>
                                                        {#if result.data_quality_score}
                                                            <span class="badge bg-secondary">
                                                                {safeToFixed(result.data_quality_score * 100, 0)}%
                                                            </span>
                                                        {:else}
                                                            <span class="text-muted">N/A</span>
                                                        {/if}
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            {formatDate(result.tested_at)}
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <button
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="View Details"
                                                            on:click={() => showTestDetails(result)}
                                                        >
                                                            <i class="fas fa-eye"></i>
                                                        </button>
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
        {/if}

        <!-- Leaderboard Tab -->
        {#if activeTab === 'leaderboard'}
            <div class="row">
                <!-- Leaderboard Filters -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-filter text-primary me-2"></i>Leaderboard Filters
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="leaderboard-stat" class="form-label">Statistic</label>
                                    <select class="form-select" id="leaderboard-stat" bind:value={leaderboardFilters.stat_type}>
                                        <option value="">All Statistics</option>
                                        {#each statTypes as stat}
                                            <option value={stat}>{stat.charAt(0).toUpperCase() + stat.slice(1)}</option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="leaderboard-limit" class="form-label">Results Limit</label>
                                    <select class="form-select" id="leaderboard-limit" bind:value={leaderboardFilters.limit}>
                                        <option value={10}>Top 10</option>
                                        <option value={20}>Top 20</option>
                                        <option value={50}>Top 50</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard Table -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-trophy text-warning me-2"></i>Prediction Accuracy Leaderboard
                            </h5>
                        </div>
                        <div class="card-body">
                            {#if loading}
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 mb-0">Loading leaderboard...</p>
                                </div>
                            {:else if leaderboard.length === 0}
                                <div class="text-center py-5">
                                    <i class="fas fa-trophy text-muted fs-48 mb-3"></i>
                                    <h5 class="text-muted">No leaderboard data available</h5>
                                    <p class="text-muted mb-0">Run some tests to see the top performers</p>
                                </div>
                            {:else}
                                <div class="table-responsive">
                                    <table class="table table-hover table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Rank</th>
                                                <th>Player</th>
                                                <th>Stat</th>
                                                <th>Avg Accuracy</th>
                                                <th>Best Accuracy</th>
                                                <th>Tests</th>
                                                <th>Sample Size</th>
                                                <th>Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each leaderboard as player, index}
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            {#if index < 3}
                                                                <i class="fas fa-medal text-warning me-2"></i>
                                                            {/if}
                                                            <span class="badge bg-warning text-dark">#{index + 1}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <h6 class="mb-0 fw-medium">{player.player_name}</h6>
                                                            <small class="text-muted">{player.player_position || 'N/A'}</small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info-subtle text-info">
                                                            {player.stat_type}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge {getAccuracyBadgeClass(player.avg_accuracy)}">
                                                            {safeToFixed(player.avg_accuracy)}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="text-success fw-medium">
                                                            {safeToFixed(player.best_accuracy)}%
                                                        </span>
                                                    </td>
                                                    <td>{player.test_count}</td>
                                                    <td>{Math.round(safeNumber(player.avg_sample_size))}</td>
                                                    <td>
                                                        <span class="text-muted">
                                                            {getPerformanceLevel(player.avg_accuracy)}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button
                                                                class="btn btn-sm btn-outline-primary"
                                                                title="View Details"
                                                                on:click={() => showTestDetails(player)}
                                                            >
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
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
        {/if}

        <!-- Analytics Tab -->
        {#if activeTab === 'analytics' && analytics}
            <div class="row">
                <!-- Stat Performance -->
                {#if analytics.stat_performance?.length > 0}
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-bar text-primary me-2"></i>Performance by Statistic
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Stat</th>
                                                <th>Avg Accuracy</th>
                                                <th>Tests</th>
                                                <th>Best</th>
                                                <th>Worst</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each analytics.stat_performance as stat}
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-info-subtle text-info">
                                                            {stat.stat_type}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge {getAccuracyBadgeClass(stat.avg_accuracy)}">
                                                            {safeToFixed(stat.avg_accuracy)}%
                                                        </span>
                                                    </td>
                                                    <td>{stat.test_count}</td>
                                                    <td class="text-success">{safeToFixed(stat.best_accuracy)}%</td>
                                                    <td class="text-danger">{safeToFixed(stat.worst_accuracy)}%</td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Accuracy Distribution -->
                {#if analytics.summary_stats?.accuracy_distribution}
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie text-primary me-2"></i>Accuracy Distribution
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-column gap-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-success">Excellent (85%+)</span>
                                        <span class="fw-medium">{analytics.summary_stats.accuracy_distribution.excellent}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-primary">Good (75-84%)</span>
                                        <span class="fw-medium">{analytics.summary_stats.accuracy_distribution.good}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-warning">Fair (65-74%)</span>
                                        <span class="fw-medium">{analytics.summary_stats.accuracy_distribution.fair}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-danger">Poor (Below 65%)</span>
                                        <span class="fw-medium">{analytics.summary_stats.accuracy_distribution.poor}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Summary Stats -->
                {#if analytics.summary_stats}
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle text-primary me-2"></i>Summary Statistics
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <h3 class="text-primary">{analytics.summary_stats.total_tests.toLocaleString()}</h3>
                                            <p class="text-muted mb-0">Total Tests</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <h3 class="text-success">{analytics.summary_stats.unique_players}</h3>
                                            <p class="text-muted mb-0">Unique Players</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <h3 class="text-info">
                                                {Object.keys(analytics.summary_stats.avg_accuracy_by_stat || {}).length}
                                            </h3>
                                            <p class="text-muted mb-0">Statistics Tested</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                {/if}
            </div>
        {/if}
    </div>
</DefaultLayout>

<!-- Detailed Test Results Modal -->
{#if showDetailModal && selectedTestResult}
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Detailed Test Results: {selectedTestResult.player_name}
                    </h5>
                    <button type="button" class="btn-close" on:click={closeDetailModal}></button>
                </div>
                <div class="modal-body">
                    <!-- Player & Test Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-user text-primary me-2"></i>Player Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>Name:</strong><br>
                                            <span class="text-muted">{selectedTestResult.player_name}</span>
                                        </div>
                                        <div class="col-6">
                                            <strong>Position:</strong><br>
                                            <span class="badge bg-info-subtle text-info">
                                                {selectedTestResult.player_position || 'N/A'}
                                            </span>
                                        </div>
                                        <div class="col-6 mt-2">
                                            <strong>Statistic:</strong><br>
                                            <span class="badge bg-primary-subtle text-primary">
                                                {selectedTestResult.stat_type}
                                            </span>
                                        </div>
                                        <div class="col-6 mt-2">
                                            <strong>Season Average:</strong><br>
                                            <span class="text-muted">{safeToFixed(selectedTestResult.season_average)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-cogs text-success me-2"></i>Test Parameters
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>Test Games:</strong><br>
                                            <span class="text-muted">{selectedTestResult.test_games}</span>
                                        </div>
                                        <div class="col-6">
                                            <strong>Sample Size:</strong><br>
                                            <span class="text-muted">{selectedTestResult.sample_size}</span>
                                        </div>
                                        <div class="col-6 mt-2">
                                            <strong>Overall Accuracy:</strong><br>
                                            <span class="badge {getAccuracyBadgeClass(selectedTestResult.accuracy_percentage)}">
                                                {safeToFixed(selectedTestResult.accuracy_percentage)}%
                                            </span>
                                        </div>
                                        <div class="col-6 mt-2">
                                            <strong>Data Quality:</strong><br>
                                            {#if selectedTestResult.data_quality_score}
                                                <span class="badge bg-secondary">
                                                    {safeToFixed(selectedTestResult.data_quality_score * 100, 0)}%
                                                </span>
                                            {:else}
                                                <span class="text-muted">N/A</span>
                                            {/if}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actual Game Results -->
                    {#if selectedTestResult.actual_game_results && selectedTestResult.actual_game_results.length > 0}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-basketball-ball text-warning me-2"></i>Actual Game Results ({selectedTestResult.actual_game_results.length} games)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Game #</th>
                                                <th>Date</th>
                                                <th>Actual {selectedTestResult.stat_type.charAt(0).toUpperCase() + selectedTestResult.stat_type.slice(1)}</th>
                                                <th>vs Season Avg</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each selectedTestResult.actual_game_results as gameResult}
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-secondary">#{gameResult.game_number}</span>
                                                    </td>
                                                    <td>{gameResult.date}</td>
                                                    <td>
                                                        <strong class="text-primary">{gameResult.actual_value}</strong>
                                                    </td>
                                                    <td>
                                                        {#if gameResult.actual_value > safeNumber(selectedTestResult.season_average)}
                                                            <span class="text-success">
                                                                <i class="fas fa-arrow-up me-1"></i>
                                                                +{safeToFixed(gameResult.actual_value - safeNumber(selectedTestResult.season_average))}
                                                            </span>
                                                        {:else if gameResult.actual_value < safeNumber(selectedTestResult.season_average)}
                                                            <span class="text-danger">
                                                                <i class="fas fa-arrow-down me-1"></i>
                                                                {safeToFixed(gameResult.actual_value - safeNumber(selectedTestResult.season_average))}
                                                            </span>
                                                        {:else}
                                                            <span class="text-muted">
                                                                <i class="fas fa-equals me-1"></i>
                                                                Even
                                                            </span>
                                                        {/if}
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    {:else}
                        <div class="card mb-4">
                            <div class="card-body text-center">
                                <i class="fas fa-info-circle text-muted fs-24 mb-2"></i>
                                <p class="text-muted mb-0">No detailed game results available for this test.</p>
                            </div>
                        </div>
                    {/if}

                    <!-- Betting Lines Results -->
                    {#if selectedTestResult.line_results && selectedTestResult.line_results.length > 0}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-chart-bar text-info me-2"></i>Betting Lines Performance ({selectedTestResult.line_results.length} lines tested)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Line</th>
                                                <th>Recommendation</th>
                                                <th>Predicted Value</th>
                                                <th>Confidence</th>
                                                <th>Prob Over</th>
                                                <th>Prob Under</th>
                                                <th>Accuracy</th>
                                                <th>Correct/Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each selectedTestResult.line_results as lineResult}
                                                <tr>
                                                    <td>
                                                        <strong class="text-primary">{lineResult.line}</strong>
                                                    </td>
                                                    <td>
                                                        {#if lineResult.recommendation === 'over'}
                                                            <span class="badge bg-success">Over</span>
                                                        {:else if lineResult.recommendation === 'under'}
                                                            <span class="badge bg-danger">Under</span>
                                                        {:else}
                                                            <span class="badge bg-secondary">Avoid</span>
                                                        {/if}
                                                    </td>
                                                    <td>{safeToFixed(lineResult.predicted_value)}</td>
                                                    <td>{safeToFixed(lineResult.confidence * 100, 0)}%</td>
                                                    <td>{safeToFixed(lineResult.probability_over * 100, 1)}%</td>
                                                    <td>{safeToFixed(lineResult.probability_under * 100, 1)}%</td>
                                                    <td>
                                                        <span class="badge {getAccuracyBadgeClass(lineResult.accuracy_percentage)}">
                                                            {safeToFixed(lineResult.accuracy_percentage)}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">
                                                            {lineResult.correct_predictions}/{lineResult.total_predictions}
                                                        </span>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    {:else}
                        <div class="card mb-4">
                            <div class="card-body text-center">
                                <i class="fas fa-info-circle text-muted fs-24 mb-2"></i>
                                <p class="text-muted mb-0">No betting line results available for this test.</p>
                            </div>
                        </div>
                    {/if}

                    <!-- Game-by-Game Predictions -->
                    {#if selectedTestResult.line_results && selectedTestResult.line_results.length > 0 && selectedTestResult.line_results.some(lr => lr.predictions && lr.predictions.length > 0)}
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-list text-success me-2"></i>Game-by-Game Predictions
                                </h6>
                            </div>
                            <div class="card-body">
                                {#each selectedTestResult.line_results as lineResult, lineIndex}
                                    {#if lineResult.predictions && lineResult.predictions.length > 0}
                                        <div class="mb-4">
                                            <h6 class="fw-semibold">
                                                Line {lineResult.line}
                                                <span class="badge {getAccuracyBadgeClass(lineResult.accuracy_percentage)} ms-2">
                                                    {safeToFixed(lineResult.accuracy_percentage)}% Accuracy
                                                </span>
                                            </h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Game</th>
                                                            <th>Actual Value</th>
                                                            <th>Actual Result</th>
                                                            <th>Predicted Result</th>
                                                            <th>Correct?</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {#each lineResult.predictions as prediction}
                                                            <tr class="{prediction.correct ? 'table-success' : 'table-danger'}">
                                                                <td>
                                                                    <span class="badge bg-secondary">#{prediction.game_number}</span>
                                                                </td>
                                                                <td>
                                                                    <strong>{prediction.actual_value}</strong>
                                                                </td>
                                                                <td>
                                                                    <span class="badge {prediction.actual_result === 'over' ? 'bg-success' : 'bg-danger'}">
                                                                        {prediction.actual_result}
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge {prediction.predicted_result === 'over' ? 'bg-success' : 'bg-danger'}">
                                                                        {prediction.predicted_result}
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    {#if prediction.correct}
                                                                        <i class="fas fa-check text-success"></i>
                                                                        <span class="text-success ms-1">Correct</span>
                                                                    {:else}
                                                                        <i class="fas fa-times text-danger"></i>
                                                                        <span class="text-danger ms-1">Wrong</span>
                                                                    {/if}
                                                                </td>
                                                            </tr>
                                                        {/each}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    {/if}
                                {/each}
                            </div>
                        </div>
                    {:else}
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-info-circle text-muted fs-24 mb-2"></i>
                                <p class="text-muted mb-0">No detailed game-by-game predictions available for this test.</p>
                            </div>
                        </div>
                    {/if}

                    <!-- Insights -->
                    {#if selectedTestResult.insights && selectedTestResult.insights.length > 0}
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-lightbulb text-warning me-2"></i>Insights
                                </h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    {#each selectedTestResult.insights as insight}
                                        <li class="mb-2">
                                            <i class="fas fa-arrow-right text-muted me-2"></i>
                                            {insight}
                                        </li>
                                    {/each}
                                </ul>
                            </div>
                        </div>
                    {/if}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" on:click={closeDetailModal}>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}

<style>
    .nav-pills .nav-link {
        border-radius: 0.375rem;
        margin: 0 0.25rem;
    }

    .nav-pills .nav-link.active {
        background-color: var(--bs-primary);
        border-color: var(--bs-primary);
    }

    .table th {
        border-top: none;
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--bs-gray-700);
    }

    .badge {
        font-size: 0.75rem;
    }

    .card-title {
        font-size: 1rem;
        font-weight: 600;
    }

    .avatar-sm {
        width: 2.5rem;
        height: 2.5rem;
    }

    .fs-18 {
        font-size: 1.125rem;
    }

    .fs-48 {
        font-size: 3rem;
    }
</style>
