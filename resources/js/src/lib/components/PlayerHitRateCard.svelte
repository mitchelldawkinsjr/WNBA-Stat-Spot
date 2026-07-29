<script lang="ts">
    import { api, type PropHitRates, type RecentGameVsLine } from '$lib/api/client';
    import HitRateStrip from '$lib/components/HitRateStrip.svelte';
    import RecentVsLineBars from '$lib/components/RecentVsLineBars.svelte';

    export let playerId: string;
    export let season: number | undefined = undefined;
    export let seasonStats: Record<string, string | null> | null = null;

    const stats = [
        { value: 'points', label: 'PTS', avgKey: 'avgPoints' },
        { value: 'rebounds', label: 'REB', avgKey: 'avgRebounds' },
        { value: 'assists', label: 'AST', avgKey: 'avgAssists' },
        { value: 'steals', label: 'STL', avgKey: 'avgSteals' },
        { value: 'blocks', label: 'BLK', avgKey: 'avgBlocks' },
    ] as const;

    const fallbackLines: Record<string, number> = {
        points: 15,
        rebounds: 6,
        assists: 4,
        steals: 1,
        blocks: 0.5,
    };

    let selectedStat: string = 'points';
    let line: number = 15;
    let lineInitialized = false;
    let hitRates: PropHitRates | null = null;
    let recentGames: RecentGameVsLine[] = [];
    let loading = false;
    let error: string | null = null;
    let loadedKey = '';
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    function defaultLineFor(stat: string): number {
        const avgKey = stats.find((s) => s.value === stat)?.avgKey;
        const raw = avgKey ? seasonStats?.[avgKey] : null;
        const n = parseFloat(String(raw ?? '0'));
        if (!n) return fallbackLines[stat] ?? 10;
        return Math.round(n * 2) / 2;
    }

    function normalizeLine(value: number): number {
        return Math.round(Math.max(0.5, Math.abs(value)) * 2) / 2;
    }

    function handleLineInput(event: Event) {
        const target = event.target as HTMLInputElement;
        const value = parseFloat(target.value);
        if (!isNaN(value)) {
            line = normalizeLine(value);
            target.value = String(line);
        }
    }

    function selectStat(stat: string) {
        selectedStat = stat;
        line = defaultLineFor(stat);
    }

    async function loadHitRates() {
        if (!playerId || line < 0.5) return;
        const key = `${playerId}:${selectedStat}:${line}:${season ?? ''}`;
        if (key === loadedKey) return;
        loadedKey = key;

        loading = true;
        error = null;
        try {
            const response = await api.wnba.predictions.getHitRates({
                player_id: playerId,
                stat: selectedStat,
                line,
                season,
            });
            if (response.success && response.data) {
                hitRates = response.data.hit_rates ?? null;
                recentGames = response.data.recent_games ?? [];
            } else {
                hitRates = null;
                recentGames = [];
                error = 'No hit rate data';
            }
        } catch (err) {
            hitRates = null;
            recentGames = [];
            error = err instanceof Error ? err.message : 'Failed to load hit rates';
            loadedKey = '';
        } finally {
            loading = false;
        }
    }

    function scheduleLoad() {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadHitRates, 250);
    }

    $: if (seasonStats && !lineInitialized) {
        line = defaultLineFor(selectedStat);
        lineInitialized = true;
    }

    $: if (playerId && line >= 0.5) {
        selectedStat;
        line;
        season;
        scheduleLoad();
    }
</script>

<div class="card h-100">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="card-title mb-0">Hit rate vs line</h5>
            <small class="text-muted">Over rate by window — same as Predictions</small>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="Stat">
                {#each stats as stat}
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        class:active={selectedStat === stat.value}
                        on:click={() => selectStat(stat.value)}
                    >
                        {stat.label}
                    </button>
                {/each}
            </div>
            <div class="input-group input-group-sm" style="width: 7.5rem;">
                <span class="input-group-text">Line</span>
                <input
                    type="number"
                    class="form-control"
                    step="0.5"
                    min="0.5"
                    value={line}
                    on:change={handleLineInput}
                />
            </div>
        </div>
    </div>
    <div class="card-body">
        {#if loading && !hitRates}
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        {:else if error && !hitRates}
            <p class="text-muted mb-0 text-center py-3">{error}</p>
        {:else if hitRates}
            <div class="d-flex flex-wrap align-items-end gap-3" class:opacity-50={loading}>
                <HitRateStrip {hitRates} />
                <RecentVsLineBars games={recentGames} />
            </div>
            <p class="text-muted small mb-0 mt-2">
                Hits = games over {line} {selectedStat.replace(/_/g, ' ')}
            </p>
        {:else}
            <p class="text-muted mb-0 text-center py-3">No hit rate data</p>
        {/if}
    </div>
</div>
