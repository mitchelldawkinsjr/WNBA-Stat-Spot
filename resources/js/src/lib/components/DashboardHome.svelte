<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import type { Game } from '$lib/api/client';
    import PageHeader from '$lib/components/ui/PageHeader.svelte';
    import StatCard from '$lib/components/ui/StatCard.svelte';
    import StatusBadge from '$lib/components/ui/StatusBadge.svelte';
    import PersonalizedDashboard from '$lib/components/PersonalizedDashboard.svelte';
    import TodaysBestProps from '$lib/components/TodaysBestProps.svelte';
    import Icon from '@iconify/svelte';

    let games: Game[] = [];
    let totalPlayers = 0;
    let totalTeams = 0;
    let loading = true;

    const quickLinks = [
        { href: '/reports/predictions', label: 'Prediction Engine', icon: 'iconamoon:crystal-ball-duotone', desc: 'AI prop predictions' },
        { href: '/advanced/prop-scanner', label: 'Prop Scanner', icon: 'iconamoon:radar-duotone', desc: 'Find +EV opportunities' },
        { href: '/advanced/live-odds', label: 'Live Odds', icon: 'iconamoon:lightning-duotone', desc: 'Real-time lines' },
        { href: '/compare/players', label: 'Compare Players', icon: 'iconamoon:compare-duotone', desc: 'Side-by-side stats' },
        { href: '/reports/players', label: 'Player Reports', icon: 'iconamoon:user-duotone', desc: 'Deep analytics' },
        { href: '/methodology', label: 'Methodology', icon: 'iconamoon:book-duotone', desc: 'Model documentation' },
    ];

    onMount(async () => {
        try {
            const [playersRes, teamsRes, gamesRes] = await Promise.all([
                api.players.getSummary(),
                api.teams.getSummary(),
                api.games.getAll({ season: 2026 }),
            ]);
            totalPlayers = playersRes.data?.length ?? 0;
            totalTeams = teamsRes.data?.length ?? 0;
            games = gamesRes.data ?? [];
        } catch (e) {
            console.error('Dashboard load failed', e);
            totalPlayers = 0;
            totalTeams = 0;
            games = [];
        } finally {
            loading = false;
        }
    });

    function formatGameTime(dateStr: string): string {
        return new Date(dateStr).toLocaleString('en-US', {
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
</script>

<PageHeader
    title="Dashboard"
    subtitle="WNBA analytics, predictions, and live insights"
    label="WNBA Stat Spot"
/>

<section class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle bg-danger" style="width:8px;height:8px;"></span>
            <span class="ds-section-label mb-0">Recent Games</span>
        </div>
        <a href="/games" class="btn btn-link btn-sm text-decoration-none p-0" style="color: var(--ds-primary);">
            View All
        </a>
    </div>

    {#if loading}
        <div class="ds-text-muted">Loading games…</div>
    {:else if games.length === 0}
        <div class="ds-score-card">
            <span class="ds-text-muted">No games loaded. Check data import or try the games page.</span>
        </div>
    {:else}
        <div class="ds-horizontal-scroll">
            {#each games.slice(0, 8) as game}
                <a href="/games/{game.game_id}" class="ds-score-card text-decoration-none text-reset">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="ds-section-label mb-0">{formatGameTime(game.game_date)}</span>
                        <StatusBadge variant="neutral" label={game.season} />
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold">{teamAbbr(game, 'away')}</span>
                        <span class="ds-stat-value">{game.away_team_score ?? game.away_team?.score ?? '–'}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{teamAbbr(game, 'home')}</span>
                        <span class="ds-stat-value">{game.home_team_score ?? game.home_team?.score ?? '–'}</span>
                    </div>
                </a>
            {/each}
        </div>
    {/if}
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <StatCard label="Players" value={loading ? '…' : totalPlayers} accent="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <StatCard label="Teams" value={loading ? '…' : totalTeams} accent="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <StatCard label="Games" value={loading ? '…' : games.length} accent="info" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <StatCard label="Season" value="2026" accent="warning" />
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <PersonalizedDashboard />
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header border-0 pb-0">
                <h2 class="ds-headline-sm mb-0">Quick Access</h2>
            </div>
            <div class="card-body d-grid gap-2">
                {#each quickLinks as link}
                    <a href={link.href} class="ds-quick-link">
                        <span class="ds-quick-link__icon">
                            <Icon icon={link.icon} width="20" height="20" />
                        </span>
                        <span>
                            <div class="fw-semibold">{link.label}</div>
                            <small class="ds-text-muted">{link.desc}</small>
                        </span>
                    </a>
                {/each}
            </div>
        </div>
    </div>
</div>

<section class="mb-4">
    <TodaysBestProps />
</section>
