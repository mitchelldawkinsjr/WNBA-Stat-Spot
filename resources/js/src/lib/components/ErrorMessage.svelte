<script lang="ts">
    export let message: string;
</script>

<div class="alert alert-danger ds-error-message" role="alert" data-testid="error-container">
    <div class="ds-error-message__row">
        <svg
            class="ds-error-message__icon"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
            data-testid="error-icon"
        >
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <p class="ds-error-message__text mb-0">{message}</p>
    </div>
</div>

<style>
    .ds-error-message {
        max-width: 100%;
    }

    .ds-error-message__row {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .ds-error-message__icon {
        width: 1.25rem;
        height: 1.25rem;
        flex-shrink: 0;
        margin-top: 0.1rem;
    }

    .ds-error-message__text {
        min-width: 0;
        word-break: break-word;
        font-size: 0.875rem;
    }
</style>
