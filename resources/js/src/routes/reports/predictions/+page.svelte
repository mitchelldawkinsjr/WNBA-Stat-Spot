<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import TodaysBestProps from "$lib/components/TodaysBestProps.svelte";
    import type { Player, Prediction, PropBet } from '$lib/api/client';

    let players: Player[] = [];
    let selectedPlayer: string = '';
    let selectedStat: string = '';
    let selectedLine: number = 0;
    let predictions: Prediction[] = [];
    let propBets: PropBet[] = [];
    let loading = true;
    let predictionLoading = false;
    let error = '';

    const availableStats = [
        { value: 'points', label: 'Points' },
        { value: 'rebounds', label: 'Rebounds' },
        { value: 'assists', label: 'Assists' },
        { value: 'steals', label: 'Steals' },
        { value: 'blocks', label: 'Blocks' },
        { value: 'three_pointers_made', label: '3-Pointers Made' },
        { value: 'field_goals_made', label: 'Field Goals Made' },
        { value: 'free_throws_made', label: 'Free Throws Made' },
        { value: 'turnovers', label: 'Turnovers' },
        { value: 'minutes', label: 'Minutes' }
    ];

    onMount(async () => {
        try {
            const [playersResponse, propBetsResponse] = await Promise.all([
                api.players.getAll(),
                api.wnba.predictions.getPropBets()
            ]);

            players = playersResponse.data.data;
            propBets = propBetsResponse.data || [];
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load data';
        } finally {
            loading = false;
        }
    });

    async function generatePrediction() {
        if (!selectedPlayer || !selectedStat || selectedLine <= 0) return;

        predictionLoading = true;
        try {
            const response = await api.wnba.predictions.generatePrediction({
                player_id: selectedPlayer,
                stat: selectedStat,
                line: selectedLine
            });

            predictions = [response.data, ...predictions.slice(0, 9)]; // Keep last 10 predictions
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to generate prediction';
        } finally {
            predictionLoading = false;
        }
    }

    function formatPercentage(value: number): string {
        return (value * 100).toFixed(1) + '%';
    }

    function formatNumber(value: number): string {
        return value.toFixed(1);
    }

    function getConfidenceColor(confidence: number): string {
        if (confidence >= 0.7) return 'success';
        if (confidence >= 0.5) return 'warning';
        return 'danger';
    }

    function getRecommendationBadge(recommendation: string): string {
        switch (recommendation.toLowerCase()) {
            case 'over': return 'success';
            case 'under': return 'primary';
            case 'avoid': return 'danger';
            default: return 'secondary';
        }
    }
</script>

<svelte:head>
    <title>Today's Best Props & Predictions | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Predictions & Today's Best Props</h4>
                    <p class="text-muted mb-0">Discover today's most profitable prop bets and generate custom AI-powered predictions</p>
                </div>
            </div>
        </div>

        <!-- Today's Best Props - Featured Section -->
        <div class="row mb-4">
            <div class="col-12">
                <TodaysBestProps />
            </div>
        </div>

        {#if loading}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 mb-0">Loading prediction data...</p>
                        </div>
                    </div>
                </div>
            </div>
        {:else if error}
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> {error}
                    </div>
                </div>
            </div>
        {:else}
            <!-- Prediction Generator -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-magic text-primary me-2"></i>Generate Prediction
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="player-select" class="form-label">Select Player</label>
                                    <select
                                        id="player-select"
                                        bind:value={selectedPlayer}
                                        class="form-select"
                                    >
                                        <option value="">Choose a player...</option>
                                        {#each players as player}
                                            <option value={player.athlete_id}>
                                                {player.athlete_display_name} - {player.athlete_position_abbreviation || 'N/A'}
                                            </option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="stat-select" class="form-label">Statistic</label>
                                    <select
                                        id="stat-select"
                                        bind:value={selectedStat}
                                        class="form-select"
                                    >
                                        <option value="">Choose a stat...</option>
                                        {#each availableStats as stat}
                                            <option value={stat.value}>{stat.label}</option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="line-input" class="form-label">Betting Line</label>
                                    <input
                                        id="line-input"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        bind:value={selectedLine}
                                        class="form-control"
                                        placeholder="e.g., 15.5"
                                    />
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button
                                        on:click={generatePrediction}
                                        disabled={!selectedPlayer || !selectedStat || selectedLine <= 0 || predictionLoading}
                                        class="btn btn-primary w-100"
                                    >
                                        {#if predictionLoading}
                                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                            Generating...
                                        {:else}
                                            <i class="fas fa-crystal-ball me-2"></i>
                                            Predict
                                        {/if}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Predictions -->
            {#if predictions.length > 0}
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history text-success me-2"></i>Recent Predictions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Player</th>
                                                <th>Stat</th>
                                                <th>Line</th>
                                                <th>Prediction</th>
                                                <th>Confidence</th>
                                                <th>Recommendation</th>
                                                <th>Expected Value</th>
                                                <th>Generated</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each predictions as prediction}
                                                <tr>
                                                    <td>
                                                        <div class="fw-medium">{prediction.player_name}</div>
                                                        <small class="text-muted">{prediction.player_position || 'N/A'}</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary-subtle text-secondary text-capitalize">
                                                            {prediction.stat.replace('_', ' ')}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-medium">{prediction.line}</span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-medium text-primary">{formatNumber(prediction.predicted_value)}</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{getConfidenceColor(prediction.confidence)}-subtle text-{getConfidenceColor(prediction.confidence)}">
                                                            {formatPercentage(prediction.confidence)}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{getRecommendationBadge(prediction.recommendation)}-subtle text-{getRecommendationBadge(prediction.recommendation)} text-uppercase">
                                                            {prediction.recommendation}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-medium {prediction.expected_value > 0 ? 'text-success' : 'text-danger'}">
                                                            {prediction.expected_value > 0 ? '+' : ''}{formatNumber(prediction.expected_value)}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            {new Date(prediction.created_at).toLocaleString()}
                                                        </small>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Available Prop Bets -->
            <!-- {#if propBets.length > 0}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line text-info me-2"></i>Available Prop Bets
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Player</th>
                                                <th>Stat</th>
                                                <th>Line</th>
                                                <th>Over Odds</th>
                                                <th>Under Odds</th>
                                                <th>Sportsbook</th>
                                                <th>Game Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each propBets as bet}
                                                <tr>
                                                    <td>
                                                        <div class="fw-medium">{bet.player_name}</div>
                                                        <small class="text-muted">{bet.team_abbreviation}</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary-subtle text-primary text-capitalize">
                                                            {bet.stat_type.replace('_', ' ')}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-medium">{bet.line}</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success-subtle text-success">
                                                            {bet.over_odds > 0 ? '+' : ''}{bet.over_odds}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-danger-subtle text-danger">
                                                            {bet.under_odds > 0 ? '+' : ''}{bet.under_odds}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">{bet.sportsbook}</small>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            {new Date(bet.game_date).toLocaleDateString()}
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <button
                                                            on:click={() => {
                                                                selectedPlayer = bet.player_id;
                                                                selectedStat = bet.stat_type;
                                                                selectedLine = bet.line;
                                                            }}
                                                            class="btn btn-sm btn-outline-primary"
                                                        >
                                                            <i class="fas fa-magic me-1"></i>
                                                            Predict
                                                        </button>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {:else}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line text-muted fs-48 mb-3"></i>
                                <h5 class="text-muted">No Prop Bets Available</h5>
                                <p class="text-muted mb-0">Prop betting data will appear here when available from sportsbooks.</p>
                            </div>
                        </div>
                    </div>
                </div>
            {/if} -->
        {/if}
    </div>
</DefaultLayout>
