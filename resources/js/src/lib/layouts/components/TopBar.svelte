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
    import {toggleDocumentAttribute} from "$lib/helpers/layout";

    export let onOpenThemeSettings: (() => void) | undefined = undefined;

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

    const toggleLeftSideBar = () => {
        toggleDocumentAttribute('class', 'sidebar-enable')
        showBackdrop()
    }

    const showBackdrop = () => {
        const backdrop = document.createElement('div') as HTMLDivElement;
        backdrop.classList.add('offcanvas-backdrop', 'fade', 'show')
        document.body.appendChild(backdrop);
        document.body.style.overflow = 'hidden';

        backdrop.addEventListener('click', () => {
            toggleDocumentAttribute('class', '')
            document.body.removeChild(backdrop);
            document.body.style.overflow = '';
        })
    }

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
                    aria-label="Open menu"
                >
                    <DsIcon name="menu" size={22} />
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
