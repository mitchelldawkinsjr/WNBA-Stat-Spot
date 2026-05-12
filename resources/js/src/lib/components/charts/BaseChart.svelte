<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import { Chart, registerables, type ChartConfiguration, type ChartData, type ChartOptions } from 'chart.js';
    import { Card, CardBody, CardHeader, CardTitle } from '@sveltestrap/sveltestrap';

    Chart.register(...registerables);

    export let title: string = '';
    export let chartType: 'line' | 'bar' | 'doughnut' | 'pie' | 'radar' = 'bar';
    export let data: ChartData;
    export let options: ChartOptions = {};
    export let height: string = '400px';
    export let loading: boolean = false;
    export let error: string | null = null;

    let canvas: HTMLCanvasElement;
    let chart: Chart | null = null;
    let chartId = `chart-${Math.random().toString(36).substr(2, 9)}`;

    // Default chart options
    const defaultOptions: ChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            },
        },
        scales: {
            x: {
                display: true,
                grid: {
                    display: false,
                },
            },
            y: {
                display: true,
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)',
                },
            },
        },
        animation: {
            duration: 750,
            easing: 'easeInOutQuart',
        },
    };

    // Merge provided options with defaults
    $: mergedOptions = {
        ...defaultOptions,
        ...options,
        plugins: {
            ...defaultOptions.plugins,
            ...options.plugins,
        },
        scales: {
            ...defaultOptions.scales,
            ...options.scales,
        },
    };

    onMount(() => {
        if (canvas && data) {
            createChart();
        }
    });

    onDestroy(() => {
        if (chart) {
            chart.destroy();
            chart = null;
        }
    });

    function createChart() {
        if (!canvas || !data) return;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        // Destroy existing chart if it exists
        if (chart) {
            chart.destroy();
        }

        const chartConfig: ChartConfiguration = {
            type: chartType,
            data: data,
            options: mergedOptions,
        };

        chart = new Chart(ctx, chartConfig);
    }

    function updateChart() {
        if (chart && data) {
            chart.data = data;
            chart.update('none'); // No animation for updates
        }
    }

    // Reactive updates when data changes
    $: if (chart && data) {
        updateChart();
    }

    // Recreate chart when type changes
    $: if (chart && chartType) {
        createChart();
    }

    // Export chart update function for external use
    export function refreshChart() {
        if (chart) {
            chart.update();
        }
    }

    // Export chart resize function
    export function resizeChart() {
        if (chart) {
            chart.resize();
        }
    }

    // Export chart data export function
    export function exportChart(format: 'png' | 'jpeg' = 'png'): string | null {
        if (chart) {
            return chart.toBase64Image(format, 1);
        }
        return null;
    }
</script>

<Card class="h-100">
    {#if title}
        <CardHeader>
            <CardTitle class="mb-0">{title}</CardTitle>
        </CardHeader>
    {/if}
    
    <CardBody class="d-flex flex-column">
        {#if loading}
            <div class="d-flex justify-content-center align-items-center flex-grow-1">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading chart...</span>
                </div>
            </div>
        {:else if error}
            <div class="alert alert-danger mb-0" role="alert">
                <i class="mdi mdi-alert-circle me-2"></i>
                <strong>Error:</strong> {error}
            </div>
        {:else if !data || !data.datasets || data.datasets.length === 0}
            <div class="d-flex justify-content-center align-items-center flex-grow-1 text-muted">
                <div class="text-center">
                    <i class="mdi mdi-chart-line fs-1 mb-2"></i>
                    <p class="mb-0">No data available</p>
                </div>
            </div>
        {:else}
            <div class="chart-container flex-grow-1" style="height: {height}; position: relative;">
                <canvas
                    bind:this={canvas}
                    id={chartId}
                    style="width: 100%; height: 100%;"
                ></canvas>
            </div>
        {/if}
    </CardBody>
</Card>

<style>
    .chart-container {
        min-height: 300px;
    }
    
    .chart-container canvas {
        max-height: 100%;
        max-width: 100%;
    }
</style>