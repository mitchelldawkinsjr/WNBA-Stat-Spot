<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import { Card, CardBody, CardHeader, CardTitle } from '@sveltestrap/sveltestrap';
    import BaseChart from './charts/BaseChart.svelte';
    import LoadingError from './LoadingError.svelte';
    import type { ChartData, ChartOptions } from 'chart.js';

    export let playerIds: number[] = [];
    export let maxPlayers = 3;

    interface Player {
        id: number;
        athlete_id: string;
        athlete_display_name: string;
        athlete_headshot_href?: string;
        athlete_position_name?: string;
        athlete_jersey?: string;
        team_name?: string;
    }

    interface PlayerStats {
        playerId: number;
        games_played: number;
        minutes_per_game: number;
        points: number;
        rebounds: number;
        assists: number;
        steals: number;
        blocks: number;
        turnovers: number;
        field_goal_percentage: number;
        three_point_percentage: number;
        free_throw_percentage: number;
        player_efficiency_rating: number;
        true_shooting_percentage: number;
        avg_points: number;
        avg_rebounds: number;
        avg_assists: number;
    }

    let players: Player[] = [];
    let playerStats: PlayerStats[] = [];
    let loading = false;
    let error: string | null = null;
    let availablePlayers: Player[] = [];
    let searchTerm = '';
    let selectedPlayers: Player[] = [];

    $: filteredAvailablePlayers = availablePlayers.filter(player =>
        !selectedPlayers.some(selected => selected.id === player.id) &&
        player.athlete_display_name.toLowerCase().includes(searchTerm.toLowerCase())
    );

    // Statistical categories for comparison
    const statCategories = [
        { key: 'points', label: 'Points', unit: 'PPG' },
        { key: 'rebounds', label: 'Rebounds', unit: 'RPG' },
        { key: 'assists', label: 'Assists', unit: 'APG' },
        { key: 'steals', label: 'Steals', unit: 'SPG' },
        { key: 'blocks', label: 'Blocks', unit: 'BPG' },
        { key: 'minutes_per_game', label: 'Minutes', unit: 'MPG' },
        { key: 'field_goal_percentage', label: 'FG%', unit: '%' },
        { key: 'three_point_percentage', label: '3P%', unit: '%' },
        { key: 'free_throw_percentage', label: 'FT%', unit: '%' },
        { key: 'true_shooting_percentage', label: 'TS%', unit: '%' },
        { key: 'player_efficiency_rating', label: 'PER', unit: '' }
    ];

    const colors = [
        '#3b82f6', // Blue
        '#ef4444', // Red
        '#10b981', // Green
        '#f59e0b', // Amber
        '#8b5cf6'  // Purple
    ];

    onMount(async () => {
        await loadAvailablePlayers();
        if (playerIds.length > 0) {
            await loadPlayerComparison(playerIds);
        }
    });

    async function loadAvailablePlayers() {
        try {
            const response = await api.players.getAll({ per_page: 200 });
            availablePlayers = response.data.data;
        } catch (e) {
            console.error('Failed to load players:', e);
        }
    }

    async function loadPlayerComparison(ids: number[]) {
        loading = true;
        error = null;

        try {
            // Load player info and stats in parallel
            const playerPromises = ids.map(id => api.players.getById(id));
            const statsPromises = ids.map(id => api.wnba.analytics.getPlayer(id));

            const [playerResponses, statsResponses] = await Promise.all([
                Promise.all(playerPromises),
                Promise.all(statsPromises)
            ]);

            players = playerResponses.map(response => response.data);
            playerStats = statsResponses.map((response, index) => ({
                playerId: ids[index],
                ...response.data?.summary || {}
            }));

            selectedPlayers = [...players];
        } catch (e) {
            error = e instanceof Error ? e.message : 'Failed to load player data';
        } finally {
            loading = false;
        }
    }

    function addPlayer(player: Player) {
        if (selectedPlayers.length >= maxPlayers) return;

        selectedPlayers = [...selectedPlayers, player];
        const newPlayerIds = selectedPlayers.map(p => p.id);
        loadPlayerComparison(newPlayerIds);
        searchTerm = '';
    }

    function removePlayer(playerId: number) {
        selectedPlayers = selectedPlayers.filter(p => p.id !== playerId);
        players = players.filter(p => p.id !== playerId);
        playerStats = playerStats.filter(s => s.playerId !== playerId);
    }

    function clearComparison() {
        selectedPlayers = [];
        players = [];
        playerStats = [];
        error = null;
    }

    // Generate radar chart data for comparison
    $: radarChartData = {
        labels: ['Points', 'Rebounds', 'Assists', 'Steals', 'Blocks', 'FG%', '3P%', 'FT%'],
        datasets: playerStats.map((stats, index) => {
            const player = players.find(p => p.id === stats.playerId);
            return {
                label: player?.athlete_display_name || `Player ${index + 1}`,
                data: [
                    stats.avg_points || 0,
                    stats.avg_rebounds || 0,
                    stats.avg_assists || 0,
                    stats.steals || 0,
                    stats.blocks || 0,
                    stats.field_goal_percentage || 0,
                    stats.three_point_percentage || 0,
                    stats.free_throw_percentage || 0
                ],
                borderColor: colors[index % colors.length],
                backgroundColor: `${colors[index % colors.length]}20`,
                pointBackgroundColor: colors[index % colors.length],
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: colors[index % colors.length],
                borderWidth: 2
            };
        })
    } as ChartData<'radar'>;

    const radarChartOptions: ChartOptions<'radar'> = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            r: {
                beginAtZero: true,
                max: 100,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                angleLines: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                pointLabels: {
                    font: {
                        size: 12
                    }
                }
            }
        },
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    };

    function formatStatValue(value: number | undefined, unit: string): string {
        if (value === undefined || value === null) return 'N/A';

        if (unit === '%') {
            return `${value.toFixed(1)}%`;
        } else if (Number.isInteger(value)) {
            return value.toString();
        } else {
            return value.toFixed(1);
        }
    }
</script>

<div class="player-comparison">
    <!-- Player Selection -->
    <Card class="mb-4">
        <CardHeader>
            <div class="d-flex justify-content-between align-items-center">
                <CardTitle class="mb-0">Player Comparison</CardTitle>
                {#if selectedPlayers.length > 0}
                    <button class="btn btn-outline-danger btn-sm" on:click={clearComparison}>
                        <i class="mdi mdi-close me-1"></i>Clear All
                    </button>
                {/if}
            </div>
        </CardHeader>
        <CardBody>
            {#if selectedPlayers.length < maxPlayers}
                <div class="mb-3">
                    <label class="form-label">Add Player to Compare:</label>
                    <input
                        type="text"
                        class="form-control"
                        placeholder="Search for players..."
                        bind:value={searchTerm}
                    />

                    {#if searchTerm && filteredAvailablePlayers.length > 0}
                        <div class="search-results mt-2">
                            {#each filteredAvailablePlayers.slice(0, 5) as player}
                                <button
                                    class="search-result-item"
                                    on:click={() => addPlayer(player)}
                                >
                                    <div class="d-flex align-items-center">
                                        {#if player.athlete_headshot_href}
                                            <img
                                                src={player.athlete_headshot_href}
                                                alt={player.athlete_display_name}
                                                class="player-avatar me-2"
                                            />
                                        {:else}
                                            <div class="player-avatar-placeholder me-2">
                                                <i class="mdi mdi-account"></i>
                                            </div>
                                        {/if}
                                        <div>
                                            <div class="fw-medium">{player.athlete_display_name}</div>
                                            <small class="text-muted">
                                                {player.athlete_position_name || 'Player'}
                                                {player.team_name ? `• ${player.team_name}` : ''}
                                            </small>
                                        </div>
                                    </div>
                                </button>
                            {/each}
                        </div>
                    {/if}
                </div>
            {/if}

            <!-- Selected Players -->
            {#if selectedPlayers.length > 0}
                <div class="selected-players">
                    <h6 class="mb-2">Comparing Players:</h6>
                    <div class="player-chips">
                        {#each selectedPlayers as player, index}
                            <div class="player-chip" style="border-color: {colors[index % colors.length]}">
                                <span class="player-name">{player.athlete_display_name}</span>
                                <button
                                    class="btn-close btn-close-sm ms-1"
                                    on:click={() => removePlayer(player.id)}
                                ></button>
                            </div>
                        {/each}
                    </div>
                </div>
            {/if}
        </CardBody>
    </Card>

    <!-- Comparison Results -->
    {#if selectedPlayers.length > 0}
        <LoadingError {loading} {error} loadingText="Loading player comparison..." />

        {#if !loading && !error && players.length > 0}
            <!-- Overview Cards -->
            <div class="row mb-4">
                {#each players as player, index}
                    <div class="col-md-{12 / players.length}">
                        <Card class="player-overview-card" style="border-top: 4px solid {colors[index % colors.length]}">
                            <CardBody class="text-center">
                                {#if player.athlete_headshot_href}
                                    <img
                                        src={player.athlete_headshot_href}
                                        alt={player.athlete_display_name}
                                        class="player-headshot mb-2"
                                    />
                                {:else}
                                    <div class="player-headshot-placeholder mb-2">
                                        <i class="mdi mdi-account fs-1"></i>
                                    </div>
                                {/if}
                                <h5 class="mb-1">{player.athlete_display_name}</h5>
                                <p class="text-muted mb-2">
                                    {player.athlete_position_name || 'Player'}
                                    {#if player.athlete_jersey}
                                        • #{player.athlete_jersey}
                                    {/if}
                                </p>
                                {#if player.team_name}
                                    <span class="badge bg-secondary">{player.team_name}</span>
                                {/if}
                            </CardBody>
                        </Card>
                    </div>
                {/each}
            </div>

            <!-- Radar Chart Comparison -->
            {#if playerStats.length > 0}
                <div class="row mb-4">
                    <div class="col-12">
                        <Card>
                            <CardHeader>
                                <CardTitle class="mb-0">Performance Comparison</CardTitle>
                            </CardHeader>
                            <CardBody>
                                <BaseChart
                                    chartType="radar"
                                    data={radarChartData}
                                    options={radarChartOptions}
                                    height="400px"
                                />
                            </CardBody>
                        </Card>
                    </div>
                </div>

                <!-- Detailed Statistics Table -->
                <Card>
                    <CardHeader>
                        <CardTitle class="mb-0">Detailed Statistics</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Statistic</th>
                                        {#each players as player, index}
                                            <th style="color: {colors[index % colors.length]}">
                                                {player.athlete_display_name}
                                            </th>
                                        {/each}
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each statCategories as category}
                                        <tr>
                                            <td class="fw-medium">{category.label}</td>
                                            {#each playerStats as stats}
                                                <td>
                                                    {formatStatValue(stats[category.key], category.unit)}
                                                </td>
                                            {/each}
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    </CardBody>
                </Card>
            {/if}
        {/if}
    {:else}
        <!-- Empty State -->
        <Card>
            <CardBody class="text-center py-5">
                <div class="mb-3">
                    <i class="mdi mdi-compare fs-1 text-muted"></i>
                </div>
                <h5 class="mb-2">Compare Players</h5>
                <p class="text-muted mb-0">
                    Select up to {maxPlayers} players to compare their statistics and performance.
                </p>
            </CardBody>
        </Card>
    {/if}
</div>

<style>
    .search-results {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        max-height: 200px;
        overflow-y: auto;
    }

    .search-result-item {
        width: 100%;
        padding: 0.5rem;
        border: none;
        background: none;
        text-align: left;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .search-result-item:hover {
        background-color: #f8f9fa;
    }

    .player-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
    }

    .player-avatar-placeholder {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        color: #6c757d;
    }

    .player-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .player-chip {
        display: flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        border: 2px solid;
        border-radius: 1rem;
        background-color: #f8f9fa;
        font-size: 0.875rem;
    }

    .player-name {
        font-weight: 500;
    }

    .player-headshot {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
    }

    .player-headshot-placeholder {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background-color: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        color: #6c757d;
    }

    .player-overview-card {
        height: 100%;
    }
</style>
