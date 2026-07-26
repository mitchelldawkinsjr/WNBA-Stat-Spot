<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api } from '$lib/api/client';
    import { playerProfile, tabHref } from '$lib/stores/playerProfile';
    import {
        mapGamelogToPlayerGame,
        computeAverages,
        type ProfileGameRow,
    } from '$lib/utils/playerGamelog';

    let recentGames: ProfileGameRow[] = [];
    let gamelogLoading = false;
    let loadedForKey = '';

    $: profile = $playerProfile;
    $: playerId = $page.params.id ?? '';
    $: season = profile.season;
    $: averages = computeAverages(recentGames);
    $: live = profile.seasonStats;

    async function loadRecent() {
        if (!playerId || !season) return;
        const key = `${playerId}:${season}`;
        if (key === loadedForKey) return;
        loadedForKey = key;
        gamelogLoading = true;
        try {
            const response = await api.players.getGamelog(playerId, { season, last_n_games: 10 });
            if (response.success && response.data?.games?.length) {
                recentGames = response.data.games
                    .map((row) => mapGamelogToPlayerGame(row, season))
                    .filter((g) => !g.did_not_play);
            } else {
                recentGames = [];
            }
        } catch {
            recentGames = [];
        } finally {
            gamelogLoading = false;
        }
    }

    onMount(loadRecent);
    $: if (playerId && season) loadRecent();

    function lineFromAvg(value: string | null | undefined): number {
        const n = parseFloat(value || '0');
        if (!n) return 10;
        return Math.round(n * 2) / 2;
    }
</script>

<svelte:head>
    <title>{profile.player?.athlete_display_name || 'Player'} | WNBA Stat Spot</title>
</svelte:head>

{#if profile.injuries.length > 0}
    <div class="card border-danger mb-3">
        <div class="card-body py-3">
            <h6 class="text-danger mb-2">Injury report</h6>
            {#each profile.injuries as injury}
                <div class="mb-1">
                    <span class="badge bg-danger me-2">{injury.status ?? 'Unknown'}</span>
                    <span>{injury.short_comment ?? injury.description ?? 'No details'}</span>
                </div>
            {/each}
        </div>
    </div>
{/if}

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Season snapshot</h5>
        <a href={tabHref(playerId, '/stats', season)} class="small">Full stats →</a>
    </div>
    <div class="card-body">
        {#if averages}
            <div class="row g-3">
                {#each [
                    { key: 'points', label: 'PPG', value: averages.points, color: 'primary', propStat: 'points' },
                    { key: 'rebounds', label: 'RPG', value: averages.rebounds, color: 'success', propStat: 'rebounds' },
                    { key: 'assists', label: 'APG', value: averages.assists, color: 'info', propStat: 'assists' },
                    { key: 'steals', label: 'SPG', value: averages.steals, color: 'warning', propStat: 'steals' },
                    { key: 'blocks', label: 'BPG', value: averages.blocks, color: 'danger', propStat: 'blocks' },
                    { key: 'fg', label: 'FG%', value: `${averages.fg_percentage}%`, color: 'secondary', propStat: null },
                ] as tile}
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-tile">
                            <div class="stat-tile__value text-{tile.color}">{tile.value}</div>
                            <div class="stat-tile__label">{tile.label}</div>
                            <div class="stat-tile__links">
                                <a href={tabHref(playerId, '/trends', season)}>Trend</a>
                                {#if tile.propStat}
                                    <a href="{tabHref(playerId, '/props', season)}&stat={tile.propStat}&line={lineFromAvg(tile.value)}">Prop</a>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
            <p class="text-muted small mt-3 mb-0">{averages.games} games in {season} gamelog sample</p>
        {:else if live}
            <div class="row g-3 text-center">
                <div class="col-4 col-md-2"><strong>{live.avgPoints ?? '—'}</strong><div class="text-muted small">PPG</div></div>
                <div class="col-4 col-md-2"><strong>{live.avgRebounds ?? '—'}</strong><div class="text-muted small">RPG</div></div>
                <div class="col-4 col-md-2"><strong>{live.avgAssists ?? '—'}</strong><div class="text-muted small">APG</div></div>
                <div class="col-4 col-md-2"><strong>{live.gamesPlayed ?? '—'}</strong><div class="text-muted small">GP</div></div>
                <div class="col-4 col-md-2"><strong>{live.avgMinutes ?? '—'}</strong><div class="text-muted small">MIN</div></div>
                <div class="col-4 col-md-2"><strong>{live.fieldGoalPct ?? '—'}%</strong><div class="text-muted small">FG%</div></div>
            </div>
        {:else if gamelogLoading}
            <div class="text-center py-3 text-muted">Loading snapshot...</div>
        {:else}
            <p class="text-muted mb-0">No season averages available yet.</p>
        {/if}
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent games</h5>
                <a href={tabHref(playerId, '/gamelog', season)} class="small">Full game log →</a>
            </div>
            <div class="card-body p-0">
                {#if gamelogLoading && recentGames.length === 0}
                    <div class="text-center py-4 text-muted">Loading games...</div>
                {:else if recentGames.length > 0}
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Opp</th>
                                    <th>MIN</th>
                                    <th>PTS</th>
                                    <th>REB</th>
                                    <th>AST</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each recentGames.slice(0, 5) as game}
                                    <tr>
                                        <td><small>{new Date(game.game?.game_date || '').toLocaleDateString()}</small></td>
                                        <td><small>{game.team?.team_abbreviation || 'N/A'}</small></td>
                                        <td><small>{game.minutes || '—'}</small></td>
                                        <td><strong>{game.points}</strong></td>
                                        <td>{game.rebounds}</td>
                                        <td>{game.assists}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {:else}
                    <div class="text-center py-4 text-muted">No recent games.</div>
                {/if}
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Jump to</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href={tabHref(playerId, '/stats', season)} class="btn btn-outline-primary text-start">
                        <i class="fas fa-table me-2"></i>Season &amp; advanced stats
                    </a>
                    <a href={tabHref(playerId, '/trends', season)} class="btn btn-outline-primary text-start">
                        <i class="fas fa-chart-line me-2"></i>Trends &amp; shooting charts
                    </a>
                    <a href={tabHref(playerId, '/props', season)} class="btn btn-outline-success text-start">
                        <i class="fas fa-dice me-2"></i>Props &amp; predictions
                    </a>
                </div>
                {#if profile.news.length > 0}
                    <hr />
                    <h6 class="text-muted text-uppercase small">Recent news</h6>
                    {#each profile.news.slice(0, 3) as item, i}
                        <div class="small py-1" class:border-bottom={i < Math.min(profile.news.length, 3) - 1}>
                            {#if typeof item.url === 'string' && item.url.startsWith('http')}
                                <a href={item.url} target="_blank" rel="noopener noreferrer">{item.headline}</a>
                            {:else}
                                <span>{item.headline}</span>
                            {/if}
                        </div>
                    {/each}
                {/if}
            </div>
        </div>
    </div>
</div>

<style>
    .stat-tile {
        text-align: center;
        padding: 0.85rem 0.5rem;
        background: var(--bs-light, #f8f9fa);
        border-radius: 0.5rem;
        height: 100%;
    }

    .stat-tile__value {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .stat-tile__label {
        font-size: 0.75rem;
        color: var(--bs-secondary-color, #6c757d);
        margin-bottom: 0.35rem;
    }

    .stat-tile__links {
        display: flex;
        justify-content: center;
        gap: 0.65rem;
        font-size: 0.7rem;
    }

    .stat-tile__links a {
        text-decoration: none;
    }

    .stat-tile__links a:hover {
        text-decoration: underline;
    }
</style>
