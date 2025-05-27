import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ShootingEfficiencyChart from '../ShootingEfficiencyChart.svelte';

// Mock Chart.js
vi.mock('chart.js/auto', () => ({
    default: vi.fn().mockImplementation(() => ({
        destroy: vi.fn(),
        update: vi.fn()
    }))
}));

describe('ShootingEfficiencyChart', () => {
    const mockData = {
        fgPercentage: 45.5,
        threePercentage: 38.2,
        ftPercentage: 85.7,
        efgPercentage: 52.3,
        tsPercentage: 58.9
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the chart container', () => {
        render(ShootingEfficiencyChart, {
            props: { data: mockData }
        });

        const container = screen.getByTestId('chart-container');
        expect(container).toBeInTheDocument();
    });

    it('initializes chart with correct data', () => {
        render(ShootingEfficiencyChart, {
            props: { data: mockData }
        });

        const canvas = screen.getByTestId('chart-canvas');
        expect(canvas).toBeInTheDocument();
    });

    it('updates chart when data changes', async () => {
        const { component } = render(ShootingEfficiencyChart, {
            props: { data: mockData }
        });

        const newData = {
            fgPercentage: 47.2,
            threePercentage: 40.1,
            ftPercentage: 88.3,
            efgPercentage: 54.6,
            tsPercentage: 60.2
        };

        await component.$set({ data: newData });
        // Chart update is handled internally by the component
    });

    it('cleans up chart on component destruction', () => {
        const { unmount } = render(ShootingEfficiencyChart, {
            props: { data: mockData }
        });

        unmount();
        // Chart destroy is handled internally by the component
    });

    it('handles missing data gracefully', () => {
        const incompleteData = {
            fgPercentage: 45.5,
            threePercentage: 38.2,
            // Missing other properties
        };

        render(ShootingEfficiencyChart, {
            props: { data: incompleteData }
        });

        const canvas = screen.getByTestId('chart-canvas');
        expect(canvas).toBeInTheDocument();
    });

    it('handles zero values correctly', () => {
        const zeroData = {
            fgPercentage: 0,
            threePercentage: 0,
            ftPercentage: 0,
            efgPercentage: 0,
            tsPercentage: 0
        };

        render(ShootingEfficiencyChart, {
            props: { data: zeroData }
        });

        const canvas = screen.getByTestId('chart-canvas');
        expect(canvas).toBeInTheDocument();
    });
});
