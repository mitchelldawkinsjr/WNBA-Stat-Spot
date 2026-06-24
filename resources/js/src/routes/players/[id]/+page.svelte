<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import PredictionEngine from "$lib/components/PredictionEngine.svelte";
    import type { PropScannerBet } from '$lib/api/client';

    interface PlayerGame {
        id: number;
        game_id: number;
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

    interface Player {
        id: number;
        athlete_id: string;
        athlete_display_name: string;
        athlete_short_name: string;
        athlete_jersey: string | null;
        athlete_headshot_href: string | null;
        athlete_position_name: string | null;
        athlete_position_abbreviation: string | null;
        player_games?: PlayerGame[];
    }

    let player: Player | null = null;
    let loading = true;
    let error: string | null = null;
    let playerId: string;
    let gamelogSeason = 2026;
    let gamelogLoading = false;
    let gamelogGames: PlayerGame[] = [];
    let predictionEngineRef: any;

    // Prop Scanner state
    let propScannerData: PropScannerBet[] = [];
    let propScannerLoading = false;
    let propScannerError = '';

    // Historical Testing state
    let historicalTestResults: any[] = [];
    let historicalTestLoading = false;
    let historicalTestError = '';
    let showDetailModal = false;
    let selectedTestResult: any = null;

    // Historical Testing filters
    let testFilters = {
        stat_type: '',
        min_accuracy: null as number | null,
        sort_by: 'accuracy_percentage',
        sort_order: 'desc'
    };

    $: playerId = $page.params.id;

    function mapGamelogToPlayerGame(row: Record<string, unknown>, season: number): PlayerGame {
        const minutes = row.minutes;
        const oppAbbr = String(row.opponent_team_abbreviation ?? 'OPP');
        const homeAway = row.home_away === 'away' ? '@' : 'vs';
        return {
            id: 0,
            game_id: Number(row.game_id) || 0,
            minutes: minutes != null ? String(minutes) : null,
            field_goals_made: Number(row.field_goals_made) || 0,
            field_goals_attempted: Number(row.field_goals_attempted) || 0,
            three_point_field_goals_made: Number(row.three_point_field_goals_made) || 0,
            three_point_field_goals_attempted: Number(row.three_point_field_goals_attempted) || 0,
            free_throws_made: Number(row.free_throws_made) || 0,
            free_throws_attempted: Number(row.free_throws_attempted) || 0,
            rebounds: Number(row.rebounds) || 0,
            assists: Number(row.assists) || 0,
            steals: Number(row.steals) || 0,
            blocks: Number(row.blocks) || 0,
            turnovers: Number(row.turnovers) || 0,
            fouls: Number(row.fouls) || 0,
            points: Number(row.points) || 0,
            starter: false,
            did_not_play: false,
            team: {
                team_display_name: String(row.opponent_team_name ?? 'Opponent'),
                team_abbreviation: `${homeAway} ${oppAbbr}`,
                team_logo: '',
            },
            game: {
                game_date: String(row.game_date ?? ''),
                season: String(season),
            },
        };
    }

    // Prefer live ESPN/Tank01 gamelog for the selected season; fall back to DB rows.
    $: dbGameStats = player?.player_games?.filter(game => !game.did_not_play) || [];
    $: gameStats = (gamelogGames.length > 0 ? gamelogGames : dbGameStats)
        .filter(game => !game.did_not_play);
    $: averages = gameStats.length > 0 ? {
        points: (gameStats.reduce((sum, game) => sum + game.points, 0) / gameStats.length).toFixed(1),
        rebounds: (gameStats.reduce((sum, game) => sum + game.rebounds, 0) / gameStats.length).toFixed(1),
        assists: (gameStats.reduce((sum, game) => sum + game.assists, 0) / gameStats.length).toFixed(1),
        steals: (gameStats.reduce((sum, game) => sum + game.steals, 0) / gameStats.length).toFixed(1),
        blocks: (gameStats.reduce((sum, game) => sum + game.blocks, 0) / gameStats.length).toFixed(1),
        fg_percentage: gameStats.reduce((sum, game) => sum + game.field_goals_attempted, 0) > 0
            ? ((gameStats.reduce((sum, game) => sum + game.field_goals_made, 0) / gameStats.reduce((sum, game) => sum + game.field_goals_attempted, 0)) * 100).toFixed(1)
            : '0.0'
    } : null;

    function scrollToPredictionEngine(stat?: string, suggestedLine?: number) {
        const element = document.getElementById('prediction-engine');
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // If we have a stat, we could trigger the prediction engine to pre-fill
            if (stat && predictionEngineRef) {
                // This would require exposing methods from the PredictionEngine component
                setTimeout(() => {
                    predictionEngineRef.prefillStat?.(stat, suggestedLine);
                }, 500);
            }
        }
    }

    function scrollToHistoricalTesting() {
        const element = document.getElementById('historical-testing');
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    async function loadPropScannerData() {
        try {
            propScannerLoading = true;
            propScannerError = '';

            // Use the same prediction API as quick predictions for consistency
            const statTypes = ['points', 'rebounds', 'assists', 'steals', 'blocks'];
            const predictions = [];

            for (const statType of statTypes) {
                try {
                    // Use the same prediction endpoint as quick predictions with odds integration
                    const response = await api.wnba.predictions.generatePrediction({
                        player_id: player?.athlete_id || playerId, // Use athlete_id if available
                        stat: statType,
                        line: getDefaultLine(statType)
                    });

                    if (response.success && response.data) {
                        const data = response.data;

                        // Convert to PropScannerBet format for consistency
                        predictions.push({
                            player_id: player?.athlete_id || playerId, // Use athlete_id if available
                            player_name: player?.athlete_display_name || 'Unknown',
                            player_position: player?.athlete_position_abbreviation || 'N/A',
                            stat_type: statType,
                            suggested_line: data.line || getDefaultLine(statType), // Use actual line from odds API
                            original_line: getDefaultLine(statType), // Keep original for reference
                            predicted_value: data.predicted_value || getDefaultLine(statType),
                            confidence: data.confidence || 0.75,
                            probability_over: data.probability_over || 0.5,
                            probability_under: data.probability_under || 0.5,
                            expected_value: data.expected_value || 0,
                            recommendation: data.recommendation as 'over' | 'under' | 'avoid' || 'avoid',
                            // Use cached prediction data instead of frontend averages
                            recent_form: data.predicted_value || parseFloat(getStatFromAverages(averages, statType) || '0') || 0,
                            season_average: data.predicted_value || parseFloat(getStatFromAverages(averages, statType) || '0') || 0,
                            matchup_difficulty: 'Average',
                            injury_risk: 'Low',
                            betting_value: getValueRating(data.expected_value || 0),
                            reasoning: data.reasoning || 'Based on cached prediction engine with odds data',
                            data_source: data.data_source || 'cached_prediction_engine_with_odds',
                            line_source: data.line_source || 'estimated',
                            odds_data: data.odds_data || {},
                            created_at: new Date().toISOString()
                        });
                    }
                } catch (err) {
                    // Failed to get prediction, using fallback
                    // Add fallback data
                    predictions.push(generateFallbackPropBet(statType));
                }
            }

            propScannerData = predictions;

        } catch (err) {
            propScannerError = err instanceof Error ? err.message : 'Failed to load prop scanner data';
            // Generate fallback data
            const statTypes = ['points', 'rebounds', 'assists', 'steals', 'blocks'];
            propScannerData = statTypes.map(statType => generateFallbackPropBet(statType));
        } finally {
            propScannerLoading = false;
        }
    }

    function getDefaultLine(statType: string): number {
        // Use the same logic as quick prediction buttons
        if (!averages) return 10;

        switch (statType) {
            case 'points':
                return Math.round((parseFloat(averages.points) || 15) * 2) / 2;
            case 'rebounds':
                return Math.round((parseFloat(averages.rebounds) || 6) * 2) / 2;
            case 'assists':
                return Math.round((parseFloat(averages.assists) || 4) * 2) / 2;
            case 'steals':
                return Math.round((parseFloat(averages.steals) || 1) * 2) / 2;
            case 'blocks':
                return Math.round((parseFloat(averages.blocks) || 0.5) * 2) / 2;
            default:
                return 10;
        }
    }

    function generateFallbackPropBet(statType: string): PropScannerBet {
        const line = getDefaultLine(statType);
        return {
            player_id: player?.athlete_id || playerId, // Use athlete_id if available
            player_name: player?.athlete_display_name || 'Unknown',
            player_position: player?.athlete_position_abbreviation || 'N/A',
            stat_type: statType,
            suggested_line: line,
            predicted_value: line,
            confidence: 0.5,
            probability_over: 0.5,
            probability_under: 0.5,
            expected_value: 0,
            recommendation: 'avoid',
            recent_form: parseFloat(averages?.points || '0') || 0,
            season_average: parseFloat(getStatFromAverages(averages, statType) || '0') || 0,
            matchup_difficulty: 'Average',
            injury_risk: 'Low',
            betting_value: 'fair',
            created_at: new Date().toISOString()
        };
    }

    function getValueRating(expectedValue: number): 'excellent' | 'good' | 'fair' | 'poor' {
        if (expectedValue > 0.1) return 'excellent';
        if (expectedValue > 0.05) return 'good';
        if (expectedValue > 0) return 'fair';
        return 'poor';
    }

    function formatPercentage(value: number): string {
        return `${(value * 100).toFixed(1)}%`;
    }

    function getRecommendationColor(recommendation: string): string {
        switch (recommendation) {
            case 'over': return 'success';
            case 'under': return 'warning';
            case 'avoid': return 'danger';
            default: return 'secondary';
        }
    }

    function getBettingValueColor(value: string): string {
        switch (value) {
            case 'excellent': return 'success';
            case 'good': return 'info';
            case 'fair': return 'warning';
            case 'poor': return 'danger';
            default: return 'secondary';
        }
    }

    function getMatchupDifficultyColor(difficulty: string): string {
        switch (difficulty.toLowerCase()) {
            case 'easy': return 'success';
            case 'moderate': return 'warning';
            case 'hard': return 'danger';
            default: return 'secondary';
        }
    }

    function getInjuryRiskColor(risk: string): string {
        switch (risk.toLowerCase()) {
            case 'low': return 'success';
            case 'medium': return 'warning';
            case 'high': return 'danger';
            default: return 'secondary';
        }
    }

    function getStatFromAverages(averages: any, statType: string): string | null {
        switch (statType) {
            case 'points':
                return averages.points;
            case 'rebounds':
                return averages.rebounds;
            case 'assists':
                return averages.assists;
            case 'steals':
                return averages.steals;
            case 'blocks':
                return averages.blocks;
            default:
                return null;
        }
    }

    async function loadHistoricalTestResults() {
        try {
            historicalTestLoading = true;
            historicalTestError = '';

            // Use athlete_id instead of database id for historical testing API
            const params: any = {
                player_id: player?.athlete_id || playerId, // Use athlete_id if available, fallback to playerId
                limit: 50,
                sort_by: testFilters.sort_by,
                sort_order: testFilters.sort_order
            };

            if (testFilters.stat_type) {
                params.stat_type = testFilters.stat_type;
            }

            if (testFilters.min_accuracy !== null) {
                params.min_accuracy = testFilters.min_accuracy;
            }

            // Loading historical test results with filtered parameters

            const response = await api.wnba.testing.getHistoricalResults(params);

            // Processing historical test response

            if (response.success) {
                // The API returns data in response.data.results, not response.data
                historicalTestResults = response.data?.results || [];
                // Historical test results loaded successfully
            } else {
                historicalTestError = 'Failed to load historical test results';
                // API returned success=false
            }

        } catch (err) {
            historicalTestError = err instanceof Error ? err.message : 'Failed to load historical test results';
            // Error loading historical test results
        } finally {
            historicalTestLoading = false;
        }
    }

    function showTestDetails(testResult: any) {
        selectedTestResult = testResult;
        showDetailModal = true;
    }

    function closeDetailModal() {
        showDetailModal = false;
        selectedTestResult = null;
    }

    function getAccuracyBadgeClass(accuracy: number): string {
        if (accuracy >= 85) return 'bg-success';
        if (accuracy >= 75) return 'bg-primary';
        if (accuracy >= 65) return 'bg-warning';
        return 'bg-danger';
    }

    function formatDate(dateString: string): string {
        return new Date(dateString).toLocaleDateString();
    }

    function safeToFixed(value: number | null | undefined, decimals: number = 1): string {
        const numValue = typeof value === 'number' ? value : parseFloat(String(value || 0));
        return isNaN(numValue) ? '0.0' : numValue.toFixed(decimals);
    }

    function safeNumber(value: number | null | undefined): number {
        const numValue = typeof value === 'number' ? value : parseFloat(String(value || 0));
        return isNaN(numValue) ? 0 : numValue;
    }

    // Computed property to check if there are game-by-game predictions
    $: hasGameByGamePredictions = selectedTestResult?.line_results?.some((lr: any) => lr.predictions && lr.predictions.length > 0) || false;

    async function loadPlayer() {
        try {
            loading = true;
            const response = await api.players.getById(playerId);
            player = response.data;
        } catch (e) {
            error = e instanceof Error ? e.message : 'An error occurred';
        } finally {
            loading = false;
        }
    }

    async function loadGamelog(season = gamelogSeason) {
        try {
            gamelogLoading = true;
            const response = await api.players.getGamelog(playerId, { season, last_n_games: 50 });
            if (response.success && response.data?.games?.length) {
                gamelogGames = response.data.games.map((row) => mapGamelogToPlayerGame(row, season));
            } else {
                gamelogGames = [];
            }
        } catch {
            gamelogGames = [];
        } finally {
            gamelogLoading = false;
        }
    }

    async function changeGamelogSeason(season: number) {
        gamelogSeason = season;
        await loadGamelog(season);
    }

    onMount(async () => {
        await loadPlayer();
        await loadGamelog();
        await loadPropScannerData();
        await loadHistoricalTestResults();
    });
</script>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <a href="/players" class="btn btn-outline-primary me-2">
                            ← Back to Players
                        </a>
                        <a href="/players/{playerId}/analytics" class="btn btn-success me-2">
                            <i class="fas fa-chart-line me-1"></i>Advanced Analytics
                        </a>
                        <a href="/players/{playerId}/data" class="btn btn-success me-2">
                            <i class="fas fa-database me-1"></i>Season Stats
                        </a>
                        <button
                            on:click={() => scrollToPredictionEngine()}
                            class="btn btn-outline-success me-2"
                        >
                            <i class="fas fa-crystal-ball me-1"></i>Predictions
                        </button>
                        <button
                            on:click={scrollToHistoricalTesting}
                            class="btn btn-outline-info me-2"
                        >
                            <i class="fas fa-chart-line me-1"></i>Historical Prop Results
                        </button>
                    </div>
                    <h4 class="page-title">Player Details</h4>
                </div>
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
                            <p class="mt-2 mb-0">Loading player details...</p>
                        </div>
                    </div>
                </div>
            </div>
        {:else if error}
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <strong>Error:</strong> {error}
                    </div>
                </div>
            </div>
        {:else if player}
            <!-- Player Info Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                        {#if player.athlete_headshot_href}
                                            <img src={player.athlete_headshot_href} alt={player.athlete_display_name} class="rounded-circle" style="width: 64px; height: 64px; object-fit: cover;" />
                                        {:else}
                                            <i class="fas fa-user text-success fs-24"></i>
                                        {/if}
                                    </div>
                                </div>
                                <div class="col">
                                    <h3 class="mb-1">{player.athlete_display_name}</h3>
                                    <p class="text-muted mb-2">{player.athlete_short_name}</p>
                                    <div class="row g-3">
                                        <div class="col-auto">
                                            <span class="badge bg-primary">#{player.athlete_jersey || 'N/A'}</span>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-secondary">{player.athlete_position_name || 'Position N/A'}</span>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-info">{gameStats.length} Games Played</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Season Averages -->
            {#if averages}
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Season Averages</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-2 col-6">
                                        <div class="text-center p-3 bg-light rounded position-relative">
                                            <h4 class="mb-1 text-primary">{averages.points}</h4>
                                            <small class="text-muted">PPG</small>
                                            <button
                                                on:click={() => scrollToPredictionEngine('points', Math.round(parseFloat(averages.points) * 2) / 2)}
                                                class="btn btn-sm btn-outline-primary position-absolute top-0 end-0 m-1"
                                                style="font-size: 10px; padding: 2px 6px;"
                                                title="Quick predict points"
                                            >
                                                <i class="fas fa-magic"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="text-center p-3 bg-light rounded position-relative">
                                            <h4 class="mb-1 text-success">{averages.rebounds}</h4>
                                            <small class="text-muted">RPG</small>
                                            <button
                                                on:click={() => scrollToPredictionEngine('rebounds', Math.round(parseFloat(averages.rebounds) * 2) / 2)}
                                                class="btn btn-sm btn-outline-success position-absolute top-0 end-0 m-1"
                                                style="font-size: 10px; padding: 2px 6px;"
                                                title="Quick predict rebounds"
                                            >
                                                <i class="fas fa-magic"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="text-center p-3 bg-light rounded position-relative">
                                            <h4 class="mb-1 text-info">{averages.assists}</h4>
                                            <small class="text-muted">APG</small>
                                            <button
                                                on:click={() => scrollToPredictionEngine('assists', Math.round(parseFloat(averages.assists) * 2) / 2)}
                                                class="btn btn-sm btn-outline-info position-absolute top-0 end-0 m-1"
                                                style="font-size: 10px; padding: 2px 6px;"
                                                title="Quick predict assists"
                                            >
                                                <i class="fas fa-magic"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="text-center p-3 bg-light rounded position-relative">
                                            <h4 class="mb-1 text-warning">{averages.steals}</h4>
                                            <small class="text-muted">SPG</small>
                                            <button
                                                on:click={() => scrollToPredictionEngine('steals', Math.round(parseFloat(averages.steals) * 2) / 2)}
                                                class="btn btn-sm btn-outline-warning position-absolute top-0 end-0 m-1"
                                                style="font-size: 10px; padding: 2px 6px;"
                                                title="Quick predict steals"
                                            >
                                                <i class="fas fa-magic"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="text-center p-3 bg-light rounded position-relative">
                                            <h4 class="mb-1 text-danger">{averages.blocks}</h4>
                                            <small class="text-muted">BPG</small>
                                            <button
                                                on:click={() => scrollToPredictionEngine('blocks', Math.round(parseFloat(averages.blocks) * 2) / 2)}
                                                class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-1"
                                                style="font-size: 10px; padding: 2px 6px;"
                                                title="Quick predict blocks"
                                            >
                                                <i class="fas fa-magic"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <h4 class="mb-1 text-secondary">{averages.fg_percentage}%</h4>
                                            <small class="text-muted">FG%</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Game Log -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Game Log</h5>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Season">
                                <button
                                    type="button"
                                    class="btn btn-outline-primary"
                                    class:active={gamelogSeason === 2026}
                                    disabled={gamelogLoading}
                                    on:click={() => changeGamelogSeason(2026)}
                                >2026</button>
                                <button
                                    type="button"
                                    class="btn btn-outline-primary"
                                    class:active={gamelogSeason === 2025}
                                    disabled={gamelogLoading}
                                    on:click={() => changeGamelogSeason(2025)}
                                >2025</button>
                            </div>
                        </div>
                        <div class="card-body">
                            {#if gameStats.length > 0}
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Team</th>
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
                                            {#each gameStats.slice(0, 20) as game}
                                                <tr>
                                                    <td>
                                                        <small>{new Date(game.game?.game_date || '').toLocaleDateString()}</small>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            {#if game.team?.team_logo}
                                                                <img src={game.team.team_logo} alt={game.team.team_abbreviation} style="width: 20px; height: 20px; object-fit: contain;" class="me-2" />
                                                            {/if}
                                                            <small>{game.team?.team_abbreviation || 'N/A'}</small>
                                                        </div>
                                                    </td>
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
                                    <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-chart-bar text-muted fs-24"></i>
                                    </div>
                                    <h5 class="mb-2">No Game Stats</h5>
                                    <p class="text-muted mb-0">This player has no recorded game statistics.</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Prediction Engine -->
            <div class="row mb-4" id="prediction-engine">
                <div class="col-12">
                    <PredictionEngine
                        bind:this={predictionEngineRef}
                        playerId={playerId}
                        playerName={player?.athlete_display_name || 'Unknown Player'}
                    />
                </div>
            </div>

            <!-- Prop Scanner -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-search-dollar text-primary me-2"></i>Prop Betting Opportunities (Cached Prediction Engine)
                                </h5>
                                <button
                                    on:click={loadPropScannerData}
                                    class="btn btn-sm btn-outline-primary"
                                    disabled={propScannerLoading}
                                >
                                    {#if propScannerLoading}
                                        <span class="spinner-border spinner-border-sm me-1"></span>
                                    {:else}
                                        <i class="fas fa-sync me-1"></i>
                                    {/if}
                                    Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            {#if propScannerLoading}
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 mb-0">Scanning prop betting opportunities...</p>
                                </div>
                            {:else if propScannerError}
                                <div class="alert alert-warning" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Notice:</strong> {propScannerError}
                                    <button
                                        on:click={loadPropScannerData}
                                        class="btn btn-sm btn-outline-warning ms-2"
                                    >
                                        Try Again
                                    </button>
                                </div>
                            {:else if propScannerData.length > 0}
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Stat</th>
                                                <th>Line</th>
                                                <th>Line Source</th>
                                                <th>Predicted</th>
                                                <th>Confidence</th>
                                                <th>Recommendation</th>
                                                <th>Expected Value</th>
                                                <th>Over Prob</th>
                                                <th>Under Prob</th>
                                                <th>Odds Info</th>
                                                <th>Recent Form</th>
                                                <th>Season Avg</th>
                                                <th>Betting Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each propScannerData as bet}
                                                <tr>
                                                    <td class="fw-medium text-capitalize">{bet.stat_type.replace('_', ' ')}</td>
                                                    <td class="fw-medium">{bet.suggested_line}</td>
                                                    <td>
                                                        <span class="badge bg-{bet.line_source === 'odds_api' ? 'success' : 'warning'}-subtle text-{bet.line_source === 'odds_api' ? 'success' : 'warning'}">
                                                            {bet.line_source === 'odds_api' ? 'Real Odds' : 'Estimated'}
                                                        </span>
                                                    </td>
                                                    <td class="fw-medium">{bet.predicted_value.toFixed(1)}</td>
                                                    <td>
                                                        <span class="badge bg-{bet.confidence >= 0.8 ? 'success' : bet.confidence >= 0.6 ? 'warning' : 'danger'}-subtle text-{bet.confidence >= 0.8 ? 'success' : bet.confidence >= 0.6 ? 'warning' : 'danger'}">
                                                            {formatPercentage(bet.confidence)}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{getRecommendationColor(bet.recommendation)}-subtle text-{getRecommendationColor(bet.recommendation)} text-uppercase">
                                                            {bet.recommendation}
                                                        </span>
                                                    </td>
                                                    <td class="fw-medium {bet.expected_value > 0 ? 'text-success' : 'text-danger'}">
                                                        {bet.expected_value > 0 ? '+' : ''}{formatPercentage(bet.expected_value)}
                                                    </td>
                                                    <td>{formatPercentage(bet.probability_over)}</td>
                                                    <td>{formatPercentage(bet.probability_under)}</td>
                                                    <td>
                                                        {#if bet.odds_data && bet.odds_data.available}
                                                            <div class="small">
                                                                <div>O: {bet.odds_data.over_odds > 0 ? '+' : ''}{bet.odds_data.over_odds}</div>
                                                                <div>U: {bet.odds_data.under_odds > 0 ? '+' : ''}{bet.odds_data.under_odds}</div>
                                                                {#if bet.odds_data.bookmaker_over && bet.odds_data.bookmaker_over !== 'Unknown'}
                                                                    <div class="text-muted">{bet.odds_data.bookmaker_over}</div>
                                                                {/if}
                                                            </div>
                                                        {:else}
                                                            <span class="text-muted small">No odds</span>
                                                        {/if}
                                                    </td>
                                                    <td>{bet.recent_form}</td>
                                                    <td>{bet.season_average}</td>
                                                    <td>
                                                        <span class="badge bg-{getBettingValueColor(bet.betting_value)}-subtle text-{getBettingValueColor(bet.betting_value)} text-capitalize">
                                                            {bet.betting_value}
                                                        </span>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Predictions generated using cached prediction engine with real betting lines from The Odds API
                                        {#if propScannerData.length > 0}
                                            • Data source: {propScannerData[0].data_source}
                                            {#if propScannerData.some(bet => bet.line_source === 'odds_api')}
                                                • <span class="text-success">Real sportsbook lines detected</span>
                                            {:else}
                                                • <span class="text-warning">Using estimated lines (no real odds available)</span>
                                            {/if}
                                        {/if}
                                    </small>
                                </div>
                            {:else}
                                <div class="text-center py-4">
                                    <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-search-dollar text-muted fs-24"></i>
                                    </div>
                                    <h5 class="mb-2">No Prop Opportunities Found</h5>
                                    <p class="text-muted mb-3">No betting opportunities were found for this player at this time.</p>
                                    <button
                                        on:click={loadPropScannerData}
                                        class="btn btn-primary"
                                    >
                                        <i class="fas fa-search me-2"></i>Scan for Opportunities
                                    </button>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historical Testing Results -->
            <div class="row mb-4" id="historical-testing">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line text-primary me-2"></i>Historical Prediction Testing Results
                                </h5>
                                <div class="d-flex gap-2">
                                    <button
                                        on:click={loadHistoricalTestResults}
                                        class="btn btn-sm btn-outline-primary"
                                        disabled={historicalTestLoading}
                                    >
                                        {#if historicalTestLoading}
                                            <span class="spinner-border spinner-border-sm me-1"></span>
                                        {:else}
                                            <i class="fas fa-sync me-1"></i>
                                        {/if}
                                        Refresh
                                    </button>
                                    <a
                                        href="/advanced/prediction-testing?player={playerId}"
                                        class="btn btn-sm btn-primary"
                                        target="_blank"
                                    >
                                        <i class="fas fa-external-link-alt me-1"></i>
                                        Full Testing Interface
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Filters -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label for="filter-stat" class="form-label">Statistic</label>
                                    <select class="form-select" id="filter-stat" bind:value={testFilters.stat_type} on:change={loadHistoricalTestResults}>
                                        <option value="">All Statistics</option>
                                        <option value="points">Points</option>
                                        <option value="rebounds">Rebounds</option>
                                        <option value="assists">Assists</option>
                                        <option value="steals">Steals</option>
                                        <option value="blocks">Blocks</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="filter-accuracy" class="form-label">Min Accuracy (%)</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="filter-accuracy"
                                        bind:value={testFilters.min_accuracy}
                                        on:input={loadHistoricalTestResults}
                                        min="0"
                                        max="100"
                                        placeholder="Any accuracy"
                                    />
                                </div>

                                <div class="col-md-3">
                                    <label for="filter-sort" class="form-label">Sort By</label>
                                    <select class="form-select" id="filter-sort" bind:value={testFilters.sort_by} on:change={loadHistoricalTestResults}>
                                        <option value="accuracy_percentage">Accuracy</option>
                                        <option value="tested_at">Test Date</option>
                                        <option value="stat_type">Statistic</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="filter-order" class="form-label">Order</label>
                                    <select class="form-select" id="filter-order" bind:value={testFilters.sort_order} on:change={loadHistoricalTestResults}>
                                        <option value="desc">Descending</option>
                                        <option value="asc">Ascending</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Results Table -->
                            {#if historicalTestLoading}
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 mb-0">Loading historical test results...</p>
                                </div>
                            {:else if historicalTestError}
                                <div class="alert alert-warning" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Notice:</strong> {historicalTestError}
                                    <button
                                        on:click={loadHistoricalTestResults}
                                        class="btn btn-sm btn-outline-warning ms-2"
                                    >
                                        Try Again
                                    </button>
                                </div>
                            {:else if historicalTestResults.length === 0}
                                <div class="text-center py-5">
                                    <i class="fas fa-chart-line text-muted fs-48 mb-3"></i>
                                    <h5 class="text-muted">No Test Results Found</h5>
                                    <p class="text-muted mb-3">No historical prediction tests have been run for this player yet.</p>
                                    <a
                                        href="/advanced/prediction-testing"
                                        class="btn btn-primary"
                                        target="_blank"
                                    >
                                        <i class="fas fa-play me-2"></i>Run Prediction Tests
                                    </a>
                                </div>
                            {:else}
                                <div class="table-responsive">
                                    <table class="table table-hover table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Stat</th>
                                                <th>Accuracy</th>
                                                <th>Tests</th>
                                                <th>Sample Size</th>
                                                <th>Quality</th>
                                                <th>Tested</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each historicalTestResults as result}
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-info-subtle text-info">
                                                            {result.stat_type}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge {getAccuracyBadgeClass(result.accuracy_percentage)}">
                                                            {safeToFixed(result.accuracy_percentage)}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">
                                                            {result.correct_predictions}/{result.total_predictions}
                                                        </span>
                                                    </td>
                                                    <td>{result.sample_size}</td>
                                                    <td>
                                                        {#if result.data_quality_score}
                                                            <span class="badge bg-secondary">
                                                                {safeToFixed(result.data_quality_score * 100, 0)}%
                                                            </span>
                                                        {:else}
                                                            <span class="text-muted">N/A</span>
                                                        {/if}
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            {formatDate(result.tested_at)}
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <button
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="View Details"
                                                            on:click={() => showTestDetails(result)}
                                                        >
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Summary Stats -->
                                {#if historicalTestResults.length > 0}
                                    <div class="row mt-4">
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-primary">{historicalTestResults.length}</h4>
                                                <p class="text-muted mb-0">Total Tests</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-success">
                                                    {safeToFixed(historicalTestResults.reduce((sum, r) => sum + (r.accuracy_percentage || 0), 0) / historicalTestResults.length)}%
                                                </h4>
                                                <p class="text-muted mb-0">Avg Accuracy</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-info">
                                                    {safeToFixed(Math.max(...historicalTestResults.map(r => r.accuracy_percentage || 0)))}%
                                                </h4>
                                                <p class="text-muted mb-0">Best Accuracy</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-warning">
                                                    {Math.round(historicalTestResults.reduce((sum, r) => sum + (r.sample_size || 0), 0) / historicalTestResults.length)}
                                                </h4>
                                                <p class="text-muted mb-0">Avg Sample Size</p>
                                            </div>
                                        </div>
                                    </div>
                                {/if}
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</DefaultLayout>

<!-- Detailed Test Results Modal -->
{#if showDetailModal && selectedTestResult}
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Detailed Test Results: {selectedTestResult.stat_type}
                    </h5>
                    <button type="button" class="btn-close" on:click={closeDetailModal}></button>
                </div>
                <div class="modal-body">
                    <!-- Test Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-info-circle text-primary me-2"></i>Test Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-2"><strong>Statistic:</strong> {selectedTestResult.stat_type}</p>
                                            <p class="mb-2"><strong>Test Games:</strong> {selectedTestResult.test_games}</p>
                                            <p class="mb-2"><strong>Sample Size:</strong> {selectedTestResult.sample_size}</p>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-2"><strong>Season Average:</strong> {safeToFixed(selectedTestResult.season_average)}</p>
                                            <p class="mb-2"><strong>Test Date:</strong> {formatDate(selectedTestResult.tested_at)}</p>
                                            <p class="mb-2"><strong>Test Version:</strong> {selectedTestResult.test_version}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-chart-bar text-success me-2"></i>Performance Summary
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-2"><strong>Overall Accuracy:</strong>
                                                <span class="badge {getAccuracyBadgeClass(selectedTestResult.accuracy_percentage)}">
                                                    {safeToFixed(selectedTestResult.accuracy_percentage)}%
                                                </span>
                                            </p>
                                            <p class="mb-2"><strong>Correct/Total:</strong> {selectedTestResult.correct_predictions}/{selectedTestResult.total_predictions}</p>
                                            <p class="mb-2"><strong>Confidence:</strong> {safeToFixed((selectedTestResult.confidence_score || 0) * 100, 0)}%</p>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-2"><strong>Best Line:</strong> {safeToFixed(selectedTestResult.best_line_accuracy)}%</p>
                                            <p class="mb-2"><strong>Worst Line:</strong> {safeToFixed(selectedTestResult.worst_line_accuracy)}%</p>
                                            <p class="mb-2"><strong>Data Quality:</strong>
                                                {#if selectedTestResult.data_quality_score}
                                                    <span class="badge bg-secondary">
                                                        {safeToFixed(selectedTestResult.data_quality_score * 100, 0)}%
                                                    </span>
                                                {:else}
                                                    <span class="text-muted">N/A</span>
                                                {/if}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actual Game Results -->
                    {#if selectedTestResult.actual_game_results && selectedTestResult.actual_game_results.length > 0}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-gamepad text-info me-2"></i>Actual Game Results ({selectedTestResult.actual_game_results.length} games)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Game</th>
                                                <th>Date</th>
                                                <th>Actual Value</th>
                                                <th>vs Season Avg</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each selectedTestResult.actual_game_results as gameResult}
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-secondary">#{gameResult.game_number}</span>
                                                    </td>
                                                    <td>
                                                        <small>{formatDate(gameResult.date)}</small>
                                                    </td>
                                                    <td>
                                                        <strong>{gameResult.actual_value}</strong>
                                                    </td>
                                                    <td>
                                                        {#if gameResult.actual_value > safeNumber(selectedTestResult.season_average)}
                                                            <span class="text-success">
                                                                <i class="fas fa-arrow-up me-1"></i>
                                                                +{safeToFixed(gameResult.actual_value - safeNumber(selectedTestResult.season_average))}
                                                            </span>
                                                        {:else if gameResult.actual_value < safeNumber(selectedTestResult.season_average)}
                                                            <span class="text-danger">
                                                                <i class="fas fa-arrow-down me-1"></i>
                                                                {safeToFixed(gameResult.actual_value - safeNumber(selectedTestResult.season_average))}
                                                            </span>
                                                        {:else}
                                                            <span class="text-muted">
                                                                <i class="fas fa-equals me-1"></i>
                                                                Even
                                                            </span>
                                                        {/if}
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    {/if}

                    <!-- Betting Lines Results -->
                    {#if selectedTestResult.line_results && selectedTestResult.line_results.length > 0}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-chart-bar text-info me-2"></i>Betting Lines Performance ({selectedTestResult.line_results.length} lines tested)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Line</th>
                                                <th>Recommendation</th>
                                                <th>Predicted Value</th>
                                                <th>Confidence</th>
                                                <th>Prob Over</th>
                                                <th>Prob Under</th>
                                                <th>Accuracy</th>
                                                <th>Correct/Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {#each selectedTestResult.line_results as lineResult}
                                                <tr>
                                                    <td>
                                                        <strong class="text-primary">{lineResult.line}</strong>
                                                    </td>
                                                    <td>
                                                        {#if lineResult.recommendation === 'over'}
                                                            <span class="badge bg-success">Over</span>
                                                        {:else if lineResult.recommendation === 'under'}
                                                            <span class="badge bg-danger">Under</span>
                                                        {:else}
                                                            <span class="badge bg-secondary">Avoid</span>
                                                        {/if}
                                                    </td>
                                                    <td>{safeToFixed(lineResult.predicted_value)}</td>
                                                    <td>{safeToFixed(lineResult.confidence * 100, 0)}%</td>
                                                    <td>{safeToFixed(lineResult.probability_over * 100, 1)}%</td>
                                                    <td>{safeToFixed(lineResult.probability_under * 100, 1)}%</td>
                                                    <td>
                                                        <span class="badge {getAccuracyBadgeClass(lineResult.accuracy_percentage)}">
                                                            {safeToFixed(lineResult.accuracy_percentage)}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">
                                                            {lineResult.correct_predictions}/{lineResult.total_predictions}
                                                        </span>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    {/if}

                    <!-- Game-by-Game Predictions -->
                    {#if selectedTestResult.line_results && selectedTestResult.line_results.length > 0 && hasGameByGamePredictions}
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-list text-success me-2"></i>Game-by-Game Predictions
                                </h6>
                            </div>
                            <div class="card-body">
                                {#each selectedTestResult.line_results as lineResult, lineIndex}
                                    {#if lineResult.predictions && lineResult.predictions.length > 0}
                                        <div class="mb-4">
                                            <h6 class="fw-semibold">
                                                Line {lineResult.line}
                                                <span class="badge {getAccuracyBadgeClass(lineResult.accuracy_percentage)} ms-2">
                                                    {safeToFixed(lineResult.accuracy_percentage)}% Accuracy
                                                </span>
                                            </h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Game</th>
                                                            <th>Actual Value</th>
                                                            <th>Actual Result</th>
                                                            <th>Predicted Result</th>
                                                            <th>Correct?</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {#each lineResult.predictions as prediction}
                                                            <tr class="{prediction.correct ? 'table-success' : 'table-danger'}">
                                                                <td>
                                                                    <span class="badge bg-secondary">#{prediction.game_number}</span>
                                                                </td>
                                                                <td>
                                                                    <strong>{prediction.actual_value}</strong>
                                                                </td>
                                                                <td>
                                                                    <span class="badge {prediction.actual_result === 'over' ? 'bg-success' : 'bg-danger'}">
                                                                        {prediction.actual_result}
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge {prediction.predicted_result === 'over' ? 'bg-success' : 'bg-danger'}">
                                                                        {prediction.predicted_result}
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    {#if prediction.correct}
                                                                        <i class="fas fa-check text-success"></i>
                                                                        <span class="text-success ms-1">Correct</span>
                                                                    {:else}
                                                                        <i class="fas fa-times text-danger"></i>
                                                                        <span class="text-danger ms-1">Wrong</span>
                                                                    {/if}
                                                                </td>
                                                            </tr>
                                                        {/each}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    {/if}
                                {/each}
                            </div>
                        </div>
                    {/if}

                    <!-- Insights -->
                    {#if selectedTestResult.insights && selectedTestResult.insights.length > 0}
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-lightbulb text-warning me-2"></i>Insights
                                </h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    {#each selectedTestResult.insights as insight}
                                        <li class="mb-2">
                                            <i class="fas fa-arrow-right text-muted me-2"></i>
                                            {insight}
                                        </li>
                                    {/each}
                                </ul>
                            </div>
                        </div>
                    {/if}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" on:click={closeDetailModal}>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}

