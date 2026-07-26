import { redirect, type LoadEvent } from '@sveltejs/kit';

export const ssr = false;

export function load({ params }: LoadEvent) {
    throw redirect(302, `/teams/${params.id}`);
}
