<script lang="ts">
    import { onDestroy, onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import type { Game, LeagueLeader, PlayerSpotlight } from '$lib/api/client';
    import StatusBadge from '$lib/components/ui/StatusBadge.svelte';
    import DsIcon from '$lib/components/ui/DsIcon.svelte';
    import TodaysBestProps from '$lib/components/TodaysBestProps.svelte';
    import {
        WNBA_TIMEZONE,
        isGameFinal,
        isGameLive,
        isGameTodayEt,
        sortGamesForToday,
        todayEtDate,
    } from '$lib/utils/gameDates';

    let games: Game[] = [];
    let leaders: LeagueLeader[] = [];
    let newsItems: Array<Record<string, unknown>> = [];
    let featuredPlayer: PlayerSpotlight & { position: string } = {
        name: 'Featured Player',
        player_id: '',
        team: 'WNBA',
        position: '—',
        ppg: 0,
        apg: null,
        rpg: null,
        headshot: null,
    };
    let loading = true;
    let refreshTimer: ReturnType<typeof setInterval> | null = null;

    $: todaysGames = sortGamesForToday(games.filter((game) => isGameTodayEt(game) && !isGameFinal(game)));
    $: liveGames = todaysGames.filter(isGameLive);
    $: displayGames = todaysGames;
    $: gamesSectionTitle = liveGames.length > 0 ? "Today's Games" : (todaysGames.length > 0 ? "Today's Games" : 'Games');
    $: gamesSectionSubtitle = liveGames.length > 0
        ? `${liveGames.length} live now · ${todaysGames.length} total on ${todayEtDate()} (ET)`
        : (todaysGames.length > 0 ? `Scheduled for ${todayEtDate()} (ET)` : 'No games scheduled for today');

    onMount(async () => {
        await loadDashboard();
        refreshTimer = setInterval(loadGames, 30_000);
    });

    onDestroy(() => {
        if (refreshTimer) clearInterval(refreshTimer);
    });

    async function loadDashboard() {
        try {
            loading = true;
            const [gamesRes, leadersRes, newsRes] = await Promise.all([
                api.games.getAll({ season: 2026 }),
                api.players.getLeaders({ season: 2026 }),
                api.wnba.getNews({ limit: 4 }).catch(() => null),
            ]);
            games = gamesRes.data ?? [];
            leaders = leadersRes.data?.leaders ?? [];
            newsItems = newsRes?.data?.items ?? [];

            const spotlight = leadersRes.data?.spotlight;
            if (spotlight) {
                featuredPlayer = {
                    ...spotlight,
                    position: spotlight.position ?? '—',
                    ppg: spotlight.ppg ?? 0,
                    apg: spotlight.apg ?? null,
                    rpg: spotlight.rpg ?? null,
                };
            }
        } catch (e) {
            console.error('Dashboard load failed', e);
        } finally {
            loading = false;
        }
    }

    async function loadGames() {
        try {
            const gamesRes = await api.games.getAll({ season: 2026 });
            games = gamesRes.data ?? [];
        } catch (e) {
            console.error('Games refresh failed', e);
        }
    }

    function formatGameTime(game: Game): string {
        const raw = game.game_date_time || game.game_date;
        return new Date(raw).toLocaleString('en-US', {
            timeZone: WNBA_TIMEZONE,
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }

    function teamAbbr(game: Game, side: 'home' | 'away'): string {
        const team = side === 'home' ? game.home_team : game.away_team;
        return team?.abbreviation ?? team?.name?.slice(0, 3).toUpperCase() ?? 'TBD';
    }

    function formatStatus(game: Game): string {
        if (isGameLive(game)) return 'Live';
        const status = game.status_name ?? '';
        if (status === 'STATUS_SCHEDULED') return 'Scheduled';
        if (status === 'STATUS_FINAL') return 'Final';
        return status.replace(/^STATUS_/, '').replaceAll('_', ' ').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function newsTitle(item: Record<string, unknown>): string {
        return String(item.headline ?? item.title ?? 'WNBA Update');
    }

    function newsDescription(item: Record<string, unknown>): string | null {
        const desc = item.description;
        if (typeof desc !== 'string' || !desc) return null;
        return desc.length > 120 ? `${desc.slice(0, 120)}…` : desc;
    }

    function formatStat(value: number | null | undefined): string {
        return value != null ? value.toFixed(1) : '—';
    }
</script>

<!-- Live / Today's Games -->
<section class="ds-dashboard-section">
    <div class="ds-section-header">
        <div class="d-flex align-items-center gap-2">
            {#if liveGames.length > 0}
                <span class="ds-live-dot"></span>
            {/if}
            <div>
                <h2 class="ds-section-label mb-0">{gamesSectionTitle}</h2>
                <small class="ds-text-muted">{gamesSectionSubtitle}</small>
            </div>
        </div>
        <a href="/games" class="ds-link-caps">View All</a>
    </div>

    {#if loading}
        <p class="ds-text-muted">Loading games…</p>
    {:else if displayGames.length === 0}
        <div class="ds-score-card"><span class="ds-text-muted">No live or scheduled games right now. Check back on game day.</span></div>
    {:else}
        <div class="ds-horizontal-scroll">
            {#each displayGames as game}
                <a
                    href="/games/{game.game_id}"
                    class="ds-score-card text-decoration-none text-reset"
                    class:is-live={isGameLive(game)}
                >
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="ds-meta-caps">{formatGameTime(game)}</span>
                        {#if isGameLive(game)}
                            <StatusBadge variant="live" label="LIVE" />
                        {:else}
                            <span class="ds-meta-caps">{formatStatus(game)}</span>
                        {/if}
                    </div>
                    <div class="ds-score-row">
                        <span class="ds-team-abbr d-inline-flex align-items-center gap-1">
                            {#if game.away_team?.logo}
                                <img src={game.away_team.logo} alt={teamAbbr(game, 'away')} style="width:18px;height:18px;object-fit:contain;" />
                            {/if}
                            {teamAbbr(game, 'away')}
                        </span>
                        <span class="ds-stat-value">{game.away_team_score ?? game.away_team?.score ?? '–'}</span>
                    </div>
                    <div class="ds-score-row">
                        <span class="ds-team-abbr d-inline-flex align-items-center gap-1">
                            {#if game.home_team?.logo}
                                <img src={game.home_team.logo} alt={teamAbbr(game, 'home')} style="width:18px;height:18px;object-fit:contain;" />
                            {/if}
                            {teamAbbr(game, 'home')}
                        </span>
                        <span class="ds-stat-value">{game.home_team_score ?? game.home_team?.score ?? '–'}</span>
                    </div>
                </a>
            {/each}
        </div>
    {/if}
</section>

<div class="ds-dashboard-grid">
    <section class="ds-dashboard-main">
        <!-- Featured Player Hero -->
        <div class="ds-hero">
            {#if featuredPlayer.headshot}
                <img class="ds-hero__bg" src={featuredPlayer.headshot} alt="" />
            {/if}
            <div class="ds-hero__gradient"></div>
            <div class="ds-hero__content">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="ds-hero__badge">Player Spotlight</span>
                    <span class="ds-meta-caps">{featuredPlayer.team} • {featuredPlayer.position}</span>
                </div>
                <h1 class="ds-display-title">{featuredPlayer.name}</h1>
                <div class="ds-hero__stats">
                    <div><span class="ds-meta-caps">PPG</span><span class="ds-hero__stat">{formatStat(featuredPlayer.ppg)}</span></div>
                    <div><span class="ds-meta-caps">RPG</span><span class="ds-hero__stat">{formatStat(featuredPlayer.rpg)}</span></div>
                    <div><span class="ds-meta-caps">APG</span><span class="ds-hero__stat">{formatStat(featuredPlayer.apg)}</span></div>
                </div>
                {#if featuredPlayer.player_id}
                    <a href="/players/{featuredPlayer.player_id}" class="btn btn-sm btn-primary mt-3">View Profile</a>
                {/if}
            </div>
        </div>

        <div class="ds-bento-grid">
            <div class="ds-panel">
                <h3 class="ds-panel__title"><DsIcon name="trending_up" size={20} className="text-primary" /> League Leaders</h3>
                <p class="ds-text-muted small mb-3">Season averages (min. 5 games)</p>
                <div class="ds-leader-list">
                    {#each leaders as leader, i}
                        <a href="/players/{leader.player_id}" class="ds-leader-row">
                            <div class="d-flex align-items-center gap-3">
                                <span class="ds-leader-rank">{i + 1}</span>
                                {#if leader.headshot}
                                    <img class="ds-leader-avatar" src={leader.headshot} alt="" />
                                {:else}
                                    <span class="ds-leader-avatar ds-leader-avatar--placeholder"></span>
                                {/if}
                                <div>
                                    <span class="fw-semibold d-block">{leader.name}</span>
                                    <small class="ds-text-muted">{leader.category}</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="ds-stat-value text-primary">{leader.value}</span>
                                <small class="ds-text-muted d-block">{leader.category_abbr}</small>
                            </div>
                        </a>
                    {:else}
                        <p class="ds-text-muted mb-0">No leader data yet</p>
                    {/each}
                </div>
            </div>

            <div class="ds-panel">
                <h3 class="ds-panel__title"><DsIcon name="query_stats" size={20} className="text-primary" /> Quick Links</h3>
                <div class="ds-link-list">
                    <a href="/reports/predictions" class="ds-link-row"><DsIcon name="auto_awesome" size={18} /> Prediction Engine</a>
                    <a href="/reports/todays-props" class="ds-link-row"><DsIcon name="local_fire_department" size={18} /> Today's Best Props</a>
                    <a href="/advanced/prop-scanner" class="ds-link-row"><DsIcon name="radar" size={18} /> Prop Scanner</a>
                    <a href="/advanced/live-odds" class="ds-link-row"><DsIcon name="bolt" size={18} /> Live Odds</a>
                    <a href="/compare/players" class="ds-link-row"><DsIcon name="compare" size={18} /> Compare Players</a>
                </div>
            </div>
        </div>

        <section class="ds-dashboard-section">
            <TodaysBestProps />
        </section>
    </section>

    <aside class="ds-dashboard-aside">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="ds-panel__title mb-0"><DsIcon name="newspaper" size={20} className="text-primary" /> Latest News</h3>
        </div>
        <div class="ds-news-list">
            {#each newsItems.slice(0, 4) as item, i}
                <article class="ds-news-item" class:ds-news-item--featured={i === 0}>
                    <span class="ds-news-tag">News</span>
                    <h4 class="ds-news-title">{newsTitle(item)}</h4>
                    {#if newsDescription(item)}
                        <p class="ds-news-desc">{newsDescription(item)}</p>
                    {/if}
                </article>
            {:else}
                <article class="ds-news-item ds-news-item--featured">
                    <span class="ds-news-tag">Analysis</span>
                    <h4 class="ds-news-title">WNBA analytics and prop insights</h4>
                    <p class="ds-news-desc">Explore predictions, live odds, and player trends across the league.</p>
                </article>
            {/each}
        </div>
        <a href="/reports" class="ds-btn-outline w-100 mt-3">Load More Stories</a>
    </aside>
</div>

<style>
    .ds-dashboard-section { margin-bottom: var(--ds-spacing-lg); }
    .ds-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: var(--ds-spacing-sm);
    }
    .ds-live-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--ds-danger);
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    .ds-link-caps {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--ds-primary);
        text-decoration: none;
    }
    .ds-meta-caps {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--ds-text-muted);
    }
    .ds-score-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }
    .ds-team-abbr { font-weight: 600; font-size: 14px; }
    .ds-dashboard-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--ds-spacing-lg);
    }
    @media (min-width: 992px) {
        .ds-dashboard-grid { grid-template-columns: 2fr 1fr; }
    }
    .ds-dashboard-main { display: flex; flex-direction: column; gap: var(--ds-spacing-md); }
    .ds-hero {
        position: relative;
        height: 360px;
        border-radius: var(--ds-radius-xl);
        border: 1px solid var(--ds-border-subtle);
        background: var(--ds-surface-dark);
        overflow: hidden;
        display: flex;
        align-items: flex-end;
    }
    .ds-hero__bg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.45;
    }
    .ds-hero__gradient {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, var(--ds-surface) 10%, transparent 70%);
    }
    .ds-hero__content {
        position: relative;
        z-index: 1;
        padding: var(--ds-spacing-lg);
        width: 100%;
    }
    .ds-hero__badge {
        background: var(--ds-primary);
        color: var(--ds-on-primary);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 4px 12px;
        border-radius: var(--ds-radius-pill);
    }
    .ds-display-title {
        font-size: clamp(28px, 5vw, 36px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.1;
        margin: 0;
    }
    .ds-hero__stats {
        display: flex;
        gap: var(--ds-spacing-xl);
        margin-top: var(--ds-spacing-md);
    }
    .ds-hero__stats > div { display: flex; flex-direction: column; gap: 4px; }
    .ds-hero__stat {
        font-size: 24px;
        font-weight: 700;
        color: var(--ds-primary);
        font-variant-numeric: tabular-nums;
    }
    .ds-bento-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--ds-spacing-md);
    }
    @media (min-width: 768px) {
        .ds-bento-grid { grid-template-columns: 1fr 1fr; }
    }
    .ds-panel {
        background: var(--ds-surface-container);
        border: 1px solid var(--ds-border-subtle);
        border-radius: var(--ds-radius-xl);
        padding: var(--ds-spacing-md);
    }
    .ds-panel__title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 18px;
        font-weight: 600;
        margin-bottom: var(--ds-spacing-md);
    }
    .ds-leader-list { display: flex; flex-direction: column; }
    .ds-leader-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: var(--ds-spacing-sm);
        border-radius: var(--ds-radius-lg);
        color: inherit;
        text-decoration: none;
        border-top: 1px solid rgba(47, 57, 68, 0.5);
    }
    .ds-leader-row:first-child { border-top: none; }
    .ds-leader-row:hover { background: var(--ds-surface-variant); }
    .ds-leader-rank { color: var(--ds-text-muted); font-weight: 700; width: 16px; }
    .ds-leader-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        background: var(--ds-surface-container-high);
    }
    .ds-leader-avatar--placeholder { display: inline-block; }
    .ds-link-list { display: flex; flex-direction: column; gap: 4px; }
    .ds-link-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: var(--ds-radius-lg);
        color: var(--ds-on-surface);
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
    }
    .ds-link-row:hover { background: var(--ds-surface-variant); color: var(--ds-primary); }
    .ds-dashboard-aside { display: flex; flex-direction: column; }
    .ds-news-list { display: flex; flex-direction: column; gap: var(--ds-spacing-md); }
    .ds-news-item {
        padding-bottom: var(--ds-spacing-md);
        border-bottom: 1px solid var(--ds-border-subtle);
    }
    .ds-news-item--featured .ds-news-title { font-size: 18px; }
    .ds-news-tag {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--ds-primary);
        display: block;
        margin-bottom: 4px;
    }
    .ds-news-title {
        font-size: 14px;
        font-weight: 600;
        line-height: 1.3;
        margin: 0 0 6px;
    }
    .ds-news-desc {
        font-size: 14px;
        color: var(--ds-text-muted);
        margin: 0;
        line-height: 1.4;
    }
    .ds-btn-outline {
        display: block;
        text-align: center;
        padding: 12px;
        border: 1px solid var(--ds-border-subtle);
        border-radius: var(--ds-radius-xl);
        color: var(--ds-on-surface);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        text-decoration: none;
        transition: background 0.15s ease;
    }
    .ds-btn-outline:hover {
        background: var(--ds-surface-container-high);
        color: var(--ds-on-surface);
    }
</style>
