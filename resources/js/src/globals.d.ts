// Ambient module declarations for optional runtime dependencies.

interface Window {
    dataLayer: unknown[];
    gtag?: (...args: unknown[]) => void;
}
