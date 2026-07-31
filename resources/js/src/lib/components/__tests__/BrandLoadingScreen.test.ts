import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/svelte';
import BrandLoadingScreen from '../BrandLoadingScreen.svelte';

describe('BrandLoadingScreen', () => {
    it('renders grayscale and color logo layers', () => {
        const { container } = render(BrandLoadingScreen, {
            props: { progress: 40 },
        });

        const images = container.querySelectorAll('img.brand-loading__img');
        expect(images.length).toBe(2);
        expect(container.querySelector('.brand-loading__img--bw')).toBeTruthy();
        expect(container.querySelector('.brand-loading__color')).toBeTruthy();
    });

    it('exposes progress on the root for the color fill', () => {
        const { container } = render(BrandLoadingScreen, {
            props: { progress: 25 },
        });

        const root = container.querySelector('.brand-loading') as HTMLElement;
        expect(root.style.getPropertyValue('--brand-load-progress')).toBe('25%');
        expect(root.getAttribute('aria-label')).toContain('25%');
    });

    it('uses indeterminate animation when progress is omitted', () => {
        const { container } = render(BrandLoadingScreen);

        expect(container.querySelector('.brand-loading--indeterminate')).toBeTruthy();
    });
});
