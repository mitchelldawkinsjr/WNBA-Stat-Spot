<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api } from '$lib/api/client';
    import { playerProfile } from '$lib/stores/playerProfile';
    import {
        mapGamelogToPlayerGame,
        type ProfileGameRow,
    } from '$lib/utils/playerGamelog';

    let games: ProfileGameRow[] = [];
    let loading = false;
    let loadedKey = '';

    $: profile = $playerProfile;
    $: playerId = $page.params.id;
    $: season = profile.season;

    async function loadGamelog() {
        if (!playerId) return;
        const key = `${playerId}:${season}`;
        if (key === loadedKey) return;
        loadedKey = key;

        loading = true;
        try {
            const response = await api.players.getGamelog(playerId, { season, last_n_games: 50 });
            if (response.success && response.data?.games?.length) {
                games = response.data.games
                    .map((row) => mapGamelogToPlayerGame(row, season))
                    .filter((g) => !g.did_not_play);
            } else {
                games = [];
            }
        } catch {
            games = [];
        } finally {
            loading = false;
        }
    }

    onMount(loadGamelog);
    $: if (playerId && season) loadGamelog();
</script>

<svelte:head>
    <title>{profile.player?.athlete_display_name || 'Player'} Game Log | WNBA Stat Spot</title>
</svelte:head>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Game log · {season}</h5>
        {#if loading}
            <span class="spinner-border spinner-border-sm text-muted" role="status"></span>
        {/if}
    </div>
    <div class="card-body">
        {#if loading && games.length === 0}
            <div class="text-center py-4 text-muted">Loading game log...</div>
        {:else if games.length > 0}
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Opp</th>
                            <th>MIN</th>
                            <th>PTS</th>
                            <th>REB</th>
                            <th>AST</th>
                            <th>STL</th>
                            <th>BLK</th>
                            <th>FG</th>
                            <th>3PT</th>
                            <th>FT</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each games as game}
                            <tr>
                                <td><small>{new Date(game.game?.game_date || '').toLocaleDateString()}</small></td>
                                <td><small>{game.team?.team_abbreviation || 'N/A'}</small></td>
                                <td><small>{game.minutes || '0:00'}</small></td>
                                <td><strong>{game.points}</strong></td>
                                <td>{game.rebounds}</td>
                                <td>{game.assists}</td>
                                <td>{game.steals}</td>
                                <td>{game.blocks}</td>
                                <td><small>{game.field_goals_made}/{game.field_goals_attempted}</small></td>
                                <td><small>{game.three_point_field_goals_made}/{game.three_point_field_goals_attempted}</small></td>
                                <td><small>{game.free_throws_made}/{game.free_throws_attempted}</small></td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {:else}
            <div class="text-center py-4">
                <h5 class="mb-2">No game stats</h5>
                <p class="text-muted mb-0">No recorded games for {season}.</p>
            </div>
        {/if}
    </div>
</div>
