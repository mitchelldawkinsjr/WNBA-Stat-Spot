<script lang="ts">
    /**
     * Modular brand loading screen: logo fills from grayscale → full color
     * bottom-up, like a vertical loading bar.
     */
    export let progress: number | null = null;
    /** When true (or progress is null), runs a looping fill animation */
    export let indeterminate = false;
    export let size: 'sm' | 'md' | 'lg' = 'md';
    /** Fixed overlay covering the viewport (boot-style) */
    export let fullscreen = false;
    /** Content-area loader with min-height (default for page gates) */
    export let page = true;
    export let label = 'Loading';
    export let showLabel = false;
    /** Mark used for the fill animation */
    export let src = '/images/logo-sm.png';

    $: useIndeterminate = indeterminate || progress == null;
    $: clamped = progress == null ? 0 : Math.max(0, Math.min(100, progress));
    $: progressStyle = useIndeterminate
        ? undefined
        : `--brand-load-progress: ${clamped}%`;
</script>

<div
    class="brand-loading brand-loading--{size}"
    class:brand-loading--page={page && !fullscreen}
    class:brand-loading--fullscreen={fullscreen}
    class:brand-loading--indeterminate={useIndeterminate}
    class:brand-loading--ready={!useIndeterminate && clamped >= 100}
    style={progressStyle}
    role="status"
    aria-live="polite"
    aria-busy="true"
    aria-label={useIndeterminate ? label : `${label} ${Math.round(clamped)}%`}
>
    <div class="brand-loading__inner">
        <div class="brand-loading__logo" aria-hidden="true">
            <img class="brand-loading__img brand-loading__img--bw" {src} alt="" />
            <div class="brand-loading__color">
                <img class="brand-loading__img" {src} alt="" />
            </div>
        </div>
        {#if showLabel}
            <p class="brand-loading__label">{label}</p>
        {/if}
    </div>
</div>
