<script lang="ts">
    import BrandLoadingScreen from '$lib/components/BrandLoadingScreen.svelte';

    /** 0–100 progress toward matchup readiness */
    export let progress = 0;
</script>

<BrandLoadingScreen {progress} size="md" page />
