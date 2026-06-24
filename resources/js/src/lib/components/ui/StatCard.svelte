<script lang="ts">
    export let label: string;
    export let value: string | number;
    export let accent: 'primary' | 'success' | 'info' | 'warning' = 'primary';
</script>

<div class="ds-stat-card">
    <div class="ds-stat-card__label mb-2">{label}</div>
    <div class="ds-stat-card__value text-{accent}">{value}</div>
    {#if $$slots.footer}
        <div class="mt-2">
            <slot name="footer" />
        </div>
    {/if}
</div>
