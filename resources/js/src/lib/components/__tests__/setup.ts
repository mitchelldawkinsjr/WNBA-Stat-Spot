import { vi, beforeEach, afterEach } from 'vitest';
import '@testing-library/jest-dom';

// Mock environment variables
vi.mock('$env/dynamic/public', () => ({
    env: {
        PUBLIC_API_URL: 'http://localhost:8000/api'
    }
}));

// Mock SvelteKit stores and navigation
vi.mock('$app/environment', () => ({
    browser: true,
    dev: true
}));

vi.mock('$app/stores', () => ({
    page: {
        subscribe: vi.fn()
    }
}));

vi.mock('$app/navigation', () => ({
    goto: vi.fn(),
    invalidate: vi.fn(),
    invalidateAll: vi.fn()
}));

// Mock Chart.js (must match `$lib/chart/register` import path)
vi.mock('$lib/chart/register', () => ({
    Chart: vi.fn(() => ({
        destroy: vi.fn(),
        update: vi.fn(),
        resize: vi.fn(),
        toBase64Image: vi.fn(() => 'data:image/png;base64,mock'),
        data: {},
        options: {}
    }))
}));

// Mock API client
const mockApiResponse = {
    data: [],
    meta: {
        current_page: 1,
        last_page: 1,
        per_page: 100,
        total: 0
    }
};

global.fetch = vi.fn(() =>
    Promise.resolve({
        ok: true,
        json: () => Promise.resolve(mockApiResponse),
        headers: new Headers(),
        status: 200,
        statusText: 'OK'
    } as Response)
);

// Setup and cleanup for each test
beforeEach(() => {
    // Clear all mocks before each test
    vi.clearAllMocks();
    
    // Mock console methods to reduce noise in tests
    vi.spyOn(console, 'log').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
    // Restore console methods after each test
    vi.restoreAllMocks();
});

// Custom matchers for Svelte testing
expect.extend({
    toBeInTheDocument: (received) => {
        const pass = received !== null && received !== undefined;
        return {
            message: () => `expected element ${pass ? 'not ' : ''}to be in the document`,
            pass
        };
    }
});