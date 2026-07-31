<script lang="ts">
    import { onDestroy, onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import { trackPageLoad } from '$lib/stores/pageLoading';

    interface Stats {
        id: number;
        game_id: number;
        player_id: number;
        team_id: number;
        minutes: string | null;
        field_goals_made: number;
        field_goals_attempted: number;
        three_point_field_goals_made: number;
        three_point_field_goals_attempted: number;
        free_throws_made: number;
        free_throws_attempted: number;
        rebounds: number;
        assists: number;
        steals: number;
        blocks: number;
        turnovers: number;
        fouls: number;
        points: number;
        starter: boolean;
        did_not_play: boolean;
        player?: {
            athlete_id: string;
            athlete_display_name: string;
            athlete_position_abbreviation: string | null;
            athlete_headshot_href: string | null;
        };
        team?: {
            team_display_name: string;
            team_abbreviation: string;
            team_logo: string;
        };
        game?: {
            game_date: string;
            season: string;
        };
    }

    let stats: Stats[] = [];
    let loading = true;
    let error: string | null = null;
    let sortBy: 'points' | 'rebounds' | 'assists' | 'steals' | 'blocks' = 'points';
    let searchTerm = '';

    $: filteredStats = stats.filter(stat =>
        !stat.did_not_play && (
            (stat.player?.athlete_display_name && stat.player.athlete_display_name.toLowerCase().includes(searchTerm.toLowerCase())) ||
            (stat.team?.team_display_name && stat.team.team_display_name.toLowerCase().includes(searchTerm.toLowerCase()))
        )
    );

    $: sortedStats = [...filteredStats].sort((a, b) => b[sortBy] - a[sortBy]);

    const doneLoading = trackPageLoad();
    onDestroy(doneLoading);

    onMount(async () => {
        try {
            const response = await api.stats.getAll();
            stats = response.data;
        } catch (e) {
            error = e instanceof Error ? e.message : 'An error occurred';
        } finally {
            loading = false;
            doneLoading();
        }
    });

    function formatDate(dateString: string): string {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    }

    function getFieldGoalPercentage(made: number, attempted: number): string {
        if (attempted === 0) return '0.0';
        return ((made / attempted) * 100).toFixed(1);
    }
</script>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <a href="/" class="btn btn-outline-primary">
                            ← Back to Dashboard
                        </a>
                    </div>
                    <h4 class="page-title">WNBA Statistics</h4>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="row mb-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Search by player or team name..."
                                bind:value={searchTerm}
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <select class="form-select" bind:value={sortBy}>
                            <option value="points">Sort by Points</option>
                            <option value="rebounds">Sort by Rebounds</option>
                            <option value="assists">Sort by Assists</option>
                            <option value="steals">Sort by Steals</option>
                            <option value="blocks">Sort by Blocks</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {#if loading}
            <!-- Global BrandLoadingScreen covers the viewport -->
        {:else if error}
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <strong>Error:</strong> {error}
                    </div>
                </div>
            </div>
        {:else}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Top Performances
                                {#if sortedStats.length > 0}
                                    <span class="badge bg-primary-subtle text-primary ms-2">
                                        Showing {Math.min(sortedStats.length, 200)} of {sortedStats.length}
                                    </span>
                                {/if}
                            </h5>
                        </div>
                        <div class="card-body">
                            {#if sortedStats.length > 0}
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Player</th>
                                                <th>Team</th>
                                                <th>Date</th>
                                                <th>MIN</th>
                                                <th class="text-center">PTS</th>
                                                <th class="text-center">REB</th>
                                                <th class="text-center">AST</th>
                                                <th class="text-center">STL</th>
                                                <th class="text-center">BLK</th>
                                                <th class="text-center">FG%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each sortedStats.slice(0, 200) as stat, index}
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-secondary">{index + 1}</span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-xs bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2">
                                                                {#if stat.player?.athlete_headshot_href}
                                                                    <img src={stat.player.athlete_headshot_href} alt={stat.player.athlete_display_name} class="rounded-circle" style="width: 24px; height: 24px; object-fit: cover;" />
                                                                {:else}
                                                                    <i class="fas fa-user text-success fs-12"></i>
                                                                {/if}
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold">{stat.player?.athlete_display_name || 'Unknown'}</div>
                                                                <small class="text-muted">{stat.player?.athlete_position_abbreviation || 'N/A'}</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            {#if stat.team?.team_logo}
                                                                <img src={stat.team.team_logo} alt={stat.team.team_abbreviation} style="width: 20px; height: 20px; object-fit: contain;" class="me-2" />
                                                            {/if}
                                                            <small>{stat.team?.team_abbreviation || 'N/A'}</small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <small>{formatDate(stat.game?.game_date || '')}</small>
                                                    </td>
                                                    <td>
                                                        <small>{stat.minutes || '0:00'}</small>
                                                    </td>
                                                    <td class="text-center">
                                                        <strong class="text-primary">{stat.points}</strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <strong class="text-success">{stat.rebounds}</strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <strong class="text-info">{stat.assists}</strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <strong class="text-warning">{stat.steals}</strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <strong class="text-danger">{stat.blocks}</strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <small>{getFieldGoalPercentage(stat.field_goals_made, stat.field_goals_attempted)}%</small>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            {:else}
                                <div class="text-center py-4">
                                    <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-chart-bar text-muted fs-24"></i>
                                    </div>
                                    <h5 class="mb-2">No Statistics Found</h5>
                                    <p class="text-muted mb-0">
                                        {#if searchTerm}
                                            No statistics match your search criteria.
                                        {:else}
                                            There are currently no statistics in the database.
                                        {/if}
                                    </p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>
