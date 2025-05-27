<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { playerAnalytics, gameStatsChartData, shootingEfficiencyData, homeAwayComparison } from '$lib/stores/playerAnalytics';
    import PlayerStatsChart from '$lib/components/charts/PlayerStatsChart.svelte';
    import ShootingEfficiencyChart from '$lib/components/charts/ShootingEfficiencyChart.svelte';
    import HomeAwayComparisonChart from '$lib/components/charts/HomeAwayComparisonChart.svelte';
    import LoadingSpinner from '$lib/components/LoadingSpinner.svelte';
    import ErrorMessage from '$lib/components/ErrorMessage.svelte';

    const playerId = $page.params.id;

    onMount(() => {
        playerAnalytics.fetchAnalytics(playerId);
    });
</script>

<div class="container mx-auto px-4 py-8">
    {#if $playerAnalytics.loading}
        <div class="flex justify-center items-center h-64">
            <LoadingSpinner />
        </div>
    {:else if $playerAnalytics.error}
        <ErrorMessage message={$playerAnalytics.error} />
    {:else}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Game Stats Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Game Statistics</h2>
                {#if $gameStatsChartData.length > 0}
                    <PlayerStatsChart data={$gameStatsChartData} />
                {:else}
                    <p class="text-gray-500">No game statistics available</p>
                {/if}
            </div>

            <!-- Shooting Efficiency Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Shooting Efficiency</h2>
                {#if $shootingEfficiencyData}
                    <ShootingEfficiencyChart data={$shootingEfficiencyData} />
                {:else}
                    <p class="text-gray-500">No shooting efficiency data available</p>
                {/if}
            </div>

            <!-- Home vs Away Comparison -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Home vs Away Performance</h2>
                {#if $homeAwayComparison}
                    <HomeAwayComparisonChart data={$homeAwayComparison} />
                {:else}
                    <p class="text-gray-500">No home/away comparison data available</p>
                {/if}
            </div>

            <!-- Recent Form -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Recent Form</h2>
                {#if $playerAnalytics.recentForm}
                    <div class="space-y-4">
                        <p class="text-gray-600">
                            Last {$playerAnalytics.recentForm.games_analyzed} games
                        </p>
                        <div class="grid grid-cols-2 gap-4">
                            {#each Object.entries($playerAnalytics.recentForm.averages) as [stat, value]}
                                <div class="bg-gray-50 p-3 rounded">
                                    <p class="text-sm text-gray-500">{stat}</p>
                                    <p class="text-lg font-semibold">{value.toFixed(1)}</p>
                                </div>
                            {/each}
                        </div>
                    </div>
                {:else}
                    <p class="text-gray-500">No recent form data available</p>
                {/if}
            </div>

            <!-- Per 36 Minutes Stats -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Per 36 Minutes</h2>
                {#if $playerAnalytics.per36Stats}
                    <div class="grid grid-cols-2 gap-4">
                        {#each Object.entries($playerAnalytics.per36Stats) as [stat, value]}
                            <div class="bg-gray-50 p-3 rounded">
                                <p class="text-sm text-gray-500">{stat}</p>
                                <p class="text-lg font-semibold">{value.toFixed(1)}</p>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <p class="text-gray-500">No per 36 minutes data available</p>
                {/if}
            </div>

            <!-- Advanced Metrics -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Advanced Metrics</h2>
                {#if $playerAnalytics.advancedMetrics}
                    <div class="grid grid-cols-2 gap-4">
                        {#each Object.entries($playerAnalytics.advancedMetrics) as [stat, value]}
                            <div class="bg-gray-50 p-3 rounded">
                                <p class="text-sm text-gray-500">{stat}</p>
                                <p class="text-lg font-semibold">{value.toFixed(1)}</p>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <p class="text-gray-500">No advanced metrics available</p>
                {/if}
            </div>
        </div>
    {/if}
</div>

<style>
    .container {
        max-width: 1280px;
    }
</style>
