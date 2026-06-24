<script lang="ts">
    import TopBar from "$lib/layouts/components/TopBar.svelte";
    import Footer from "$lib/layouts/components/Footer.svelte";
    import LeftSideBar from "$lib/layouts/components/LeftSideBar.svelte";
    import MobileBottomNav from "$lib/layouts/components/MobileBottomNav.svelte";
    import RightSideBar from "$lib/layouts/components/RightSideBar.svelte";
    import { onMount } from 'svelte';
    import { initLayout } from '$lib/stores/layout';

    let isRightSideBarOpen = false;

    onMount(() => {
        document.documentElement.classList.add('ds-shell-ready');
        initLayout();
    });
</script>

<div class="wrapper ds-shell">
    <TopBar onOpenThemeSettings={() => isRightSideBarOpen = true} />
    <LeftSideBar/>
    <div class="page-content">
        <div class="ds-page">
            <slot/>
        </div>
        <Footer/>
    </div>
    <MobileBottomNav/>
    <RightSideBar bind:isRightSideBarOpen/>
</div>
