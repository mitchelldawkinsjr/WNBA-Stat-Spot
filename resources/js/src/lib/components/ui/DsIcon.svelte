<script lang="ts">
    /** Google Material Symbols Outlined icon name */
    export let name: string;
    export let size: number = 24;
    export let filled: boolean = false;
    export let className: string = '';
</script>

<span
    class="material-symbols-outlined {className}"
    style="font-size: {size}px; font-variation-settings: 'FILL' {filled ? 1 : 0}, 'wght' 400, 'GRAD' 0, 'opsz' 24;"
    aria-hidden="true"
>{name}</span>
