<script lang="ts">
    import LogoBox from "$lib/components/LogoBox.svelte";
    import AppMenu from "$lib/components/AppMenu/index.svelte"
    import {getMenuItems} from "$lib/helpers/menu";
    import 'simplebar'
</script>

<div class="main-nav" id="ds-main-nav" aria-label="Site menu">
    <LogoBox/>

    <div data-simplebar class="scrollbar">
        <AppMenu menuItems={getMenuItems()}/>
    </div>
</div>
