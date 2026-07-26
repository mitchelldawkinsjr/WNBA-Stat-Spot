import { redirect, type LoadEvent } from '@sveltejs/kit';

export const ssr = false;

export function load({ params, url }: LoadEvent) {
    const season = url.searchParams.get('season');
    const qs = season ? `?season=${encodeURIComponent(season)}` : '';
    throw redirect(302, `/players/${params.id}/trends${qs}`);
}
