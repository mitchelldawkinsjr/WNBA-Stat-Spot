<script lang="ts">
    import TopBar from "$lib/layouts/components/TopBar.svelte";
    import Footer from "$lib/layouts/components/Footer.svelte";
    import LeftSideBar from "$lib/layouts/components/LeftSideBar.svelte";
    import MobileBottomNav from "$lib/layouts/components/MobileBottomNav.svelte";
    import { onMount } from 'svelte';
    import { setLeftSideBarSize, setTheme, setTopBarColor, setLeftSideBarColor } from '$lib/stores/layout';

    onMount(() => {
        document.documentElement.classList.add('ds-shell-ready');
        setTheme('dark');
        setTopBarColor('dark');
        setLeftSideBarColor('dark');
        setLeftSideBarSize('hidden');
    });
</script>

<div class="wrapper ds-shell">
    <TopBar/>
    <LeftSideBar/>
    <div class="page-content">
        <div class="ds-page">
            <slot/>
        </div>
        <Footer/>
    </div>
    <MobileBottomNav/>
</div>
