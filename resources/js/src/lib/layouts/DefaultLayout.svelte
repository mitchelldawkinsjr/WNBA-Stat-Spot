<script lang="ts">
    import TopBar from "$lib/layouts/components/TopBar.svelte";
    import Footer from "$lib/layouts/components/Footer.svelte";
    import LeftSideBar from "$lib/layouts/components/LeftSideBar.svelte";
    import { onMount } from 'svelte';

    onMount(() => {
        document.documentElement.classList.add('ds-shell-ready');
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
</div>
