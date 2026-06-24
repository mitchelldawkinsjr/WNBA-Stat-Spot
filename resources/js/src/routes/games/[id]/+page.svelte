<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api } from '$lib/api/client';
    import type { Game, GameBoxScorePlayer, GamePreview } from '$lib/api/client';
    import DefaultLayout from '$lib/layouts/DefaultLayout.svelte';
    import PageHeader from '$lib/components/ui/PageHeader.svelte';
    import StatusBadge from '$lib/components/ui/StatusBadge.svelte';
    import LoadingSpinner from '$lib/components/LoadingSpinner.svelte';
    import ErrorMessage from '$lib/components/ErrorMessage.svelte';
    import GamePreviewPanel from '$lib/components/GamePreviewPanel.svelte';

    let game: Game | null = null;
    let preview: GamePreview | null = null;
    let boxScore: GameBoxScorePlayer[] = [];
    let loading = true;
    let previewLoading = true;
    let error: string | null = null;
    let previewError: string | null = null;
    let activeTab: 'preview' | 'boxscore' = 'preview';

    $: gameId = $page.params.id;
    $: isScheduled = game?.status_name === 'STATUS_SCHEDULED' || !game?.status_name;
    $: showPreviewTab = game?.status_name !== 'STATUS_FINAL' || preview !== null;

    onMount(() => {
        void loadGame();
        void loadPreview();
    });

    async function loadGame() {
        if (!gameId) return;
        loading = true;
        error = null;
        try {
            const response = await api.games.getById(gameId, { season: 2026 });
            game = response.data;
            boxScore = response.data.box_score ?? [];
            if (response.data.status_name === 'STATUS_FINAL' && boxScore.length > 0) {
                activeTab = 'boxscore';
            }
        } catch (e) {
            error = e instanceof Error ? e.message : 'Failed to load game';
            game = null;
            boxScore = [];
        } finally {
            loading = false;
        }
    }

    async function loadPreview() {
        if (!gameId) return;
        previewLoading = true;
        previewError = null;
        try {
            const response = await api.games.getPreview(gameId, { season: 2026 });
            preview = response.data;
            if (preview?.error && !preview.home_team) {
                previewError = preview.error;
                preview = null;
            }
        } catch (e) {
            previewError = e instanceof Error ? e.message : 'Failed to load game preview';
            preview = null;
        } finally {
            previewLoading = false;
        }
    }

    function teamLabel(side: 'home' | 'away'): string {
        if (!game) return 'TBD';
        const team = side === 'home' ? game.home_team : game.away_team;
        return team?.name ?? team?.abbreviation ?? 'TBD';
    }

    function teamAbbr(side: 'home' | 'away'): string {
        if (!game) return 'TBD';
        const team = side === 'home' ? game.home_team : game.away_team;
        return team?.abbreviation ?? team?.name?.slice(0, 3).toUpperCase() ?? 'TBD';
    }

    function teamLogo(side: 'home' | 'away'): string | null {
        if (!game) return null;
        const team = side === 'home' ? game.home_team : game.away_team;
        return team?.logo ?? null;
    }

    function teamScore(side: 'home' | 'away'): string | number {
        if (!game) return '–';
        if (side === 'home') return game.home_team_score ?? game.home_team?.score ?? '–';
        return game.away_team_score ?? game.away_team?.score ?? '–';
    }

    function teamRecord(side: 'home' | 'away'): string | null {
        const teamPreview = side === 'home' ? preview?.home_team : preview?.away_team;
        if (!teamPreview) return null;
        return `${teamPreview.record.wins}-${teamPreview.record.losses}`;
    }

    function formatStatus(status: string | null | undefined): string {
        if (!status) return 'Scheduled';
        if (status === 'STATUS_FINAL') return 'Final';
        if (status === 'STATUS_SCHEDULED') return 'Scheduled';
        if (status === 'STATUS_IN_PROGRESS') return 'Live';
        return status.replace(/^STATUS_/, '').replaceAll('_', ' ').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function gameDateTimeLabel(g: Game): string {
        return new Date(g.game_date_time || g.game_date).toLocaleString(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }

    function venueLabel(g: Game): string {
        const parts: string[] = [];
        if (g.venue_name) parts.push(g.venue_name);
        const location = [g.venue_city, g.venue_state].filter(Boolean).join(', ');
        if (location) parts.push(location);
        if (g.venue_capacity) parts.push(`Capacity ${g.venue_capacity.toLocaleString()}`);
        return parts.join(' · ') || 'Venue TBD';
    }
</script>

<svelte:head>
    <title>{game ? `${teamAbbr('away')} @ ${teamAbbr('home')}` : 'Game'} | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <PageHeader title="Game Preview" subtitle="Data-driven matchup analysis and prediction">
        <svelte:fragment slot="actions">
            <a href="/games" class="btn btn-outline-primary btn-sm">← All Games</a>
        </svelte:fragment>
    </PageHeader>

    {#if loading}
        <LoadingSpinner />
    {:else if error}
        <ErrorMessage message={error} />
    {:else if game}
        <div class="game-detail-page">
            <div class="card game-detail-page__header-card">
                <div class="card-body">
                    <div class="game-detail-page__meta">
                        <div class="game-detail-page__meta-main">
                            <p class="ds-section-label mb-1">{game.season} • {game.season_type}</p>
                            <h2 class="ds-headline-md mb-0">{teamLabel('away')} @ {teamLabel('home')}</h2>
                            <p class="ds-text-muted mb-0 mt-1">{gameDateTimeLabel(game)}</p>
                            <p class="ds-text-muted small mb-0 mt-1">{venueLabel(game)}</p>
                        </div>
                        <StatusBadge variant="neutral" label={formatStatus(game.status_name)} />
                    </div>

                    <div class="game-detail-page__scoreboard">
                        <div class="ds-score-card game-detail-page__score-card">
                            <p class="ds-section-label mb-2">Away</p>
                            <div class="game-detail-page__score-row">
                                <div class="game-detail-page__team">
                                    {#if teamLogo('away')}
                                        <img src={teamLogo('away')} alt={teamAbbr('away')} class="game-detail-page__logo" />
                                    {/if}
                                    <div class="game-detail-page__team-text">
                                        <span class="fw-semibold">{teamAbbr('away')}</span>
                                        {#if teamRecord('away')}
                                            <div class="small text-muted">{teamRecord('away')}</div>
                                        {/if}
                                    </div>
                                </div>
                                <span class="ds-stat-value fs-3">{teamScore('away')}</span>
                            </div>
                        </div>
                        <div class="ds-score-card game-detail-page__score-card">
                            <p class="ds-section-label mb-2">Home</p>
                            <div class="game-detail-page__score-row">
                                <div class="game-detail-page__team">
                                    {#if teamLogo('home')}
                                        <img src={teamLogo('home')} alt={teamAbbr('home')} class="game-detail-page__logo" />
                                    {/if}
                                    <div class="game-detail-page__team-text">
                                        <span class="fw-semibold">{teamAbbr('home')}</span>
                                        {#if teamRecord('home')}
                                            <div class="small text-muted">{teamRecord('home')}</div>
                                        {/if}
                                    </div>
                                </div>
                                <span class="ds-stat-value fs-3">{teamScore('home')}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {#if showPreviewTab || boxScore.length > 0}
                <ul class="nav nav-tabs game-detail-page__tabs" role="tablist">
                        {#if showPreviewTab}
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link"
                                    class:active={activeTab === 'preview'}
                                    type="button"
                                    on:click={() => (activeTab = 'preview')}
                                >
                                    Preview & Prediction
                                </button>
                            </li>
                        {/if}
                        {#if boxScore.length > 0 || !isScheduled}
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link"
                                    class:active={activeTab === 'boxscore'}
                                    type="button"
                                    on:click={() => (activeTab = 'boxscore')}
                                >
                                    Box Score
                                </button>
                            </li>
                        {/if}
                    </ul>
                {/if}

            {#if activeTab === 'preview'}
                {#if previewLoading}
                    <div class="game-detail-page__tab-panel"><LoadingSpinner /></div>
                {:else if previewError}
                    <div class="game-detail-page__tab-panel"><ErrorMessage message={previewError} /></div>
                {:else if preview?.home_team}
                    <div class="game-detail-page__tab-panel">
                        <GamePreviewPanel {preview} />
                    </div>
                {/if}
            {:else if activeTab === 'boxscore'}
                <div class="card game-detail-page__tab-panel" id="box-score">
                        <div class="card-header border-0">
                            <h3 class="ds-headline-sm mb-0">Box Score</h3>
                        </div>
                        <div class="card-body">
                            {#if boxScore.length > 0}
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Player</th>
                                                <th>Team</th>
                                                <th>MIN</th>
                                                <th>PTS</th>
                                                <th>REB</th>
                                                <th>AST</th>
                                                <th>FG</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each boxScore as row}
                                                <tr>
                                                    <td>{row.player_name ?? '—'}</td>
                                                    <td>{row.team_abbreviation ?? '—'}</td>
                                                    <td>{row.minutes ?? '—'}</td>
                                                    <td><strong>{row.points}</strong></td>
                                                    <td>{row.rebounds}</td>
                                                    <td>{row.assists}</td>
                                                    <td><small>{row.field_goals_made}/{row.field_goals_attempted}</small></td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            {:else}
                                <p class="ds-text-muted mb-0">Box score not available yet for this game.</p>
                            {/if}
                        </div>
                </div>
            {/if}
        </div>
    {/if}
</DefaultLayout>

<style>
    .game-detail-page {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        min-width: 0;
        max-width: 100%;
    }

    .game-detail-page__header-card,
    .game-detail-page__tab-panel {
        min-width: 0;
        max-width: 100%;
    }

    .game-detail-page__meta {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        margin-bottom: 1.25rem;
    }

    .game-detail-page__meta-main {
        flex: 1 1 12rem;
        min-width: 0;
    }

    .game-detail-page__scoreboard {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .game-detail-page__score-card {
        flex: 1 1 14rem;
        min-width: 0;
    }

    .game-detail-page__score-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        min-width: 0;
    }

    .game-detail-page__team {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
        flex: 1 1 auto;
    }

    .game-detail-page__team-text {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .game-detail-page__logo {
        width: 32px;
        height: 32px;
        flex-shrink: 0;
        object-fit: contain;
    }

    .game-detail-page__tabs {
        margin-top: 0.25rem;
        flex-wrap: nowrap;
        overflow-x: auto;
    }
</style>
