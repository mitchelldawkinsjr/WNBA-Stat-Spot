<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import { createEventDispatcher } from 'svelte';
    import { api } from '$lib/api/client';
    import { debounce } from '$lib/helpers/others';

    const dispatch = createEventDispatcher();

    export let placeholder = 'Search players, teams, or stats...';
    export let minSearchLength = 2;
    export let maxResults = 8;
    export let showFilters = false;

    let searchTerm = '';
    let searchResults: SearchResult[] = [];
    let loading = false;
    let showDropdown = false;
    let searchInput: HTMLInputElement;
    let selectedIndex = -1;
    let searchContainer: HTMLElement;

    interface SearchResult {
        id: string;
        type: 'player' | 'team' | 'game' | 'stat';
        title: string;
        subtitle?: string;
        image?: string;
        url: string;
        metadata?: {
            position?: string;
            team?: string;
            conference?: string;
            stats?: string;
        };
    }

    // Debounced search function
    const debouncedSearch = debounce(async (term: string) => {
        if (term.length < minSearchLength) {
            searchResults = [];
            showDropdown = false;
            return;
        }

        loading = true;
        try {
            const results = await performSearch(term);
            searchResults = results.slice(0, maxResults);
            showDropdown = searchResults.length > 0;
        } catch (error) {
            console.error('Search failed:', error);
            searchResults = [];
            showDropdown = false;
        } finally {
            loading = false;
        }
    }, 300);

    async function performSearch(term: string): Promise<SearchResult[]> {
        const results: SearchResult[] = [];

        // Search players
        try {
            const playersResponse = await api.players.getAll({
                search: term,
                per_page: maxResults / 2
            });

            const playerResults = playersResponse.data.data.map((player: any) => ({
                id: `player-${player.id}`,
                type: 'player' as const,
                title: player.athlete_display_name,
                subtitle: `${player.athlete_position_name || 'Player'}`,
                image: player.athlete_headshot_href,
                url: `/players/${player.id}`,
                metadata: {
                    position: player.athlete_position_abbreviation,
                    team: player.team_name
                }
            }));

            results.push(...playerResults);
        } catch (error) {
            console.warn('Player search failed:', error);
        }

        // Search teams
        try {
            const teamsResponse = await api.teams.getAll({ search: term });

            const teamResults = teamsResponse.data.data.slice(0, maxResults / 4).map((team: any) => ({
                id: `team-${team.id}`,
                type: 'team' as const,
                title: team.team_display_name,
                subtitle: team.team_location,
                url: `/teams/${team.team_id}`,
                metadata: {
                    conference: team.conference
                }
            }));

            results.push(...teamResults);
        } catch (error) {
            console.warn('Team search failed:', error);
        }

        // Sort results by relevance (exact matches first)
        return results.sort((a, b) => {
            const aExact = a.title.toLowerCase().includes(term.toLowerCase());
            const bExact = b.title.toLowerCase().includes(term.toLowerCase());

            if (aExact && !bExact) return -1;
            if (!aExact && bExact) return 1;

            // Prioritize players over teams
            if (a.type === 'player' && b.type === 'team') return -1;
            if (a.type === 'team' && b.type === 'player') return 1;

            return 0;
        });
    }

    function handleInput(event: Event) {
        const target = event.target as HTMLInputElement;
        searchTerm = target.value;
        selectedIndex = -1;

        if (searchTerm.trim()) {
            debouncedSearch(searchTerm.trim());
        } else {
            searchResults = [];
            showDropdown = false;
        }
    }

    function handleKeydown(event: KeyboardEvent) {
        if (!showDropdown) return;

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, searchResults.length - 1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                break;
            case 'Enter':
                event.preventDefault();
                if (selectedIndex >= 0 && searchResults[selectedIndex]) {
                    selectResult(searchResults[selectedIndex]);
                } else if (searchResults.length > 0) {
                    selectResult(searchResults[0]);
                }
                break;
            case 'Escape':
                closeDropdown();
                break;
        }
    }

    function selectResult(result: SearchResult) {
        dispatch('select', result);
        searchTerm = '';
        searchResults = [];
        showDropdown = false;
        selectedIndex = -1;

        // Navigate to the result URL
        window.location.href = result.url;
    }

    function closeDropdown() {
        showDropdown = false;
        selectedIndex = -1;
        searchInput?.blur();
    }

    function handleClickOutside(event: MouseEvent) {
        if (searchContainer && !searchContainer.contains(event.target as Node)) {
            closeDropdown();
        }
    }

    function getResultIcon(type: string): string {
        switch (type) {
            case 'player': return 'mdi-account-circle';
            case 'team': return 'mdi-shield-star';
            case 'game': return 'mdi-basketball';
            case 'stat': return 'mdi-chart-line';
            default: return 'mdi-magnify';
        }
    }

    onMount(() => {
        document.addEventListener('click', handleClickOutside);
    });

    onDestroy(() => {
        document.removeEventListener('click', handleClickOutside);
    });
</script>

<div class="global-search" bind:this={searchContainer}>
    <div class="search-input-container">
        <div class="input-group">
            <span class="input-group-text">
                <i class="mdi mdi-magnify"></i>
            </span>
            <input
                bind:this={searchInput}
                type="text"
                class="form-control"
                {placeholder}
                bind:value={searchTerm}
                on:input={handleInput}
                on:keydown={handleKeydown}
                on:focus={() => { if (searchResults.length > 0) showDropdown = true; }}
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
            />
            {#if loading}
                <span class="input-group-text">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Searching...</span>
                    </div>
                </span>
            {/if}
        </div>

        {#if showFilters}
            <div class="search-filters mt-2">
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="searchType" id="all" checked>
                    <label class="btn btn-outline-primary" for="all">All</label>

                    <input type="radio" class="btn-check" name="searchType" id="players">
                    <label class="btn btn-outline-primary" for="players">Players</label>

                    <input type="radio" class="btn-check" name="searchType" id="teams">
                    <label class="btn btn-outline-primary" for="teams">Teams</label>
                </div>
            </div>
        {/if}
    </div>

    {#if showDropdown && searchResults.length > 0}
        <div class="search-dropdown">
            <div class="dropdown-header">
                <small class="text-muted">
                    {searchResults.length} result{searchResults.length !== 1 ? 's' : ''} for "{searchTerm}"
                </small>
            </div>

            {#each searchResults as result, index}
                <button
                    class="search-result-item {selectedIndex === index ? 'selected' : ''}"
                    on:click={() => selectResult(result)}
                >
                    <div class="result-icon">
                        {#if result.image}
                            <img src={result.image} alt={result.title} class="result-image" />
                        {:else}
                            <i class="mdi {getResultIcon(result.type)} fs-4"></i>
                        {/if}
                    </div>

                    <div class="result-content">
                        <div class="result-title">{result.title}</div>
                        {#if result.subtitle}
                            <div class="result-subtitle">{result.subtitle}</div>
                        {/if}
                        {#if result.metadata}
                            <div class="result-metadata">
                                {#if result.metadata.position}
                                    <span class="badge badge-soft-primary">{result.metadata.position}</span>
                                {/if}
                                {#if result.metadata.team}
                                    <span class="badge badge-soft-secondary">{result.metadata.team}</span>
                                {/if}
                            </div>
                        {/if}
                    </div>

                    <div class="result-type">
                        <span class="badge badge-soft-info">{result.type}</span>
                    </div>
                </button>
            {/each}

            {#if searchResults.length >= maxResults}
                <div class="dropdown-footer">
                    <small class="text-muted">Showing first {maxResults} results. Refine your search for more specific results.</small>
                </div>
            {/if}
        </div>
    {/if}
</div>

<style>
    .global-search {
        position: relative;
        width: 100%;
        max-width: 400px;
    }

    .search-input-container {
        position: relative;
    }

    .search-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e3e6f0;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        z-index: 1050;
        max-height: 400px;
        overflow-y: auto;
    }

    .dropdown-header {
        padding: 0.5rem 1rem;
        border-bottom: 1px solid #e3e6f0;
        background-color: #f8f9fa;
    }

    .search-result-item {
        width: 100%;
        padding: 0.75rem 1rem;
        border: none;
        background: none;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: background-color 0.15s ease-in-out;
        cursor: pointer;
    }

    .search-result-item:hover,
    .search-result-item.selected {
        background-color: #f8f9fa;
    }

    .result-icon {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: #e3e6f0;
    }

    .result-image {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .result-content {
        flex: 1;
        min-width: 0;
    }

    .result-title {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.25rem;
    }

    .result-subtitle {
        font-size: 0.875rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    .result-metadata {
        display: flex;
        gap: 0.25rem;
        flex-wrap: wrap;
    }

    .result-metadata .badge {
        font-size: 0.75rem;
    }

    .result-type {
        flex-shrink: 0;
    }

    .dropdown-footer {
        padding: 0.5rem 1rem;
        border-top: 1px solid #e3e6f0;
        background-color: #f8f9fa;
    }

    .search-filters {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .badge-soft-primary {
        color: #0d6efd;
        background-color: rgba(13, 110, 253, 0.1);
    }

    .badge-soft-secondary {
        color: #6c757d;
        background-color: rgba(108, 117, 125, 0.1);
    }

    .badge-soft-info {
        color: #0dcaf0;
        background-color: rgba(13, 202, 240, 0.1);
    }
</style>
