<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import CalendarDays from '@lucide/svelte/icons/calendar-days';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import X from '@lucide/svelte/icons/x';
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

    let { bookings, filters, unavailableDates }: {
        bookings: BookingPage;
        filters: { search: string };
        unavailableDates: string[];
    } = $props();

    let search = $state('');
    let isModalOpen = $state(false);
    let detailedAddress = $state('');
    let contactNumber = $state('');
    let bodyParts = $state('');
    let designPicture = $state<File | null>(null);
    let designPicturePreview = $state('');
    let serviceDate = $state('');
    let isCalendarOpen = $state(false);
    let calendarMonth = $state(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
    let priceRange = $state(100);
    let errors = $state<Record<string, string>>({});
    let successMessage = $state('');
    let validationMessage = $state('');

    const minimumPrice = 20;
    const maximumPrice = 2000;
    const weekdayLabels = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    const calendarCells = $derived.by(() => {
        const year = calendarMonth.getFullYear();
        const month = calendarMonth.getMonth();
        const firstWeekday = new Date(year, month, 1).getDay();
        const today = dateKey(new Date());

        return Array.from({ length: 42 }, (_, index) => {
            const date = new Date(year, month, index - firstWeekday + 1);
            const key = dateKey(date);

            return {
                key,
                day: date.getDate(),
                isCurrentMonth: date.getMonth() === month,
                isPast: key < today,
                isUnavailable: unavailableDates.includes(key),
            };
        });
    });

    const calendarMonthLabel = $derived(
        calendarMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }),
    );

    $effect(() => {
        search = filters.search ?? '';
    });

    $effect(() => {
        if (!designPicture) {
            designPicturePreview = '';
            return;
        }

        const previewUrl = URL.createObjectURL(designPicture);
        designPicturePreview = previewUrl;

        return () => URL.revokeObjectURL(previewUrl);
    });

    $effect(() => {
        if (!successMessage && !validationMessage) return;

        const timeoutId = window.setTimeout(() => {
            successMessage = '';
            validationMessage = '';
        }, 5000);

        return () => window.clearTimeout(timeoutId);
    });

    function submitSearch(event: SubmitEvent) {
        event.preventDefault();
        router.get('/book-a-service', { search }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function openBookingModal() {
        errors = {};
        successMessage = '';
        validationMessage = '';
        isModalOpen = true;
        router.reload({
            only: ['unavailableDates'],
        });
    }

    function closeBookingModal() {
        isModalOpen = false;
        isCalendarOpen = false;
        errors = {};
        successMessage = '';
        validationMessage = '';
    }

    function formatContactNumber(event: Event) {
        const input = event.currentTarget as HTMLInputElement;
        let digits = input.value.replace(/\D/g, '');

        if (digits.startsWith('1818')) {
            digits = digits.slice(4);
        } else if (digits.startsWith('818')) {
            digits = digits.slice(3);
        } else if (digits.startsWith('1') && digits.length === 11) {
            digits = digits.slice(1);
        }

        digits = digits.slice(0, 7);
        contactNumber = digits.length === 0
            ? ''
            : `+1 (818)-${digits.slice(0, 3)}${digits.length > 3 ? `-${digits.slice(3)}` : ''}`;
        input.value = contactNumber;
    }

    function submitBooking(event: SubmitEvent) {
        event.preventDefault();
        errors = {};
        successMessage = '';

        const nextErrors: Record<string, string> = {};
        if (!detailedAddress.trim()) {
            nextErrors.detailed_address = 'Enter your detailed address.';
        }
        if (!contactNumber.trim()) {
            nextErrors.contact_number = 'Enter your contact number.';
        } else if (!/^\+1 \(818\)-\d{3}-\d{4}$/.test(contactNumber)) {
            nextErrors.contact_number = 'Use the format +1 (818)-123-1234.';
        }
        if (!bodyParts.trim()) {
            nextErrors.body_parts = 'Enter which body part or parts the tattoo is for.';
        } else if (bodyParts.length > 255) {
            nextErrors.body_parts = 'Body parts must be 255 characters or fewer.';
        }
        if (!designPicture) {
            nextErrors.design_picture = 'Upload a design picture.';
        } else if (!['image/jpeg', 'image/png', 'image/webp'].includes(designPicture.type)) {
            nextErrors.design_picture = 'Upload a JPG, PNG, or WEBP image.';
        } else if (designPicture.size > 5 * 1024 * 1024) {
            nextErrors.design_picture = 'The design picture must be 5 MB or smaller.';
        }
        if (!serviceDate) {
            nextErrors.service_date = 'Choose a service date.';
        } else if (serviceDate < new Date().toISOString().slice(0, 10)) {
            nextErrors.service_date = 'Service date cannot be in the past.';
        } else if (unavailableDates.includes(serviceDate)) {
            nextErrors.service_date = 'That date has already been booked. Choose another date.';
        }
        if (priceRange < minimumPrice || priceRange > maximumPrice) {
            nextErrors.price_range = `Choose a price between $${minimumPrice} and $${maximumPrice}.`;
        }

        if (Object.keys(nextErrors).length > 0) {
            errors = nextErrors;
            validationMessage = 'Please correct the highlighted fields and try again.';
            return;
        }

        router.post('/book-a-service', {
            detailed_address: detailedAddress,
            contact_number: contactNumber,
            body_parts: bodyParts,
            design_picture: designPicture,
            service_date: serviceDate,
            price_range: priceRange,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                errors = {};
                validationMessage = '';
                successMessage = 'Your booking request was submitted successfully.';
                isModalOpen = false;
                detailedAddress = '';
                contactNumber = '';
                bodyParts = '';
                designPicture = null;
                serviceDate = '';
                priceRange = minimumPrice;
            },
            onError: (validationErrors) => {
                errors = validationErrors;
                validationMessage = 'Please correct the highlighted fields and try again.';
            },
        });
    }

    function formatDate(value: string) {
        return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    function dateKey(date: Date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function changeCalendarMonth(amount: number) {
        calendarMonth = new Date(
            calendarMonth.getFullYear(),
            calendarMonth.getMonth() + amount,
            1,
        );
    }

    function chooseServiceDate(date: string) {
        serviceDate = date;
        isCalendarOpen = false;
        delete errors.service_date;
        if (Object.keys(errors).length === 0) validationMessage = '';
    }

    function displayServiceDate(date: string) {
        if (!date) return 'Select a service date';

        return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

</script>

<AppHead title="Book a Service" />

<div class="flex min-h-full flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#b98766]">Client workspace</p>
        <h1 class="mt-2 text-3xl font-semibold text-foreground">Book a Service</h1>
    </div>

    {#if successMessage}
        <div class="rounded-xl border border-cyan-700 bg-[#062d33] px-5 py-4 text-lg font-medium text-cyan-300" role="status" aria-live="polite">
            {successMessage}
        </div>
    {/if}

    <section class="overflow-hidden rounded-lg border border-border bg-card text-card-foreground shadow-sm" aria-label="Your service bookings">
        <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
            <button
                type="button"
                class="inline-flex h-10 w-fit cursor-pointer items-center gap-2 rounded-md bg-[#a46f4f] px-4 text-sm font-semibold text-white transition-colors hover:bg-[#8d5c40] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a] focus-visible:ring-offset-2"
                onclick={openBookingModal}
            >
                <Plus class="size-4" aria-hidden="true" />
                Book Service
            </button>

            <form class="relative w-full sm:max-w-xs" onsubmit={submitSearch} role="search">
                <label class="sr-only" for="booking-search">Search bookings</label>
                <input
                    id="booking-search"
                    type="search"
                    bind:value={search}
                    placeholder="Search bookings..."
                    class="h-10 w-full rounded-md border border-input bg-background py-2 pl-3 pr-10 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                />
                <button
                    type="submit"
                    aria-label="Search bookings"
                    class="absolute inset-y-0 right-0 inline-flex w-10 cursor-pointer items-center justify-center text-muted-foreground transition-colors hover:text-foreground"
                >
                    <Search class="size-4" aria-hidden="true" />
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse text-left text-sm">
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
                                <span class="inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300">
                                    {booking.status}
                                </span>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center">
                                <CalendarDays class="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
                                <p class="mt-3 text-sm font-medium text-foreground">
                                    {search ? 'No bookings match your search.' : 'No bookings yet.'}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {search ? 'Try another address, number, or date.' : 'Create a booking request to see it listed here.'}
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
                    Showing {bookings.from}–{bookings.to} of {bookings.total} bookings
                {:else}
                    0 bookings
                {/if}
            </p>
            <nav class="flex items-center justify-end gap-1" aria-label="Booking pages">
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

{#if isModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/65 px-4 py-6 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="booking-modal-title">
        <div class="my-auto w-full max-w-xl rounded-lg border border-border bg-background text-foreground shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#a46f4f]">New request</p>
                    <h2 id="booking-modal-title" class="mt-1 text-xl font-semibold">Book Service</h2>
                </div>
                <button
                    type="button"
                    class="inline-flex size-9 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                    aria-label="Close booking form"
                    onclick={closeBookingModal}
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>

            <form class="space-y-5 px-6 py-5" novalidate onsubmit={submitBooking}>
                {#if validationMessage}
                    <div class="rounded-xl border border-rose-700 bg-[#320d14] px-5 py-4 text-lg font-medium text-rose-300" role="alert" aria-live="assertive">
                        <p>{validationMessage}</p>
                        {#if errors.detailed_address}<p class="mt-1 text-sm">{errors.detailed_address}</p>{/if}
                        {#if errors.contact_number}<p class="mt-1 text-sm">{errors.contact_number}</p>{/if}
                        {#if errors.body_parts}<p class="mt-1 text-sm">{errors.body_parts}</p>{/if}
                        {#if errors.design_picture}<p class="mt-1 text-sm">{errors.design_picture}</p>{/if}
                        {#if errors.service_date}<p class="mt-1 text-sm">{errors.service_date}</p>{/if}
                        {#if errors.price_range}<p class="mt-1 text-sm">{errors.price_range}</p>{/if}
                    </div>
                {/if}

                <div>
                    <label for="detailed-address" class="mb-1.5 block text-sm font-medium">Detailed Address</label>
                    <textarea
                        id="detailed-address"
                        bind:value={detailedAddress}
                        required
                        maxlength="2000"
                        rows="3"
                        autocomplete="street-address"
                        placeholder="House number, street, barangay, city, and directions"
                        class="w-full resize-y rounded-md border border-input bg-background px-3 py-2.5 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                    ></textarea>
                    {#if errors.detailed_address}<p class="mt-1.5 text-sm text-destructive">{errors.detailed_address}</p>{/if}
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="contact-number" class="mb-1.5 block text-sm font-medium">Contact Number</label>
                        <input
                            id="contact-number"
                            type="tel"
                            value={contactNumber}
                            oninput={formatContactNumber}
                            required
                            maxlength="19"
                            autocomplete="tel"
                            inputmode="tel"
                            placeholder="+1 (818)-123-1234"
                            aria-describedby="contact-number-format"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                        />
                        <p id="contact-number-format" class="mt-1 text-xs text-muted-foreground">Format: +1 (818)-123-1234</p>
                        {#if errors.contact_number}<p class="mt-1.5 text-sm text-destructive">{errors.contact_number}</p>{/if}
                    </div>
                    <div>
                        <label for="service-date" class="mb-1.5 block text-sm font-medium">Service Date</label>
                        <div class="relative">
                            <button
                                id="service-date"
                                type="button"
                                aria-haspopup="dialog"
                                aria-expanded={isCalendarOpen}
                                class="flex h-10 w-full cursor-pointer items-center justify-between gap-3 rounded-md border border-input bg-background px-3 text-left text-sm outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                                onclick={() => (isCalendarOpen = !isCalendarOpen)}
                            >
                                <span class={serviceDate ? 'text-foreground' : 'text-muted-foreground'}>{displayServiceDate(serviceDate)}</span>
                                <CalendarDays class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            </button>

                            {#if isCalendarOpen}
                                <div class="absolute right-0 z-20 mt-2 w-[min(20rem,calc(100vw-3rem))] rounded-md border border-border bg-background p-3 text-foreground shadow-xl" role="dialog" aria-label="Choose service date">
                                    <div class="mb-3 flex items-center justify-between">
                                        <button
                                            type="button"
                                            aria-label="Previous month"
                                            class="inline-flex size-8 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                            disabled={calendarMonth.getFullYear() === new Date().getFullYear() && calendarMonth.getMonth() <= new Date().getMonth()}
                                            onclick={() => changeCalendarMonth(-1)}
                                        >
                                            <ChevronLeft class="size-4" aria-hidden="true" />
                                        </button>
                                        <p class="text-sm font-semibold">{calendarMonthLabel}</p>
                                        <button
                                            type="button"
                                            aria-label="Next month"
                                            class="inline-flex size-8 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
                                            onclick={() => changeCalendarMonth(1)}
                                        >
                                            <ChevronRight class="size-4" aria-hidden="true" />
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground" role="group" aria-label="Calendar dates">
                                        {#each weekdayLabels as weekday (weekday)}
                                            <span class="py-1 font-medium" aria-hidden="true">{weekday}</span>
                                        {/each}
                                        {#each calendarCells as cell (cell.key)}
                                            <button
                                                type="button"
                                                aria-label={`${displayServiceDate(cell.key)}${cell.isUnavailable ? ', already booked' : ''}`}
                                                aria-pressed={serviceDate === cell.key}
                                                title={cell.isUnavailable ? 'Already booked' : cell.isPast ? 'Date has passed' : undefined}
                                                disabled={!cell.isCurrentMonth || cell.isPast || cell.isUnavailable}
                                                class={`relative mx-auto inline-flex size-9 cursor-pointer items-center justify-center rounded-md text-sm transition-colors disabled:cursor-not-allowed ${
                                                    serviceDate === cell.key
                                                        ? 'bg-[#a46f4f] font-semibold text-white'
                                                        : cell.isUnavailable
                                                          ? 'bg-rose-500/10 text-rose-700 line-through opacity-60 dark:text-rose-300'
                                                          : cell.isCurrentMonth
                                                            ? 'text-foreground hover:bg-muted'
                                                            : 'text-muted-foreground/30'
                                                }`}
                                                onclick={() => chooseServiceDate(cell.key)}
                                            >
                                                {cell.day}
                                            </button>
                                        {/each}
                                    </div>
                                    <p class="mt-3 border-t border-border pt-2 text-xs text-muted-foreground">
                                        Dates marked unavailable are already booked.
                                    </p>
                                </div>
                            {/if}
                        </div>
                        {#if errors.service_date}<p class="mt-1.5 text-sm text-destructive">{errors.service_date}</p>{/if}
                    </div>
                </div>

                <div>
                    <label for="body-parts" class="mb-1.5 block text-sm font-medium">Which body parts?</label>
                    <input
                        id="body-parts"
                        type="text"
                        bind:value={bodyParts}
                        required
                        maxlength="255"
                        placeholder="e.g. left forearm, shoulder"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                    />
                    {#if errors.body_parts}<p class="mt-1.5 text-sm text-destructive">{errors.body_parts}</p>{/if}
                </div>

                <div>
                    <label for="design-picture" class="mb-1.5 block text-sm font-medium">Design Picture</label>
                    <input
                        id="design-picture"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        required
                        onchange={(event) => {
                            designPicture = (event.currentTarget as HTMLInputElement).files?.[0] ?? null;
                            delete errors.design_picture;
                            if (Object.keys(errors).length === 0) validationMessage = '';
                        }}
                        aria-describedby="design-picture-help"
                        class="block w-full cursor-pointer rounded-md border border-input bg-background text-sm text-foreground file:mr-4 file:h-10 file:cursor-pointer file:border-0 file:border-r file:border-input file:bg-muted file:px-4 file:text-sm file:font-medium"
                    />
                    <p id="design-picture-help" class="mt-1 text-xs text-muted-foreground">
                        JPG, PNG, or WEBP. Maximum file size: 5 MB.
                        {#if designPicture}<span class="ml-1 text-foreground">Selected: {designPicture.name}</span>{/if}
                    </p>
                    {#if designPicturePreview}
                        <div class="mt-3 overflow-hidden rounded-md border border-border bg-muted/30 p-2">
                            <img
                                src={designPicturePreview}
                                alt={`Preview of selected design: ${designPicture?.name ?? 'design image'}`}
                                class="mx-auto max-h-56 w-full rounded object-contain"
                            />
                        </div>
                    {/if}
                    {#if errors.design_picture}<p class="mt-1.5 text-sm text-destructive">{errors.design_picture}</p>{/if}
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="price-range" class="text-sm font-medium">Price Range</label>
                        <output for="price-range" class="rounded-md bg-muted px-2.5 py-1 text-sm font-semibold tabular-nums">${priceRange.toLocaleString('en-US')}</output>
                    </div>
                    <input
                        id="price-range"
                        type="range"
                        bind:value={priceRange}
                        min={minimumPrice}
                        max={maximumPrice}
                        step="20"
                        class="h-2 w-full cursor-pointer accent-[#a46f4f]"
                    />
                    <div class="mt-1 flex justify-between text-xs text-muted-foreground">
                        <span>${minimumPrice.toLocaleString('en-US')}</span>
                        <span>${maximumPrice.toLocaleString('en-US')}+</span>
                    </div>
                    {#if errors.price_range}<p class="mt-1.5 text-sm text-destructive">{errors.price_range}</p>{/if}
                </div>

                <div class="flex justify-end gap-3 border-t border-border pt-4">
                    <button
                        type="button"
                        class="h-10 cursor-pointer rounded-md border border-border px-4 text-sm font-medium transition-colors hover:bg-muted"
                        onclick={closeBookingModal}
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="h-10 cursor-pointer rounded-md bg-[#a46f4f] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#8d5c40] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a] focus-visible:ring-offset-2"
                    >
                        Submit Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}
