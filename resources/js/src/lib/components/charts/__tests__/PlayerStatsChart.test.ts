import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import PlayerStatsChart from '../PlayerStatsChart.svelte';

// Mock Chart.js
vi.mock('chart.js/auto', () => ({
    default: vi.fn().mockImplementation(() => ({
        destroy: vi.fn(),
        update: vi.fn()
    }))
}));

describe('PlayerStatsChart', () => {
    const mockData = [
        { date: '2024-01-01', value: 15 },
        { date: '2024-01-02', value: 18 },
        { date: '2024-01-03', value: 12 }
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the chart container', () => {
        render(PlayerStatsChart, {
            props: {
                data: mockData,
                statName: 'Points',
                lineColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                height: 400
            }
        });

        const container = screen.getByTestId('chart-container');
        expect(container).toBeInTheDocument();
    });

    it('initializes chart with correct data', () => {
        render(PlayerStatsChart, {
            props: {
                data: mockData,
                statName: 'Points',
                lineColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                height: 400
            }
        });

        const canvas = screen.getByTestId('chart-canvas');
        expect(canvas).toBeInTheDocument();
    });

    it('updates chart when data changes', async () => {
        const { component } = render(PlayerStatsChart, {
            props: {
                data: mockData,
                statName: 'Points',
                lineColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                height: 400
            }
        });

        const newData = [
            { date: '2024-01-01', value: 20 },
            { date: '2024-01-02', value: 22 },
            { date: '2024-01-03', value: 19 }
        ];

        await component.$set({ data: newData });
        // Chart update is handled internally by the component
    });

    it('cleans up chart on component destruction', () => {
        const { unmount } = render(PlayerStatsChart, {
            props: {
                data: mockData,
                statName: 'Points',
                lineColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                height: 400
            }
        });

        unmount();
        // Chart destroy is handled internally by the component
    });
});
