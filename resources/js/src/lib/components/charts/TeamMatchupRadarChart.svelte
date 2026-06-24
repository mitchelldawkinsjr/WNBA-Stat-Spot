<script lang="ts">
    import { onDestroy, tick } from 'svelte';
    import { Chart } from '$lib/chart/register';
    import type { ChartConfiguration } from 'chart.js';

    export let labels: string[] = [];
    export let homeData: number[] = [];
    export let awayData: number[] = [];
    export let homeLabel = 'Home';
    export let awayLabel = 'Away';

    let canvas: HTMLCanvasElement;
    let chart: Chart | null = null;

    function buildConfig(): ChartConfiguration {
        return {
            type: 'radar',
            data: {
                labels,
                datasets: [
                    {
                        label: homeLabel,
                        data: homeData,
                        backgroundColor: 'rgba(255, 108, 47, 0.15)',
                        borderColor: '#ff6c2f',
                        borderWidth: 2,
                        pointBackgroundColor: '#ff6c2f',
                    },
                    {
                        label: awayLabel,
                        data: awayData,
                        backgroundColor: 'rgba(52, 145, 252, 0.15)',
                        borderColor: '#3491fc',
                        borderWidth: 2,
                        pointBackgroundColor: '#3491fc',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: { color: 'rgba(0,0,0,0.08)' },
                        grid: { color: 'rgba(0,0,0,0.08)' },
                        suggestedMin: 0,
                        ticks: { display: false },
                    },
                },
                plugins: {
                    legend: { position: 'top' },
                },
            },
        };
    }

    async function ensureChart() {
        await tick();
        if (!canvas) return;

        if (!chart) {
            const ctx = canvas.getContext('2d');
            if (!ctx) return;
            chart = new Chart(ctx, buildConfig());
            return;
        }

        chart.data.labels = labels;
        chart.data.datasets[0].data = homeData;
        chart.data.datasets[0].label = homeLabel;
        chart.data.datasets[1].data = awayData;
        chart.data.datasets[1].label = awayLabel;
        chart.update();
    }

    $: if (canvas && labels.length) {
        void ensureChart();
    }

    onDestroy(() => {
        chart?.destroy();
        chart = null;
    });
</script>

<div class="chart-container">
    <canvas bind:this={canvas}></canvas>
</div>

<style>
    .chart-container {
        position: relative;
        height: 320px;
    }
</style>
