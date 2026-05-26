<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import type { Team, Game } from '$lib/api/client';

    let teams: Team[] = [];
    let recentGames: Game[] = [];
    let cacheStats = {
        hit_rate: 0,
        total_keys: 0,
        key_distribution: {
            player: 0,
            team: 0,
            game: 0,
            prediction: 0,
            league: 0,
            analytics: 0
        }
    };
    let loading = true;
    let error = '';

    onMount(async () => {
        try {
            const [teamsResponse, gamesResponse, cacheResponse] = await Promise.all([
                api.teams.getAll(),
                api.games.getAll(),
                api.wnba.cache.getStats()
            ]);

            teams = teamsResponse.data.data;
            recentGames = gamesResponse.data.slice(0, 10);
            cacheStats = cacheResponse.data || cacheStats;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load data';
        } finally {
            loading = false;
        }
    });

    async function analyzeGame(gameId: string) {
        try {
            const response = await api.wnba.analytics.getGame(gameId);
            if (response.success) {
                return response.data;
            }
        } catch (err) {
            console.error('Failed to analyze game:', err);
        }
    }

    async function analyzeTeam(teamId: string) {
        try {
            const response = await api.wnba.analytics.getTeam(teamId);
            if (response.success) {
                return response.data;
            }
        } catch (err) {
            console.error('Failed to analyze team:', err);
        }
    }

    async function clearCaches() {
        if (confirm('Clear all caches? This will improve performance but may slow down the next few requests.')) {
            try {
                const cacheResponse = await api.wnba.cache.getStats();
                cacheStats = cacheResponse.data || cacheStats;
                alert('Cache stats refreshed successfully!');
            } catch (err) {
                alert('Error refreshing cache stats: ' + (err instanceof Error ? err.message : 'Unknown error'));
            }
        }
    }
</script>

<svelte:head>
    <title>WNBA Analytics Reports | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">WNBA Analytics Reports</h4>
                    <p class="text-muted mb-0">Comprehensive analytics and predictions for WNBA player props and team performance</p>
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
                            <p class="mt-2 mb-0">Loading analytics data...</p>
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
        {:else}
            <!-- Quick Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="fas fa-users text-primary fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Teams</h5>
                                    <h3 class="text-primary mb-0">{teams.length}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="fas fa-chart-line text-success fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Cache Hit Rate</h5>
                                    <h3 class="text-success mb-0">{(cacheStats.hit_rate || 0).toFixed(1)}%</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="fas fa-basketball-ball text-info fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Recent Games</h5>
                                    <h3 class="text-info mb-0">{recentGames.length}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="fas fa-database text-warning fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Cache Keys</h5>
                                    <h3 class="text-warning mb-0">{cacheStats.total_keys}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Tools -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-tools text-primary me-2"></i>Analytics Tools
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <a href="/reports/analytics" class="text-decoration-none">
                                        <div class="card bg-primary bg-gradient text-white h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-chart-bar fs-24 me-3"></i>
                                                    <div>
                                                        <h6 class="card-title text-white mb-1">Advanced Analytics</h6>
                                                        <p class="card-text text-white-50 mb-0">Deep dive into player and team metrics</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>

                                <div class="col-md-4">
                                    <a href="/reports/predictions" class="text-decoration-none">
                                        <div class="card bg-success bg-gradient text-white h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-crystal-ball fs-24 me-3"></i>
                                                    <div>
                                                        <h6 class="card-title text-white mb-1">Prediction Engine</h6>
                                                        <p class="card-text text-white-50 mb-0">Generate player prop predictions</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>

                                <div class="col-md-4">
                                    <div class="card bg-warning bg-gradient text-white h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-database fs-24 me-3"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title text-white mb-1">Cache Management</h6>
                                                    <p class="card-text text-white-50 mb-0">Optimize system performance</p>
                                                </div>
                                                <button
                                                    on:click={clearCaches}
                                                    class="btn btn-light btn-sm"
                                                >
                                                    Clear
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Games and Teams -->
            <div class="row">
                <!-- Recent Games -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-basketball-ball text-info me-2"></i>Recent Games
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Game ID</th>
                                            <th>Date</th>
                                            <th>Season</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each recentGames as game}
                                            <tr>
                                                <td>
                                                    <span class="fw-medium">{game.game_id}</span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">{new Date(game.game_date).toLocaleDateString()}</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary">{game.season}</span>
                                                </td>
                                                <td>
                                                    <button
                                                        on:click={() => analyzeGame(game.game_id)}
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        <i class="fas fa-chart-line me-1"></i>
                                                        Analyze
                                                    </button>
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teams Overview -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users text-success me-2"></i>Teams Overview
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Team</th>
                                            <th>Abbreviation</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each teams.slice(0, 8) as team}
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        {#if team.team_logo}
                                                            <img src={team.team_logo} alt={team.team_abbreviation} class="avatar-xs rounded me-2" />
                                                        {/if}
                                                        <span class="fw-medium">{team.team_display_name}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary-subtle text-secondary">{team.team_abbreviation}</span>
                                                </td>
                                                <td>
                                                    <button
                                                        on:click={() => analyzeTeam(team.team_id)}
                                                        class="btn btn-sm btn-outline-success"
                                                    >
                                                        <i class="fas fa-chart-bar me-1"></i>
                                                        Analyze
                                                    </button>
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                            {#if teams.length > 8}
                                <div class="text-center mt-3">
                                    <a href="/reports/teams" class="btn btn-sm btn-outline-secondary">
                                        View All Teams ({teams.length})
                                    </a>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>
