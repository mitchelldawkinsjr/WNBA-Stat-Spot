<script lang="ts">
    import 'simplebar'
    import LogoBox from '$lib/components/LogoBox.svelte';
    import DsIcon from '$lib/components/ui/DsIcon.svelte';
    import { page } from '$app/stores';
    import { TOP_NAV_ITEMS } from '$lib/assets/data/top-nav-items';
    import { MENU_ITEMS } from '$lib/assets/data/menu-items';
    import { layout, toggleTheme } from '$lib/stores/layout';
    import {
        Dropdown,
        DropdownItem,
        DropdownMenu,
        DropdownToggle
    } from '@sveltestrap/sveltestrap';
    import { onDestroy, onMount } from 'svelte';

    export let onOpenThemeSettings: (() => void) | undefined = undefined;

    const SIDEBAR_CLASS = 'sidebar-enable';
    const BACKDROP_ATTR = 'data-ds-sidebar-backdrop';

    let sidebarOpen = false;

    const moreLinks = MENU_ITEMS.flatMap((item) => {
        if (item.isTitle) return [];
        if (item.children) {
            return item.children.map((c) => ({ label: c.label, url: c.url }));
        }
        if (item.url && !TOP_NAV_ITEMS.some((t) => t.url === item.url)) {
            return [{ label: item.label, url: item.url }];
        }
        return [];
    });

    function getBackdrop(): HTMLElement | null {
        return document.querySelector(`[${BACKDROP_ATTR}]`);
    }

    function removeBackdrop() {
        const existing = getBackdrop();
        if (existing?.parentNode) {
            existing.parentNode.removeChild(existing);
        }
        document.body.style.overflow = '';
    }

    function closeSidebar() {
        document.documentElement.classList.remove(SIDEBAR_CLASS);
        removeBackdrop();
        sidebarOpen = false;
    }

    function openSidebar() {
        document.documentElement.classList.add(SIDEBAR_CLASS);
        if (!getBackdrop()) {
            const backdrop = document.createElement('div');
            backdrop.classList.add('offcanvas-backdrop', 'fade', 'show');
            backdrop.setAttribute(BACKDROP_ATTR, 'true');
            // Sit above page content but below fixed topbar so the close control stays clickable
            backdrop.style.zIndex = '1010';
            backdrop.addEventListener('click', closeSidebar);
            document.body.appendChild(backdrop);
            document.body.style.overflow = 'hidden';
        }
        sidebarOpen = true;
    }

    const toggleLeftSideBar = () => {
        if (document.documentElement.classList.contains(SIDEBAR_CLASS)) {
            closeSidebar();
        } else {
            openSidebar();
        }
    };

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape' && sidebarOpen) {
            closeSidebar();
        }
    }

    function handleNavClick(event: MouseEvent) {
        const target = event.target as HTMLElement | null;
        if (target?.closest?.('.main-nav a')) {
            closeSidebar();
        }
    }

    onMount(() => {
        sidebarOpen = document.documentElement.classList.contains(SIDEBAR_CLASS);
        document.addEventListener('keydown', handleKeydown);
        document.addEventListener('click', handleNavClick);
        return () => {
            document.removeEventListener('keydown', handleKeydown);
            document.removeEventListener('click', handleNavClick);
            closeSidebar();
        };
    });

    onDestroy(() => {
        if (typeof document !== 'undefined') {
            closeSidebar();
        }
    });

    function isActive(url: string, pathname: string): boolean {
        if (url === '/') return pathname === '/';
        return pathname === url || pathname.startsWith(url + '/');
    }
</script>

<header class="topbar ds-topbar">
    <div class="container-xxl ds-topbar__inner">
        <div class="navbar-header">
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <button
                    type="button"
                    class="topbar-button d-lg-none"
                    on:click={toggleLeftSideBar}
                    aria-label={sidebarOpen ? 'Close menu' : 'Open menu'}
                    aria-expanded={sidebarOpen}
                    aria-controls="ds-main-nav"
                >
                    <DsIcon name={sidebarOpen ? 'close' : 'menu'} size={22} />
                </button>
                <div class="ds-topbar__brand">
                    <LogoBox logoSmHeight={28} logoLgHeight={32} />
                </div>
            </div>

            <nav class="ds-topbar__nav" aria-label="Primary">
                {#each TOP_NAV_ITEMS as item}
                    <a
                        href={item.url}
                        class="ds-topbar__link"
                        class:active={isActive(item.url, $page.url.pathname)}
                    >
                        {item.label}
                    </a>
                {/each}
            </nav>

            <div class="ds-topbar__actions">
                <button
                    type="button"
                    class="topbar-button"
                    aria-label={$layout.theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
                    on:click={toggleTheme}
                >
                    <DsIcon name={$layout.theme === 'dark' ? 'light_mode' : 'dark_mode'} size={22} />
                </button>

                {#if onOpenThemeSettings}
                    <button
                        type="button"
                        class="topbar-button d-none d-md-inline-flex"
                        aria-label="Open theme settings"
                        on:click={onOpenThemeSettings}
                    >
                        <DsIcon name="palette" size={22} />
                    </button>
                {/if}

                <a href="/players" class="topbar-button d-none d-md-inline-flex" aria-label="Search players">
                    <DsIcon name="search" size={22} />
                </a>

                <Dropdown nav class="topbar-item">
                    <DropdownToggle nav>
                        <button type="button" class="topbar-button" aria-label="More navigation">
                            <DsIcon name="more_horiz" size={22} />
                        </button>
                    </DropdownToggle>
                    <DropdownMenu class="dropdown-menu dropdown-menu-end ds-more-menu">
                        <DropdownItem header>More</DropdownItem>
                        {#each moreLinks as link}
                            {#if link.url}
                                <DropdownItem href={link.url}>{link.label}</DropdownItem>
                            {/if}
                        {/each}
                    </DropdownMenu>
                </Dropdown>
            </div>
        </div>
    </div>
</header>
