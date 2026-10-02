<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import CalendarDays from '@lucide/svelte/icons/calendar-days';
    import CheckCheck from '@lucide/svelte/icons/check-check';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Search from '@lucide/svelte/icons/search';
    import X from '@lucide/svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import BookingDesignPreview from '@/components/BookingDesignPreview.svelte';
    import {
        Tooltip,
        TooltipContent,
        TooltipProvider,
        TooltipTrigger,
    } from '@/components/ui/tooltip';

    type Booking = {
        id: number;
        detailed_address: string;
        contact_number: string;
        body_parts: string;
        design_picture: string | null;
        service_date: string;
        price_range: number;
        status: string;
        user: {
            name: string;
            username: string;
            email: string;
        };
    };

    type PageLink = { url: string | null; label: string; active: boolean };
    type BookingPage = {
        data: Booking[];
        from: number | null;
        to: number | null;
        total: number;
        links: PageLink[];
    };

    let { bookings, filters }: {
        bookings: BookingPage;
        filters: { search: string };
    } = $props();

    let search = $state('');
    let selectedBooking = $state<Booking | null>(null);
    let isProcessing = $state(false);
    let successMessage = $state('');
    let errorMessage = $state('');

    $effect(() => {
        search = filters.search ?? '';
    });

    $effect(() => {
        if (!successMessage && !errorMessage) return;
        const timeoutId = window.setTimeout(() => {
            successMessage = '';
            errorMessage = '';
        }, 5000);
        return () => window.clearTimeout(timeoutId);
    });

    function submitSearch(event: SubmitEvent) {
        event.preventDefault();
        router.get('/schedules', { search }, {
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

    function confirmBookingDone() {
        if (!selectedBooking || isProcessing) return;

        isProcessing = true;
        router.patch(`/bookings/${selectedBooking.id}/status`, { status: 'completed' }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedBooking = null;
                successMessage = 'Booking marked as done.';
            },
            onError: () => {
                errorMessage = 'The booking could not be completed. Please try again.';
            },
            onFinish: () => {
                isProcessing = false;
            },
        });
    }

    function closeConfirmation() {
        if (isProcessing) return;
        selectedBooking = null;
        errorMessage = '';
    }
</script>

<AppHead title="Schedules" />

<div class="flex min-h-full flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#b98766]">Admin workspace</p>
        <h1 class="mt-2 text-3xl font-semibold text-foreground">Schedules</h1>
    </div>

    {#if successMessage}
        <div class="rounded-lg border border-cyan-700 bg-[#062d33] px-5 py-4 text-base font-medium text-cyan-300" role="status" aria-live="polite">
            {successMessage}
        </div>
    {/if}
    {#if errorMessage && !selectedBooking}
        <div class="rounded-lg border border-rose-700 bg-[#320d14] px-5 py-4 text-base font-medium text-rose-300" role="alert" aria-live="assertive">
            {errorMessage}
        </div>
    {/if}

    <section class="overflow-hidden rounded-lg border border-border bg-card text-card-foreground shadow-sm" aria-label="Approved service schedules">
        <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-end">
            <form class="relative w-full sm:max-w-xs" onsubmit={submitSearch} role="search">
                <label class="sr-only" for="schedule-search">Search schedules</label>
                <input
                    id="schedule-search"
                    type="search"
                    bind:value={search}
                    placeholder="Search approved bookings..."
                    class="h-10 w-full rounded-md border border-input bg-background py-2 pl-3 pr-10 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                />
                <button type="submit" aria-label="Search schedules" class="absolute inset-y-0 right-0 inline-flex w-10 cursor-pointer items-center justify-center text-muted-foreground hover:text-foreground">
                    <Search class="size-4" aria-hidden="true" />
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1240px] border-collapse text-left text-sm">
                <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Client</th>
                        <th class="px-4 py-3 font-semibold">Design Picture</th>
                        <th class="px-4 py-3 font-semibold">Service Date</th>
                        <th class="px-4 py-3 font-semibold">Price Range</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    {#each bookings.data as booking (booking.id)}
                        <tr class="hover:bg-muted/30">
                            <td class="px-4 py-4">
                                <p class="font-medium text-foreground">{booking.user.name}</p>
                                <p class="mt-1 max-w-xs text-sm text-muted-foreground">{booking.detailed_address}</p>
                                <p class="mt-1 text-sm text-muted-foreground">{booking.contact_number}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col items-start gap-2">
                                {#if booking.design_picture}
                                    <BookingDesignPreview
                                        src={`/bookings/${booking.id}/design-picture`}
                                        alt={`Design for ${booking.body_parts}`}
                                        downloadUrl={`/bookings/${booking.id}/design-picture/download`}
                                    />
                                {:else}
                                    <span class="text-xs text-muted-foreground">No image</span>
                                {/if}
                                    <span class="max-w-40 text-sm text-muted-foreground">{booking.body_parts}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-muted-foreground">{formatDate(booking.service_date)}</td>
                            <td class="whitespace-nowrap px-4 py-4 font-medium text-foreground">${booking.price_range.toLocaleString('en-US')}</td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">{booking.status}</span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-end gap-2">
                                    <TooltipProvider delayDuration={0}>
                                        <Tooltip>
                                            <TooltipTrigger>
                                                {#snippet child({ props })}
                                                    <button
                                                        {...props}
                                                        type="button"
                                                        aria-label={`Mark booking for ${booking.user.name} as done`}
                                                        class="inline-flex size-9 cursor-pointer items-center justify-center rounded-md border border-cyan-600/40 text-cyan-700 transition-colors hover:bg-cyan-600/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500"
                                                        onclick={() => (selectedBooking = booking)}
                                                    >
                                                        <CheckCheck class="size-4" aria-hidden="true" />
                                                    </button>
                                                {/snippet}
                                            </TooltipTrigger>
                                            <TooltipContent><p>Mark as Done</p></TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center">
                                <CalendarDays class="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
                                <p class="mt-3 text-sm font-medium text-foreground">{search ? 'No schedules match your search.' : 'No approved bookings yet.'}</p>
                                <p class="mt-1 text-sm text-muted-foreground">Approved bookings will appear here.</p>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-muted-foreground">
                {#if bookings.total > 0}
                    Showing {bookings.from}–{bookings.to} of {bookings.total} schedules
                {:else}
                    0 schedules
                {/if}
            </p>
            <nav class="flex items-center justify-end gap-1" aria-label="Schedule pages">
                {#each bookings.links as link, index (`${index}-${link.label}`)}
                    {#if link.url}
                        <Link
                            href={link.url}
                            preserveScroll
                            aria-label={index === 0 ? 'Previous page' : index === bookings.links.length - 1 ? 'Next page' : `Page ${link.label}`}
                            aria-current={link.active ? 'page' : undefined}
                            class={`inline-flex h-9 min-w-9 items-center justify-center gap-1 rounded-md border px-2.5 text-sm transition-colors ${link.active ? 'border-[#a46f4f] bg-[#a46f4f] text-white' : 'border-border text-foreground hover:bg-muted'}`}
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

{#if selectedBooking}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 px-4 py-6 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="schedule-confirm-title">
        <div class="w-full max-w-md rounded-lg border border-border bg-background text-foreground shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#a46f4f]">Confirm action</p>
                    <h2 id="schedule-confirm-title" class="mt-1 text-lg font-semibold">Mark this booking as done?</h2>
                </div>
                <button
                    type="button"
                    class="inline-flex size-9 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                    aria-label="Close confirmation"
                    disabled={isProcessing}
                    onclick={closeConfirmation}
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>
            <div class="space-y-4 px-5 py-4">
                {#if errorMessage}
                    <div class="rounded-md border border-rose-700 bg-[#320d14] px-4 py-3 text-sm text-rose-300" role="alert">{errorMessage}</div>
                {/if}
                <p class="text-sm leading-6 text-muted-foreground">
                    Confirm completion of {selectedBooking.user.name}'s booking for {formatDate(selectedBooking.service_date)}?
                </p>
                <div class="flex justify-end gap-2 border-t border-border pt-4">
                    <button type="button" class="h-10 cursor-pointer rounded-md border border-border px-4 text-sm font-medium hover:bg-muted disabled:opacity-50" disabled={isProcessing} onclick={closeConfirmation}>Cancel</button>
                    <button type="button" class="h-10 cursor-pointer rounded-md bg-cyan-700 px-4 text-sm font-semibold text-white hover:bg-cyan-800 disabled:opacity-50" disabled={isProcessing} onclick={confirmBookingDone}>
                        {isProcessing ? 'Updating...' : 'Mark as Done'}
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}
