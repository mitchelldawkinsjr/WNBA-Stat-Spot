<script lang="ts">
    import 'simplebar'
    import Icon from '@iconify/svelte';
    import LogoBox from '$lib/components/LogoBox.svelte';
    import { page } from '$app/stores';
    import { TOP_NAV_ITEMS } from '$lib/assets/data/top-nav-items';
    import {
        layout, setLeftSideBarSize, setTheme
    } from "$lib/stores/layout";
    import {
        Dropdown,
        DropdownItem,
        DropdownMenu,
        DropdownToggle
    } from '@sveltestrap/sveltestrap';
    import {toggleDocumentAttribute} from "$lib/helpers/layout";
    import RightSideBar from "$lib/layouts/components/RightSideBar.svelte";

    const avatar1 = "/images/users/avatar-1.jpg"

    let currentTheme: 'light' | 'dark';
    let currentLeftSideBarSize: 'sm-hover-active' | 'sm-hover' | 'hidden' | 'condensed' | 'default';

    $: {
        const {theme, leftSideBarSize} = $layout;
        currentTheme = theme;
        currentLeftSideBarSize = leftSideBarSize;
    }

    let isRightSideBarOpen: boolean = false

    const toggleTheme = () => {
        if (currentTheme === 'light') {
            return setTheme('dark')
        }
        return setTheme('light')
    }

    const toggleLeftSideBar = () => {
        if (currentLeftSideBarSize === 'default') {
            return setLeftSideBarSize('condensed')
        }
        if (currentLeftSideBarSize === 'condensed') {
            return setLeftSideBarSize('default')
        }
        toggleDocumentAttribute('class', 'sidebar-enable')
        showBackdrop()
    }

    const showBackdrop = () => {
        let backdrop = document.createElement('div') as HTMLDivElement;
        if (backdrop) {
            backdrop.classList.add("offcanvas-backdrop", "fade", "show")
            document.body.appendChild(backdrop);
            document.body.style.overflow = "hidden";
            if (window.innerWidth > 1040) {
                document.body.style.paddingRight = "15px";
            }

            backdrop.addEventListener('click', function () {
                toggleDocumentAttribute('class', '')
                document.body.removeChild(backdrop);
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            })
        }
    }

    const profileMenuItems = [
        { key: 'advanced', label: 'Advanced Analytics', icon: 'bx-brain', url: '/advanced' },
        { key: 'prop-scanner', label: 'Prop Scanner', icon: 'bx-radar', url: '/advanced/prop-scanner' },
        { key: 'predictions', label: 'Predictions', icon: 'bx-crystal-ball', url: '/reports/predictions' },
    ];

    function isActive(url: string, pathname: string): boolean {
        if (url === '/') return pathname === '/';
        return pathname === url || pathname.startsWith(url + '/');
    }
</script>

<header class="topbar ds-topbar">
    <div class="container-xxl ds-topbar__inner">
        <div class="navbar-header">
            <div class="d-flex align-items-center gap-2">
                <div class="topbar-item d-xl-none">
                    <button type="button" class="button-toggle-menu topbar-button" on:click={toggleLeftSideBar} aria-label="Open menu">
                        <Icon icon="iconamoon:menu-burger-horizontal" class="fs-22" />
                    </button>
                </div>
                <div class="ds-topbar__brand">
                    <LogoBox logoSmHeight={28} logoLgHeight={36} />
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
                <div class="topbar-item">
                    <button type="button" class="topbar-button" on:click={toggleTheme} aria-label="Toggle theme">
                        <Icon icon="iconamoon:mode-dark-duotone" class="fs-24 align-middle" />
                    </button>
                </div>

                <div class="topbar-item">
                    <button type="button" class="topbar-button" on:click={() => isRightSideBarOpen = !isRightSideBarOpen} aria-label="Settings">
                        <Icon icon="iconamoon:settings-duotone" class="fs-24 align-middle"/>
                    </button>
                </div>

                <Dropdown nav class="topbar-item">
                    <DropdownToggle nav>
                        <a href="null" type="button" class="topbar-button" aria-label="Profile menu">
                            <span class="d-flex align-items-center">
                                <img class="rounded-circle" width="32" src={avatar1} alt="" />
                            </span>
                        </a>
                    </DropdownToggle>

                    <DropdownMenu class="dropdown-menu dropdown-menu-end">
                        <DropdownItem header>WNBA Analytics</DropdownItem>
                        {#each profileMenuItems as item}
                            <DropdownItem href={item.url}>
                                <i class={`bx text-muted fs-18 align-middle me-1 ${item.icon}`}></i>
                                <span class="align-middle">{item.label}</span>
                            </DropdownItem>
                        {/each}
                        <DropdownItem divider class="my-1"/>
                        <DropdownItem href="/reports">
                            <i class="bx bx-file fs-18 align-middle me-1"></i>
                            <span class="align-middle">All Reports</span>
                        </DropdownItem>
                    </DropdownMenu>
                </Dropdown>
            </div>
        </div>
    </div>
</header>

<RightSideBar {isRightSideBarOpen}/>
