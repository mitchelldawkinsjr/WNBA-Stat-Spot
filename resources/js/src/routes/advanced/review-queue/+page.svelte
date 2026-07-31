<script lang="ts">
    import { onDestroy, onMount } from 'svelte';
    import { api, type ReviewQueueItem } from '$lib/api/client';
    import DefaultLayout from '$lib/layouts/DefaultLayout.svelte';
    import PageHeader from '$lib/components/ui/PageHeader.svelte';
    import { pageLoading, trackPageLoad } from '$lib/stores/pageLoading';

    let items: ReviewQueueItem[] = [];
    let count = 0;
    let loading = true;
    let error: string | null = null;
    let resolvingId: number | null = null;
    let activeId: number | null = null;
    let reason = '';
    let selectedValue = '';
    let successMessage: string | null = null;
    let entityFilter: '' | 'player' | 'team' | 'game' = '';

    const doneLoading = trackPageLoad();
    onDestroy(doneLoading);

    onMount(() => {
        void loadQueue({ initial: true });
    });

    async function loadQueue(opts: { initial?: boolean } = {}) {
        if (!opts.initial) pageLoading.start();
        loading = true;
        error = null;
        try {
            const response = await api.wnba.dataImport.getReviewQueue({
                ...(entityFilter ? { entity_type: entityFilter } : {}),
                limit: 100,
            });
            items = response.data.items ?? [];
            count = response.data.count ?? items.length;
        } catch (e) {
            error = e instanceof Error ? e.message : 'Failed to load review queue';
            items = [];
            count = 0;
        } finally {
            loading = false;
            if (opts.initial) doneLoading();
            else pageLoading.stop();
        }
    }

    function openResolve(item: ReviewQueueItem) {
        activeId = item.id;
        reason = '';
        selectedValue = item.selected_value ?? '';
        successMessage = null;
        error = null;
    }

    function cancelResolve() {
        activeId = null;
        reason = '';
        selectedValue = '';
    }

    async function resolveItem(item: ReviewQueueItem) {
        const trimmed = reason.trim();
        if (!trimmed) {
            error = 'Resolution reason is required';
            return;
        }

        resolvingId = item.id;
        error = null;
        successMessage = null;
        try {
            await api.wnba.dataImport.resolveReviewItem(item.id, {
                resolution_reason: trimmed,
                ...(selectedValue.trim() ? { selected_value: selectedValue.trim() } : {}),
            });
            successMessage = `Resolved item #${item.id}`;
            cancelResolve();
            await loadQueue();
        } catch (e) {
            error = e instanceof Error ? e.message : 'Failed to resolve item';
        } finally {
            resolvingId = null;
        }
    }

    function presetKeepBoth(item: ReviewQueueItem) {
        openResolve(item);
        reason = 'Distinct entities / false positive; keep both rows';
        selectedValue = '';
    }

    function presetKeepEntity(item: ReviewQueueItem, entityId: number | string) {
        openResolve(item);
        reason = `Keep entity ${entityId}; close duplicate finding`;
        selectedValue = String(entityId);
    }

    function fieldLabel(field: string): string {
        return field.replaceAll('_', ' ');
    }
</script>

<svelte:head>
    <title>Review Queue | WNBA Stat Spot</title>
</svelte:head>

<DefaultLayout>
    <PageHeader
        title="Review Queue"
        subtitle="Resolve entity integrity findings and data conflicts"
        label="Data quality"
    >
        <svelte:fragment slot="actions">
            <button class="btn btn-outline-primary btn-sm" type="button" on:click={() => loadQueue()} disabled={loading}>
                Refresh
            </button>
        </svelte:fragment>
    </PageHeader>

    <div class="review-queue">
        <div class="review-queue__toolbar">
            <div class="btn-group" role="group" aria-label="Filter by entity">
                <button
                    type="button"
                    class="btn btn-sm"
                    class:btn-primary={entityFilter === ''}
                    class:btn-outline-secondary={entityFilter !== ''}
                    on:click={() => { entityFilter = ''; void loadQueue(); }}
                >All</button>
                <button
                    type="button"
                    class="btn btn-sm"
                    class:btn-primary={entityFilter === 'player'}
                    class:btn-outline-secondary={entityFilter !== 'player'}
                    on:click={() => { entityFilter = 'player'; void loadQueue(); }}
                >Players</button>
                <button
                    type="button"
                    class="btn btn-sm"
                    class:btn-primary={entityFilter === 'team'}
                    class:btn-outline-secondary={entityFilter !== 'team'}
                    on:click={() => { entityFilter = 'team'; void loadQueue(); }}
                >Teams</button>
                <button
                    type="button"
                    class="btn btn-sm"
                    class:btn-primary={entityFilter === 'game'}
                    class:btn-outline-secondary={entityFilter !== 'game'}
                    on:click={() => { entityFilter = 'game'; void loadQueue(); }}
                >Games</button>
            </div>
            <p class="ds-text-muted mb-0 small">{count} open item{count === 1 ? '' : 's'}</p>
        </div>

        {#if error}
            <div class="alert alert-danger" role="alert">{error}</div>
        {/if}
        {#if successMessage}
            <div class="alert alert-success" role="alert">{successMessage}</div>
        {/if}

        {#if loading && items.length === 0}
            <!-- Global BrandLoadingScreen covers the viewport -->
        {:else if items.length === 0}
            <div class="card">
                <div class="card-body">
                    <p class="ds-text-muted mb-0">Review queue is clear. No open findings.</p>
                </div>
            </div>
        {:else}
            <div class="review-queue__list">
                {#each items as item (item.id)}
                    <article class="card review-queue__item">
                        <div class="card-body">
                            <div class="review-queue__item-head">
                                <div>
                                    <p class="ds-section-label mb-1">#{item.id} · {item.entity_type} · {fieldLabel(item.field)}</p>
                                    <h2 class="ds-headline-sm mb-1">
                                        {item.resolution_reason || item.field}
                                    </h2>
                                    <p class="ds-text-muted small mb-0">
                                        Key <code>{item.entity_key}</code>
                                        {#if item.created_at}
                                            · {new Date(item.created_at).toLocaleString()}
                                        {/if}
                                    </p>
                                </div>
                                <div class="review-queue__actions">
                                    {#if item.field === 'possible_duplicate'}
                                        <button class="btn btn-outline-secondary btn-sm" type="button" on:click={() => presetKeepBoth(item)}>
                                            Keep both
                                        </button>
                                    {/if}
                                    <button class="btn btn-primary btn-sm" type="button" on:click={() => openResolve(item)}>
                                        Resolve
                                    </button>
                                </div>
                            </div>

                            {#if item.entities?.length}
                                <div class="review-queue__entities">
                                    {#each item.entities as entity}
                                        <div class="ds-score-card review-queue__entity">
                                            <p class="ds-section-label mb-1">DB id {entity.id}</p>
                                            <p class="fw-semibold mb-1">{entity.name ?? 'Unknown'}</p>
                                            <p class="small ds-text-muted mb-1">
                                                athlete_id <code>{entity.athlete_id ?? '—'}</code>
                                            </p>
                                            <p class="small ds-text-muted mb-2">
                                                ESPN <code>{entity.espn_athlete_id ?? '—'}</code>
                                                · Tank01 <code>{entity.tank01_player_id ?? '—'}</code>
                                                · {entity.games_count ?? 0} games
                                            </p>
                                            <div class="d-flex flex-wrap gap-2">
                                                {#if entity.profile_url}
                                                    <a class="btn btn-outline-primary btn-sm" href={entity.profile_url}>Open profile</a>
                                                {/if}
                                                {#if item.field === 'possible_duplicate'}
                                                    <button
                                                        class="btn btn-outline-secondary btn-sm"
                                                        type="button"
                                                        on:click={() => presetKeepEntity(item, entity.id)}
                                                    >
                                                        Keep this
                                                    </button>
                                                {/if}
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            {/if}

                            {#if activeId === item.id}
                                <div class="review-queue__resolve">
                                    <label class="form-label" for="reason-{item.id}">Resolution reason</label>
                                    <textarea
                                        id="reason-{item.id}"
                                        class="form-control mb-2"
                                        rows="2"
                                        bind:value={reason}
                                        placeholder="Why are you closing this finding?"
                                    ></textarea>
                                    <label class="form-label" for="selected-{item.id}">Selected value (optional)</label>
                                    <input
                                        id="selected-{item.id}"
                                        class="form-control mb-3"
                                        bind:value={selectedValue}
                                        placeholder="e.g. winning player DB id"
                                    />
                                    <div class="d-flex flex-wrap gap-2">
                                        <button
                                            class="btn btn-primary btn-sm"
                                            type="button"
                                            disabled={resolvingId === item.id}
                                            on:click={() => resolveItem(item)}
                                        >
                                            {resolvingId === item.id ? 'Saving…' : 'Confirm resolve'}
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" on:click={cancelResolve}>
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </article>
                {/each}
            </div>
        {/if}
    </div>
</DefaultLayout>

<style>
    .review-queue {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .review-queue__toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .review-queue__list {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .review-queue__item-head {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        margin-bottom: 0.85rem;
    }

    .review-queue__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        align-items: flex-start;
    }

    .review-queue__entities {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
        gap: 0.75rem;
        margin-bottom: 0.5rem;
    }

    .review-queue__entity {
        min-width: 0;
    }

    .review-queue__resolve {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--ds-border-subtle, #2f3944);
    }
</style>
