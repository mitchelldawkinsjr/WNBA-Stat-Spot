<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api } from '$lib/api/client';
    import type { Team } from '$lib/api/client';
    import DefaultLayout from '$lib/layouts/DefaultLayout.svelte';

    let team: Team | null = null;
    let loading = true;
    let error: string | null = null;

    $: teamId = $page.params.id;

    onMount(async () => {
        if (!teamId) return;
        try {
            const res = await api.teams.getById(teamId);
            team = res.data;
        } catch (e) {
            error = e instanceof Error ? e.message : 'Failed to load team';
        } finally {
            loading = false;
        }
    });
</script>

<svelte:head>
    <title>{team?.team_display_name ? `${team.team_display_name} | Team` : 'Team'} | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right d-flex flex-wrap gap-2 justify-content-end">
                        <a href="/teams" class="btn btn-outline-primary">← All teams</a>
                        <a href="/teams/{teamId}/players" class="btn btn-primary">
                            <i class="fas fa-users me-1"></i>Roster
                        </a>
                        <a href="/teams/{teamId}/analytics" class="btn btn-outline-secondary">
                            <i class="fas fa-chart-line me-1"></i>Analytics
                        </a>
                    </div>
                    <h4 class="page-title">Team profile</h4>
                </div>
            </div>
        </div>

        {#if loading}
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 mb-0">Loading team…</p>
                </div>
            </div>
        {:else if error}
            <div class="alert alert-danger">{error}</div>
        {:else if team}
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-start gap-4">
                        <div class="avatar-xl bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mx-auto mx-md-0">
{#if team.team_logo}
                                <img src={team.team_logo} alt="" class="rounded-circle p-2" style="width: 96px; height: 96px; object-fit: contain;" />
                            {:else}
                                <i class="fas fa-basketball-ball text-primary fs-1"></i>
                            {/if}
                        </div>
                        <div class="flex-grow-1 text-center text-md-start">
                            <h2 class="mb-1">{team.team_display_name}</h2>
                            <p class="text-muted mb-3">{team.team_location}</p>
                            <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                                <span class="badge bg-primary fs-6">{team.team_abbreviation}</span>
                                <span class="badge bg-secondary fs-6">ID {team.team_id}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>
