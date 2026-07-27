<script lang="ts">
    export type RecentGameBar = {
        game_date?: string | null;
        value: number;
        over: boolean;
    };

    export let games: RecentGameBar[] | null | undefined = [];
    export let maxBars: number = 10;

    $: bars = (games ?? []).slice(0, maxBars);
    $: maxVal = Math.max(1, ...bars.map((g) => g.value));
</script>

{#if bars.length > 0}
    <div class="recent-bars" aria-label="Recent games vs line">
        {#each bars as game}
            <div
                class="bar"
                class:over={game.over}
                class:under={!game.over}
                style="height: {Math.max(12, (game.value / maxVal) * 100)}%"
                title="{game.game_date ?? 'Game'}: {game.value} ({game.over ? 'over' : 'under'})"
            ></div>
        {/each}
    </div>
{/if}

<style>
    .recent-bars {
        display: flex;
        align-items: flex-end;
        gap: 2px;
        height: 28px;
        min-width: 72px;
    }
    .bar {
        flex: 1 1 0;
        min-width: 4px;
        max-width: 8px;
        border-radius: 2px 2px 0 0;
    }
    .bar.over {
        background: #198754;
    }
    .bar.under {
        background: #dc3545;
    }
</style>
