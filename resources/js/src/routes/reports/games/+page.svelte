<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import type { Game, Team } from '$lib/api/client';

    let games: Game[] = [];
    let teams: Team[] = [];
    let filteredGames: Game[] = [];
    let searchTerm = '';
    let selectedSeason = '';
    let selectedTeam = '';
    let sortBy = 'game_date';
    let sortOrder = 'desc';
    let loading = true;
    let error = '';

    const seasons = ['2026', '2025', '2024', '2023'];

    onMount(async () => {
        try {
            const [gamesResponse, teamsResponse] = await Promise.all([
                api.games.getAll(),
                api.teams.getAll()
            ]);

            games = gamesResponse.data;
            teams = teamsResponse.data.data;
            filteredGames = games;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load data';
        } finally {
            loading = false;
        }
    });

    $: {
        // Filter games based on search term, season, and team
        filteredGames = games.filter(game => {
            const matchesSearch = game.game_id.toLowerCase().includes(searchTerm.toLowerCase());
            const matchesSeason = !selectedSeason || game.season === selectedSeason;
            const matchesTeam = !selectedTeam ||
                (game.home_team_id === selectedTeam || game.away_team_id === selectedTeam);

            return matchesSearch && matchesSeason && matchesTeam;
        });

        // Sort games
        filteredGames.sort((a, b) => {
            let aValue = (a as any)[sortBy] || '';
            let bValue = (b as any)[sortBy] || '';

            if (sortBy === 'game_date') {
                aValue = new Date(aValue).getTime();
                bValue = new Date(bValue).getTime();
            } else if (typeof aValue === 'string') {
                aValue = aValue.toLowerCase();
                bValue = bValue.toLowerCase();
            }

            if (sortOrder === 'asc') {
                return aValue < bValue ? -1 : aValue > bValue ? 1 : 0;
            } else {
                return aValue > bValue ? -1 : aValue < bValue ? 1 : 0;
            }
        });
    }

    function handleSort(column: string) {
        if (sortBy === column) {
            sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            sortBy = column;
            sortOrder = 'asc';
        }
    }

    function getSortIcon(column: string): string {
        if (sortBy !== column) return 'fas fa-sort text-muted';
        return sortOrder === 'asc' ? 'fas fa-sort-up text-primary' : 'fas fa-sort-down text-primary';
    }

    function getTeamName(teamId: string): string {
        const team = teams.find(t => t.team_id === teamId);
        return team ? team.team_abbreviation : 'N/A';
    }

    function clearFilters() {
        searchTerm = '';
        selectedSeason = '';
        selectedTeam = '';
    }

    function formatDate(dateString: string): string {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function formatTime(dateString: string): string {
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }

    function getGameStatus(game: Game): { text: string, class: string } {
        const gameDate = new Date(game.game_date);
        const now = new Date();

        if (gameDate > now) {
            return { text: 'Scheduled', class: 'bg-info-subtle text-info' };
        } else if (game.home_team_score !== null && game.away_team_score !== null) {
            return { text: 'Final', class: 'bg-success-subtle text-success' };
        } else {
            return { text: 'In Progress', class: 'bg-warning-subtle text-warning' };
        }
    }

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
</script>

<svelte:head>
    <title>Games Report | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Games Report</h4>
                    <p class="text-muted mb-0">Comprehensive overview of all WNBA games with analytics and performance data</p>
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
                            <p class="mt-2 mb-0">Loading games data...</p>
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
                                    <i class="fas fa-basketball-ball text-primary fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Total Games</h5>
                                    <h3 class="text-primary mb-0">{games.length}</h3>
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
                                    <h5 class="card-title mb-1">Filtered Results</h5>
                                    <h3 class="text-success mb-0">{filteredGames.length}</h3>
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
                                    <i class="fas fa-check-circle text-info fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Completed Games</h5>
                                    <h3 class="text-info mb-0">
                                        {games.filter(g => g.home_team_score !== null && g.home_team_score !== undefined && g.away_team_score !== null && g.away_team_score !== undefined).length}
                                    </h3>
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
                                    <i class="fas fa-calendar text-warning fs-18"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">Upcoming Games</h5>
                                    <h3 class="text-warning mb-0">
                                        {games.filter(g => new Date(g.game_date) > new Date()).length}
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
                                    <label for="search-input" class="form-label">Search Games</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input
                                            id="search-input"
                                            type="text"
                                            class="form-control"
                                            placeholder="Search by game ID..."
                                            bind:value={searchTerm}
                                        />
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label for="season-select" class="form-label">Season</label>
                                    <select
                                        id="season-select"
                                        bind:value={selectedSeason}
                                        class="form-select"
                                    >
                                        <option value="">All Seasons</option>
                                        {#each seasons as season}
                                            <option value={season}>{season}</option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="team-select" class="form-label">Team</label>
                                    <select
                                        id="team-select"
                                        bind:value={selectedTeam}
                                        class="form-select"
                                    >
                                        <option value="">All Teams</option>
                                        {#each teams as team}
                                            <option value={team.team_id}>{team.team_display_name}</option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button
                                        on:click={clearFilters}
                                        class="btn btn-outline-secondary w-100"
                                    >
                                        <i class="fas fa-times me-2"></i>Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Games Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-basketball-ball text-primary me-2"></i>All Games
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th
                                                class="cursor-pointer user-select-none"
                                                on:click={() => handleSort('game_id')}
                                            >
                                                Game ID
                                                <i class="{getSortIcon('game_id')} ms-1"></i>
                                            </th>
                                            <th
                                                class="cursor-pointer user-select-none"
                                                on:click={() => handleSort('game_date')}
                                            >
                                                Date & Time
                                                <i class="{getSortIcon('game_date')} ms-1"></i>
                                            </th>
                                            <th>Matchup</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                            <th
                                                class="cursor-pointer user-select-none"
                                                on:click={() => handleSort('season')}
                                            >
                                                Season
                                                <i class="{getSortIcon('season')} ms-1"></i>
                                            </th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each filteredGames as game}
                                            {@const status = getGameStatus(game)}
                                            <tr>
                                                <td>
                                                    <span class="fw-medium">{game.game_id}</span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="fw-medium">{formatDate(game.game_date)}</div>
                                                        <small class="text-muted">{formatTime(game.game_date)}</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-secondary-subtle text-secondary me-2">
                                                            {getTeamName(game.away_team_id || '')}
                                                        </span>
                                                        <span class="text-muted mx-2">@</span>
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            {getTeamName(game.home_team_id || '')}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    {#if game.away_team_score !== null && game.away_team_score !== undefined && game.home_team_score !== null && game.home_team_score !== undefined}
                                                        <div class="d-flex align-items-center">
                                                            <span class="fw-medium me-2">{game.away_team_score}</span>
                                                            <span class="text-muted mx-1">-</span>
                                                            <span class="fw-medium ms-2">{game.home_team_score}</span>
                                                        </div>
                                                    {:else}
                                                        <span class="text-muted">-</span>
                                                    {/if}
                                                </td>
                                                <td>
                                                    <span class="badge {status.class}">
                                                        {status.text}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info-subtle text-info">
                                                        {game.season}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button
                                                            on:click={() => analyzeGame(game.game_id)}
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="Analyze Game"
                                                        >
                                                            <i class="fas fa-chart-bar"></i>
                                                        </button>
                                                        <a
                                                            href="/games/{game.game_id}"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            title="View Details"
                                                        >
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button
                                                            on:click={() => window.open(`/reports/predictions?game=${game.game_id}`, '_blank')}
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

                            {#if filteredGames.length === 0}
                                <div class="text-center py-5">
                                    <i class="fas fa-search text-muted fs-48 mb-3"></i>
                                    <h5 class="text-muted">No games found</h5>
                                    <p class="text-muted mb-0">Try adjusting your search criteria or filters</p>
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
