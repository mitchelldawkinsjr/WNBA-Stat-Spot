import { writable, get } from 'svelte/store';
import { api, type Player } from '$lib/api/client';

export const AVAILABLE_SEASONS = [2026, 2025] as const;
export const DEFAULT_SEASON = 2026;

export type PlayerProfileTab = 'overview' | 'stats' | 'trends' | 'gamelog' | 'props';

export const PLAYER_PROFILE_TABS: Array<{
    key: PlayerProfileTab;
    label: string;
    path: string;
}> = [
    { key: 'overview', label: 'Overview', path: '' },
    { key: 'stats', label: 'Stats', path: '/stats' },
    { key: 'trends', label: 'Trends', path: '/trends' },
    { key: 'gamelog', label: 'Game log', path: '/gamelog' },
    { key: 'props', label: 'Props', path: '/props' },
];

export interface PlayerProfileState {
    playerId: string | null;
    player: Player | null;
    season: number;
    loading: boolean;
    error: string | null;
    seasonStats: Record<string, string | null> | null;
    news: Array<Record<string, unknown>>;
    injuries: Array<Record<string, unknown>>;
    nextGame: Record<string, unknown> | null;
    intelLoading: boolean;
}

const initialState: PlayerProfileState = {
    playerId: null,
    player: null,
    season: DEFAULT_SEASON,
    loading: false,
    error: null,
    seasonStats: null,
    news: [],
    injuries: [],
    nextGame: null,
    intelLoading: false,
};

function createPlayerProfileStore() {
    const { subscribe, set, update } = writable<PlayerProfileState>(initialState);

    async function loadIntel(playerId: string, season: number) {
        update((s) => ({ ...s, intelLoading: true }));
        try {
            const response = await api.players.getOverview(playerId, { season });
            if (response.success && response.data) {
                update((s) => ({
                    ...s,
                    seasonStats: response.data.season_stats,
                    news: response.data.news ?? [],
                    injuries: response.data.injuries ?? [],
                    nextGame: response.data.next_game,
                    intelLoading: false,
                }));
                return;
            }
        } catch {
            // fall through
        }
        update((s) => ({
            ...s,
            seasonStats: null,
            news: [],
            injuries: [],
            nextGame: null,
            intelLoading: false,
        }));
    }

    return {
        subscribe,
        reset: () => set(initialState),
        async init(playerId: string, season = DEFAULT_SEASON) {
            const current = get({ subscribe });
            if (current.playerId === playerId && current.player && current.season === season) {
                return;
            }

            update((s) => ({
                ...s,
                playerId,
                season,
                loading: true,
                error: null,
                player: current.playerId === playerId ? current.player : null,
            }));

            try {
                const response = await api.players.getById(playerId);
                update((s) => ({
                    ...s,
                    player: response.data,
                    loading: false,
                    error: null,
                }));
                await loadIntel(playerId, season);
            } catch (e) {
                update((s) => ({
                    ...s,
                    loading: false,
                    error: e instanceof Error ? e.message : 'Failed to load player',
                    player: null,
                }));
            }
        },
        async setSeason(season: number) {
            const current = get({ subscribe });
            if (!current.playerId || current.season === season) return;
            update((s) => ({ ...s, season }));
            await loadIntel(current.playerId, season);
        },
        async refreshIntel() {
            const current = get({ subscribe });
            if (!current.playerId) return;
            await loadIntel(current.playerId, current.season);
        },
    };
}

export const playerProfile = createPlayerProfileStore();

export function tabHref(playerId: string, tabPath: string, season: number): string {
    const base = `/players/${playerId}${tabPath}`;
    return `${base}?season=${season}`;
}

export function activeTabFromPath(pathname: string, playerId: string): PlayerProfileTab {
    const base = `/players/${playerId}`;
    if (pathname.endsWith('/stats') || pathname.includes(`${base}/stats`)) return 'stats';
    if (pathname.endsWith('/trends') || pathname.includes(`${base}/trends`)) return 'trends';
    if (pathname.endsWith('/gamelog') || pathname.includes(`${base}/gamelog`)) return 'gamelog';
    if (pathname.endsWith('/props') || pathname.includes(`${base}/props`)) return 'props';
    if (pathname.endsWith('/data') || pathname.includes(`${base}/data`)) return 'stats';
    if (pathname.endsWith('/analytics') || pathname.includes(`${base}/analytics`)) return 'trends';
    return 'overview';
}
