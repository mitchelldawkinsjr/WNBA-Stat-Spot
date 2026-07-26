<script lang="ts">
    /** 0–100 progress toward matchup readiness */
    export let progress = 0;

    $: clamped = Math.max(0, Math.min(100, progress));
</script>

<div class="matchup-loading" role="status" aria-live="polite" aria-busy="true">
    <div class="matchup-loading__inner">
        <div class="matchup-loading__logo" aria-hidden="true">
            <img
                src="/images/logo-dark.png"
                class="matchup-loading__logo-img matchup-loading__logo-img--dark"
                alt=""
            />
            <img
                src="/images/logo-light.png"
                class="matchup-loading__logo-img matchup-loading__logo-img--light"
                alt=""
            />
        </div>

        <p
            class="matchup-loading__word"
            style="--matchup-load-progress: {clamped}%"
            aria-label="Loading {Math.round(clamped)}%"
        >
            <span class="matchup-loading__word-base">Loading</span>
            <span class="matchup-loading__word-fill" aria-hidden="true">Loading</span>
        </p>
    </div>
</div>

<style>
    .matchup-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: min(70vh, 36rem);
        width: 100%;
        padding: 2rem 1rem;
    }

    .matchup-loading__inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.5rem;
    }

    .matchup-loading__logo {
        position: relative;
        height: 48px;
        width: auto;
    }

    .matchup-loading__logo-img {
        display: block;
        height: 48px;
        width: auto;
        object-fit: contain;
        /* Force monochrome mark */
        filter: grayscale(1) contrast(1.05);
    }

    .matchup-loading__logo-img--dark {
        display: block;
    }

    .matchup-loading__logo-img--light {
        display: none;
    }

    :global(html[data-bs-theme='dark']) .matchup-loading__logo-img--dark {
        display: none;
    }

    :global(html[data-bs-theme='dark']) .matchup-loading__logo-img--light {
        display: block;
        filter: grayscale(1) brightness(1.15);
    }

    .matchup-loading__word {
        position: relative;
        margin: 0;
        font-family: 'Hanken Grotesk', system-ui, sans-serif;
        font-size: 0.95rem;
        font-weight: 600;
        letter-spacing: 0.28em;
        text-transform: uppercase;
        line-height: 1.2;
        user-select: none;
    }

    .matchup-loading__word-base {
        color: var(--ds-text-muted);
        opacity: 0.45;
    }

    .matchup-loading__word-fill {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        color: var(--ds-primary);
        overflow: hidden;
        width: var(--matchup-load-progress, 0%);
        white-space: nowrap;
        transition: width 0.35s ease;
    }
</style>
