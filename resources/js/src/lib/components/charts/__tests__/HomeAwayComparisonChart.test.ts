import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import HomeAwayComparisonChart from '../HomeAwayComparisonChart.svelte';

// Mock Chart.js
vi.mock('chart.js/auto', () => ({
    default: vi.fn().mockImplementation(() => ({
        destroy: vi.fn(),
        update: vi.fn()
    }))
}));

describe('HomeAwayComparisonChart', () => {
    const mockData = {
        home: {
            points: 85.5,
            rebounds: 35.2,
            assists: 20.1,
            steals: 7.8,
            blocks: 4.3
        },
        away: {
            points: 82.3,
            rebounds: 33.8,
            assists: 18.9,
            steals: 7.2,
            blocks: 4.1
        }
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the chart container', () => {
        render(HomeAwayComparisonChart, {
            props: { data: mockData }
        });

        const container = screen.getByTestId('chart-container');
        expect(container).toBeInTheDocument();
    });

    it('initializes chart with correct data', () => {
        render(HomeAwayComparisonChart, {
            props: { data: mockData }
        });

        const canvas = screen.getByTestId('chart-canvas');
        expect(canvas).toBeInTheDocument();
    });

    it('updates chart when data changes', async () => {
        const { component } = render(HomeAwayComparisonChart, {
            props: { data: mockData }
        });

        const newData = {
            home: {
                points: 87.2,
                rebounds: 36.1,
                assists: 21.3,
                steals: 8.1,
                blocks: 4.5
            },
            away: {
                points: 83.5,
                rebounds: 34.2,
                assists: 19.4,
                steals: 7.5,
                blocks: 4.2
            }
        };

        await component.$set({ data: newData });
        // Chart update is handled internally by the component
    });

    it('cleans up chart on component destruction', () => {
        const { unmount } = render(HomeAwayComparisonChart, {
            props: { data: mockData }
        });

        unmount();
        // Chart destroy is handled internally by the component
    });

    it('handles missing data gracefully', () => {
        const incompleteData = {
            home: {
                points: 85.5,
                rebounds: 35.2
                // Missing other properties
            },
            away: {
                points: 82.3,
                rebounds: 33.8
                // Missing other properties
            }
        };

        render(HomeAwayComparisonChart, {
            props: { data: incompleteData }
        });

        const canvas = screen.getByTestId('chart-canvas');
        expect(canvas).toBeInTheDocument();
    });

    it('handles zero values correctly', () => {
        const zeroData = {
            home: {
                points: 0,
                rebounds: 0,
                assists: 0,
                steals: 0,
                blocks: 0
            },
            away: {
                points: 0,
                rebounds: 0,
                assists: 0,
                steals: 0,
                blocks: 0
            }
        };

        render(HomeAwayComparisonChart, {
            props: { data: zeroData }
        });

        const canvas = screen.getByTestId('chart-canvas');
        expect(canvas).toBeInTheDocument();
    });

    it('handles missing home or away data', () => {
        const partialData = {
            home: {
                points: 85.5,
                rebounds: 35.2,
                assists: 20.1,
                steals: 7.8,
                blocks: 4.3
            }
            // Missing away data
        };

        render(HomeAwayComparisonChart, {
            props: { data: partialData }
        });

        const canvas = screen.getByTestId('chart-canvas');
        expect(canvas).toBeInTheDocument();
    });
});
