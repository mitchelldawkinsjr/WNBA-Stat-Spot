<script lang="ts">
    import { onMount } from 'svelte';

    interface BeforeInstallPromptEvent extends Event {
        prompt(): Promise<void>;
        userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
    }

    let deferredPrompt: BeforeInstallPromptEvent | null = null;
    let dismissed = false;
    let visible = false;

    onMount(() => {
        if (typeof window === 'undefined') return;

        const standalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            // iOS Safari
            (window.navigator as Navigator & { standalone?: boolean }).standalone === true;

        if (standalone) return;

        if (localStorage.getItem('pwa-install-dismissed') === '1') {
            dismissed = true;
            return;
        }

        const handler = (event: Event) => {
            event.preventDefault();
            deferredPrompt = event as BeforeInstallPromptEvent;
            visible = true;
        };

        window.addEventListener('beforeinstallprompt', handler);
        return () => window.removeEventListener('beforeinstallprompt', handler);
    });

    async function handleInstall() {
        if (!deferredPrompt) return;
        await deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        visible = false;
        dismissed = true;
    }

    function handleDismiss() {
        dismissed = true;
        visible = false;
        deferredPrompt = null;
        try {
            localStorage.setItem('pwa-install-dismissed', '1');
        } catch {
            // ignore
        }
    }
</script>

{#if visible && deferredPrompt && !dismissed}
    <div class="pwa-install" role="banner" aria-label="Install app">
        <img src="/icons/icon-72.png" alt="" class="pwa-install__icon" width="40" height="40" />
        <div class="pwa-install__copy">
            <p class="pwa-install__title">Install WNBA Stat Spot</p>
            <p class="pwa-install__sub">Add to home screen for the full experience</p>
        </div>
        <div class="pwa-install__actions">
            <button type="button" class="btn btn-sm btn-link text-muted" aria-label="Dismiss" on:click={handleDismiss}>
                Close
            </button>
            <button type="button" class="btn btn-sm btn-primary" on:click={handleInstall}>Install</button>
        </div>
    </div>
{/if}

<style>
    .pwa-install {
        position: fixed;
        left: 1rem;
        right: 1rem;
        bottom: calc(var(--ds-bottom-nav-height, 64px) + env(safe-area-inset-bottom, 0px) + 0.75rem);
        z-index: 1020;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        background: var(--ds-surface-container, #fff);
        border: 1px solid var(--ds-border-subtle, #eaedf1);
        border-radius: 0.75rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    @media (min-width: 768px) {
        .pwa-install {
            left: auto;
            right: 1.5rem;
            bottom: 1.5rem;
            width: 22rem;
        }
    }

    .pwa-install__icon {
        flex-shrink: 0;
        border-radius: 0.5rem;
    }

    .pwa-install__copy {
        min-width: 0;
        flex: 1;
    }

    .pwa-install__title {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .pwa-install__sub {
        margin: 0.15rem 0 0;
        font-size: 0.75rem;
        color: var(--bs-secondary-color, #6c757d);
        line-height: 1.3;
    }

    .pwa-install__actions {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        flex-shrink: 0;
    }
</style>
