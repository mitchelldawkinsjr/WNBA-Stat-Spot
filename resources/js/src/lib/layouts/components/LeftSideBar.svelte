<script lang="ts">
    import LogoBox from "$lib/components/LogoBox.svelte";
    import AppMenu from "$lib/components/AppMenu/index.svelte"
    import {MENU_ITEMS} from "$lib/assets/data/menu-items";
</script>

<div class="main-nav" id="ds-main-nav" aria-label="Site menu">
    <LogoBox/>

    <div class="scrollbar">
        <AppMenu menuItems={MENU_ITEMS}/>
    </div>
</div>
