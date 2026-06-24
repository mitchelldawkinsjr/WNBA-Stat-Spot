<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from '$lib/layouts/DefaultLayout.svelte';
    import ErrorMessage from '$lib/components/ErrorMessage.svelte';
    import DsSearchInput from '$lib/components/ui/DsSearchInput.svelte';
    import DsChip from '$lib/components/ui/DsChip.svelte';
    import DsIcon from '$lib/components/ui/DsIcon.svelte';

    interface Player {
        id: number;
        athlete_id: string;
        athlete_display_name: string;
        athlete_short_name: string;
        athlete_jersey: string | null;
        athlete_headshot_href: string | null;
        athlete_position_name: string | null;
        athlete_position_abbreviation: string | null;
    }

    let players: Player[] = [];
    let loading = true;
    let error: string | null = null;
    let searchTerm = '';
    let positionFilter = 'all';

    const positionChips = [
        { key: 'all', label: 'All Players' },
        { key: 'G', label: 'Guards' },
        { key: 'F', label: 'Forwards' },
        { key: 'C', label: 'Centers' },
    ];

    $: filteredPlayers = players.filter((player) => {
        const matchesSearch =
            player.athlete_display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (player.athlete_position_name?.toLowerCase().includes(searchTerm.toLowerCase()) ?? false);
        const pos = player.athlete_position_abbreviation ?? '';
        const matchesPosition =
            positionFilter === 'all' ||
            pos === positionFilter ||
            (positionFilter === 'G' && ['G', 'PG', 'SG'].includes(pos)) ||
            (positionFilter === 'F' && ['F', 'PF', 'SF'].includes(pos)) ||
            (positionFilter === 'C' && pos === 'C');
        return matchesSearch && matchesPosition;
    });

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

<svelte:head>
    <title>Players | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <section class="ds-players-page">
        <h1 class="ds-players-title">League Players</h1>

        <div class="mb-4">
            <DsSearchInput bind:value={searchTerm} placeholder="Search players, teams, or positions…" />
        </div>

        <div class="ds-chip-row mb-4">
            {#each positionChips as chip}
                <DsChip
                    label={chip.label}
                    active={positionFilter === chip.key}
                    on:click={() => (positionFilter = chip.key)}
                />
            {/each}
        </div>

        {#if loading}
            <p class="ds-text-muted">Loading players…</p>
        {:else if error}
            <ErrorMessage message={error} />
        {:else}
            <div class="ds-player-grid">
                {#each filteredPlayers as player, i}
                    <article class="ds-player-card">
                        {#if i < 2}
                            <div class="ds-player-card__hot">
                                <DsIcon name="trending_up" size={12} /> HOT
                            </div>
                        {/if}
                        <div class="ds-player-card__head">
                            <div class="ds-player-card__avatar-wrap">
                                {#if player.athlete_headshot_href}
                                    <img src={player.athlete_headshot_href} alt="" class="ds-player-card__avatar" />
                                {:else}
                                    <div class="ds-player-card__avatar ds-player-card__avatar--empty">
                                        <DsIcon name="person" size={28} />
                                    </div>
                                {/if}
                            </div>
                            <div>
                                <h2 class="ds-player-card__name">{player.athlete_display_name}</h2>
                                <p class="ds-player-card__meta">
                                    {player.athlete_position_abbreviation ?? player.athlete_position_name ?? '—'}
                                    {#if player.athlete_jersey}• #{player.athlete_jersey}{/if}
                                </p>
                                <div class="ds-player-card__status">
                                    <span class="ds-status-dot"></span>
                                    <span>AVAILABLE</span>
                                </div>
                            </div>
                        </div>
                        <div class="ds-player-card__stats">
                            <div><span>POS</span><strong>{player.athlete_position_abbreviation ?? '—'}</strong></div>
                            <div><span>#</span><strong>{player.athlete_jersey ?? '—'}</strong></div>
                            <div><span>ID</span><strong class="text-truncate">{player.athlete_id.slice(-4)}</strong></div>
                        </div>
                        <a href="/players/{player.athlete_id}" class="ds-player-card__cta">View Full Stats</a>
                    </article>
                {/each}
            </div>
            {#if filteredPlayers.length === 0}
                <p class="ds-text-muted">No players match your filters.</p>
            {/if}
        {/if}
    </section>
</DefaultLayout>

<style>
    .ds-players-page { padding-bottom: var(--ds-spacing-lg); }
    .ds-players-title {
        font-size: clamp(28px, 4vw, 36px);
        font-weight: 700;
        letter-spacing: -0.02em;
        margin-bottom: var(--ds-spacing-md);
    }
    .ds-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: var(--ds-spacing-sm);
    }
    .ds-player-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: var(--ds-spacing-md);
    }
    .ds-player-card {
        position: relative;
        background: var(--ds-surface-container-lowest);
        border: 1px solid var(--ds-outline-variant);
        border-radius: var(--ds-radius-xl);
        padding: var(--ds-spacing-md);
        transition: transform 0.2s ease, border-color 0.2s ease;
        overflow: hidden;
    }
    .ds-player-card:hover {
        transform: translateY(-2px);
        border-color: var(--ds-primary);
    }
    .ds-player-card__hot {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--ds-primary);
        color: var(--ds-on-primary);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.06em;
        padding: 4px 10px;
        border-bottom-left-radius: var(--ds-radius-lg);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .ds-player-card__head {
        display: flex;
        gap: var(--ds-spacing-md);
        margin-bottom: var(--ds-spacing-md);
    }
    .ds-player-card__avatar-wrap { flex-shrink: 0; }
    .ds-player-card__avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--ds-primary);
    }
    .ds-player-card__avatar--empty {
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--ds-surface-container-high);
        border-color: var(--ds-border-subtle);
        color: var(--ds-text-muted);
    }
    .ds-player-card__name {
        font-size: 18px;
        font-weight: 600;
        margin: 0 0 4px;
        line-height: 1.2;
    }
    .ds-player-card:hover .ds-player-card__name { color: var(--ds-primary); }
    .ds-player-card__meta {
        font-size: 14px;
        color: var(--ds-on-surface-variant);
        margin: 0 0 6px;
    }
    .ds-player-card__status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.06em;
        color: var(--ds-tertiary);
    }
    .ds-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--ds-success);
    }
    .ds-player-card__stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 4px;
        border-top: 1px solid var(--ds-outline-variant);
        padding-top: var(--ds-spacing-md);
        margin-bottom: var(--ds-spacing-md);
        text-align: center;
    }
    .ds-player-card__stats span {
        display: block;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.06em;
        color: var(--ds-outline);
        margin-bottom: 2px;
    }
    .ds-player-card__stats strong {
        font-size: 16px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .ds-player-card__stats > div:nth-child(2) {
        border-left: 1px solid var(--ds-outline-variant);
        border-right: 1px solid var(--ds-outline-variant);
    }
    .ds-player-card__cta {
        display: block;
        text-align: center;
        background: var(--ds-secondary);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 10px;
        border-radius: var(--ds-radius-lg);
        text-decoration: none;
        transition: background 0.15s ease;
    }
    .ds-player-card__cta:hover {
        background: var(--ds-primary);
        color: var(--ds-on-primary);
    }
</style>
