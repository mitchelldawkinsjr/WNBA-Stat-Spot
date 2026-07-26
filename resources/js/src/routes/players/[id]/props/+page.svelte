<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { page } from '$app/stores';
    import { api, type PropScannerBet } from '$lib/api/client';
    import { playerProfile } from '$lib/stores/playerProfile';
    import PredictionEngine from '$lib/components/PredictionEngine.svelte';

    let predictionEngineRef: { prefillStat?: (stat: string, line?: number) => void } | null = null;
    let propScannerData: PropScannerBet[] = [];
    let propScannerLoading = false;
    let propScannerError = '';
    let historicalTestResults: any[] = [];
    let historicalTestLoading = false;
    let historicalTestError = '';
    let showDetailModal = false;
    let selectedTestResult: any = null;
    let testFilters = {
        stat_type: '',
        min_accuracy: null as number | null,
        sort_by: 'accuracy_percentage',
        sort_order: 'desc',
    };
    let prefillsApplied = false;

    $: profile = $playerProfile;
    $: playerId = $page.params.id ?? '';
    $: athleteId = profile.player?.athlete_id || playerId;
    $: playerName = profile.player?.athlete_display_name || 'Unknown Player';
    $: queryStat = $page.url.searchParams.get('stat') || '';
    $: queryLine = Number($page.url.searchParams.get('line') || 0);

    function getDefaultLine(statType: string): number {
        const live = profile.seasonStats;
        const map: Record<string, string | null | undefined> = {
            points: live?.avgPoints,
            rebounds: live?.avgRebounds,
            assists: live?.avgAssists,
            steals: live?.avgSteals,
            blocks: live?.avgBlocks,
        };
        const n = parseFloat(String(map[statType] ?? '0'));
        if (!n) {
            const defaults: Record<string, number> = {
                points: 15,
                rebounds: 6,
                assists: 4,
                steals: 1,
                blocks: 0.5,
            };
            return defaults[statType] ?? 10;
        }
        return Math.round(n * 2) / 2;
    }

    function formatPercentage(value: number): string {
        return `${(value * 100).toFixed(1)}%`;
    }

    function getRecommendationColor(recommendation: string): string {
        switch (recommendation) {
            case 'over':
                return 'success';
            case 'under':
                return 'warning';
            case 'avoid':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    function getBettingValueColor(value: string): string {
        switch (value) {
            case 'excellent':
                return 'success';
            case 'good':
                return 'info';
            case 'fair':
                return 'warning';
            case 'poor':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    function getValueRating(expectedValue: number): 'excellent' | 'good' | 'fair' | 'poor' {
        if (expectedValue > 0.1) return 'excellent';
        if (expectedValue > 0.05) return 'good';
        if (expectedValue > 0) return 'fair';
        return 'poor';
    }

    function generateFallbackPropBet(statType: string): PropScannerBet {
        const line = getDefaultLine(statType);
        return {
            player_id: athleteId,
            player_name: playerName,
            player_position: profile.player?.athlete_position_abbreviation || 'N/A',
            stat_type: statType,
            suggested_line: line,
            predicted_value: line,
            confidence: 0.5,
            probability_over: 0.5,
            probability_under: 0.5,
            expected_value: 0,
            recommendation: 'avoid',
            recent_form: line,
            season_average: line,
            matchup_difficulty: 'Average',
            injury_risk: 'Low',
            betting_value: 'fair',
            created_at: new Date().toISOString(),
        };
    }

    async function loadPropScannerData() {
        propScannerLoading = true;
        propScannerError = '';
        const statTypes = ['points', 'rebounds', 'assists', 'steals', 'blocks'];
        const predictions: PropScannerBet[] = [];

        try {
            for (const statType of statTypes) {
                try {
                    const response = await api.wnba.predictions.generatePrediction({
                        player_id: athleteId,
                        stat: statType,
                        line: getDefaultLine(statType),
                    });

                    if (response.success && response.data) {
                        const data = response.data;
                        predictions.push({
                            player_id: athleteId,
                            player_name: playerName,
                            player_position: profile.player?.athlete_position_abbreviation || 'N/A',
                            stat_type: statType,
                            suggested_line: data.line || getDefaultLine(statType),
                            original_line: getDefaultLine(statType),
                            predicted_value: data.predicted_value || getDefaultLine(statType),
                            confidence: data.confidence || 0.75,
                            probability_over: data.probability_over || 0.5,
                            probability_under: data.probability_under || 0.5,
                            expected_value: data.expected_value || 0,
                            recommendation: (data.recommendation as 'over' | 'under' | 'avoid') || 'avoid',
                            recent_form: data.predicted_value || getDefaultLine(statType),
                            season_average: data.predicted_value || getDefaultLine(statType),
                            matchup_difficulty: 'Average',
                            injury_risk: 'Low',
                            betting_value: getValueRating(data.expected_value || 0),
                            reasoning: data.reasoning || 'Based on prediction engine with odds data',
                            data_source: data.data_source || 'cached_prediction_engine_with_odds',
                            line_source: data.line_source || 'estimated',
                            odds_data: data.odds_data || {},
                            created_at: new Date().toISOString(),
                        });
                    } else {
                        predictions.push(generateFallbackPropBet(statType));
                    }
                } catch {
                    predictions.push(generateFallbackPropBet(statType));
                }
            }
            propScannerData = predictions;
        } catch (err) {
            propScannerError = err instanceof Error ? err.message : 'Failed to load prop scanner data';
            propScannerData = statTypes.map((statType) => generateFallbackPropBet(statType));
        } finally {
            propScannerLoading = false;
        }
    }

    async function loadHistoricalTestResults() {
        historicalTestLoading = true;
        historicalTestError = '';
        try {
            const params: Record<string, string | number> = {
                player_id: athleteId,
                limit: 50,
                sort_by: testFilters.sort_by,
                sort_order: testFilters.sort_order,
            };
            if (testFilters.stat_type) params.stat_type = testFilters.stat_type;
            if (testFilters.min_accuracy !== null) params.min_accuracy = testFilters.min_accuracy;

            const response = await api.wnba.testing.getHistoricalResults(params);
            if (response.success) {
                historicalTestResults = response.data?.results || [];
            } else {
                historicalTestError = 'Failed to load historical test results';
            }
        } catch (err) {
            historicalTestError = err instanceof Error ? err.message : 'Failed to load historical test results';
        } finally {
            historicalTestLoading = false;
        }
    }

    function getAccuracyBadgeClass(accuracy: number): string {
        if (accuracy >= 85) return 'bg-success';
        if (accuracy >= 75) return 'bg-primary';
        if (accuracy >= 65) return 'bg-warning';
        return 'bg-danger';
    }

    function safeToFixed(value: number | null | undefined, decimals = 1): string {
        const numValue = typeof value === 'number' ? value : parseFloat(String(value || 0));
        return isNaN(numValue) ? '0.0' : numValue.toFixed(decimals);
    }

    function formatDate(dateString: string): string {
        return new Date(dateString).toLocaleDateString();
    }

    async function applyQueryPrefill() {
        if (prefillsApplied || !queryStat) return;
        await tick();
        predictionEngineRef?.prefillStat?.(queryStat, queryLine > 0 ? queryLine : getDefaultLine(queryStat));
        prefillsApplied = true;
    }

    onMount(async () => {
        await Promise.all([loadPropScannerData(), loadHistoricalTestResults()]);
        await applyQueryPrefill();
    });

    $: if (queryStat && predictionEngineRef) applyQueryPrefill();
</script>

<svelte:head>
    <title>{playerName} Props | WNBA Stat Spot</title>
</svelte:head>

<div class="card mb-3" id="prediction-engine">
    <div class="card-body">
        <PredictionEngine bind:this={predictionEngineRef} {playerId} {playerName} />
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Prop opportunities</h5>
        <button class="btn btn-sm btn-outline-primary" on:click={loadPropScannerData} disabled={propScannerLoading}>
            {#if propScannerLoading}
                <span class="spinner-border spinner-border-sm me-1"></span>
            {:else}
                <i class="fas fa-sync me-1"></i>
            {/if}
            Refresh
        </button>
    </div>
    <div class="card-body">
        {#if propScannerLoading && propScannerData.length === 0}
            <div class="text-center py-4 text-muted">Scanning prop opportunities...</div>
        {:else if propScannerError}
            <div class="alert alert-warning mb-0">{propScannerError}</div>
        {:else if propScannerData.length > 0}
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Stat</th>
                            <th>Line</th>
                            <th>Source</th>
                            <th>Predicted</th>
                            <th>Confidence</th>
                            <th>Rec</th>
                            <th>EV</th>
                            <th>Over</th>
                            <th>Under</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each propScannerData as bet}
                            <tr>
                                <td class="fw-medium text-capitalize">{bet.stat_type.replace('_', ' ')}</td>
                                <td>{bet.suggested_line}</td>
                                <td>
                                    <span class="badge bg-{bet.line_source === 'odds_api' ? 'success' : 'warning'}-subtle text-{bet.line_source === 'odds_api' ? 'success' : 'warning'}">
                                        {bet.line_source === 'odds_api' ? 'Real odds' : 'Estimated'}
                                    </span>
                                </td>
                                <td>{bet.predicted_value.toFixed(1)}</td>
                                <td>{formatPercentage(bet.confidence)}</td>
                                <td>
                                    <span class="badge bg-{getRecommendationColor(bet.recommendation)}-subtle text-{getRecommendationColor(bet.recommendation)} text-uppercase">
                                        {bet.recommendation}
                                    </span>
                                </td>
                                <td class="{bet.expected_value > 0 ? 'text-success' : 'text-danger'}">
                                    {bet.expected_value > 0 ? '+' : ''}{formatPercentage(bet.expected_value)}
                                </td>
                                <td>{formatPercentage(bet.probability_over)}</td>
                                <td>{formatPercentage(bet.probability_under)}</td>
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
        {:else}
            <p class="text-muted mb-0 text-center py-3">No prop opportunities found.</p>
        {/if}
    </div>
</div>

<div class="card" id="historical-testing">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="card-title mb-0">Historical prediction tests</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" on:click={loadHistoricalTestResults} disabled={historicalTestLoading}>
                Refresh
            </button>
            <a href="/advanced/prediction-testing?player={playerId}" class="btn btn-sm btn-primary" target="_blank" rel="noopener noreferrer">
                Full testing UI
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label" for="filter-stat">Statistic</label>
                <select class="form-select" id="filter-stat" bind:value={testFilters.stat_type} on:change={loadHistoricalTestResults}>
                    <option value="">All</option>
                    <option value="points">Points</option>
                    <option value="rebounds">Rebounds</option>
                    <option value="assists">Assists</option>
                    <option value="steals">Steals</option>
                    <option value="blocks">Blocks</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter-accuracy">Min accuracy (%)</label>
                <input
                    type="number"
                    class="form-control"
                    id="filter-accuracy"
                    bind:value={testFilters.min_accuracy}
                    on:change={loadHistoricalTestResults}
                    min="0"
                    max="100"
                    placeholder="Any"
                />
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter-sort">Sort by</label>
                <select class="form-select" id="filter-sort" bind:value={testFilters.sort_by} on:change={loadHistoricalTestResults}>
                    <option value="accuracy_percentage">Accuracy</option>
                    <option value="tested_at">Test date</option>
                    <option value="stat_type">Statistic</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter-order">Order</label>
                <select class="form-select" id="filter-order" bind:value={testFilters.sort_order} on:change={loadHistoricalTestResults}>
                    <option value="desc">Descending</option>
                    <option value="asc">Ascending</option>
                </select>
            </div>
        </div>

        {#if historicalTestLoading}
            <div class="text-center py-4 text-muted">Loading test results...</div>
        {:else if historicalTestError}
            <div class="alert alert-warning mb-0">{historicalTestError}</div>
        {:else if historicalTestResults.length === 0}
            <div class="text-center py-4">
                <p class="text-muted mb-3">No historical tests for this player yet.</p>
                <a href="/advanced/prediction-testing" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
                    Run prediction tests
                </a>
            </div>
        {:else}
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Stat</th>
                            <th>Accuracy</th>
                            <th>Tests</th>
                            <th>Sample</th>
                            <th>Tested</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each historicalTestResults as result}
                            <tr>
                                <td><span class="badge bg-info-subtle text-info">{result.stat_type}</span></td>
                                <td>
                                    <span class="badge {getAccuracyBadgeClass(result.accuracy_percentage)}">
                                        {safeToFixed(result.accuracy_percentage)}%
                                    </span>
                                </td>
                                <td class="text-muted">{result.correct_predictions}/{result.total_predictions}</td>
                                <td>{result.sample_size}</td>
                                <td><small class="text-muted">{formatDate(result.tested_at)}</small></td>
                                <td>
                                    <button
                                        class="btn btn-sm btn-outline-primary"
                                        on:click={() => {
                                            selectedTestResult = result;
                                            showDetailModal = true;
                                        }}
                                    >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
</div>

{#if showDetailModal && selectedTestResult}
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test details: {selectedTestResult.stat_type}</h5>
                    <button
                        type="button"
                        class="btn-close"
                        on:click={() => {
                            showDetailModal = false;
                            selectedTestResult = null;
                        }}
                    ></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Accuracy:</strong> {safeToFixed(selectedTestResult.accuracy_percentage)}%</p>
                            <p class="mb-1"><strong>Correct/Total:</strong> {selectedTestResult.correct_predictions}/{selectedTestResult.total_predictions}</p>
                            <p class="mb-1"><strong>Sample size:</strong> {selectedTestResult.sample_size}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Season avg:</strong> {safeToFixed(selectedTestResult.season_average)}</p>
                            <p class="mb-1"><strong>Tested:</strong> {formatDate(selectedTestResult.tested_at)}</p>
                            <p class="mb-1"><strong>Version:</strong> {selectedTestResult.test_version}</p>
                        </div>
                    </div>
                    {#if selectedTestResult.insights?.length}
                        <hr />
                        <ul class="mb-0">
                            {#each selectedTestResult.insights as insight}
                                <li>{insight}</li>
                            {/each}
                        </ul>
                    {/if}
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        on:click={() => {
                            showDetailModal = false;
                            selectedTestResult = null;
                        }}
                    >Close</button>
                </div>
            </div>
        </div>
    </div>
{/if}
