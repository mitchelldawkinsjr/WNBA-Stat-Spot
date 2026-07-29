<script lang="ts">
    import { onDestroy } from 'svelte';
    import { browser } from '$app/environment';
    import { page } from '$app/stores';
    import { goto } from '$app/navigation';
    import { api } from '$lib/api/client';
    import type { Player, Team } from '$lib/api/client';
    import DefaultLayout from '$lib/layouts/DefaultLayout.svelte';
    import {
        teamAnalytics,
        gameResultsChartData,
        seasonStatsData,
        advancedMetricsData,
    } from '$lib/stores/teamAnalytics';
    import { AVAILABLE_SEASONS, DEFAULT_SEASON } from '$lib/stores/playerProfile';
    import TeamGameResultsChart from '$lib/components/charts/TeamGameResultsChart.svelte';

    let team: Team | null = null;
    let players: Player[] = [];
    let loading = true;
    let rosterLoading = false;
    let error: string | null = null;
    let searchTerm = '';
    let selectedPosition = '';
    let sortBy = 'athlete_display_name';
    let sortOrder: 'asc' | 'desc' = 'asc';
    let loadedKey = '';

    const positions = ['G', 'F', 'C', 'PG', 'SG', 'SF', 'PF'];

    $: teamId = $page.params.id ?? '';
    $: seasonParam = Number($page.url.searchParams.get('season') || DEFAULT_SEASON);
    $: season = AVAILABLE_SEASONS.includes(seasonParam as (typeof AVAILABLE_SEASONS)[number])
        ? seasonParam
        : DEFAULT_SEASON;

    $: filteredPlayers = players.filter((player) => {
        const matchesSearch =
            !searchTerm ||
            player.athlete_display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (player.athlete_jersey && player.athlete_jersey.includes(searchTerm));
        const matchesPosition =
            !selectedPosition || player.athlete_position_abbreviation === selectedPosition;
        return matchesSearch && matchesPosition;
    });

    $: sortedPlayers = [...filteredPlayers].sort((a, b) => {
        let aValue: string | number;
        let bValue: string | number;

        switch (sortBy) {
            case 'athlete_jersey':
                aValue = parseInt(a.athlete_jersey || '999', 10);
                bValue = parseInt(b.athlete_jersey || '999', 10);
                break;
            case 'athlete_position_abbreviation':
                aValue = a.athlete_position_abbreviation || 'ZZ';
                bValue = b.athlete_position_abbreviation || 'ZZ';
                break;
            default:
                aValue = a.athlete_display_name;
                bValue = b.athlete_display_name;
        }

        if (typeof aValue === 'string' && typeof bValue === 'string') {
            return sortOrder === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
        }
        return sortOrder === 'asc' ? Number(aValue) - Number(bValue) : Number(bValue) - Number(aValue);
    });

    $: if (browser && teamId) {
        void loadTeam(teamId, season);
    }

    async function loadTeam(id: string, selectedSeason: number) {
        const key = `${id}:${selectedSeason}`;
        if (key === loadedKey) return;
        const isSeasonChange = loadedKey.startsWith(`${id}:`) && loadedKey !== key;
        loadedKey = key;

        try {
            if (isSeasonChange) {
                rosterLoading = true;
            } else {
                loading = true;
            }
            error = null;

            const [teamRes, rosterRes] = await Promise.all([
                isSeasonChange && team
                    ? Promise.resolve({ data: team })
                    : api.teams.getById(id),
                api.teams.getPlayers(id, { season: selectedSeason }),
                teamAnalytics.fetchAnalytics(id, { season: selectedSeason }),
            ]);

            team = teamRes.data ?? rosterRes.meta?.team ?? team;
            players = rosterRes.data ?? [];
            if (!team) {
                error = 'Team not found';
            }
        } catch (e) {
            error = e instanceof Error ? e.message : 'Failed to load team';
            if (!isSeasonChange) {
                players = [];
            }
        } finally {
            loading = false;
            rosterLoading = false;
        }
    }

    onDestroy(() => {
        teamAnalytics.reset();
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

    function handleSort(column: string) {
        if (sortBy === column) {
            sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            sortBy = column;
            sortOrder = 'asc';
        }
    }

    function getSortIcon(column: string) {
        if (sortBy !== column) return 'fas fa-sort text-muted';
        return sortOrder === 'asc' ? 'fas fa-sort-up text-primary' : 'fas fa-sort-down text-primary';
    }

    function hideImage(e: Event) {
        const img = e.target as HTMLImageElement;
        img.style.display = 'none';
    }
</script>

<svelte:head>
    <title>{team?.team_display_name ? `${team.team_display_name} | Team` : 'Team'} | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl team-profile">
        {#if loading}
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Loading team…</p>
                </div>
            </div>
        {:else if error && !team}
            <div class="alert alert-danger" role="alert">
                <strong>Error:</strong> {error}
            </div>
            <a href="/teams" class="btn btn-outline-primary">← All teams</a>
        {:else if team}
            <div class="page-title-box d-flex flex-wrap align-items-start justify-content-between gap-2">
                <div>
                    <div class="text-muted small mb-1">
                        <a href="/teams">Teams</a>
                        <span> / </span>
                        <span>Profile</span>
                    </div>
                    <h4 class="page-title mb-0">{team.team_display_name}</h4>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
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
                    <a href="/teams" class="btn btn-outline-primary">← All teams</a>
                </div>
            </div>

            {#if team.is_exhibition}
                <div class="alert alert-secondary mb-3" role="status">
                    <strong>{team.competition_label || 'All-Star / Exhibition'}</strong>
                    — This side is not a league franchise. Roster and box scores here are secondary
                    to regular-season / playoff team stats and do not count toward primary season averages.
                </div>
            {/if}

            <!-- Identity -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-start gap-4">
                        <div
                            class="avatar-xl bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mx-auto mx-md-0"
                        >
                            {#if team.team_logo}
                                <img
                                    src={team.team_logo}
                                    alt=""
                                    class="rounded-circle p-2"
                                    style="width: 96px; height: 96px; object-fit: contain;"
                                    on:error={hideImage}
                                />
                            {:else}
                                <i class="fas fa-basketball-ball text-primary fs-1"></i>
                            {/if}
                        </div>
                        <div class="flex-grow-1 text-center text-md-start">
                            <h2 class="mb-1">{team.team_display_name}</h2>
                            <p class="text-muted mb-3">{team.team_location}</p>
                            <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                                <span class="badge bg-primary fs-6">{team.team_abbreviation}</span>
                                {#if team.is_exhibition}
                                    <span class="badge bg-secondary fs-6">{team.competition_label || 'Exhibition'}</span>
                                {/if}
                                {#if $seasonStatsData}
                                    <span class="badge bg-success fs-6">{$seasonStatsData.record}</span>
                                    <span class="badge bg-info fs-6">{$seasonStatsData.streak} streak</span>
                                {/if}
                                <span class="badge bg-secondary fs-6">{players.length} players</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics -->
            <div id="analytics" class="mb-4">
                <h5 class="mb-3">
                    <i class="fas fa-chart-line text-primary me-2"></i>Analytics
                </h5>

                {#if $teamAnalytics.loading && !$seasonStatsData}
                    <div class="card">
                        <div class="card-body text-center py-4">
                            <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                            <span class="ms-2 text-muted">Loading analytics…</span>
                        </div>
                    </div>
                {:else if $teamAnalytics.error && !$seasonStatsData}
                    <div class="alert alert-warning mb-0">{$teamAnalytics.error}</div>
                {:else}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-xl-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Record</p>
                                    <h3 class="mb-0">{$seasonStatsData?.record ?? '—'}</h3>
                                    {#if $seasonStatsData}
                                        <small class="text-muted">Win% {$seasonStatsData.winPercentage.toFixed(3)}</small>
                                    {/if}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Points / Game</p>
                                    <h3 class="mb-0">
                                        {$seasonStatsData ? $seasonStatsData.pointsPerGame.toFixed(1) : '—'}
                                    </h3>
                                    {#if $seasonStatsData}
                                        <small class="text-muted">
                                            Allowed {$seasonStatsData.pointsAllowedPerGame.toFixed(1)}
                                        </small>
                                    {/if}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Net Rating</p>
                                    <h3 class="mb-0">
                                        {$advancedMetricsData
                                            ? $advancedMetricsData.netRating.toFixed(1)
                                            : '—'}
                                    </h3>
                                    {#if $advancedMetricsData}
                                        <small class="text-muted">
                                            Off {$advancedMetricsData.offensiveRating.toFixed(1)} · Def
                                            {$advancedMetricsData.defensiveRating.toFixed(1)}
                                        </small>
                                    {/if}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Pace / TS%</p>
                                    <h3 class="mb-0">
                                        {$advancedMetricsData
                                            ? $advancedMetricsData.pace.toFixed(1)
                                            : '—'}
                                    </h3>
                                    {#if $advancedMetricsData}
                                        <small class="text-muted">
                                            TS {$advancedMetricsData.trueShootingPercentage.toFixed(1)}%
                                        </small>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Game results</h5>
                                </div>
                                <div class="card-body">
                                    {#if $gameResultsChartData.length > 0}
                                        <TeamGameResultsChart data={$gameResultsChartData} height="320px" />
                                    {:else}
                                        <p class="text-muted mb-0">No game results available</p>
                                    {/if}
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Home / Away</h5>
                                </div>
                                <div class="card-body">
                                    {#if $teamAnalytics.homeAwaySplits}
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <p class="text-muted mb-1 small">Home</p>
                                                <h4 class="mb-1">
                                                    {$teamAnalytics.homeAwaySplits.home.wins}-{$teamAnalytics
                                                        .homeAwaySplits.home.losses}
                                                </h4>
                                                <small class="text-muted d-block">
                                                    PPG {$teamAnalytics.homeAwaySplits.home.points_per_game.toFixed(1)}
                                                </small>
                                                <small class="text-muted d-block">
                                                    Opp {$teamAnalytics.homeAwaySplits.home.points_allowed_per_game.toFixed(1)}
                                                </small>
                                            </div>
                                            <div class="col-6">
                                                <p class="text-muted mb-1 small">Away</p>
                                                <h4 class="mb-1">
                                                    {$teamAnalytics.homeAwaySplits.away.wins}-{$teamAnalytics
                                                        .homeAwaySplits.away.losses}
                                                </h4>
                                                <small class="text-muted d-block">
                                                    PPG {$teamAnalytics.homeAwaySplits.away.points_per_game.toFixed(1)}
                                                </small>
                                                <small class="text-muted d-block">
                                                    Opp {$teamAnalytics.homeAwaySplits.away.points_allowed_per_game.toFixed(1)}
                                                </small>
                                            </div>
                                        </div>
                                    {:else}
                                        <p class="text-muted mb-0">No home/away splits available</p>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Roster -->
            <div id="roster" class="mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-users text-primary me-2"></i>{season} Roster
                        <span class="text-muted fw-normal">({sortedPlayers.length})</span>
                    </h5>
                    {#if rosterLoading}
                        <span class="spinner-border spinner-border-sm text-muted" role="status"></span>
                    {/if}
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="team-roster-search" class="form-label">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input
                                        id="team-roster-search"
                                        type="text"
                                        class="form-control"
                                        placeholder="Name or jersey…"
                                        bind:value={searchTerm}
                                    />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="team-roster-position" class="form-label">Position</label>
                                <select
                                    id="team-roster-position"
                                    class="form-select"
                                    bind:value={selectedPosition}
                                >
                                    <option value="">All</option>
                                    {#each positions as position}
                                        <option value={position}>{position}</option>
                                    {/each}
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="team-roster-sort" class="form-label">Sort</label>
                                <select id="team-roster-sort" class="form-select" bind:value={sortBy}>
                                    <option value="athlete_display_name">Name</option>
                                    <option value="athlete_jersey">Jersey</option>
                                    <option value="athlete_position_abbreviation">Position</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        {#if sortedPlayers.length > 0}
                            <div class="table-responsive">
                                <table class="table table-hover table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>
                                                <button
                                                    type="button"
                                                    class="btn btn-link p-0 text-decoration-none fw-semibold"
                                                    on:click={() => handleSort('athlete_display_name')}
                                                >
                                                    Player <i class="{getSortIcon('athlete_display_name')} ms-1"></i>
                                                </button>
                                            </th>
                                            <th>
                                                <button
                                                    type="button"
                                                    class="btn btn-link p-0 text-decoration-none fw-semibold"
                                                    on:click={() => handleSort('athlete_jersey')}
                                                >
                                                    Jersey <i class="{getSortIcon('athlete_jersey')} ms-1"></i>
                                                </button>
                                            </th>
                                            <th>
                                                <button
                                                    type="button"
                                                    class="btn btn-link p-0 text-decoration-none fw-semibold"
                                                    on:click={() =>
                                                        handleSort('athlete_position_abbreviation')}
                                                >
                                                    Position
                                                    <i class="{getSortIcon('athlete_position_abbreviation')} ms-1"
                                                    ></i>
                                                </button>
                                            </th>
                                            <th>Height</th>
                                            <th>Weight</th>
                                            <th>Exp</th>
                                            <th>College</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each sortedPlayers as player}
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        {#if player.athlete_headshot_href}
                                                            <img
                                                                src={player.athlete_headshot_href}
                                                                alt=""
                                                                class="avatar-sm rounded-circle me-3"
                                                                on:error={hideImage}
                                                            />
                                                        {:else}
                                                            <div
                                                                class="avatar-sm bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                                            >
                                                                <i class="fas fa-user text-secondary"></i>
                                                            </div>
                                                        {/if}
                                                        <div>
                                                            <a
                                                                href="/players/{player.athlete_id}"
                                                                class="fw-medium text-decoration-none"
                                                            >
                                                                {player.athlete_display_name}
                                                            </a>
                                                            {#if player.athlete_short_name}
                                                                <br /><small class="text-muted"
                                                                    >{player.athlete_short_name}</small
                                                                >
                                                            {/if}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary">
                                                        #{player.athlete_jersey || 'N/A'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info-subtle text-info">
                                                        {player.athlete_position_abbreviation || 'N/A'}
                                                    </span>
                                                </td>
                                                <td class="text-muted">{player.athlete_height || '—'}</td>
                                                <td class="text-muted">
                                                    {player.athlete_weight
                                                        ? `${player.athlete_weight} lbs`
                                                        : '—'}
                                                </td>
                                                <td class="text-muted">{player.athlete_experience || '—'}</td>
                                                <td class="text-muted">{player.athlete_college || '—'}</td>
                                                <td class="text-end">
                                                    <a
                                                        href="/players/{player.athlete_id}"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Player profile"
                                                    >
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        {:else}
                            <div class="text-center py-5">
                                <i class="fas fa-search text-muted fs-48 mb-3"></i>
                                <h5 class="text-muted">No players found</h5>
                                <p class="text-muted mb-0">
                                    {#if searchTerm || selectedPosition}
                                        Try adjusting search or filters
                                    {:else}
                                        No players appeared for this team in {season}
                                    {/if}
                                </p>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>

<style>
    .team-profile {
        padding-bottom: 2rem;
    }

    .btn-link:hover {
        text-decoration: none !important;
    }

    .table th button {
        color: inherit;
    }

    .table th button:hover {
        color: var(--bs-primary);
    }
</style>
