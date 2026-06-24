<script lang="ts">
    import { page } from '$app/stores';
    import DsIcon from '$lib/components/ui/DsIcon.svelte';

    const items = [
        { href: '/', label: 'Home', icon: 'scoreboard' },
        { href: '/stats', label: 'Stats', icon: 'query_stats' },
        { href: '/teams', label: 'Teams', icon: 'groups' },
        { href: '/players', label: 'Players', icon: 'person' },
    ];

    function isActive(href: string, path: string) {
        if (href === '/') return path === '/';
        return path === href || path.startsWith(href + '/');
    }
</script>

<nav class="ds-bottom-nav d-md-none" aria-label="Mobile navigation">
    {#each items as item}
        <a
            href={item.href}
            class="ds-bottom-nav__item"
            class:active={isActive(item.href, $page.url.pathname)}
        >
            <DsIcon name={item.icon} size={22} filled={isActive(item.href, $page.url.pathname)} />
            <span>{item.label}</span>
        </a>
    {/each}
</nav>

<style>
    .ds-bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1010;
        display: flex;
        justify-content: space-around;
        align-items: center;
        height: 64px;
        padding: 0 8px env(safe-area-inset-bottom);
        background: var(--ds-surface-container);
        border-top: 1px solid var(--ds-border-subtle);
    }

    .ds-bottom-nav__item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        color: var(--ds-text-muted);
        text-decoration: none;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 6px 12px;
        border-radius: var(--ds-radius-pill);
        transition: color 0.15s ease, background 0.15s ease, transform 0.15s ease;
    }

    .ds-bottom-nav__item.active {
        color: var(--ds-on-primary);
        background: var(--ds-secondary);
        transform: translateY(-2px);
    }
</style>
