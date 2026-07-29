<script lang="ts">
    import BaseChart from './BaseChart.svelte';
    import type { ChartData, ChartOptions } from 'chart.js';

    export let data: {
        date: string;
        points_scored: number;
        points_allowed: number;
        result: 'W' | 'L';
        home_away: 'home' | 'away';
    }[] = [];
    export let loading: boolean = false;
    export let error: string | null = null;
    export let height: string = '400px';

    let baseChart: BaseChart;

    // APIs return recent games newest-first; charts need oldest → newest on the x-axis.
    $: chronological = [...data].reverse();

    // Transform data for Chart.js
    $: chartData = {
        labels: chronological.map(d => d.date),
        datasets: [
            {
                label: 'Points Scored',
                data: chronological.map(d => d.points_scored),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: false,
                borderWidth: 2,
                pointBackgroundColor: chronological.map(d => d.result === 'W' ? '#10b981' : '#ef4444'),
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
            },
            {
                label: 'Points Allowed',
                data: chronological.map(d => d.points_allowed),
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: false,
                borderWidth: 2,
                pointBackgroundColor: '#ef4444',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            }
        ]
    } as ChartData<'line'>;

    // Chart options specific to team game results
    const chartOptions: ChartOptions<'line'> = {
        scales: {
            x: {
                grid: {
                    display: false,
                },
                ticks: {
                    maxTicksLimit: 10,
                },
            },
            y: {
                beginAtZero: false,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)',
                },
                ticks: {
                    stepSize: 10,
                },
                title: {
                    display: true,
                    text: 'Points',
                },
            },
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                callbacks: {
                    afterBody: (context) => {
                        const index = context[0].dataIndex;
                        const gameData = chronological[index];
                        if (!gameData) return [];
                        return [
                            `Result: ${gameData.result}`,
                            `Location: ${gameData.home_away === 'home' ? 'Home' : 'Away'}`,
                            `Point Diff: ${gameData.points_scored - gameData.points_allowed > 0 ? '+' : ''}${gameData.points_scored - gameData.points_allowed}`
                        ];
                    }
                }
            },
        },
        interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false,
        },
        elements: {
            point: {
                hoverBackgroundColor: (context) => {
                    const index = context.dataIndex;
                    const gameData = chronological[index];
                    return gameData?.result === 'W' ? '#10b981' : '#ef4444';
                },
            },
        },
    };

    // Export functions to parent components
    export function refreshChart() {
        if (baseChart) {
            baseChart.refreshChart();
        }
    }

    export function exportChart(format: 'png' | 'jpeg' = 'png') {
        if (baseChart) {
            return baseChart.exportChart(format);
        }
        return null;
    }
</script>

<BaseChart
    bind:this={baseChart}
    title="Team Game Results"
    chartType="line"
    data={chartData}
    options={chartOptions}
    {height}
    {loading}
    {error}
/>