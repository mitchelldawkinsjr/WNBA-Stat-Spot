<script lang="ts">
    const logoSm = "/images/logo-sm.png";
    const logoDark = "/images/logo-dark.png";

    /** Compact mark for sidebar / mobile */
    export let logoSmHeight: number = 28;
    /** Full wordmark */
    export let logoLgHeight: number = 36;
    /** When true, only render the compact mark (sidebar collapsed) */
    export let compact: boolean = false;
</script>

<div class="logo-box">
    {#if compact}
        <a href="/" class="logo-dark" aria-label="WNBA Stat Spot home">
            <img src={logoSm} class="logo-sm" height={logoSmHeight} alt="WNBA Stat Spot"/>
        </a>
        <a href="/" class="logo-light" aria-label="WNBA Stat Spot home">
            <img src={logoSm} class="logo-sm" height={logoSmHeight} alt="WNBA Stat Spot"/>
        </a>
    {:else}
        <a href="/" class="logo-dark" aria-label="WNBA Stat Spot home">
            <img src={logoSm} class="logo-sm logo-box__sm" height={logoSmHeight} alt="WNBA Stat Spot"/>
            <img src={logoDark} class="logo-lg logo-box__lg" height={logoLgHeight} alt="WNBA Stat Spot"/>
        </a>
        <a href="/" class="logo-light" aria-label="WNBA Stat Spot home">
            <img src={logoSm} class="logo-sm logo-box__sm" height={logoSmHeight} alt="WNBA Stat Spot"/>
            <img src={logoDark} class="logo-lg logo-box__lg" height={logoLgHeight} alt="WNBA Stat Spot"/>
        </a>
    {/if}
</div>

<style>
    .logo-box__sm {
        display: none;
    }

    .logo-box__lg {
        display: inline-block;
    }

    @media (max-width: 767.98px) {
        .logo-box__sm {
            display: inline-block;
        }

        .logo-box__lg {
            display: none;
        }
    }
</style>
