<script lang="ts">
    import {onMount} from "svelte";
    import {page} from '$app/stores';

    const favicon = '/favicon.ico';

    // Using reference-based SCSS imports
    import '$lib/assets/scss/app-reference.scss'
    import '$lib/assets/scss/icons.scss'

    // Re-enabling layout store
    import {initLayout} from "$lib/stores/layout";

    onMount(() => {
        initLayout()
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

    <link rel="shortcut icon" href={favicon}/>
    <!-- Font Awesome for additional icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</svelte:head>

{#key $page.url.pathname}
    <slot/>
{/key}
