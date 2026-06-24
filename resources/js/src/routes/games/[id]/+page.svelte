<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api } from '$lib/api/client';
    import type { Game } from '$lib/api/client';
    import DefaultLayout from '$lib/layouts/DefaultLayout.svelte';
    import PageHeader from '$lib/components/ui/PageHeader.svelte';
    import StatusBadge from '$lib/components/ui/StatusBadge.svelte';
    import LoadingSpinner from '$lib/components/LoadingSpinner.svelte';
    import ErrorMessage from '$lib/components/ErrorMessage.svelte';

    let game: Game | null = null;
    let loading = true;
    let error: string | null = null;

    $: gameId = $page.params.id;

    onMount(() => loadGame());

    async function loadGame() {
        if (!gameId) return;
        loading = true;
        error = null;
        try {
            const response = await api.games.getAll({ season: 2026 });
            game = response.data.find((g) => g.game_id === gameId) ?? null;
            if (!game) {
                error = 'Game not found';
            }
        } catch (e) {
            error = e instanceof Error ? e.message : 'Failed to load game';
        } finally {
            loading = false;
        }
    }

    function teamLabel(side: 'home' | 'away'): string {
        if (!game) return 'TBD';
        const team = side === 'home' ? game.home_team : game.away_team;
        return team?.name ?? team?.abbreviation ?? 'TBD';
    }

    function teamScore(side: 'home' | 'away'): string | number {
        if (!game) return '–';
        if (side === 'home') return game.home_team_score ?? game.home_team?.score ?? '–';
        return game.away_team_score ?? game.away_team?.score ?? '–';
    }
</script>

<svelte:head>
    <title>{game ? `${teamLabel('away')} @ ${teamLabel('home')}` : 'Game'} | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <PageHeader title="Game Details" subtitle="Box score and matchup information">
        <svelte:fragment slot="actions">
            <a href="/games" class="btn btn-outline-primary btn-sm">← All Games</a>
        </svelte:fragment>
    </PageHeader>

    {#if loading}
        <LoadingSpinner />
    {:else if error}
        <ErrorMessage message={error} />
    {:else if game}
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
                            <div>
                                <p class="ds-section-label mb-1">{game.season} • {game.season_type}</p>
                                <h2 class="ds-headline-md mb-0">{teamLabel('away')} @ {teamLabel('home')}</h2>
                                <p class="ds-text-muted mb-0 mt-1">
                                    {new Date(game.game_date_time || game.game_date).toLocaleString()}
                                </p>
                            </div>
                            {#if game.status_name}
                                <StatusBadge variant="neutral" label={game.status_name} />
                            {/if}
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="ds-score-card h-100">
                                    <p class="ds-section-label mb-2">Away</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold">{teamLabel('away')}</span>
                                        <span class="ds-stat-value fs-3">{teamScore('away')}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="ds-score-card h-100">
                                    <p class="ds-section-label mb-2">Home</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold">{teamLabel('home')}</span>
                                        <span class="ds-stat-value fs-3">{teamScore('home')}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header border-0">
                        <h3 class="ds-headline-sm mb-0">Venue</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-1 fw-semibold">{game.venue_name ?? 'TBD'}</p>
                        <p class="ds-text-muted mb-0">
                            {[game.venue_city, game.venue_state].filter(Boolean).join(', ') || 'Location TBD'}
                        </p>
                        {#if game.venue_capacity}
                            <p class="ds-text-muted mt-2 mb-0">Capacity: {game.venue_capacity.toLocaleString()}</p>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    {/if}
</DefaultLayout>
