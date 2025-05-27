<script lang="ts">
    import { onMount } from 'svelte';
    import Chart from 'chart.js/auto';
    import type { ChartConfiguration } from 'chart.js';

    export let data: {
        home: Record<string, number>;
        away: Record<string, number>;
    };

    let canvas: HTMLCanvasElement;
    let chart: Chart;

    const statLabels = ['Points', 'Rebounds', 'Assists', 'Steals', 'Blocks'];
    const statKeys = ['points', 'rebounds', 'assists', 'steals', 'blocks'];

    onMount(() => {
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const chartConfig: ChartConfiguration = {
            type: 'bar',
            data: {
                labels: statLabels,
                datasets: [
                    {
                        label: 'Home',
                        data: statKeys.map(key => data.home[key] || 0),
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Away',
                        data: statKeys.map(key => data.away[key] || 0),
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Average per Game'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Statistics'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return `${context.dataset.label}: ${context.parsed.y.toFixed(1)}`;
                            }
                        }
                    }
                }
            }
        };

        chart = new Chart(ctx, chartConfig);

        return () => {
            if (chart) {
                chart.destroy();
            }
        };
    });

    $: if (chart && data) {
        chart.data.datasets[0].data = statKeys.map(key => data.home[key] || 0);
        chart.data.datasets[1].data = statKeys.map(key => data.away[key] || 0);
        chart.update();
    }
</script>

<div class="chart-container" style="position: relative; height: 400px;">
    <canvas bind:this={canvas}></canvas>
</div>

<style>
    .chart-container {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
</style>
