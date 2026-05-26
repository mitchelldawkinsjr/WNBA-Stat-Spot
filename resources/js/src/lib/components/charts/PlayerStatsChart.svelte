<script lang="ts">
    import BaseChart from './BaseChart.svelte';
    import type { ChartData, ChartOptions } from 'chart.js';

    export let data: { date: string; value: number }[] = [];
    export let statName: string = '';
    export let lineColor: string = '#3b82f6';
    export let backgroundColor: string = 'rgba(59, 130, 246, 0.1)';
    export let height: string = '300px';
    export let loading: boolean = false;
    export let error: string | null = null;

    let baseChart: BaseChart;

    // Transform data for Chart.js
    $: chartData = {
        labels: data.map(d => d.date),
        datasets: [{
            label: statName,
            data: data.map(d => d.value),
            borderColor: lineColor,
            backgroundColor: backgroundColor,
            tension: 0.4,
            fill: true,
            borderWidth: 2,
            pointBackgroundColor: lineColor,
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
        }]
    } as ChartData<'line'>;

    // Chart options specific to player stats
    const chartOptions: ChartOptions<'line'> = {
        scales: {
            x: {
                grid: {
                    display: false,
                },
                ticks: {
                    maxTicksLimit: 8,
                },
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)',
                },
                ticks: {
                    stepSize: 1,
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
                borderColor: lineColor,
                borderWidth: 1,
            },
        },
        interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false,
        },
        elements: {
            point: {
                hoverBackgroundColor: lineColor,
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
    title={statName ? `${statName} Trend` : 'Player Statistics'}
    chartType="line"
    data={chartData}
    options={chartOptions}
    {height}
    {loading}
    {error}
/>