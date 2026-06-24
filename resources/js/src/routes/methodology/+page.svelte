<script lang="ts">
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
</script>

<svelte:head>
    <title>Methodology | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Prediction Methodology</h4>
                    <p class="text-muted mb-0">Mathematical foundations and philosophical approach to WNBA statistical predictions</p>
                </div>
            </div>
        </div>

        <!-- Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-brain text-primary me-2"></i>Our Approach
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="lead">
                            Our prediction engine combines <strong>Bayesian inference</strong>, <strong>Monte Carlo simulations</strong>,
                            and <strong>ensemble modeling</strong> to generate probabilistic forecasts for WNBA player performance.
                        </p>
                        <p>
                            Rather than providing single-point predictions, we focus on <em>probability distributions</em>
                            that capture the inherent uncertainty in sports performance. This approach acknowledges that
                            basketball is a complex, dynamic system where multiple factors influence outcomes.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Core Philosophy -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-lightbulb text-warning me-2"></i>Core Philosophy
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6 class="text-primary">Probabilistic Thinking</h6>
                        <p class="small mb-3">
                            We express uncertainty explicitly through probability distributions rather than false precision with point estimates.
                        </p>

                        <h6 class="text-success">Evidence-Based Updates</h6>
                        <p class="small mb-3">
                            Our models continuously update beliefs as new evidence (games, performance data) becomes available.
                        </p>

                        <h6 class="text-info">Context Awareness</h6>
                        <p class="small mb-0">
                            We account for situational factors: opponent strength, rest days, home/away, recent form, and injury status.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line text-success me-2"></i>Key Principles
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="avatar-xs bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2 mt-1">
                                <i class="fas fa-check text-primary fs-10"></i>
                            </div>
                            <div>
                                <strong>Transparency:</strong> All mathematical assumptions are documented and explainable.
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-3">
                            <div class="avatar-xs bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2 mt-1">
                                <i class="fas fa-check text-success fs-10"></i>
                            </div>
                            <div>
                                <strong>Validation:</strong> Models are backtested against historical data and continuously evaluated.
                            </div>
                        </div>
                        <div class="d-flex align-items-start">
                            <div class="avatar-xs bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2 mt-1">
                                <i class="fas fa-check text-info fs-10"></i>
                            </div>
                            <div>
                                <strong>Calibration:</strong> Predicted probabilities should match observed frequencies over time.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mathematical Framework -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calculator text-primary me-2"></i>Mathematical Framework
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="text-primary">1. Bayesian Foundation</h6>
                                <div class="bg-light p-3 rounded mb-3">
                                    <code class="text-dark">P(θ|D) ∝ P(D|θ) × P(θ)</code>
                                </div>
                                <p class="small">
                                    <strong>Posterior</strong> belief about player ability (θ) given observed data (D)
                                    combines the <strong>likelihood</strong> of the data and our <strong>prior</strong> beliefs.
                                </p>
                            </div>

                            <div class="col-md-4">
                                <h6 class="text-success">2. Hierarchical Modeling</h6>
                                <div class="bg-light p-3 rounded mb-3">
                                    <code class="text-dark">θᵢ ~ N(μ_position, σ²_position)</code>
                                </div>
                                <p class="small">
                                    Individual player abilities are drawn from position-specific distributions,
                                    allowing information sharing across similar players.
                                </p>
                            </div>

                            <div class="col-md-4">
                                <h6 class="text-info">3. Time-Varying Effects</h6>
                                <div class="bg-light p-3 rounded mb-3">
                                    <code class="text-dark">θₜ = θₜ₋₁ + εₜ</code>
                                </div>
                                <p class="small">
                                    Player abilities evolve over time through a random walk process,
                                    capturing improvement, decline, and form fluctuations.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Model Components -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-cogs text-primary me-2"></i>Model Components
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="text-primary">Base Performance Model</h6>
                                <p class="small mb-2">
                                    Each statistic follows a <strong>Negative Binomial</strong> or <strong>Gamma</strong> distribution
                                    to handle overdispersion common in counting statistics.
                                </p>
                                <div class="bg-light p-2 rounded">
                                    <code class="small">Points ~ NegBin(μ = exp(Xβ), φ)</code>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-success">Contextual Adjustments</h6>
                                <p class="small mb-2">
                                    Linear predictors incorporate game context through feature engineering:
                                </p>
                                <ul class="small mb-0">
                                    <li>Opponent defensive rating</li>
                                    <li>Rest days (fatigue effects)</li>
                                    <li>Home court advantage</li>
                                    <li>Recent performance trends</li>
                                </ul>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-warning">Correlation Structure</h6>
                                <p class="small mb-2">
                                    Statistics are correlated (e.g., high-scoring games often have more rebounds).
                                    We model this through:
                                </p>
                                <div class="bg-light p-2 rounded">
                                    <code class="small">Σ = LLᵀ (Cholesky decomposition)</code>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-info">Uncertainty Quantification</h6>
                                <p class="small mb-2">
                                    Multiple sources of uncertainty are explicitly modeled:
                                </p>
                                <ul class="small mb-0">
                                    <li><strong>Aleatory:</strong> Inherent randomness in performance</li>
                                    <li><strong>Epistemic:</strong> Parameter uncertainty from limited data</li>
                                    <li><strong>Model:</strong> Structural assumptions and approximations</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prediction Process -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-flow-chart text-primary me-2"></i>Prediction Process
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center mb-3">
                                    <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2">
                                        <i class="fas fa-database text-primary fs-20"></i>
                                    </div>
                                    <h6>1. Data Ingestion</h6>
                                    <p class="small text-muted">
                                        Historical performance, game context, and real-time updates
                                    </p>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="text-center mb-3">
                                    <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2">
                                        <i class="fas fa-brain text-success fs-20"></i>
                                    </div>
                                    <h6>2. Bayesian Update</h6>
                                    <p class="small text-muted">
                                        Posterior distributions updated with latest evidence
                                    </p>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="text-center mb-3">
                                    <div class="avatar-lg bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2">
                                        <i class="fas fa-dice text-warning fs-20"></i>
                                    </div>
                                    <h6>3. Monte Carlo</h6>
                                    <p class="small text-muted">
                                        10,000+ simulations generate full probability distributions
                                    </p>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="text-center mb-3">
                                    <div class="avatar-lg bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2">
                                        <i class="fas fa-chart-bar text-info fs-20"></i>
                                    </div>
                                    <h6>4. Risk Assessment</h6>
                                    <p class="small text-muted">
                                        Confidence intervals, expected values, and betting recommendations
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Validation & Limitations -->
        <div class="row">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-check-circle text-success me-2"></i>Model Validation
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6 class="text-primary">Backtesting</h6>
                        <p class="small mb-3">
                            Models are tested on historical data using time-series cross-validation
                            to ensure predictions would have been accurate in real-time.
                        </p>

                        <h6 class="text-success">Calibration Testing</h6>
                        <p class="small mb-3">
                            We verify that predicted probabilities match observed frequencies
                            (e.g., events predicted at 70% should occur ~70% of the time).
                        </p>

                        <h6 class="text-info">Continuous Monitoring</h6>
                        <p class="small mb-0">
                            Model performance is tracked in real-time with automatic alerts
                            for degradation or systematic biases.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>Known Limitations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6 class="alert-heading">Important Disclaimers</h6>
                            <ul class="mb-0 small">
                                <li>Models cannot predict injuries or unexpected events</li>
                                <li>Small sample sizes early in season reduce accuracy</li>
                                <li>Coaching changes and team dynamics are hard to quantify</li>
                                <li>Market efficiency may limit profitable betting opportunities</li>
                            </ul>
                        </div>

                        <h6 class="text-danger">Responsible Use</h6>
                        <p class="small mb-0">
                            Our predictions are tools for analysis, not guarantees.
                            Always bet responsibly and within your means. Past performance
                            does not guarantee future results.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Methodology Links -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-book-open text-primary me-2"></i>Detailed Methodology
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">
                            Dive deeper into the specific mathematical techniques and implementations behind our predictions.
                        </p>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <a href="/methodology/prop-analysis" class="text-decoration-none">
                                    <div class="card border h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start">
                                                <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <i class="fas fa-balance-scale text-success fs-18"></i>
                                                </div>
                                                <div>
                                                    <h6 class="card-title">Prop Betting Analysis</h6>
                                                    <p class="text-muted small mb-0">
                                                        Expected value theory, probability estimation, market analysis,
                                                        and risk management for profitable betting opportunities.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DefaultLayout>
