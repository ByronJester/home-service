<script lang="ts">
    import Download from '@lucide/svelte/icons/download';
    import X from '@lucide/svelte/icons/x';

    let {
        src,
        alt,
        downloadUrl = null,
    }: {
        src: string;
        alt: string;
        downloadUrl?: string | null;
    } = $props();

    let isOpen = $state(false);

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') isOpen = false;
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<button
    type="button"
    class="group relative inline-flex cursor-zoom-in rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
    aria-label={`View larger image: ${alt}`}
    onclick={() => (isOpen = true)}
>
    <img
        {src}
        {alt}
        loading="lazy"
        class="size-16 rounded-md border border-border bg-muted object-cover transition-opacity group-hover:opacity-80"
    />
</button>

{#if isOpen}
    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label={`Image preview: ${alt}`}>
        <div class="relative flex max-h-[92vh] max-w-[94vw] items-center justify-center">
            <button
                type="button"
                class="absolute -right-3 -top-3 z-10 inline-flex size-10 cursor-pointer items-center justify-center rounded-full border border-white/20 bg-black/80 text-white transition-colors hover:bg-black focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                aria-label="Close image preview"
                onclick={() => (isOpen = false)}
            >
                <X class="size-5" aria-hidden="true" />
            </button>
            {#if downloadUrl}
                <a
                    href={downloadUrl}
                    download
                    class="absolute -bottom-3 right-0 z-10 inline-flex h-10 cursor-pointer items-center gap-2 rounded-md border border-white/20 bg-black/80 px-3 text-sm font-medium text-white transition-colors hover:bg-black focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                >
                    <Download class="size-4" aria-hidden="true" />
                    Save image
                </a>
            {/if}
            <img
                {src}
                {alt}
                class="max-h-[88vh] max-w-[92vw] rounded-md object-contain shadow-2xl"
            />
        </div>
    </div>
{/if}
