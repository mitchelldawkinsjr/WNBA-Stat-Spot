<script lang="ts">
    import { onDestroy, onMount } from 'svelte';

    /**
     * Universal brand loading screen: logo fills grayscale → color bottom-up,
     * with a percentage counter underneath. Used for boot, route, and page loads.
     */
    export let progress: number | null = null;
    /** When true (or progress is null), climbs toward ~90% until real progress arrives */
    export let indeterminate = false;
    export let size: 'sm' | 'md' | 'lg' = 'md';
    /** Fixed overlay covering the viewport (boot / global gate) */
    export let fullscreen = false;
    /** Content-area loader with min-height (rarely needed; prefer fullscreen) */
    export let page = false;
    export let label = 'Loading';
    export let showLabel = false;
    export let src = '/images/logo-sm.png';

    let displayProgress = 8;
    let tick: ReturnType<typeof setInterval> | null = null;

    $: useIndeterminate = indeterminate || progress == null;
    $: clamped = progress == null ? displayProgress : Math.max(0, Math.min(100, progress));
    $: progressStyle = `--brand-load-progress: ${clamped}%`;
    $: percentLabel = `${Math.round(clamped)}%`;

    onMount(() => {
        if (!useIndeterminate) return;

        const reduceMotion =
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion) {
            displayProgress = 40;
            return;
        }

        tick = setInterval(() => {
            if (!useIndeterminate) return;
            const remaining = 90 - displayProgress;
            if (remaining <= 0.15) return;
            displayProgress = Math.min(90, displayProgress + Math.max(0.35, remaining * 0.07));
        }, 50);
    });

    onDestroy(() => {
        if (tick) clearInterval(tick);
    });

    $: if (!useIndeterminate && progress != null) {
        displayProgress = Math.max(0, Math.min(100, progress));
        if (tick) {
            clearInterval(tick);
            tick = null;
        }
    }
</script>

<div
    class="brand-loading brand-loading--{size}"
    class:brand-loading--page={page && !fullscreen}
    class:brand-loading--fullscreen={fullscreen}
    class:brand-loading--ready={!useIndeterminate && clamped >= 100}
    style={progressStyle}
    role="status"
    aria-live="polite"
    aria-busy="true"
    aria-label={`${label} ${percentLabel}`}
>
    <div class="brand-loading__inner">
        <div class="brand-loading__logo" aria-hidden="true">
            <img class="brand-loading__img brand-loading__img--bw" {src} alt="" />
            <div class="brand-loading__color">
                <img class="brand-loading__img" {src} alt="" />
            </div>
        </div>
        <p class="brand-loading__percent" aria-hidden="true">{percentLabel}</p>
        {#if showLabel}
            <p class="brand-loading__label">{label}</p>
        {/if}
    </div>
</div>
