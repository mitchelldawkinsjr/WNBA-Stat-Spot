<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { teamAnalytics, gameResultsChartData, seasonStatsData, advancedMetricsData } from '$lib/stores/teamAnalytics';
    import TeamGameResultsChart from '$lib/components/charts/TeamGameResultsChart.svelte';
    import LoadingSpinner from '$lib/components/LoadingSpinner.svelte';
    import ErrorMessage from '$lib/components/ErrorMessage.svelte';

    const teamId = $page.params.id;

    onMount(() => {
        teamAnalytics.fetchAnalytics(teamId);
    });
</script>

<div class="container mx-auto px-4 py-8">
    {#if $teamAnalytics.loading}
        <div class="flex justify-center items-center h-64">
            <LoadingSpinner />
        </div>
    {:else if $teamAnalytics.error}
        <ErrorMessage message={$teamAnalytics.error} />
    {:else}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Game Results Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Game Results</h2>
                {#if $gameResultsChartData.length > 0}
                    <TeamGameResultsChart data={$gameResultsChartData} />
                {:else}
                    <p class="text-gray-500">No game results available</p>
                {/if}
            </div>

            <!-- Season Stats -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Season Stats</h2>
                {#if $seasonStatsData}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Record</p>
                            <p class="text-lg font-semibold">{$seasonStatsData.record}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Win %</p>
                            <p class="text-lg font-semibold">{$seasonStatsData.winPercentage.toFixed(3)}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Points Per Game</p>
                            <p class="text-lg font-semibold">{$seasonStatsData.pointsPerGame.toFixed(1)}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Points Allowed</p>
                            <p class="text-lg font-semibold">{$seasonStatsData.pointsAllowedPerGame.toFixed(1)}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded col-span-2">
                            <p class="text-sm text-gray-500">Current Streak</p>
                            <p class="text-lg font-semibold">{$seasonStatsData.streak}</p>
                        </div>
                    </div>
                {:else}
                    <p class="text-gray-500">No season stats available</p>
                {/if}
            </div>

            <!-- Advanced Metrics -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Advanced Metrics</h2>
                {#if $advancedMetricsData}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Offensive Rating</p>
                            <p class="text-lg font-semibold">{$advancedMetricsData.offensiveRating.toFixed(1)}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Defensive Rating</p>
                            <p class="text-lg font-semibold">{$advancedMetricsData.defensiveRating.toFixed(1)}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Net Rating</p>
                            <p class="text-lg font-semibold">{$advancedMetricsData.netRating.toFixed(1)}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Pace</p>
                            <p class="text-lg font-semibold">{$advancedMetricsData.pace.toFixed(1)}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded col-span-2">
                            <p class="text-sm text-gray-500">True Shooting %</p>
                            <p class="text-lg font-semibold">{$advancedMetricsData.trueShootingPercentage.toFixed(1)}%</p>
                        </div>
                    </div>
                {:else}
                    <p class="text-gray-500">No advanced metrics available</p>
                {/if}
            </div>

            <!-- Home/Away Splits -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Home/Away Splits</h2>
                {#if $teamAnalytics.homeAwaySplits}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Home Record</p>
                            <p class="text-lg font-semibold">
                                {$teamAnalytics.homeAwaySplits.home.wins}-{$teamAnalytics.homeAwaySplits.home.losses}
                            </p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Away Record</p>
                            <p class="text-lg font-semibold">
                                {$teamAnalytics.homeAwaySplits.away.wins}-{$teamAnalytics.homeAwaySplits.away.losses}
                            </p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Home PPG</p>
                            <p class="text-lg font-semibold">
                                {$teamAnalytics.homeAwaySplits.home.points_per_game.toFixed(1)}
                            </p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Away PPG</p>
                            <p class="text-lg font-semibold">
                                {$teamAnalytics.homeAwaySplits.away.points_per_game.toFixed(1)}
                            </p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Home Points Allowed</p>
                            <p class="text-lg font-semibold">
                                {$teamAnalytics.homeAwaySplits.home.points_allowed_per_game.toFixed(1)}
                            </p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-500">Away Points Allowed</p>
                            <p class="text-lg font-semibold">
                                {$teamAnalytics.homeAwaySplits.away.points_allowed_per_game.toFixed(1)}
                            </p>
                        </div>
                    </div>
                {:else}
                    <p class="text-gray-500">No home/away splits available</p>
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
