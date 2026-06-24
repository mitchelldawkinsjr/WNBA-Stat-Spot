<script lang="ts">
    export let variant: 'live' | 'over' | 'under' | 'neutral' = 'neutral';
    export let label: string;
</script>

<span class="badge badge-ds-{variant}">{label}</span>
