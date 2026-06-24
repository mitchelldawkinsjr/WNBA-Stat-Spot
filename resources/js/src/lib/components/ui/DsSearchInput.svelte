<script lang="ts">
    import DsIcon from './DsIcon.svelte';

    export let value: string = '';
    export let placeholder = 'Search…';
    export let id: string | undefined = undefined;
</script>

<div class="ds-search">
    <DsIcon name="search" size={20} className="ds-search__icon" />
    <input
        {id}
        type="search"
        class="ds-search__input"
        {placeholder}
        bind:value
        on:input
        on:change
    />
</div>

<style>
    .ds-search {
        position: relative;
        width: 100%;
        max-width: 640px;
    }

    .ds-search__icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--ds-outline);
        pointer-events: none;
    }

    .ds-search__input {
        width: 100%;
        background: var(--ds-surface-container-lowest);
        border: 1px solid var(--ds-outline-variant);
        border-radius: var(--ds-radius-xl);
        padding: 12px 16px 12px 44px;
        color: var(--ds-on-surface);
        font-size: 14px;
        line-height: 21px;
        transition: border-color 0.15s ease;
    }

    .ds-search__input:focus {
        outline: none;
        border-color: var(--ds-primary);
        box-shadow: 0 0 0 2px rgba(255, 108, 47, 0.2);
    }

    .ds-search__input::placeholder {
        color: var(--ds-text-muted);
    }
</style>
