import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import ErrorMessage from '../ErrorMessage.svelte';

describe('ErrorMessage', () => {
    it('renders error message', () => {
        const message = 'An error occurred';
        render(ErrorMessage, {
            props: { message }
        });

        expect(screen.getByText(message)).toBeInTheDocument();
    });

    it('renders with error icon', () => {
        render(ErrorMessage, {
            props: { message: 'Test error' }
        });

        const icon = screen.getByTestId('error-icon');
        expect(icon).toBeInTheDocument();
    });

    it('renders with correct styling', () => {
        render(ErrorMessage, {
            props: { message: 'Test error' }
        });

        const container = screen.getByTestId('error-container');
        expect(container).toHaveClass('bg-red-50');
        expect(container).toHaveClass('border-red-200');
    });

    it('renders with long error message', () => {
        const longMessage = 'This is a very long error message that should wrap properly within the container and maintain proper styling and readability for the user to understand what went wrong with their request.';
        render(ErrorMessage, {
            props: { message: longMessage }
        });

        expect(screen.getByText(longMessage)).toBeInTheDocument();
    });

    it('renders with HTML-safe message', () => {
        const message = '<script>alert("XSS")</script>';
        render(ErrorMessage, {
            props: { message }
        });

        const messageElement = screen.getByText(message);
        expect(messageElement).toBeInTheDocument();
        expect(messageElement.innerHTML).toBe(message);
    });
});
