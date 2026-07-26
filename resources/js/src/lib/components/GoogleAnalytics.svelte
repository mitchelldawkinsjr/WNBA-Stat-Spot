<script lang="ts">
    import {browser} from '$app/environment';
    import {page} from '$app/stores';
    import {env} from '$env/dynamic/public';
    import {onMount} from 'svelte';

    const measurementId = env.PUBLIC_GA_MEASUREMENT_ID;

    onMount(() => {
        if (!browser || !measurementId) {
            return;
        }

        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${measurementId}`;
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];
        window.gtag = function gtag(...args: unknown[]) {
            window.dataLayer.push(args);
        };
        window.gtag('js', new Date());
        window.gtag('config', measurementId, {send_page_view: false});

        return page.subscribe(($page) => {
            window.gtag?.('event', 'page_view', {
                page_path: $page.url.pathname + $page.url.search
            });
        });
    });
</script>
