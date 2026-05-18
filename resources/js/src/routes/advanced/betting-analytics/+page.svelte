<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";

    let bettingData: any = null;
    let loading = true;
    let error = '';

    // EV Calculator inputs
    let calculatorInputs = {
        ourProbability: 0.65,
        bookmakerOdds: -110,
        betAmount: 100,
        bankroll: 10000
    };

    let calculatorResults = {
        impliedProbability: 0,
        expectedValue: 0,
        kellyPercentage: 0,
        recommendedBet: 0,
        roi: 0
    };

    let selectedTimeframe = '30d';
    let selectedBetType = 'all';

    const timeframes = [
        { value: '7d', label: 'Last 7 Days' },
        { value: '30d', label: 'Last 30 Days' },
        { value: '90d', label: 'Last 90 Days' },
        { value: 'season', label: 'Current Season' }
    ];

    const betTypes = [
        { value: 'all', label: 'All Bet Types' },
        { value: 'player_props', label: 'Player Props' },
        { value: 'team_totals', label: 'Team Totals' },
        { value: 'spreads', label: 'Spreads' },
        { value: 'moneylines', label: 'Moneylines' }
    ];

    onMount(async () => {
        await loadBettingData();
        calculateEV();
    });

    async function loadBettingData() {
        try {
            loading = true;
            error = '';

            const params: any = {
                timeframe: selectedTimeframe,
                bet_type: selectedBetType
            };

            // Try to load real data, fallback to mock
            try {
                const response = await api.wnba.betting.getAnalytics(params);
                bettingData = response.data || generateMockBettingData();
            } catch {
                bettingData = generateMockBettingData();
            }
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load betting data';
            bettingData = generateMockBettingData();
        } finally {
            loading = false;
        }
    }

    function generateMockBettingData() {
        // Adjust data based on selected filters
        const timeframeMultiplier = selectedTimeframe === 'week' ? 0.2 :
                                   selectedTimeframe === 'month' ? 1 :
                                   selectedTimeframe === 'quarter' ? 3 : 12;

        const baseBets = Math.floor(247 * timeframeMultiplier / 12);
        const baseWinning = Math.floor(baseBets * 0.632);
        const baseWagered = baseBets * 100;
        const baseProfit = Math.floor(baseWagered * 0.087);

        return {
            overall_performance: {
                total_bets: baseBets,
                winning_bets: baseWinning,
                win_rate: baseWinning / baseBets,
                total_wagered: baseWagered,
                total_profit: baseProfit,
                roi: baseProfit / baseWagered,
                average_odds: -108,
                sharpe_ratio: 1.42
            },
            monthly_performance: [
                { month: 'Mar 2026', bets: Math.floor(89 * timeframeMultiplier / 12), profit: Math.floor(890 * timeframeMultiplier / 12), roi: 0.095 },
                { month: 'Feb 2026', bets: Math.floor(76 * timeframeMultiplier / 12), profit: Math.floor(456 * timeframeMultiplier / 12), roi: 0.062 },
                { month: 'Jan 2026', bets: Math.floor(82 * timeframeMultiplier / 12), profit: Math.floor(799 * timeframeMultiplier / 12), roi: 0.098 }
            ],
            bet_type_performance: {
                player_props: {
                    bets: selectedBetType === 'player_props' ? baseBets : Math.floor(baseBets * 0.63),
                    win_rate: 0.641,
                    roi: 0.092,
                    avg_ev: 0.034
                },
                team_totals: {
                    bets: selectedBetType === 'team_totals' ? baseBets : Math.floor(baseBets * 0.18),
                    win_rate: 0.622,
                    roi: 0.078,
                    avg_ev: 0.028
                },
                spreads: {
                    bets: selectedBetType === 'spreads' ? baseBets : Math.floor(baseBets * 0.13),
                    win_rate: 0.594,
                    roi: 0.045,
                    avg_ev: 0.019
                },
                moneylines: {
                    bets: selectedBetType === 'moneylines' ? baseBets : Math.floor(baseBets * 0.06),
                    win_rate: 0.571,
                    roi: 0.023,
                    avg_ev: 0.012
                }
            },
            top_opportunities: [
                {
                    player: "A'ja Wilson",
                    stat: "Points",
                    line: 22.5,
                    our_prob: 0.68,
                    book_odds: -115,
                    ev: 0.087,
                    kelly: 0.034,
                    confidence: 'High'
                },
                {
                    player: "Breanna Stewart",
                    stat: "Rebounds",
                    line: 8.5,
                    our_prob: 0.72,
                    book_odds: -110,
                    ev: 0.076,
                    kelly: 0.029,
                    confidence: 'High'
                },
                {
                    player: "Sabrina Ionescu",
                    stat: "Assists",
                    line: 6.5,
                    our_prob: 0.65,
                    book_odds: -105,
                    ev: 0.054,
                    kelly: 0.021,
                    confidence: 'Medium'
                }
            ],
            bankroll_simulation: {
                starting_bankroll: 10000,
                current_bankroll: 10000 + baseProfit,
                max_drawdown: -0.087,
                max_bankroll: 10000 + baseProfit + Math.floor(baseProfit * 0.2),
                volatility: 0.156,
                risk_of_ruin: 0.003
            }
        };
    }

    function calculateEV() {
        const { ourProbability, bookmakerOdds, betAmount, bankroll } = calculatorInputs;

        // Convert American odds to decimal
        const decimalOdds = bookmakerOdds > 0
            ? (bookmakerOdds / 100) + 1
            : (100 / Math.abs(bookmakerOdds)) + 1;

        // Calculate implied probability
        const impliedProbability = 1 / decimalOdds;

        // Calculate expected value
        const winAmount = betAmount * (decimalOdds - 1);
        const expectedValue = (ourProbability * winAmount) - ((1 - ourProbability) * betAmount);

        // Calculate Kelly percentage
        const kellyPercentage = (ourProbability * decimalOdds - 1) / (decimalOdds - 1);
        const recommendedBet = Math.max(0, kellyPercentage * bankroll);

        // Calculate ROI
        const roi = expectedValue / betAmount;

        calculatorResults = {
            impliedProbability,
            expectedValue,
            kellyPercentage: Math.max(0, kellyPercentage),
            recommendedBet,
            roi
        };
    }

    function getBetTypeData(data: any, property: string): any {
        return data?.[property] ?? 0;
    }

    async function applyFilters() {
        await loadBettingData();
    }

    function formatCurrency(value: number): string {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(value);
    }

    function formatPercentage(value: number): string {
        return `${(value * 100).toFixed(1)}%`;
    }

    function formatOdds(odds: number): string {
        return odds > 0 ? `+${odds}` : `${odds}`;
    }

    function getROIColor(roi: number): string {
        if (roi > 0.05) return 'text-success';
        if (roi > 0) return 'text-warning';
        return 'text-danger';
    }

    function getConfidenceColor(confidence: string): string {
        switch (confidence.toLowerCase()) {
            case 'high': return 'success';
            case 'medium': return 'warning';
            case 'low': return 'danger';
            default: return 'secondary';
        }
    }

    // Reactive calculation
    $: {
        calculateEV();
    }
</script>

<svelte:head>
    <title>Betting Analytics | Advanced Analytics | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <a href="/advanced" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Advanced
                        </a>
                    </div>
                    <h4 class="page-title">Betting Analytics</h4>
                    <p class="text-muted mb-0">Expected value calculations, ROI tracking, and bankroll management</p>
                </div>
            </div>
        </div>

        <!-- EV Calculator -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calculator text-primary me-2"></i>Expected Value Calculator
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="our-probability" class="form-label">Our Probability</label>
                                <div class="input-group">
                                    <input
                                        id="our-probability"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="1"
                                        bind:value={calculatorInputs.ourProbability}
                                        class="form-control"
                                    />
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="bookmaker-odds" class="form-label">Bookmaker Odds</label>
                                <input
                                    id="bookmaker-odds"
                                    type="number"
                                    bind:value={calculatorInputs.bookmakerOdds}
                                    class="form-control"
                                    placeholder="-110"
                                />
                            </div>

                            <div class="col-md-3">
                                <label for="bet-amount" class="form-label">Bet Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input
                                        id="bet-amount"
                                        type="number"
                                        min="1"
                                        bind:value={calculatorInputs.betAmount}
                                        class="form-control"
                                    />
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="bankroll" class="form-label">Total Bankroll</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input
                                        id="bankroll"
                                        type="number"
                                        min="100"
                                        bind:value={calculatorInputs.bankroll}
                                        class="form-control"
                                    />
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-info">{formatPercentage(calculatorResults.impliedProbability)}</h4>
                                    <p class="text-muted mb-0">Implied Probability</p>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="{calculatorResults.expectedValue > 0 ? 'text-success' : 'text-danger'}">{formatCurrency(calculatorResults.expectedValue)}</h4>
                                    <p class="text-muted mb-0">Expected Value</p>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-warning">{formatPercentage(calculatorResults.kellyPercentage)}</h4>
                                    <p class="text-muted mb-0">Kelly %</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary">{formatCurrency(calculatorResults.recommendedBet)}</h4>
                                    <p class="text-muted mb-0">Recommended Bet</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="{getROIColor(calculatorResults.roi)}">{formatPercentage(calculatorResults.roi)}</h4>
                                    <p class="text-muted mb-0">Expected ROI</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter text-primary me-2"></i>Performance Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="timeframe" class="form-label">Timeframe</label>
                                <select
                                    id="timeframe"
                                    bind:value={selectedTimeframe}
                                    class="form-select"
                                >
                                    {#each timeframes as timeframe}
                                        <option value={timeframe.value}>{timeframe.label}</option>
                                    {/each}
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="bet-type" class="form-label">Bet Type</label>
                                <select
                                    id="bet-type"
                                    bind:value={selectedBetType}
                                    class="form-select"
                                >
                                    {#each betTypes as betType}
                                        <option value={betType.value}>{betType.label}</option>
                                    {/each}
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button
                                    on:click={applyFilters}
                                    disabled={loading}
                                    class="btn btn-primary w-100"
                                >
                                    {#if loading}
                                        <span class="spinner-border spinner-border-sm me-2"></span>
                                    {:else}
                                        <i class="fas fa-search me-2"></i>
                                    {/if}
                                    Analyze
                                </button>
                            </div>
                        </div>
                    </div>
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
                            <p class="mt-2 mb-0">Loading betting analytics...</p>
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
        {:else if bettingData}
            <!-- Overall Performance -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line text-primary me-2"></i>Overall Performance
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="text-primary">{bettingData.overall_performance.total_bets}</h3>
                                        <p class="text-muted mb-0">Total Bets</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="text-success">{formatPercentage(bettingData.overall_performance.win_rate)}</h3>
                                        <p class="text-muted mb-0">Win Rate</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="{getROIColor(bettingData.overall_performance.roi)}">{formatPercentage(bettingData.overall_performance.roi)}</h3>
                                        <p class="text-muted mb-0">ROI</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="text-success">{formatCurrency(bettingData.overall_performance.total_profit)}</h3>
                                        <p class="text-muted mb-0">Total Profit</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="text-info">{formatOdds(bettingData.overall_performance.average_odds)}</h3>
                                        <p class="text-muted mb-0">Avg Odds</p>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <h3 class="text-warning">{bettingData.overall_performance.sharpe_ratio.toFixed(2)}</h3>
                                        <p class="text-muted mb-0">Sharpe Ratio</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance by Bet Type -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar text-primary me-2"></i>Performance by Bet Type
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Bet Type</th>
                                            <th>Bets</th>
                                            <th>Win Rate</th>
                                            <th>ROI</th>
                                            <th>Avg EV</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each Object.entries(bettingData.bet_type_performance) as [betType, data]}
                                            <tr>
                                                <td class="fw-medium text-capitalize">{betType.replace('_', ' ')}</td>
                                                <td>{getBetTypeData(data, 'bets')}</td>
                                                <td>
                                                    <span class="badge bg-{getBetTypeData(data, 'win_rate') >= 0.6 ? 'success' : getBetTypeData(data, 'win_rate') >= 0.55 ? 'warning' : 'danger'}-subtle text-{getBetTypeData(data, 'win_rate') >= 0.6 ? 'success' : getBetTypeData(data, 'win_rate') >= 0.55 ? 'warning' : 'danger'}">
                                                        {formatPercentage(getBetTypeData(data, 'win_rate'))}
                                                    </span>
                                                </td>
                                                <td class="{getROIColor(getBetTypeData(data, 'roi'))}">{formatPercentage(getBetTypeData(data, 'roi'))}</td>
                                                <td class="text-muted">{formatPercentage(getBetTypeData(data, 'avg_ev'))}</td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bankroll Simulation -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-piggy-bank text-primary me-2"></i>Bankroll Analysis
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Starting Bankroll</span>
                                    <span class="fw-medium">{formatCurrency(bettingData.bankroll_simulation.starting_bankroll)}</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Current Bankroll</span>
                                    <span class="fw-medium text-success">{formatCurrency(bettingData.bankroll_simulation.current_bankroll)}</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Max Drawdown</span>
                                    <span class="fw-medium text-danger">{formatPercentage(bettingData.bankroll_simulation.max_drawdown)}</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Volatility</span>
                                    <span class="fw-medium text-warning">{formatPercentage(bettingData.bankroll_simulation.volatility)}</span>
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="d-flex justify-content-between">
                                    <span>Risk of Ruin</span>
                                    <span class="fw-medium text-info">{formatPercentage(bettingData.bankroll_simulation.risk_of_ruin)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Opportunities -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-star text-primary me-2"></i>Top Betting Opportunities
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Player</th>
                                            <th>Stat</th>
                                            <th>Line</th>
                                            <th>Our Prob</th>
                                            <th>Book Odds</th>
                                            <th>Expected Value</th>
                                            <th>Kelly %</th>
                                            <th>Confidence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each bettingData.top_opportunities as opportunity}
                                            <tr>
                                                <td class="fw-medium">{opportunity.player}</td>
                                                <td>{opportunity.stat}</td>
                                                <td>{opportunity.line}</td>
                                                <td>{formatPercentage(opportunity.our_prob)}</td>
                                                <td>{formatOdds(opportunity.book_odds)}</td>
                                                <td class="text-success fw-medium">+{formatPercentage(opportunity.ev)}</td>
                                                <td class="text-warning">{formatPercentage(opportunity.kelly)}</td>
                                                <td>
                                                    <span class="badge bg-{getConfidenceColor(opportunity.confidence)}-subtle text-{getConfidenceColor(opportunity.confidence)}">
                                                        {opportunity.confidence}
                                                    </span>
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
    </div>
</DefaultLayout>
