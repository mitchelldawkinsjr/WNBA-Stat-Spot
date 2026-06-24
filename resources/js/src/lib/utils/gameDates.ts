import type { Game } from '$lib/api/client';

export const WNBA_TIMEZONE = 'America/New_York';

const LIVE_STATUS_PATTERN = /IN_PROGRESS|HALFTIME|END_PERIOD|LIVE/i;
const FINAL_STATUS_PATTERN = /FINAL|COMPLETED|POSTPONED|CANCEL/i;

/** Calendar date for a game in US Eastern (WNBA schedule day). */
export function gameEtDate(game: Pick<Game, 'game_date' | 'game_date_time' | 'game_date_et'>): string {
    if (game.game_date_et) {
        return game.game_date_et;
    }
    const raw = game.game_date_time || game.game_date;
    if (!raw) {
        return '';
    }
    return new Date(raw).toLocaleDateString('en-CA', { timeZone: WNBA_TIMEZONE });
}

export function todayEtDate(): string {
    return new Date().toLocaleDateString('en-CA', { timeZone: WNBA_TIMEZONE });
}

export function isGameLive(game: Pick<Game, 'status_name'>): boolean {
    const status = game.status_name ?? '';
    if (FINAL_STATUS_PATTERN.test(status)) {
        return false;
    }
    return LIVE_STATUS_PATTERN.test(status);
}

export function isGameFinal(game: Pick<Game, 'status_name'>): boolean {
    return FINAL_STATUS_PATTERN.test(game.status_name ?? '');
}

export function isGameTodayEt(game: Pick<Game, 'game_date' | 'game_date_time' | 'game_date_et'>): boolean {
    return gameEtDate(game) === todayEtDate();
}

export function sortGamesForToday<T extends Pick<Game, 'game_date' | 'game_date_time' | 'game_date_et' | 'status_name'>>(games: T[]): T[] {
    return [...games].sort((a, b) => {
        const liveDiff = Number(isGameLive(b)) - Number(isGameLive(a));
        if (liveDiff !== 0) {
            return liveDiff;
        }
        const aTime = new Date(a.game_date_time || a.game_date || 0).getTime();
        const bTime = new Date(b.game_date_time || b.game_date || 0).getTime();
        return aTime - bTime;
    });
}
