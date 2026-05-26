<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import type { Player, Team, PaginatedResponse } from '$lib/api/client';

    let players: Player[] = [];
    let teams: Team[] = [];
    let searchTerm = '';
    let selectedPosition = '';
    let sortBy = 'athlete_display_name';
    let sortOrder = 'asc';
    let loading = true;
    let loadingMore = false;
    let error = '';
    let showAllPlayers = false;

    // Pagination
    let currentPage = 1;
    let totalPages = 1;
    let totalPlayers = 0;
    let hasMorePages = false;

    const positions = ['G', 'F', 'C', 'PG', 'SG', 'SF', 'PF'];

    function hideImage(e: Event) {
        const img = e.target as HTMLImageElement;
        img.style.display = 'none';
    }

    onMount(async () => {
        await loadPlayers();
    });

    async function loadPlayers(page = 1, append = false) {
        try {
            if (!append) {
                loading = true;
            } else {
                loadingMore = true;
            }

            const params: any = {
                page,
                per_page: showAllPlayers ? 1000 : 100,
            };

            if (searchTerm) params.search = searchTerm;
            if (selectedPosition) params.position = selectedPosition;

            const response = await api.players.getAll(params);

            if (append) {
                players = [...players, ...response.data.data];
            } else {
                players = response.data.data;
            }

            currentPage = response.data.meta.current_page;
            totalPages = response.data.meta.last_page;
            totalPlayers = response.data.meta.total;
            hasMorePages = currentPage < totalPages;

        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load players';
        } finally {
            loading = false;
            loadingMore = false;
        }
    }

    async function loadMorePlayers() {
        if (hasMorePages && !loadingMore) {
            await loadPlayers(currentPage + 1, true);
        }
    }

    async function loadAllPlayers() {
        showAllPlayers = true;
        currentPage = 1;
        await loadPlayers(1, false);
    }

    async function applyFilters() {
        currentPage = 1;
        await loadPlayers(1, false);
    }

    function clearFilters() {
        searchTerm = '';
        selectedPosition = '';
        applyFilters();
    }

    async function analyzePlayer(playerId: string) {
        try {
            const response = await api.wnba.analytics.getPlayer(playerId);
            if (response.success) {
                return response.data;
            }
        } catch (err) {
            console.error('Failed to analyze player:', err);
        }
    }

    // Debounced search
    let searchTimeout: number;
    $: {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (searchTerm !== undefined) {
                applyFilters();
            }
        }, 500);
    }

    // Filter changes
    $: {
        if (selectedPosition !== undefined) {
            applyFilters();
        }
    }
</script>

<svelte:head>
    <title>Players Report | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Players Report</h4>
                    <p class="text-muted mb-0">Comprehensive overview of all WNBA players with analytics and performance data</p>
                </div>
            </div>
        </div>

        {#if loading && players.length === 0}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 mb-0">Loading players data...</p>
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
                                    <i class="fas fa-user text-primary fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Total Players</h5>
                                    <h3 class="text-primary mb-0">{totalPlayers}</h3>
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
                                    <i class="fas fa-filter text-success fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">
                                        {showAllPlayers ? 'All Players' : 'Loaded'}
                                    </h5>
                                    <h3 class="text-success mb-0">{players.length}</h3>
                                    {#if !showAllPlayers && hasMorePages}
                                        <small class="text-muted">of {totalPlayers}</small>
                                    {/if}
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
                                    <h5 class="card-title mb-1">Positions</h5>
                                    <h3 class="text-info mb-0">{positions.length}</h3>
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
                                    <h5 class="card-title mb-1">
                                        {showAllPlayers ? 'View Status' : 'Pagination'}
                                    </h5>
                                    <h3 class="text-warning mb-0">
                                        {showAllPlayers ? 'Complete' : `${currentPage} of ${totalPages}`}
                                    </h3>
                                </div>
                            </div>
                        </div>
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
                                <div class="col-md-4">
                                    <label for="search-input" class="form-label">Search Players</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input
                                            id="search-input"
                                            type="text"
                                            class="form-control"
                                            placeholder="Search by name..."
                                            bind:value={searchTerm}
                                        />
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label for="position-select" class="form-label">Position</label>
                                    <select
                                        id="position-select"
                                        bind:value={selectedPosition}
                                        class="form-select"
                                    >
                                        <option value="">All Positions</option>
                                        {#each positions as position}
                                            <option value={position}>{position}</option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        {#if !showAllPlayers && totalPlayers > 100}
                                            <button
                                                on:click={loadAllPlayers}
                                                class="btn btn-success flex-fill"
                                                disabled={loading}
                                            >
                                                {#if loading}
                                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                                {:else}
                                                    <i class="fas fa-list me-2"></i>
                                                {/if}
                                                Load All ({totalPlayers})
                                            </button>
                                        {/if}
                                        <button
                                            on:click={clearFilters}
                                            class="btn btn-outline-secondary"
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

            <!-- Players Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user text-primary me-2"></i>Players ({players.length} of {totalPlayers})
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Player</th>
                                            <th>Position</th>
                                            <th>Height</th>
                                            <th>Weight</th>
                                            <th>Experience</th>
                                            <th>College</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each players as player}
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        {#if player.athlete_headshot_href}
                                                            <img
                                                                src={player.athlete_headshot_href}
                                                                alt={player.athlete_display_name}
                                                                class="avatar-sm rounded-circle me-3"
                                                                on:error={hideImage}
                                                            />
                                                        {:else}
                                                            <div class="avatar-sm bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                <i class="fas fa-user text-secondary"></i>
                                                            </div>
                                                        {/if}
                                                        <div>
                                                            <h6 class="mb-0 fw-medium">{player.athlete_display_name}</h6>
                                                            <small class="text-muted">#{player.athlete_jersey || 'N/A'}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary">
                                                        {player.athlete_position_abbreviation || 'N/A'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">N/A</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">N/A</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">N/A</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">N/A</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button
                                                            on:click={() => analyzePlayer(player.athlete_id)}
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="Analyze Player"
                                                        >
                                                            <i class="fas fa-chart-line"></i>
                                                        </button>
                                                        <a
                                                            href="/players/{player.athlete_id}"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            title="View Details"
                                                        >
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a
                                                            href="/players/{player.athlete_id}/data"
                                                            class="btn btn-sm btn-outline-info"
                                                            title="View Data"
                                                        >
                                                            <i class="fas fa-database"></i>
                                                        </a>
                                                        <button
                                                            on:click={() => window.open(`/reports/predictions?player=${player.athlete_id}`, '_blank')}
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

                            {#if players.length === 0}
                                <div class="text-center py-5">
                                    <i class="fas fa-search text-muted fs-48 mb-3"></i>
                                    <h5 class="text-muted">No players found</h5>
                                    <p class="text-muted mb-0">Try adjusting your search criteria or filters</p>
                                </div>
                            {/if}

                            <!-- Load More Button -->
                            {#if hasMorePages}
                                <div class="text-center mt-4">
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Showing {players.length} of {totalPlayers} players.
                                        {#if !showAllPlayers}
                                            <strong>Click "Load All" above or "Load More" below to see more players.</strong>
                                        {/if}
                                    </div>
                                    <button
                                        on:click={loadMorePlayers}
                                        class="btn btn-primary btn-lg"
                                        disabled={loadingMore}
                                    >
                                        {#if loadingMore}
                                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                            Loading More Players...
                                        {:else}
                                            <i class="fas fa-plus me-2"></i>
                                            Load More Players ({totalPlayers - players.length} remaining)
                                        {/if}
                                    </button>
                                </div>
                            {:else if showAllPlayers}
                                <div class="text-center mt-4">
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>
                                        All {totalPlayers} players are now loaded!
                                    </div>
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
