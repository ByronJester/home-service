<script lang="ts">
    import { page } from '@inertiajs/svelte';

    const name = $derived(page.props.name);
</script>

<img
    src="/logo.png"
    alt=""
    class="size-8 rounded-md object-cover"
/>
<div class="ml-1 grid flex-1 text-left text-sm">
    <span class="mb-0.5 truncate leading-tight font-semibold">{name}</span>
</div>
