<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import type { Game } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";

    let games: Game[] = [];
    let loading = true;
    let error: string | null = null;
    let searchTerm = '';
    let viewMode: 'cards' | 'table' = 'cards';
    let selectedSeason = 2026;

    $: filteredGames = games.filter(game =>
        (game.venue_name && game.venue_name.toLowerCase().includes(searchTerm.toLowerCase())) ||
        (game.venue_city && game.venue_city.toLowerCase().includes(searchTerm.toLowerCase())) ||
        game.season.toLowerCase().includes(searchTerm.toLowerCase()) ||
        game.game_id.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (game.away_team?.abbreviation && game.away_team.abbreviation.toLowerCase().includes(searchTerm.toLowerCase())) ||
        (game.home_team?.abbreviation && game.home_team.abbreviation.toLowerCase().includes(searchTerm.toLowerCase()))
    );

    async function loadGames(season = selectedSeason) {
        try {
            loading = true;
            error = null;
            const response = await api.games.getAll({ season });
            games = response.data;
        } catch (e) {
            error = e instanceof Error ? e.message : 'An error occurred';
        } finally {
            loading = false;
        }
    }

    async function changeSeason(season: number) {
        selectedSeason = season;
        await loadGames(season);
    }

    onMount(() => {
        loadGames();
    });

    function formatDate(dateString: string): string {
        return new Date(dateString).toLocaleDateString('en-US', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function formatTime(dateTimeString: string): string {
        return new Date(dateTimeString).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }

    function formatDateShort(dateString: string): string {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    }

    function formatStatus(status: string | null | undefined): string {
        if (!status) return 'TBD';
        if (status === 'STATUS_FINAL') return 'Final';
        if (status === 'STATUS_SCHEDULED') return 'Scheduled';
        if (status === 'STATUS_IN_PROGRESS') return 'Live';
        return status.replace(/^STATUS_/, '').replaceAll('_', ' ').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function teamAbbr(game: Game, side: 'home' | 'away'): string {
        const team = side === 'home' ? game.home_team : game.away_team;
        return team?.abbreviation ?? team?.name?.slice(0, 3).toUpperCase() ?? 'TBD';
    }
</script>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <a href="/" class="btn btn-outline-primary">
                            ← Back to Dashboard
                        </a>
                    </div>
                    <h4 class="page-title">WNBA Games</h4>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="row mb-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Search games by venue, city, season, or game ID..."
                                bind:value={searchTerm}
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="btn-group w-100 mb-2" role="group" aria-label="Season">
                            <button
                                type="button"
                                class="btn {selectedSeason === 2026 ? 'btn-warning' : 'btn-outline-warning'}"
                                disabled={loading}
                                on:click={() => changeSeason(2026)}
                            >2026</button>
                            <button
                                type="button"
                                class="btn {selectedSeason === 2025 ? 'btn-warning' : 'btn-outline-warning'}"
                                disabled={loading}
                                on:click={() => changeSeason(2025)}
                            >2025</button>
                        </div>
                        <div class="btn-group w-100" role="group">
                            <button
                                type="button"
                                class="btn {viewMode === 'cards' ? 'btn-warning' : 'btn-outline-warning'}"
                                on:click={() => viewMode = 'cards'}
                            >
                                <i class="fas fa-th-large me-1"></i>Cards
                            </button>
                            <button
                                type="button"
                                class="btn {viewMode === 'table' ? 'btn-warning' : 'btn-outline-warning'}"
                                on:click={() => viewMode = 'table'}
                            >
                                <i class="fas fa-table me-1"></i>Table
                            </button>
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
                            <p class="mt-2 mb-0">Loading games...</p>
                        </div>
                    </div>
                </div>
            </div>
        {:else if error}
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <strong>Error:</strong> {error}
                    </div>
                </div>
            </div>
        {:else if viewMode === 'table'}
            <!-- Table View -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Games ({filteredGames.length})</h5>
                        </div>
                        <div class="card-body">
                            {#if filteredGames.length > 0}
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Game</th>
                                                <th>Matchup</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Venue</th>
                                                <th>Location</th>
                                                <th>Status</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each filteredGames as game}
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-xs bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2">
                                                                <i class="fas fa-basketball text-warning fs-12"></i>
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold">{game.game_id}</div>
                                                                <small class="text-muted">{game.season} {game.season_type}</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        {#if game.away_team && game.home_team}
                                                            <div class="d-flex align-items-center justify-content-between">
                                                                <div class="d-flex align-items-center">
                                                                    {#if game.away_team.logo}
                                                                        <img src={game.away_team.logo} alt={game.away_team.abbreviation} class="avatar-xs rounded me-2" />
                                                                    {/if}
                                                                    <span class="fw-medium">{game.away_team.abbreviation}</span>
                                                                    {#if game.final_score?.final && game.away_team.score !== null}
                                                                        <span class="ms-2 badge bg-{game.away_team.winner ? 'success' : 'secondary'}-subtle text-{game.away_team.winner ? 'success' : 'secondary'}">
                                                                            {game.away_team.score}
                                                                        </span>
                                                                    {/if}
                                                                </div>
                                                                <span class="text-muted mx-2">@</span>
                                                                <div class="d-flex align-items-center">
                                                                    {#if game.home_team.logo}
                                                                        <img src={game.home_team.logo} alt={game.home_team.abbreviation} class="avatar-xs rounded me-2" />
                                                                    {/if}
                                                                    <span class="fw-medium">{game.home_team.abbreviation}</span>
                                                                    {#if game.final_score?.final && game.home_team.score !== null}
                                                                        <span class="ms-2 badge bg-{game.home_team.winner ? 'success' : 'secondary'}-subtle text-{game.home_team.winner ? 'success' : 'secondary'}">
                                                                            {game.home_team.score}
                                                                        </span>
                                                                    {/if}
                                                                </div>
                                                            </div>
                                                        {:else}
                                                            <span class="text-muted">Teams TBD</span>
                                                        {/if}
                                                    </td>
                                                    <td>{formatDateShort(game.game_date)}</td>
                                                    <td>
                                                        <small>{formatTime(game.game_date_time)}</small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold">{game.venue_name || 'TBD'}</div>
                                                    </td>
                                                    <td>
                                                        {#if game.venue_city && game.venue_state}
                                                            <small class="text-muted">{game.venue_city}, {game.venue_state}</small>
                                                        {:else}
                                                            <small class="text-muted">TBD</small>
                                                        {/if}
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{game.status_name === 'STATUS_FINAL' ? 'success' : game.status_name === 'STATUS_SCHEDULED' ? 'secondary' : 'warning'}">
                                                            {formatStatus(game.status_name)}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="/games/{game.game_id}" class="btn btn-outline-warning btn-sm me-1" title="View details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="/games/{game.game_id}#box-score" class="btn btn-outline-secondary btn-sm" title="Box score">
                                                            <i class="fas fa-chart-bar"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            {:else}
                                <div class="text-center py-4">
                                    <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-search text-muted fs-24"></i>
                                    </div>
                                    <h5 class="mb-2">No Games Found</h5>
                                    <p class="text-muted mb-0">No games match your search criteria.</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {:else}
            <!-- Card View -->
            <div class="row">
                {#each filteredGames as game}
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-basketball text-warning fs-18"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="card-title mb-1">Game #{game.game_id}</h6>
                                        <p class="text-muted mb-0 small">{game.season} {game.season_type}</p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    {#if game.away_team && game.home_team}
                                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded">
                                            <div class="d-flex align-items-center">
                                                {#if game.away_team.logo}
                                                    <img src={game.away_team.logo} alt={game.away_team.abbreviation} class="avatar-xs rounded me-2" />
                                                {/if}
                                                <span class="fw-semibold">{teamAbbr(game, 'away')}</span>
                                            </div>
                                            <span class="text-muted mx-2">@</span>
                                            <div class="d-flex align-items-center">
                                                {#if game.home_team.logo}
                                                    <img src={game.home_team.logo} alt={game.home_team.abbreviation} class="avatar-xs rounded me-2" />
                                                {/if}
                                                <span class="fw-semibold">{teamAbbr(game, 'home')}</span>
                                            </div>
                                        </div>
                                    {:else}
                                        <div class="text-center p-2 bg-light rounded text-muted">Teams TBD</div>
                                    {/if}
                                </div>

                                <div class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="text-center p-2 bg-light rounded">
                                                <small class="text-muted d-block">Date & Time</small>
                                                <span class="fw-semibold">{formatDate(game.game_date)}</span>
                                                <br>
                                                <small class="text-muted">{formatTime(game.game_date_time)}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {#if game.venue_name}
                                    <div class="mb-3">
                                        <div class="row g-2">
                                            <div class="col-12">
                                                <div class="text-center p-2 bg-light rounded">
                                                    <small class="text-muted d-block">Venue</small>
                                                    <span class="fw-semibold">{game.venue_name}</span>
                                                    {#if game.venue_city && game.venue_state}
                                                        <br>
                                                        <small class="text-muted">{game.venue_city}, {game.venue_state}</small>
                                                    {/if}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                {/if}

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <small class="text-muted d-block">Season</small>
                                            <span class="fw-semibold">{game.season}</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <small class="text-muted d-block">Status</small>
                                            <span class="fw-semibold">{formatStatus(game.status_name)}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <a href="/games/{game.game_id}" class="btn btn-warning btn-sm me-2">
                                        View Details
                                    </a>
                                    <a href="/games/{game.game_id}#box-score" class="btn btn-outline-secondary btn-sm">
                                        Box Score
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>

            {#if filteredGames.length === 0 && searchTerm}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                    <i class="fas fa-search text-muted fs-24"></i>
                                </div>
                                <h5 class="mb-2">No Games Found</h5>
                                <p class="text-muted mb-0">No games match your search criteria.</p>
                            </div>
                        </div>
                    </div>
                </div>
            {:else if games.length === 0}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                    <i class="fas fa-basketball text-muted fs-24"></i>
                                </div>
                                <h5 class="mb-2">No Games Found</h5>
                                <p class="text-muted mb-0">There are currently no games in the database.</p>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}
        {/if}
    </div>
</DefaultLayout>
