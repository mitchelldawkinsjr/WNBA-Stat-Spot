<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import type { Team } from '$lib/api/client';

    let teams: Team[] = [];
    let searchTerm = '';
    let loading = true;
    let error = '';

    const activeSeasonYear = new Date().getFullYear();

    function hideImage(e: Event) {
        const img = e.target as HTMLImageElement;
        img.style.display = 'none';
    }

    onMount(async () => {
        await loadTeams();
    });

    async function loadTeams() {
        try {
            loading = true;
            const response = await api.teams.getAll({ search: searchTerm || undefined });
            teams = response.data.data;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load teams';
        } finally {
            loading = false;
        }
    }

    async function analyzeTeam(teamId: string) {
        try {
            const response = await api.wnba.analytics.getTeam(teamId.toString());
            if (response.success) {
                return response.data;
            }
        } catch (err) {
            console.error('Failed to analyze team:', err);
        }
    }

    // Debounced search
    let searchTimeout: number;
    $: {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (searchTerm !== undefined) {
                loadTeams();
            }
        }, 500);
    }
</script>

<svelte:head>
    <title>Teams Report | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Teams Report</h4>
                    <p class="text-muted mb-0">Comprehensive overview of all WNBA teams with analytics and performance data</p>
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
                            <p class="mt-2 mb-0">Loading teams data...</p>
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
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="fas fa-users text-primary fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Total Teams</h5>
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
                                    <i class="fas fa-search text-success fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Search Results</h5>
                                    <h3 class="text-success mb-0">{teams.length}</h3>
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
                                    <h5 class="card-title mb-1">Active Season</h5>
                                    <h3 class="text-info mb-0">{activeSeasonYear}</h3>
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
                                    <i class="fas fa-chart-bar text-warning fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Analytics Ready</h5>
                                    <h3 class="text-warning mb-0">{teams.length}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teams Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-users text-primary me-2"></i>All Teams
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input
                                            type="text"
                                            class="form-control"
                                            placeholder="Search teams..."
                                            bind:value={searchTerm}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Team</th>
                                            <th>Abbreviation</th>
                                            <th>Location</th>
                                            <th>Conference</th>
                                            <th>Division</th>
                                            <th>Founded</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each teams as team}
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        {#if team.team_logo}
                                                            <img
                                                                src={team.team_logo}
                                                                alt={team.team_abbreviation}
                                                                class="avatar-sm rounded me-3"
                                                                on:error={hideImage}
                                                            />
                                                        {:else}
                                                            <div class="avatar-sm bg-secondary bg-opacity-10 rounded d-flex align-items-center justify-content-center me-3">
                                                                <i class="fas fa-basketball-ball text-secondary"></i>
                                                            </div>
                                                        {/if}
                                                        <div>
                                                            <h6 class="mb-0 fw-medium">{team.team_display_name}</h6>
                                                            <small class="text-muted">{team.team_name || 'N/A'}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary fw-medium">
                                                        {team.team_abbreviation}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">{team.team_location || 'N/A'}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info-subtle text-info">
                                                        {team.team_conference || 'N/A'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">{team.team_division || 'N/A'}</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">{team.team_founded || 'N/A'}</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button
                                                            on:click={() => analyzeTeam(team.team_id)}
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="Analyze Team"
                                                        >
                                                            <i class="fas fa-chart-bar"></i>
                                                        </button>
                                                        <a
                                                            href="/teams/{team.team_id}"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            title="View Details"
                                                        >
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button
                                                            on:click={() => window.open(`/reports/predictions?team=${team.team_id}`, '_blank')}
                                                            class="btn btn-sm btn-outline-success"
                                                            title="Generate Predictions"
                                                        >
                                                            <i class="fas fa-crystal-ball"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>

                            {#if teams.length === 0}
                                <div class="text-center py-5">
                                    <i class="fas fa-search text-muted fs-48 mb-3"></i>
                                    <h5 class="text-muted">No teams found</h5>
                                    <p class="text-muted mb-0">Try adjusting your search criteria</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>

<style>
    .cursor-pointer {
        cursor: pointer;
    }

    .user-select-none {
        user-select: none;
    }
</style>
