<script lang="ts">
    export let label: string;
    export let active = false;
    export let variant: 'default' | 'primary' | 'secondary' = 'default';
</script>

<button
    type="button"
    class="ds-chip"
    class:active
    class:ds-chip--primary={variant === 'primary' || active}
    class:ds-chip--secondary={variant === 'secondary'}
    on:click
>
    <slot>{label}</slot>
</button>

<style>
    .ds-chip {
        border: none;
        background: var(--ds-surface-container-high);
        color: var(--ds-on-surface-variant);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: var(--ds-radius-pill);
        white-space: nowrap;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .ds-chip:hover {
        background: var(--ds-surface-container-highest);
        color: var(--ds-on-surface);
    }

    .ds-chip--primary,
    .ds-chip.active {
        background: var(--ds-primary);
        color: var(--ds-on-primary);
    }

    .ds-chip--secondary {
        background: var(--ds-secondary);
        color: #fff;
    }
</style>
