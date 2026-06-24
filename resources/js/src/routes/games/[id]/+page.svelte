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
                            <StatusBadge variant="neutral" label={formatStatus(game.status_name)} />
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="ds-score-card h-100">
                                    <p class="ds-section-label mb-2">Away</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            {#if teamLogo('away')}
                                                <img src={teamLogo('away')} alt={teamAbbr('away')} style="width:32px;height:32px;object-fit:contain;" />
                                            {/if}
                                            <div>
                                                <span class="fw-semibold">{teamAbbr('away')}</span>
                                                {#if teamRecord('away')}
                                                    <div class="small text-muted">{teamRecord('away')}</div>
                                                {/if}
                                            </div>
                                        </div>
                                        <span class="ds-stat-value fs-3">{teamScore('away')}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="ds-score-card h-100">
                                    <p class="ds-section-label mb-2">Home</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            {#if teamLogo('home')}
                                                <img src={teamLogo('home')} alt={teamAbbr('home')} style="width:32px;height:32px;object-fit:contain;" />
                                            {/if}
                                            <div>
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
                </div>

                {#if showPreviewTab || boxScore.length > 0}
                    <ul class="nav nav-tabs mt-4" role="tablist">
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
                        <div class="mt-4"><LoadingSpinner /></div>
                    {:else if previewError}
                        <div class="mt-4"><ErrorMessage message={previewError} /></div>
                    {:else if preview?.home_team}
                        <div class="mt-4">
                            <GamePreviewPanel {preview} />
                        </div>
                    {/if}
                {:else if activeTab === 'boxscore'}
                    <div class="card mt-4" id="box-score">
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

                {#if preview?.prediction && activeTab === 'preview'}
                    <div class="card mt-4">
                        <div class="card-header border-0">
                            <h3 class="ds-headline-sm mb-0">Quick Pick</h3>
                        </div>
                        <div class="card-body">
                            <p class="fw-bold fs-5 mb-1">{preview.prediction.predicted_winner_label}</p>
                            <p class="ds-text-muted small mb-2">
                                {preview.prediction.win_probability.home}% / {preview.prediction.win_probability.away}%
                                ({teamAbbr('home')} / {teamAbbr('away')})
                            </p>
                            <p class="mb-0 small">
                                Projected: {teamAbbr('away')} {preview.prediction.projected_score.away} – {teamAbbr('home')} {preview.prediction.projected_score.home}
                            </p>
                        </div>
                    </div>
                {/if}
            </div>
        </div>
    {/if}
</DefaultLayout>
