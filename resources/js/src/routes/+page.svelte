<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";
    import PersonalizedDashboard from "$lib/components/PersonalizedDashboard.svelte";
    import TodaysBestProps from "$lib/components/TodaysBestProps.svelte";

    let dashboardData = {
        totalPlayers: 0,
        totalTeams: 0,
        totalGames: 0,
        totalStats: 0,
        loading: true
    };

    onMount(async () => {
        await loadDashboardData();
    });

    async function loadDashboardData() {
        try {
            // Load summary data
            const [playersResponse, teamsResponse] = await Promise.all([
                api.players.getSummary(),
                api.teams.getSummary()
            ]);

            dashboardData = {
                totalPlayers: playersResponse.data?.length || 0,
                totalTeams: teamsResponse.data?.length || 0,
                totalGames: 150, // Mock data
                totalStats: 25000, // Mock data
                loading: false
            };
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
            dashboardData = {
                totalPlayers: 144,
                totalTeams: 12,
                totalGames: 150,
                totalStats: 25000,
                loading: false
            };
        }
    }
</script>

<DefaultLayout>
    <div class="container-xxl">
        <!-- Personalized Dashboard -->
        <PersonalizedDashboard />

        <!-- Original Dashboard (kept for reference) -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Quick Links</h4>
                    <p class="text-muted mb-0">Access all features and tools</p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-user text-primary fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1">{dashboardData.loading ? '...' : dashboardData.totalPlayers}</h3>
                                <p class="text-muted mb-0">Players</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-users text-success fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1">{dashboardData.loading ? '...' : dashboardData.totalTeams}</h3>
                                <p class="text-muted mb-0">Teams</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-gamepad text-info fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1">{dashboardData.loading ? '...' : dashboardData.totalGames}</h3>
                                <p class="text-muted mb-0">Games</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-chart-bar text-warning fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1">{dashboardData.loading ? '...' : dashboardData.totalStats.toLocaleString()}</h3>
                                <p class="text-muted mb-0">Statistics</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Todays Best Props -->
        <div class="row mb-4">
            <div class="col-12">
                <TodaysBestProps />
            </div>
        </div>

        <!-- Main Feature Cards -->
        <div class="row mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-users text-primary fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Teams & Players</h5>
                                <p class="text-muted mb-0">Browse WNBA teams and player profiles with detailed analytics</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="/teams" class="btn btn-primary btn-sm me-2">
                                <i class="fas fa-users me-1"></i>
                                Teams
                            </a>
                            <a href="/players" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user me-1"></i>
                                Players
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-crystal-ball text-success fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Prediction Engine</h5>
                                <p class="text-muted mb-0">Generate AI-powered predictions with confidence intervals</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="/reports/predictions" class="btn btn-success btn-sm">
                                <i class="fas fa-magic me-1"></i>
                                Start Predicting
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-search-dollar text-info fs-18"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Prop Scanner</h5>
                                <p class="text-muted mb-0">Scan for profitable betting opportunities across all players</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="/advanced/prop-scanner" class="btn btn-info btn-sm">
                                <i class="fas fa-search me-1"></i>
                                Scan Props
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics & Reports -->
        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-chart-line text-primary me-2"></i>Analytics & Reports
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="/reports/players" class="text-decoration-none">
                                    <div class="text-center p-3 border rounded hover-shadow">
                                        <i class="fas fa-user-chart text-primary fs-24 mb-2"></i>
                                        <h6 class="mb-0">Player Reports</h6>
                                        <small class="text-muted">Comprehensive player analytics</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="/reports/teams" class="text-decoration-none">
                                    <div class="text-center p-3 border rounded hover-shadow">
                                        <i class="fas fa-users-cog text-success fs-24 mb-2"></i>
                                        <h6 class="mb-0">Team Reports</h6>
                                        <small class="text-muted">Team performance metrics</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="/stats" class="text-decoration-none">
                                    <div class="text-center p-3 border rounded hover-shadow">
                                        <i class="fas fa-chart-bar text-warning fs-24 mb-2"></i>
                                        <h6 class="mb-0">Statistics</h6>
                                        <small class="text-muted">Raw statistical data</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="/games" class="text-decoration-none">
                                    <div class="text-center p-3 border rounded hover-shadow">
                                        <i class="fas fa-gamepad text-info fs-24 mb-2"></i>
                                        <h6 class="mb-0">Games</h6>
                                        <small class="text-muted">Game results & analysis</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-brain text-danger me-2"></i>Advanced Tools
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <a href="/advanced/live-odds" class="text-decoration-none">
                                    <div class="d-flex align-items-center p-3 border rounded hover-shadow">
                                        <div class="avatar-sm bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <i class="fas fa-bolt text-danger fs-18"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Live Odds</h6>
                                            <small class="text-muted">Real-time prop lines and market movement</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-12">
                                <a href="/advanced/prop-scanner" class="text-decoration-none">
                                    <div class="d-flex align-items-center p-3 border rounded hover-shadow">
                                        <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <i class="fas fa-radar text-success fs-18"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Prop Scanner</h6>
                                            <small class="text-muted">Scan players for profitable prop opportunities</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-12">
                                <a href="/advanced/model-validation" class="text-decoration-none">
                                    <div class="d-flex align-items-center p-3 border rounded hover-shadow">
                                        <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <i class="fas fa-check-circle text-warning fs-18"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Model Validation</h6>
                                            <small class="text-muted">Analyze prediction accuracy & calibration</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-12">
                                <a href="/advanced/prediction-testing" class="text-decoration-none">
                                    <div class="d-flex align-items-center p-3 border rounded hover-shadow">
                                        <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <i class="fas fa-history text-info fs-18"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Historical Testing</h6>
                                            <small class="text-muted">Backtest predictions against actual results</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Methodology Section -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-book text-secondary me-2"></i>Methodology & Documentation
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <a href="/methodology" class="text-decoration-none">
                                    <div class="text-center p-3 border rounded hover-shadow">
                                        <i class="fas fa-book-open text-primary fs-24 mb-2"></i>
                                        <h6 class="mb-1">Overview</h6>
                                        <small class="text-muted">Mathematical foundations & philosophy</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="/methodology/prop-analysis" class="text-decoration-none">
                                    <div class="text-center p-3 border rounded hover-shadow">
                                        <i class="fas fa-chart-pie text-success fs-24 mb-2"></i>
                                        <h6 class="mb-1">Prop Analysis</h6>
                                        <small class="text-muted">Expected value & betting theory</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="/advanced/model-validation" class="text-decoration-none">
                                    <div class="text-center p-3 border rounded hover-shadow">
                                        <i class="fas fa-check-circle text-warning fs-24 mb-2"></i>
                                        <h6 class="mb-1">Model Validation</h6>
                                        <small class="text-muted">Prediction accuracy & calibration</small>
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

<style>
    .hover-shadow {
        transition: box-shadow 0.15s ease-in-out;
    }

    .hover-shadow:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
</style>
