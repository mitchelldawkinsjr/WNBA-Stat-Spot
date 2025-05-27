<script lang="ts">
    import { onMount } from 'svelte';
    import Chart from 'chart.js/auto';
    import type { ChartConfiguration } from 'chart.js';

    export let data: {
        date: string;
        points_scored: number;
        points_allowed: number;
        result: 'W' | 'L';
        home_away: 'home' | 'away';
    }[] = [];

    let canvas: HTMLCanvasElement;
    let chart: Chart;

    onMount(() => {
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const chartConfig: ChartConfiguration = {
            type: 'line',
            data: {
                labels: data.map(d => d.date),
                datasets: [
                    {
                        label: 'Points Scored',
                        data: data.map(d => d.points_scored),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Points Allowed',
                        data: data.map(d => d.points_allowed),
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (context) => {
                                const game = data[context.dataIndex];
                                return [
                                    `${context.dataset.label}: ${context.parsed.y}`,
                                    `Result: ${game.result}`,
                                    `Location: ${game.home_away}`
                                ];
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
                        title: {
                            display: true,
                            text: 'Points'
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

        chart = new Chart(ctx, chartConfig);

        return () => {
            if (chart) {
                chart.destroy();
            }
        };
    });

    $: if (chart && data) {
        chart.data.labels = data.map(d => d.date);
        chart.data.datasets[0].data = data.map(d => d.points_scored);
        chart.data.datasets[1].data = data.map(d => d.points_allowed);
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
