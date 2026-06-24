<script lang="ts">
    export let title: string;
    export let subtitle: string = '';
    export let label: string = '';
</script>

<div class="page-title-box d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        {#if label}
            <p class="ds-section-label mb-1">{label}</p>
        {/if}
        <h1 class="page-title mb-0">{title}</h1>
        {#if subtitle}
            <p class="text-muted mb-0 mt-1">{subtitle}</p>
        {/if}
    </div>
    {#if $$slots.actions}
        <div class="page-title-right">
            <slot name="actions" />
        </div>
    {/if}
</div>
