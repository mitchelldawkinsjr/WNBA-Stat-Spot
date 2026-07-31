<script lang="ts">
    import BrandLoadingScreen from '$lib/components/BrandLoadingScreen.svelte';

    /** @deprecated Prefer trackPageLoad() + the global BrandLoadingScreen overlay. */
    export let progress: number | null = null;
</script>

<BrandLoadingScreen {progress} size="md" fullscreen />
