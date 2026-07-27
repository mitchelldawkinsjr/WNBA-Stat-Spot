<script lang="ts">
    import { onMount } from 'svelte';
    import { api } from '$lib/api/client';
    import DefaultLayout from "$lib/layouts/DefaultLayout.svelte";

    interface DataSummary {
        teams: number;
        players: number;
        games: number;
        player_stats: number;
        last_updated: string;
    }

    let dataSummary: DataSummary | null = null;
    let loading = false;
    let error: string | null = null;
    let successMessage: string | null = null;
    let importInProgress = false;

    onMount(async () => {
        await loadDataSummary();
    });

    async function loadDataSummary() {
        try {
            loading = true;
            error = null;
            const response = await api.wnba.dataImport.getSummary();
            dataSummary = response.data;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to load data summary';
        } finally {
            loading = false;
        }
    }

    async function importAllData() {
        try {
            importInProgress = true;
            error = null;
            successMessage = null;

            const response = await api.wnba.dataImport.importAll();
            successMessage = response.message;
            await loadDataSummary();
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to import data';
        } finally {
            importInProgress = false;
        }
    }

    async function forceImportAllData() {
        if (!confirm('This will overwrite all existing data. Are you sure?')) {
            return;
        }

        try {
            importInProgress = true;
            error = null;
            successMessage = null;

            const response = await api.wnba.dataImport.forceImportAll();
            successMessage = response.message;
            await loadDataSummary();
        } catch (err) {
            error = err instanceof Error ? err.message : 'Failed to force import data';
        } finally {
            importInProgress = false;
        }
    }

    async function importSpecificData(type: 'teams' | 'players' | 'games' | 'stats') {
        try {
            importInProgress = true;
            error = null;
            successMessage = null;

            let response;
            switch (type) {
                case 'teams':
                    response = await api.wnba.dataImport.importTeams();
                    break;
                case 'players':
                    response = await api.wnba.dataImport.importPlayers();
                    break;
                case 'games':
                    response = await api.wnba.dataImport.importGames();
                    break;
                case 'stats':
                    response = await api.wnba.dataImport.importPlayerStats();
                    break;
            }

            successMessage = response.message;
            await loadDataSummary();
        } catch (err) {
            error = err instanceof Error ? err.message : `Failed to import ${type}`;
        } finally {
            importInProgress = false;
        }
    }
</script>

<svelte:head>
    <title>Data Management | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <div class="container-xxl">
        <!-- Page Title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Data Management</h4>
                    <p class="text-muted mb-0">Update database from WNBA stats endpoints</p>
                    <p class="mb-0 mt-2">
                        <a href="/advanced/review-queue" class="btn btn-outline-primary btn-sm">Open review queue</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Status Messages -->
        {#if error}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> {error}
                    </div>
                </div>
            </div>
        {/if}

        {#if successMessage}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Success:</strong> {successMessage}
                    </div>
                </div>
            </div>
        {/if}

        <!-- Data Summary -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-database me-2"></i>
                            Current Database Status
                        </h5>
                    </div>
                    <div class="card-body">
                        {#if loading}
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 mb-0">Loading data summary...</p>
                            </div>
                        {:else if dataSummary}
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-primary mb-1">{dataSummary.teams}</h3>
                                        <p class="text-muted mb-0">Teams</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-success mb-1">{dataSummary.players}</h3>
                                        <p class="text-muted mb-0">Players</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-info mb-1">{dataSummary.games}</h3>
                                        <p class="text-muted mb-0">Games</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-warning mb-1">{dataSummary.player_stats}</h3>
                                        <p class="text-muted mb-0">Player Stats</p>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Last updated: {new Date(dataSummary.last_updated).toLocaleString()}
                                </small>
                            </div>
                        {:else}
                            <div class="text-center py-3">
                                <p class="text-muted mb-0">Unable to load data summary</p>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Actions -->
        <div class="row">
            <!-- Full Import -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-download me-2"></i>
                            Full Data Import
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Import all WNBA data from stats endpoints. This will update existing records and add new ones.
                        </p>
                        <div class="d-grid gap-2">
                            <button
                                class="btn btn-primary"
                                disabled={importInProgress}
                                on:click={importAllData}
                            >
                                {#if importInProgress}
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Importing...
                                {:else}
                                    <i class="fas fa-sync-alt me-2"></i>
                                    Update All Data
                                {/if}
                            </button>
                            <button
                                class="btn btn-warning"
                                disabled={importInProgress}
                                on:click={forceImportAllData}
                            >
                                {#if importInProgress}
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Force Importing...
                                {:else}
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Force Overwrite All
                                {/if}
                            </button>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <strong>Update:</strong> Incremental update (recommended)<br>
                            <strong>Force Overwrite:</strong> Replaces all existing data
                        </small>
                    </div>
                </div>
            </div>

            <!-- Individual Imports -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-puzzle-piece me-2"></i>
                            Individual Data Types
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Import specific data types individually for targeted updates.
                        </p>
                        <div class="d-grid gap-2">
                            <button
                                class="btn btn-outline-primary"
                                disabled={importInProgress}
                                on:click={() => importSpecificData('teams')}
                            >
                                <i class="fas fa-users me-2"></i>
                                Import Teams
                            </button>
                            <button
                                class="btn btn-outline-success"
                                disabled={importInProgress}
                                on:click={() => importSpecificData('players')}
                            >
                                <i class="fas fa-user me-2"></i>
                                Import Players
                            </button>
                            <button
                                class="btn btn-outline-info"
                                disabled={importInProgress}
                                on:click={() => importSpecificData('games')}
                            >
                                <i class="fas fa-calendar me-2"></i>
                                Import Games
                            </button>
                            <button
                                class="btn btn-outline-warning"
                                disabled={importInProgress}
                                on:click={() => importSpecificData('stats')}
                            >
                                <i class="fas fa-chart-bar me-2"></i>
                                Import Player Stats
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Command Line Instructions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-terminal me-2"></i>
                            Command Line Options
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            You can also update the database using command line tools:
                        </p>
                        <div class="bg-dark text-light p-3 rounded">
                            <code>
                                # Standard update (incremental)<br>
                                docker exec wnba-stat-spot-laravel.test-1 php artisan app:import-wnba-data<br><br>
                                # Force update (overwrites existing)<br>
                                docker exec wnba-stat-spot-laravel.test-1 php artisan app:import-wnba-data --force<br><br>
                                # Check current data counts<br>
                                docker exec wnba-stat-spot-laravel.test-1 php artisan tinker --execute="echo 'Teams: ' . App\\Models\\WnbaTeam::count();"
                            </code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DefaultLayout>
