<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import CalendarDays from '@lucide/svelte/icons/calendar-days';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Search from '@lucide/svelte/icons/search';
    import AppHead from '@/components/AppHead.svelte';
    import BookingDesignPreview from '@/components/BookingDesignPreview.svelte';

    type Booking = {
        id: number;
        detailed_address: string;
        contact_number: string;
        body_parts: string;
        design_picture: string | null;
        service_date: string;
        price_range: number;
        status: string;
    };

    type BookingPageLink = {
        url: string | null;
        label: string;
        active: boolean;
    };

    type BookingPage = {
        data: Booking[];
        from: number | null;
        to: number | null;
        total: number;
        links: BookingPageLink[];
    };

    let { bookings, filters }: {
        bookings: BookingPage;
        filters: { search: string };
    } = $props();

    let search = $state('');

    $effect(() => {
        search = filters.search ?? '';
    });

    function submitSearch(event: SubmitEvent) {
        event.preventDefault();
        router.get('/history', { search }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function formatDate(value: string) {
        return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }
</script>

<AppHead title="History" />

<div class="flex min-h-full flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#b98766]">Client workspace</p>
        <h1 class="mt-2 text-3xl font-semibold text-foreground">History</h1>
    </div>

    <section class="overflow-hidden rounded-lg border border-border bg-card text-card-foreground shadow-sm" aria-label="Completed service bookings">
        <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-end">
            <form class="relative w-full sm:max-w-xs" onsubmit={submitSearch} role="search">
                <label class="sr-only" for="history-search">Search completed bookings</label>
                <input
                    id="history-search"
                    type="search"
                    bind:value={search}
                    placeholder="Search completed bookings..."
                    class="h-10 w-full rounded-md border border-input bg-background py-2 pl-3 pr-10 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                />
                <button
                    type="submit"
                    aria-label="Search completed bookings"
                    class="absolute inset-y-0 right-0 inline-flex w-10 cursor-pointer items-center justify-center text-muted-foreground transition-colors hover:text-foreground"
                >
                    <Search class="size-4" aria-hidden="true" />
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1040px] border-collapse text-left text-sm">
                <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Detailed Address</th>
                        <th class="px-4 py-3 font-semibold">Contact Number</th>
                        <th class="px-4 py-3 font-semibold">Body Parts</th>
                        <th class="px-4 py-3 font-semibold">Design Picture</th>
                        <th class="px-4 py-3 font-semibold">Service Date</th>
                        <th class="px-4 py-3 font-semibold">Price Range</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    {#each bookings.data as booking (booking.id)}
                        <tr class="transition-colors hover:bg-muted/30">
                            <td class="max-w-sm px-4 py-4 text-foreground">{booking.detailed_address}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-muted-foreground">{booking.contact_number}</td>
                            <td class="px-4 py-4 text-muted-foreground">{booking.body_parts}</td>
                            <td class="px-4 py-3">
                                {#if booking.design_picture}
                                    <BookingDesignPreview
                                        src={`/bookings/${booking.id}/design-picture`}
                                        alt={`Design for ${booking.body_parts}`}
                                    />
                                {:else}
                                    <span class="text-xs text-muted-foreground">No image</span>
                                {/if}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-muted-foreground">{formatDate(booking.service_date)}</td>
                            <td class="whitespace-nowrap px-4 py-4 font-medium text-foreground">${booking.price_range.toLocaleString('en-US')}</td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full border border-cyan-500/30 bg-cyan-500/10 px-2.5 py-1 text-xs font-medium text-cyan-700 dark:text-cyan-300">
                                    {booking.status}
                                </span>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center">
                                <CalendarDays class="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
                                <p class="mt-3 text-sm font-medium text-foreground">
                                    {search ? 'No history matches your search.' : 'No completed bookings yet.'}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {search ? 'Try another address, number, or date.' : 'Completed services will appear here.'}
                                </p>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-muted-foreground">
                {#if bookings.total > 0}
                    Showing {bookings.from}–{bookings.to} of {bookings.total} completed bookings
                {:else}
                    0 completed bookings
                {/if}
            </p>
            <nav class="flex items-center justify-end gap-1" aria-label="History pages">
                {#each bookings.links as link, index (`${index}-${link.label}`)}
                    {#if link.url}
                        <Link
                            href={link.url}
                            preserveScroll
                            aria-label={index === 0 ? 'Previous page' : index === bookings.links.length - 1 ? 'Next page' : `Page ${link.label}`}
                            aria-current={link.active ? 'page' : undefined}
                            class={`inline-flex h-9 min-w-9 items-center justify-center gap-1 rounded-md border px-2.5 text-sm transition-colors ${
                                link.active
                                    ? 'border-[#a46f4f] bg-[#a46f4f] text-white'
                                    : 'border-border text-foreground hover:bg-muted'
                            }`}
                        >
                            {#if index === 0}
                                <ChevronLeft class="size-4" aria-hidden="true" />
                            {:else if index === bookings.links.length - 1}
                                <ChevronRight class="size-4" aria-hidden="true" />
                            {:else}
                                {link.label}
                            {/if}
                        </Link>
                    {:else}
                        <span class="inline-flex h-9 min-w-9 items-center justify-center gap-1 rounded-md border border-border px-2.5 text-sm text-muted-foreground opacity-50">
                            {#if index === 0}
                                <ChevronLeft class="size-4" aria-hidden="true" />
                            {:else if index === bookings.links.length - 1}
                                <ChevronRight class="size-4" aria-hidden="true" />
                            {:else}
                                {link.label}
                            {/if}
                        </span>
                    {/if}
                {/each}
            </nav>
        </div>
    </section>
</div>
