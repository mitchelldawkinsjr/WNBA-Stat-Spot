<script lang="ts">
    import { onMount } from 'svelte';
    import Chart from 'chart.js/auto';
    import type { ChartConfiguration } from 'chart.js';

    export let data: {
        fgPercentage: number;
        threePercentage: number;
        ftPercentage: number;
        efgPercentage: number;
        tsPercentage: number;
    };

    let canvas: HTMLCanvasElement;
    let chart: Chart;

    onMount(() => {
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const chartConfig: ChartConfiguration = {
            type: 'radar',
            data: {
                labels: ['FG%', '3P%', 'FT%', 'eFG%', 'TS%'],
                datasets: [{
                    label: 'Shooting Efficiency',
                    data: [
                        data.fgPercentage,
                        data.threePercentage,
                        data.ftPercentage,
                        data.efgPercentage,
                        data.tsPercentage
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(54, 162, 235, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: {
                            display: true
                        },
                        suggestedMin: 0,
                        suggestedMax: 100
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return `${context.parsed.value.toFixed(1)}%`;
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
        chart.data.datasets[0].data = [
            data.fgPercentage,
            data.threePercentage,
            data.ftPercentage,
            data.efgPercentage,
            data.tsPercentage
        ];
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
