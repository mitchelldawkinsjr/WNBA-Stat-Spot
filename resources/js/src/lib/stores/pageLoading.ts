import { derived, writable } from 'svelte/store';

type PageLoadingState = {
    count: number;
    progress: number | null;
};

function createPageLoading() {
    const { subscribe, update, set } = writable<PageLoadingState>({
        count: 0,
        progress: null,
    });

    return {
        subscribe,
        /** Call synchronously at page script top so boot splash stays up through first paint. */
        start() {
            update((state) => ({ ...state, count: state.count + 1 }));
        },
        stop() {
            update((state) => {
                const count = Math.max(0, state.count - 1);
                return {
                    count,
                    progress: count === 0 ? null : state.progress,
                };
            });
        },
        setProgress(progress: number | null) {
            update((state) => ({
                ...state,
                progress:
                    progress == null ? null : Math.max(0, Math.min(100, progress)),
            }));
        },
        reset() {
            set({ count: 0, progress: null });
        },
    };
}

export const pageLoading = createPageLoading();

export const isPageLoading = derived(pageLoading, ($state) => $state.count > 0);

/**
 * Start the global brand loader for a page fetch. Call at script top;
 * invoke the returned function once when loading finishes (and from onDestroy).
 */
export function trackPageLoad(): () => void {
    pageLoading.start();
    let stopped = false;

    return () => {
        if (stopped) return;
        stopped = true;
        pageLoading.stop();
    };
}
