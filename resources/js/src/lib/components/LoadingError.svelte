<script lang="ts">
    export let loading: boolean = false;
    export let error: string | null = null;
    export let loadingText: string = 'Loading...';
    export let size: 'sm' | 'md' | 'lg' = 'md';
    export let showIcon: boolean = true;
    export let retryAction: (() => void) | null = null;

    const sizeClasses = {
        sm: 'spinner-border-sm',
        md: '',
        lg: 'spinner-border-lg'
    };

    const iconClasses = {
        sm: 'fs-3',
        md: 'fs-1',
        lg: 'display-4'
    };
</script>

{#if loading}
    <div class="d-flex justify-content-center align-items-center py-4">
        <div class="text-center">
            <div class="spinner-border text-primary {sizeClasses[size]}" role="status">
                <span class="visually-hidden">{loadingText}</span>
            </div>
            {#if loadingText}
                <p class="mt-2 mb-0 text-muted">{loadingText}</p>
            {/if}
        </div>
    </div>
{:else if error}
    <div class="d-flex justify-content-center align-items-center py-4">
        <div class="text-center">
            <div class="alert alert-danger" role="alert">
                {#if showIcon}
                    <i class="mdi mdi-alert-circle {iconClasses[size]} text-danger mb-2"></i>
                {/if}
                <div>
                    <strong>Error:</strong> {error}
                </div>
                {#if retryAction}
                    <button 
                        class="btn btn-outline-danger btn-sm mt-2"
                        on:click={retryAction}
                    >
                        <i class="mdi mdi-refresh me-1"></i>
                        Try Again
                    </button>
                {/if}
            </div>
        </div>
    </div>
{/if}