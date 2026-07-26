<script lang="ts">
    import { onDestroy } from 'svelte';
    import { browser } from '$app/environment';
    import { page } from '$app/stores';
    import { goto } from '$app/navigation';
    import DefaultLayout from '$lib/layouts/DefaultLayout.svelte';
    import {
        playerProfile,
        AVAILABLE_SEASONS,
        PLAYER_PROFILE_TABS,
        DEFAULT_SEASON,
        activeTabFromPath,
        tabHref,
    } from '$lib/stores/playerProfile';

    $: playerId = $page.params.id ?? '';
    $: seasonParam = Number($page.url.searchParams.get('season') || DEFAULT_SEASON);
    $: season = AVAILABLE_SEASONS.includes(seasonParam as (typeof AVAILABLE_SEASONS)[number])
        ? seasonParam
        : DEFAULT_SEASON;
    $: activeTab = playerId ? activeTabFromPath($page.url.pathname, playerId) : 'overview';
    $: profile = $playerProfile;

    $: currentTeam =
        profile.player?.player_games?.find((g) => g.team)?.team ?? null;
    $: gamesPlayedFromStats = profile.seasonStats?.gamesPlayed;
    $: gamesPlayedFromLog = profile.player?.player_games
        ? profile.player.player_games.filter(
              (g) =>
                  !g.did_not_play &&
                  (g.game?.season == null || String(g.game.season) === String(season)),
          ).length
        : null;
    $: gamesPlayed =
        gamesPlayedFromStats != null && String(gamesPlayedFromStats).trim() !== ''
            ? String(gamesPlayedFromStats)
            : gamesPlayedFromLog != null
              ? String(gamesPlayedFromLog)
              : null;

    $: if (browser && playerId && (profile.playerId !== playerId || profile.season !== season)) {
        playerProfile.init(playerId, season);
    }

    onDestroy(() => {
        playerProfile.reset();
    });

    async function changeSeason(next: number) {
        if (next === season) return;
        const url = new URL($page.url);
        url.searchParams.set('season', String(next));
        await goto(`${url.pathname}?${url.searchParams.toString()}`, {
            replaceState: true,
            keepFocus: true,
            noScroll: true,
        });
    }
</script>

<DefaultLayout>
    <div class="container-xxl player-profile">
        {#if profile.loading && !profile.player}
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Loading player...</p>
                </div>
            </div>
        {:else if profile.error && !profile.player}
            <div class="alert alert-danger" role="alert">
                <strong>Error:</strong> {profile.error}
            </div>
            <a href="/players" class="btn btn-outline-primary">← Back to Players</a>
        {:else if profile.player}
            <div class="player-profile__chrome">
                <div class="player-profile__identity">
                    <div class="player-profile__avatar">
                        {#if profile.player.athlete_headshot_href}
                            <img
                                src={profile.player.athlete_headshot_href}
                                alt={profile.player.athlete_display_name}
                            />
                        {:else}
                            <i class="fas fa-user"></i>
                        {/if}
                    </div>
                    <div class="player-profile__meta">
                        <div class="player-profile__eyebrow">
                            <a href="/players">Players</a>
                            <span>/</span>
                            <span>Profile</span>
                        </div>
                        <h1 class="player-profile__name">{profile.player.athlete_display_name}</h1>
                        <div class="player-profile__badges">
                            <span class="badge bg-primary">#{profile.player.athlete_jersey || 'N/A'}</span>
                            <span class="badge bg-secondary">
                                {profile.player.athlete_position_name || 'Position N/A'}
                            </span>
                            {#if currentTeam}
                                <a
                                    href="/teams/{currentTeam.team_id}"
                                    class="badge bg-dark text-decoration-none player-profile__team-link"
                                    title="View team profile"
                                >
                                    {#if currentTeam.team_logo}
                                        <img
                                            src={currentTeam.team_logo}
                                            alt=""
                                            class="player-profile__team-logo"
                                        />
                                    {/if}
                                    {currentTeam.team_abbreviation || currentTeam.team_display_name}
                                </a>
                            {/if}
                            {#if gamesPlayed != null}
                                <span class="badge bg-info">{gamesPlayed} GP</span>
                            {/if}
                            {#if profile.injuries.length > 0}
                                <span class="badge bg-danger">
                                    {profile.injuries[0].status ?? 'Injured'}
                                </span>
                            {/if}
                            {#if profile.intelLoading}
                                <span class="spinner-border spinner-border-sm text-muted" role="status"></span>
                            {/if}
                        </div>
                        {#if currentTeam}
                            <p class="player-profile__team mb-0">
                                <a href="/teams/{currentTeam.team_id}">
                                    {currentTeam.team_display_name || currentTeam.team_abbreviation}
                                </a>
                                {#if gamesPlayed != null}
                                    <span class="text-muted">· {gamesPlayed} games played in {season}</span>
                                {/if}
                            </p>
                        {:else if gamesPlayed != null}
                            <p class="player-profile__team text-muted mb-0">
                                {gamesPlayed} games played in {season}
                            </p>
                        {/if}
                        {#if profile.nextGame}
                            <p class="player-profile__next mb-0">
                                <strong>Next:</strong>
                                {profile.nextGame.name ?? profile.nextGame.short_name}
                                {#if profile.nextGame.date}
                                    · {new Date(String(profile.nextGame.date)).toLocaleString()}
                                {/if}
                            </p>
                        {/if}
                    </div>
                </div>
                <div class="player-profile__controls">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Season">
                        {#each AVAILABLE_SEASONS as s}
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                class:active={season === s}
                                on:click={() => changeSeason(s)}
                            >{s}</button>
                        {/each}
                    </div>
                </div>
            </div>

            <nav class="player-profile__tabs" aria-label="Player sections">
                {#each PLAYER_PROFILE_TABS as tab}
                    <a
                        href={tabHref(playerId, tab.path, season)}
                        class="player-profile__tab"
                        class:active={activeTab === tab.key}
                        aria-current={activeTab === tab.key ? 'page' : undefined}
                    >
                        {tab.label}
                    </a>
                {/each}
            </nav>

            <div class="player-profile__panel">
                <slot />
            </div>
        {/if}
    </div>
</DefaultLayout>

<style>
    .player-profile {
        padding-bottom: 2rem;
    }

    .player-profile__chrome {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 0.75rem;
        padding: 1rem 1.25rem;
        background: var(--bs-body-bg, #fff);
        border: 1px solid var(--bs-border-color, #e9ecef);
        border-radius: 0.5rem;
    }

    .player-profile__identity {
        display: flex;
        gap: 1rem;
        align-items: center;
        min-width: 0;
    }

    .player-profile__avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        overflow: hidden;
        background: rgba(var(--bs-success-rgb, 25, 135, 84), 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: var(--bs-success, #198754);
        font-size: 1.5rem;
    }

    .player-profile__avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .player-profile__eyebrow {
        display: flex;
        gap: 0.35rem;
        font-size: 0.75rem;
        color: var(--bs-secondary-color, #6c757d);
        margin-bottom: 0.15rem;
    }

    .player-profile__eyebrow a {
        color: inherit;
        text-decoration: none;
    }

    .player-profile__eyebrow a:hover {
        text-decoration: underline;
    }

    .player-profile__name {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0 0 0.4rem;
        line-height: 1.2;
    }

    .player-profile__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        align-items: center;
        margin-bottom: 0.35rem;
    }

    .player-profile__team-link {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .player-profile__team-link:hover {
        opacity: 0.9;
    }

    .player-profile__team-logo {
        width: 14px;
        height: 14px;
        object-fit: contain;
    }

    .player-profile__team {
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }

    .player-profile__team a {
        font-weight: 600;
        text-decoration: none;
    }

    .player-profile__team a:hover {
        text-decoration: underline;
    }

    .player-profile__next {
        font-size: 0.875rem;
        color: var(--bs-secondary-color, #6c757d);
    }

    .player-profile__tabs {
        position: sticky;
        top: 0;
        z-index: 20;
        display: flex;
        gap: 0.15rem;
        overflow-x: auto;
        padding: 0.35rem 0.25rem;
        margin: 0 0 1rem;
        background: var(--bs-body-bg, #fff);
        border-bottom: 1px solid var(--bs-border-color, #dee2e6);
        -webkit-overflow-scrolling: touch;
    }

    .player-profile__tab {
        flex: 0 0 auto;
        padding: 0.55rem 0.9rem;
        border-radius: 0.375rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--bs-secondary-color, #6c757d);
        text-decoration: none;
        white-space: nowrap;
    }

    .player-profile__tab:hover {
        color: var(--bs-body-color, #212529);
        background: rgba(0, 0, 0, 0.04);
    }

    .player-profile__tab.active {
        color: var(--bs-primary, #0d6efd);
        background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.1);
    }

    .player-profile__panel {
        min-height: 12rem;
    }

    @media (max-width: 576px) {
        .player-profile__name {
            font-size: 1.35rem;
        }

        .player-profile__avatar {
            width: 56px;
            height: 56px;
        }
    }
</style>
