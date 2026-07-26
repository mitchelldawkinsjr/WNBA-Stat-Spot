<script lang="ts">
    import {onMount} from "svelte";
    import {page} from '$app/stores';
    import GoogleAnalytics from '$lib/components/GoogleAnalytics.svelte';
    import InstallPrompt from '$lib/components/InstallPrompt.svelte';

    const favicon = '/favicon.ico';

    // Using reference-based SCSS imports
    import '$lib/assets/scss/app-reference.scss'
    import '$lib/assets/scss/icons.scss'

    // Re-enabling layout store
    import {initLayout} from "$lib/stores/layout";

    onMount(() => {
        initLayout()

        if (!('serviceWorker' in navigator)) return;

        const host = window.location.hostname;
        if (host === 'localhost' || host === '127.0.0.1') return;

        const register = async () => {
            try {
                await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            } catch (error) {
                console.warn('Service worker registration failed', error);
            }
        };

        if (document.readyState === 'complete') {
            register();
        } else {
            window.addEventListener('load', register, { once: true });
        }
    })
</script>

<svelte:head>
    <title>WNBA Stat Spot | Your Ultimate WNBA Statistics Dashboard</title>
    <meta
            name="description"
            content="WNBA Stat Spot - Your ultimate destination for WNBA statistics, player analytics, team data, and game insights. Comprehensive basketball analytics dashboard."
    />
    <meta name="author" content="WNBA Stat Spot"/>
    <meta name="keywords" content="WNBA, basketball, statistics, analytics, players, teams, games, dashboard"/>

    <meta name="theme-color" content="#ff6c2f"/>
    <meta name="mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="default"/>
    <meta name="apple-mobile-web-app-title" content="WNBA Stats"/>
    <meta name="application-name" content="WNBA Stat Spot"/>

    <link rel="manifest" href="/manifest.json"/>
    <link rel="shortcut icon" href={favicon}/>
    <link rel="apple-touch-icon" href="/icons/icon-180.png" sizes="180x180"/>
    <link rel="apple-touch-icon" href="/icons/icon-152.png" sizes="152x152"/>
    <link rel="apple-touch-icon" href="/icons/icon-144.png" sizes="144x144"/>
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png"/>
    <link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512.png"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</svelte:head>

<GoogleAnalytics/>
<InstallPrompt/>

{#key $page.url.pathname}
    <slot/>
{/key}
