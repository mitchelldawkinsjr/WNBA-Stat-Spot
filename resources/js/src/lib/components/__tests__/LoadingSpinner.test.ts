import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import LoadingSpinner from '../LoadingSpinner.svelte';

describe('LoadingSpinner', () => {
    it('renders with default size and color', () => {
        render(LoadingSpinner);

        const spinner = screen.getByTestId('loading-spinner');
        expect(spinner).toBeInTheDocument();
        expect(spinner).toHaveAttribute('width', '24');
        expect(spinner).toHaveAttribute('height', '24');
        expect(spinner).toHaveAttribute('stroke', '#3B82F6');
    });

    it('renders with custom size', () => {
        render(LoadingSpinner, {
            props: { size: 48 }
        });

        const spinner = screen.getByTestId('loading-spinner');
        expect(spinner).toHaveAttribute('width', '48');
        expect(spinner).toHaveAttribute('height', '48');
    });

    it('renders with custom color', () => {
        render(LoadingSpinner, {
            props: { color: '#FF0000' }
        });

        const spinner = screen.getByTestId('loading-spinner');
        expect(spinner).toHaveAttribute('stroke', '#FF0000');
    });

    it('renders with custom size and color', () => {
        render(LoadingSpinner, {
            props: {
                size: 32,
                color: '#00FF00'
            }
        });

        const spinner = screen.getByTestId('loading-spinner');
        expect(spinner).toHaveAttribute('width', '32');
        expect(spinner).toHaveAttribute('height', '32');
        expect(spinner).toHaveAttribute('stroke', '#00FF00');
    });
});
