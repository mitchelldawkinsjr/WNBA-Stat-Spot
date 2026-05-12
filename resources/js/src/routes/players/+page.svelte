<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import LoadingError from '$lib/components/LoadingError.svelte';

    interface Player {
        id: number;
        athlete_id: string;
        athlete_display_name: string;
        athlete_short_name: string;
        athlete_jersey: string | null;
        athlete_headshot_href: string | null;
        athlete_position_name: string | null;
        athlete_position_abbreviation: string | null;
        created_at: string;
        updated_at: string;
    }

    let players: Player[] = [];
    let loading = true;
    let error: string | null = null;
    let searchTerm = '';
    let viewMode: 'cards' | 'table' = 'cards';

    $: filteredPlayers = players.filter(player =>
        player.athlete_display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (player.athlete_position_name && player.athlete_position_name.toLowerCase().includes(searchTerm.toLowerCase()))
    );

    onMount(async () => {
        try {
            const response = await api.players.getAll({ per_page: 200 });
            players = response.data.data;
        } catch (e) {
            error = e instanceof Error ? e.message : 'An error occurred';
        } finally {
            loading = false;
        }
    });
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
                    <h4 class="page-title">WNBA Players</h4>
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
                                placeholder="Search players by name or position..."
                                bind:value={searchTerm}
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="btn-group w-100" role="group">
                            <button
                                type="button"
                                class="btn {viewMode === 'cards' ? 'btn-success' : 'btn-outline-success'}"
                                on:click={() => viewMode = 'cards'}
                            >
                                <i class="fas fa-th-large me-1"></i>Cards
                            </button>
                            <button
                                type="button"
                                class="btn {viewMode === 'table' ? 'btn-success' : 'btn-outline-success'}"
                                on:click={() => viewMode = 'table'}
                            >
                                <i class="fas fa-table me-1"></i>Table
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <LoadingError
            {loading}
            {error}
            loadingText="Loading players..."
            retryAction={() => {
                loading = true;
                error = null;
                onMount();
            }}
        />

        {#if !loading && !error}
            {#if viewMode === 'table'}
            <!-- Table View -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Players ({filteredPlayers.length})</h5>
                        </div>
                        <div class="card-body">
                            {#if filteredPlayers.length > 0}
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Player</th>
                                                <th>Position</th>
                                                <th>Jersey</th>
                                                <th>Athlete ID</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each filteredPlayers as player}
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-xs bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2">
                                                                {#if player.athlete_headshot_href}
                                                                    <img src={player.athlete_headshot_href} alt={player.athlete_display_name} class="rounded-circle" style="width: 24px; height: 24px; object-fit: cover;" />
                                                                {:else}
                                                                    <i class="fas fa-user text-success fs-12"></i>
                                                                {/if}
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold">{player.athlete_display_name}</div>
                                                                <small class="text-muted">{player.athlete_short_name}</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">{player.athlete_position_abbreviation || 'N/A'}</span>
                                                        {#if player.athlete_position_name}
                                                            <br><small class="text-muted">{player.athlete_position_name}</small>
                                                        {/if}
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">#{player.athlete_jersey || 'N/A'}</span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">{player.athlete_id}</small>
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <div
                                                            class="d-inline-flex flex-wrap gap-2 justify-content-center player-table-actions"
                                                        >
                                                            <a
                                                                href="/players/{player.athlete_id}/data"
                                                                class="btn btn-outline-success btn-sm"
                                                                title="Data"
                                                                ><i class="fas fa-database"></i></a
                                                            >
                                                            <a
                                                                href="/players/{player.athlete_id}/analytics"
                                                                class="btn btn-success btn-sm"
                                                                title="Analytics"
                                                                ><i class="fas fa-chart-line"></i></a
                                                            >
                                                            <a
                                                                href="/players/{player.athlete_id}"
                                                                class="btn btn-outline-secondary btn-sm"
                                                                title="Profile"
                                                                ><i class="fas fa-eye"></i></a
                                                            >
                                                        </div>
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
                                    <h5 class="mb-2">No Players Found</h5>
                                    <p class="text-muted mb-0">No players match your search criteria.</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {:else}
            <!-- Card View -->
            <div class="row">
                {#each filteredPlayers as player}
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        {#if player.athlete_headshot_href}
                                            <img src={player.athlete_headshot_href} alt={player.athlete_display_name} class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" />
                                        {:else}
                                            <i class="fas fa-user text-success fs-18"></i>
                                        {/if}
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="card-title mb-1">{player.athlete_display_name}</h6>
                                        <p class="text-muted mb-0 small">{player.athlete_short_name}</p>
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <small class="text-muted d-block">Position</small>
                                            <span class="fw-semibold">{player.athlete_position_abbreviation || 'N/A'}</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <small class="text-muted d-block">Jersey</small>
                                            <span class="fw-semibold">#{player.athlete_jersey || 'N/A'}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="player-card-actions d-flex flex-column flex-lg-row flex-wrap gap-2 mt-3">
                                    <a href="/players/{player.athlete_id}/data" class="btn btn-success btn-sm flex-fill text-center text-nowrap">
                                        <i class="fas fa-database me-1"></i>Data
                                    </a>
                                    <a
                                        href="/players/{player.athlete_id}/analytics"
                                        class="btn btn-outline-success btn-sm flex-fill text-center text-nowrap"
                                    >
                                        <i class="fas fa-chart-line me-1"></i>Analytics
                                    </a>
                                    <a href="/players/{player.athlete_id}" class="btn btn-outline-secondary btn-sm flex-fill text-center text-nowrap">
                                        <i class="fas fa-eye me-1"></i>Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>

            {#if filteredPlayers.length === 0 && searchTerm}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                    <i class="fas fa-search text-muted fs-24"></i>
                                </div>
                                <h5 class="mb-2">No Players Found</h5>
                                <p class="text-muted mb-0">No players match your search criteria.</p>
                            </div>
                        </div>
                    </div>
                </div>
            {:else if players.length === 0}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                    <i class="fas fa-users text-muted fs-24"></i>
                                </div>
                                <h5 class="mb-2">No Players Found</h5>
                                <p class="text-muted mb-0">There are currently no players in the database.</p>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}
        {/if}
    {/if}
    </div>
</DefaultLayout>
