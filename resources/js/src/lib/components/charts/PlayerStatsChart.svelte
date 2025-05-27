<script lang="ts">
    import { onMount } from 'svelte';
    import Chart from 'chart.js/auto';
    import type { ChartConfiguration } from 'chart.js';

    export let data: { date: string; value: number }[] = [];
    export let statName: string = '';
    export let lineColor: string = '#3b82f6';
    export let backgroundColor: string = 'rgba(59, 130, 246, 0.1)';
    export let height: string = '300px';

    let canvas: HTMLCanvasElement;
    let chart: Chart;

    onMount(() => {
        if (!canvas || !data.length) return;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const config: ChartConfiguration = {
            type: 'line',
            data: {
                labels: data.map(d => d.date),
                datasets: [{
                    label: statName,
                    data: data.map(d => d.value),
                    borderColor: lineColor,
                    backgroundColor: backgroundColor,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (context) => {
                                return `${statName}: ${context.parsed.y.toFixed(1)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        };

        chart = new Chart(ctx, config);

        return () => {
            if (chart) {
                chart.destroy();
            }
        };
    });

    $: if (chart && data) {
        chart.data.labels = data.map(d => d.date);
        chart.data.datasets[0].data = data.map(d => d.value);
        chart.update();
    }
</script>

<div class="chart-container" style="height: {height};">
    <canvas bind:this={canvas}></canvas>
</div>

<style>
    .chart-container {
        position: relative;
        width: 100%;
    }
</style>
