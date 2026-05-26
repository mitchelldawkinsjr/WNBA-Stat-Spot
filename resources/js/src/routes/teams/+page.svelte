<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";

    interface Team {
        id: number;
        team_id: string;
        team_name: string;
        team_location: string;
        team_abbreviation: string;
        team_display_name: string;
        team_uid: string;
        team_slug: string | null;
        team_logo: string;
        team_color: string;
        team_alternate_color: string;
        created_at: string;
        updated_at: string;
    }

    let teams: Team[] = [];
    let loading = true;
    let error: string | null = null;
    let searchTerm = '';
    let viewMode: 'cards' | 'table' = 'cards';

    $: filteredTeams = teams.filter(team =>
        team.team_display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        team.team_location.toLowerCase().includes(searchTerm.toLowerCase()) ||
        team.team_abbreviation.toLowerCase().includes(searchTerm.toLowerCase())
    );

    onMount(async () => {
        try {
            const response = await api.teams.getAll();
            teams = response.data.data;
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
                    <h4 class="page-title">WNBA Teams</h4>
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
                                placeholder="Search teams by name, location, or abbreviation..."
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
                                class="btn {viewMode === 'cards' ? 'btn-primary' : 'btn-outline-primary'}"
                                on:click={() => viewMode = 'cards'}
                            >
                                <i class="fas fa-th-large me-1"></i>Cards
                            </button>
                            <button
                                type="button"
                                class="btn {viewMode === 'table' ? 'btn-primary' : 'btn-outline-primary'}"
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
                            <p class="mt-2 mb-0">Loading teams...</p>
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
                            <h5 class="card-title mb-0">Teams ({filteredTeams.length})</h5>
                        </div>
                        <div class="card-body">
                            {#if filteredTeams.length > 0}
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Team</th>
                                                <th>Location</th>
                                                <th>Abbreviation</th>
                                                <th>Team ID</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each filteredTeams as team}
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-xs bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2">
                                                                {#if team.team_logo}
                                                                    <img src={team.team_logo} alt={team.team_name} class="rounded-circle" style="width: 24px; height: 24px; object-fit: contain;" />
                                                                {:else}
                                                                    <i class="fas fa-basketball text-primary fs-12"></i>
                                                                {/if}
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold">{team.team_display_name}</div>
                                                                <small class="text-muted">{team.team_name}</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>{team.team_location}</td>
                                                    <td>
                                                        <span class="badge bg-primary">{team.team_abbreviation}</span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">{team.team_id}</small>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="/teams/{team.team_id}" class="btn btn-outline-primary btn-sm me-1" title="View Team Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="/teams/{team.team_id}/players" class="btn btn-outline-secondary btn-sm" title="View Players">
                                                            <i class="fas fa-users"></i>
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
                                    <h5 class="mb-2">No Teams Found</h5>
                                    <p class="text-muted mb-0">No teams match your search criteria.</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {:else}
            <!-- Card View -->
            <div class="row">
                {#each filteredTeams as team}
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        {#if team.team_logo}
                                            <img src={team.team_logo} alt={team.team_name} class="rounded-circle" style="width: 32px; height: 32px; object-fit: contain;" />
                                        {:else}
                                            <i class="fas fa-basketball text-primary fs-18"></i>
                                        {/if}
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">{team.team_display_name}</h5>
                                        <p class="text-muted mb-0">{team.team_location}</p>
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <small class="text-muted d-block">Abbreviation</small>
                                            <span class="fw-semibold">{team.team_abbreviation}</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <small class="text-muted d-block">Team ID</small>
                                            <span class="fw-semibold">{team.team_id}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <a href="/teams/{team.team_id}" class="btn btn-outline-primary btn-sm me-2">
                                        View Details
                                    </a>
                                    <a href="/teams/{team.team_id}/players" class="btn btn-outline-secondary btn-sm">
                                        Players
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>

            {#if filteredTeams.length === 0 && searchTerm}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                    <i class="fas fa-search text-muted fs-24"></i>
                                </div>
                                <h5 class="mb-2">No Teams Found</h5>
                                <p class="text-muted mb-0">No teams match your search criteria.</p>
                            </div>
                        </div>
                    </div>
                </div>
            {:else if teams.length === 0}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                    <i class="fas fa-users text-muted fs-24"></i>
                                </div>
                                <h5 class="mb-2">No Teams Found</h5>
                                <p class="text-muted mb-0">There are currently no teams in the database.</p>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}
        {/if}
    </div>
</DefaultLayout>
