<script lang="ts">
    import {onMount} from "svelte";
    import type {ApexOptions} from "apexcharts";

    export let id: string
    export let customClass: string = ''
    export let options: ApexOptions
    export let dir = ''

    onMount(async () => {
        const ApexCharts = (await import('apexcharts')).default
        const ele = document.getElementById(id)
        if (ele) {
            const chart = new ApexCharts(ele, options);
            await chart.render();
        }
    })
</script>

<div id={id} class={`apex-charts ${customClass}`} dir={dir}></div>
