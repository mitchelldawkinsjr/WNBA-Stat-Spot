<script lang="ts">
    export type HitRateWindow = {
        hits: number;
        games: number;
        rate: number | null;
    };

    export type HitRates = {
        l5?: HitRateWindow | null;
        l10?: HitRateWindow | null;
        l20?: HitRateWindow | null;
        season?: HitRateWindow | null;
        h2h?: HitRateWindow | null;
    };

    export let hitRates: HitRates | null | undefined = null;
    export let compact: boolean = false;

    const cells: { key: keyof HitRates; label: string }[] = [
        { key: 'l5', label: 'L5' },
        { key: 'l10', label: 'L10' },
        { key: 'l20', label: 'L20' },
        { key: 'season', label: 'SZN' },
        { key: 'h2h', label: 'H2H' }
    ];

    function rateTone(rate: number | null | undefined): string {
        if (rate === null || rate === undefined) return 'hr-none';
        const pct = rate * 100;
        if (pct >= 80) return 'hr-g3';
        if (pct >= 66) return 'hr-g2';
        if (pct >= 51) return 'hr-g1';
        if (pct === 50) return 'hr-mid';
        if (pct >= 30) return 'hr-r1';
        if (pct >= 15) return 'hr-r2';
        return 'hr-r3';
    }

    function formatCell(window: HitRateWindow | null | undefined): string {
        if (!window || window.games === 0 || window.rate === null) return '—';
        return `${Math.round(window.rate * 100)}%`;
    }

    function title(label: string, window: HitRateWindow | null | undefined): string {
        if (!window || window.games === 0) return `${label}: no games`;
        return `${label}: ${window.hits}/${window.games} overs`;
    }
</script>

{#if hitRates}
    <div class="hit-rate-strip" class:compact aria-label="Hit rates vs line">
        {#each cells as cell}
            {@const w = hitRates[cell.key]}
            {#if cell.key !== 'h2h' || w}
                <div
                    class="hr-cell {rateTone(w?.rate)}"
                    title={title(cell.label, w)}
                >
                    <span class="hr-label">{cell.label}</span>
                    <span class="hr-value">{formatCell(w)}</span>
                </div>
            {/if}
        {/each}
    </div>
{/if}

<style>
    .hit-rate-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .hr-cell {
        min-width: 2.6rem;
        padding: 2px 4px;
        border-radius: 3px;
        text-align: center;
        line-height: 1.15;
        border: 1px solid transparent;
    }
    .compact .hr-cell {
        min-width: 2.2rem;
        padding: 1px 3px;
    }
    .hr-label {
        display: block;
        font-size: 0.65rem;
        opacity: 0.85;
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .hr-value {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .compact .hr-value {
        font-size: 0.7rem;
    }
    .hr-none {
        background: var(--bs-secondary-bg, #e9ecef);
        color: var(--bs-secondary-color, #6c757d);
    }
    .hr-g3 { background: #198754; color: #fff; }
    .hr-g2 { background: #20c997; color: #053b2a; }
    .hr-g1 { background: #d1e7dd; color: #0a3622; }
    .hr-mid { background: #212529; color: #fff; }
    .hr-r1 { background: #f8d7da; color: #58151c; }
    .hr-r2 { background: #dc3545; color: #fff; }
    .hr-r3 { background: #f1aeb5; color: #58151c; }
</style>
