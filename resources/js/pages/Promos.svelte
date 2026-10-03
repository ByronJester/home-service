<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import X from '@lucide/svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import BookingDesignPreview from '@/components/BookingDesignPreview.svelte';

    type Promo = {
        id: number;
        title: string;
        description: string | null;
        discount: number;
        requirements: string[];
        usage: string;
        image: string;
        is_active: boolean;
    };

    let { promos, filters }: {
        promos: Promo[];
        filters: { search: string };
    } = $props();

    const usageOptions = ['Limited Availability', 'Unlimited Availability'] as const;

    let search = $state('');
    let isModalOpen = $state(false);
    let editingId = $state<number | null>(null);
    let title = $state('');
    let description = $state('');
    let discount = $state('');
    let requirements = $state<string[]>([]);
    let requirementDraft = $state('');
    let usage = $state('');
    let image = $state<File | null>(null);
    let existingImage = $state('');
    let imagePreview = $state('');
    let errors = $state<Record<string, string>>({});
    let successMessage = $state('');
    let errorMessage = $state('');
    let isSaving = $state(false);
    let togglingId = $state<number | null>(null);
    let promoPendingDelete = $state<Promo | null>(null);
    let deletingId = $state<number | null>(null);

    $effect(() => {
        search = filters.search ?? '';
    });

    $effect(() => {
        if (!image) {
            imagePreview = '';
            return;
        }

        const previewUrl = URL.createObjectURL(image);
        imagePreview = previewUrl;

        return () => URL.revokeObjectURL(previewUrl);
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
        router.get('/promos', { search }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function resetForm() {
        editingId = null;
        title = '';
        description = '';
        discount = '';
        requirements = [];
        requirementDraft = '';
        usage = '';
        image = null;
        existingImage = '';
        errors = {};
    }

    function openModal() {
        resetForm();
        isModalOpen = true;
        errorMessage = '';
    }

    function openEdit(promo: Promo) {
        editingId = promo.id;
        title = promo.title;
        description = promo.description ?? '';
        discount = String(promo.discount);
        requirements = [...promo.requirements];
        requirementDraft = '';
        usage = promo.usage;
        image = null;
        existingImage = promo.image;
        errors = {};
        errorMessage = '';
        isModalOpen = true;
    }

    function closeModal() {
        if (isSaving) return;
        isModalOpen = false;
        resetForm();
    }

    function fieldError(key: string): string {
        if (errors[key]) return errors[key];
        const nested = Object.entries(errors).find(([name]) => name.startsWith(`${key}.`));
        return nested?.[1] ?? '';
    }

    function onRequirementInput(event: Event) {
        const value = (event.currentTarget as HTMLInputElement).value;
        if (!value.includes(' ')) {
            requirementDraft = value;
            return;
        }

        const pieces = value.split(' ');
        const completed = pieces.slice(0, -1).map((piece) => piece.trim()).filter(Boolean);
        requirements = [...requirements, ...completed];
        requirementDraft = pieces.at(-1) ?? '';
        delete errors.requirements;
    }

    function onRequirementKeydown(event: KeyboardEvent) {
        if (event.key === 'Backspace' && requirementDraft === '' && requirements.length > 0) {
            requirements = requirements.slice(0, -1);
        }
    }

    function removeRequirement(index: number) {
        requirements = requirements.filter((_, requirementIndex) => requirementIndex !== index);
    }

    function submitPromo(event: SubmitEvent) {
        event.preventDefault();
        const pendingRequirement = requirementDraft.trim();
        const nextRequirements = pendingRequirement === '' ? requirements : [...requirements, pendingRequirement];
        const nextErrors: Record<string, string> = {};
        const discountText = String(discount ?? '').trim();
        const discountValue = Number(discountText);

        if (title.trim() === '') nextErrors.title = 'Enter a discount name.';
        if (description.trim() === '') nextErrors.description = 'Enter a description.';
        if (discountText === '' || !Number.isInteger(discountValue) || discountValue < 1 || discountValue > 100) {
            nextErrors.discount = 'Enter a discount from 1% to 100%.';
        }
        if (nextRequirements.length === 0) nextErrors.requirements = 'Add at least one requirement.';
        if (!usageOptions.includes(usage as (typeof usageOptions)[number])) {
            nextErrors.usage = 'Select a usage option.';
        }
        if (!image && editingId === null) {
            nextErrors.image = 'Upload a promo image.';
        } else if (image && !isAllowedImage(image)) {
            nextErrors.image = 'Upload a JPG, PNG, or WEBP image.';
        } else if (image && image.size > 5 * 1024 * 1024) {
            nextErrors.image = 'The promo image must be 5 MB or smaller.';
        }

        errors = nextErrors;
        if (Object.keys(nextErrors).length > 0) return;

        const payload: Record<string, string | number | string[] | File> = {
            title: title.trim(),
            description: description.trim(),
            discount: discountValue,
            requirements: nextRequirements,
            usage,
        };
        if (image) payload.image = image;

        const promoId = editingId;
        isSaving = true;
        router.post(promoId === null ? '/promos' : `/promos/${promoId}`, payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                successMessage = promoId === null ? 'Promo added.' : 'Promo updated.';
                isSaving = false;
                closeModal();
            },
            onError: (formErrors) => {
                errors = formErrors;
                isSaving = false;
            },
            onFinish: () => {
                isSaving = false;
            },
        });
    }

    function isAllowedImage(file: File): boolean {
        if (['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return true;
        return /\.(jpe?g|png|webp)$/i.test(file.name);
    }

    function confirmDelete(promo: Promo) {
        promoPendingDelete = promo;
    }

    function cancelDelete() {
        if (deletingId !== null) return;
        promoPendingDelete = null;
    }

    function deletePromo() {
        if (!promoPendingDelete || deletingId !== null) return;
        const promo = promoPendingDelete;
        deletingId = promo.id;
        errorMessage = '';

        router.delete(`/promos/${promo.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                successMessage = 'Promo deleted.';
                promoPendingDelete = null;
            },
            onError: () => {
                errorMessage = 'The promo could not be deleted.';
            },
            onFinish: () => {
                deletingId = null;
            },
        });
    }

    function togglePromo(promo: Promo) {
        if (togglingId !== null) return;
        togglingId = promo.id;
        errorMessage = '';

        router.patch(`/promos/${promo.id}`, {
            is_active: !promo.is_active,
        }, {
            preserveScroll: true,
            onError: () => {
                errorMessage = 'The promo status could not be updated. Please try again.';
            },
            onFinish: () => {
                togglingId = null;
            },
        });
    }
</script>

<svelte:window onkeydown={(event) => {
    if (event.key === 'Escape' && isModalOpen) closeModal();
}} />

<AppHead title="Promos" />

<div class="flex min-h-full flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#b98766]">Admin workspace</p>
        <h1 class="mt-2 text-3xl font-semibold text-foreground">Promos</h1>
    </div>

    {#if successMessage}
        <div class="rounded-lg border border-cyan-700 bg-[#062d33] px-5 py-4 text-base font-medium text-cyan-300" role="status" aria-live="polite">
            {successMessage}
        </div>
    {/if}
    {#if errorMessage}
        <div class="rounded-lg border border-rose-700 bg-[#320d14] px-5 py-4 text-base font-medium text-rose-300" role="alert" aria-live="assertive">
            {errorMessage}
        </div>
    {/if}

    <section class="overflow-hidden rounded-lg border border-border bg-card text-card-foreground shadow-sm" aria-label="Promos">
        <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
            <button
                type="button"
                class="inline-flex h-10 w-fit cursor-pointer items-center gap-2 rounded-md bg-[#a46f4f] px-4 text-sm font-semibold text-white transition-colors hover:bg-[#8d5c40] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a] focus-visible:ring-offset-2"
                onclick={openModal}
            >
                <Plus class="size-4" aria-hidden="true" />
                Add Promo
            </button>

            <form class="relative w-full sm:max-w-xs" onsubmit={submitSearch} role="search">
                <label class="sr-only" for="promo-search">Search promos</label>
                <input
                    id="promo-search"
                    type="search"
                    bind:value={search}
                    placeholder="Search promos..."
                    class="h-10 w-full rounded-md border border-input bg-background py-2 pl-3 pr-10 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                />
                <button
                    type="submit"
                    aria-label="Search promos"
                    class="absolute inset-y-0 right-0 inline-flex w-10 cursor-pointer items-center justify-center text-muted-foreground transition-colors hover:text-foreground"
                >
                    <Search class="size-4" aria-hidden="true" />
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] table-fixed border-collapse text-left text-sm">
                <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="w-[26%] px-4 py-3 font-semibold">Discount</th>
                        <th class="w-[10%] px-4 py-3 font-semibold">Discount</th>
                        <th class="w-[18%] px-4 py-3 font-semibold">Requirements</th>
                        <th class="w-[18%] px-4 py-3 font-semibold">Usage</th>
                        <th class="w-[12%] px-4 py-3 font-semibold">Image</th>
                        <th class="w-[20%] px-4 py-3 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    {#each promos as promo (promo.id)}
                        <tr class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-4 break-words">
                                <p class="font-medium text-foreground">{promo.title}</p>
                                {#if promo.description}
                                    <p class="mt-1 text-sm font-normal text-muted-foreground">{promo.description}</p>
                                {/if}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-muted-foreground">{promo.discount}%</td>
                            <td class="px-4 py-4 whitespace-normal break-words text-muted-foreground">{promo.requirements.join(', ')}</td>
                            <td class="px-4 py-4 whitespace-normal break-words text-muted-foreground">{promo.usage}</td>
                            <td class="px-4 py-3">
                                <BookingDesignPreview src={promo.image} alt={promo.title} />
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        role="switch"
                                        aria-checked={promo.is_active}
                                        aria-label={`${promo.is_active ? 'Turn off' : 'Turn on'} ${promo.title}`}
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a] disabled:cursor-wait disabled:opacity-60 {promo.is_active ? 'bg-[#a46f4f]' : 'bg-muted'}"
                                        disabled={togglingId === promo.id}
                                        onclick={() => togglePromo(promo)}
                                    >
                                        <span class="inline-block size-5 rounded-full bg-white shadow-sm transition-transform {promo.is_active ? 'translate-x-5' : 'translate-x-0.5'}"></span>
                                    </button>
                                    <button
                                        type="button"
                                        aria-label={`Edit ${promo.title}`}
                                        class="inline-flex size-8 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                                        onclick={() => openEdit(promo)}
                                    >
                                        <Pencil class="size-4" aria-hidden="true" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label={`Delete ${promo.title}`}
                                        class="inline-flex size-8 cursor-pointer items-center justify-center rounded-md text-rose-400 transition-colors hover:bg-rose-950/40 hover:text-rose-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400"
                                        onclick={() => confirmDelete(promo)}
                                    >
                                        <Trash2 class="size-4" aria-hidden="true" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center text-sm text-muted-foreground">
                                {search ? 'No promos match your search.' : 'No promos yet.'}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-3 text-sm text-muted-foreground">
            {promos.length} {promos.length === 1 ? 'promo' : 'promos'}
        </div>
    </section>
</div>

{#if isModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/65 px-4 py-6 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="promo-modal-title">
        <div class="my-auto w-full max-w-xl rounded-lg border border-border bg-background text-foreground shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#a46f4f]">{editingId === null ? 'New promo' : 'Edit promo'}</p>
                    <h2 id="promo-modal-title" class="mt-1 text-xl font-semibold">{editingId === null ? 'Add Promo' : 'Edit Promo'}</h2>
                </div>
                <button
                    type="button"
                    class="inline-flex size-9 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                    aria-label="Close promo form"
                    onclick={closeModal}
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>

            <form class="space-y-5 px-6 py-5" novalidate onsubmit={submitPromo}>
                <div>
                    <label for="discount-name" class="mb-1.5 block text-sm font-medium">Discount Name</label>
                    <input
                        id="discount-name"
                        type="text"
                        bind:value={title}
                        maxlength="1000"
                        placeholder="Birthday Promo"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                    />
                    {#if fieldError('title')}<p class="mt-1.5 text-sm text-destructive">{fieldError('title')}</p>{/if}
                </div>

                <div>
                    <label for="promo-description" class="mb-1.5 block text-sm font-medium">Description</label>
                    <textarea
                        id="promo-description"
                        bind:value={description}
                        rows="3"
                        maxlength="5000"
                        placeholder="Describe this promo"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                    ></textarea>
                    {#if fieldError('description')}<p class="mt-1.5 text-sm text-destructive">{fieldError('description')}</p>{/if}
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="promo-discount" class="mb-1.5 block text-sm font-medium">Discount</label>
                        <div class="relative">
                            <input
                                id="promo-discount"
                                type="number"
                                min="1"
                                max="100"
                                bind:value={discount}
                                placeholder="10"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 pr-8 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                            />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">%</span>
                        </div>
                        {#if fieldError('discount')}<p class="mt-1.5 text-sm text-destructive">{fieldError('discount')}</p>{/if}
                    </div>
                    <div>
                        <label for="promo-usage" class="mb-1.5 block text-sm font-medium">Usage</label>
                        <select
                            id="promo-usage"
                            bind:value={usage}
                            class="h-10 w-full cursor-pointer rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                        >
                            <option value="" disabled>Select usage</option>
                            {#each usageOptions as option (option)}
                                <option value={option}>{option}</option>
                            {/each}
                        </select>
                        {#if fieldError('usage')}<p class="mt-1.5 text-sm text-destructive">{fieldError('usage')}</p>{/if}
                    </div>
                </div>

                <div>
                    <label for="promo-requirement" class="mb-1.5 block text-sm font-medium">Requirements</label>
                    <div class="flex min-h-10 flex-wrap items-center gap-2 rounded-md border border-input bg-background px-2 py-1.5 focus-within:ring-2 focus-within:ring-[#d7a57a]">
                        {#each requirements as requirement, index (requirement + index)}
                            <span class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-1 text-sm text-foreground">
                                {requirement}
                                <button
                                    type="button"
                                    class="inline-flex size-4 cursor-pointer items-center justify-center rounded-sm text-muted-foreground hover:text-foreground"
                                    aria-label={`Remove requirement ${requirement}`}
                                    onclick={() => removeRequirement(index)}
                                >
                                    <X class="size-3" aria-hidden="true" />
                                </button>
                            </span>
                        {/each}
                        <input
                            id="promo-requirement"
                            type="text"
                            value={requirementDraft}
                            oninput={onRequirementInput}
                            onkeydown={onRequirementKeydown}
                            placeholder={requirements.length === 0 ? 'Type a requirement, then press space' : 'Add another'}
                            class="h-8 min-w-40 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        />
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">Press space to add each requirement.</p>
                    {#if fieldError('requirements')}<p class="mt-1.5 text-sm text-destructive">{fieldError('requirements')}</p>{/if}
                </div>

                <div>
                    <label for="promo-image" class="mb-1.5 block text-sm font-medium">Image</label>
                    <input
                        id="promo-image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onchange={(event) => {
                            image = (event.currentTarget as HTMLInputElement).files?.[0] ?? null;
                            delete errors.image;
                        }}
                        class="block w-full cursor-pointer rounded-md border border-input bg-background text-sm text-foreground file:mr-4 file:h-10 file:cursor-pointer file:border-0 file:border-r file:border-input file:bg-muted file:px-4 file:text-sm file:font-medium"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">
                        JPG, PNG, or WEBP. Maximum file size: 5 MB.{editingId === null ? '' : ' Leave empty to keep the current image.'}
                    </p>
                    {#if imagePreview || existingImage}
                        <div class="mt-3 overflow-hidden rounded-md border border-border bg-muted/30 p-2">
                            <img src={imagePreview || existingImage} alt="Preview of the promo" class="mx-auto max-h-56 w-full rounded object-contain" />
                        </div>
                    {/if}
                    {#if fieldError('image')}<p class="mt-1.5 text-sm text-destructive">{fieldError('image')}</p>{/if}
                </div>

                <div class="flex justify-end gap-3 border-t border-border pt-5">
                    <button
                        type="button"
                        class="inline-flex h-10 cursor-pointer items-center rounded-md px-4 text-sm font-medium text-foreground hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                        onclick={closeModal}
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 cursor-pointer items-center rounded-md bg-[#a46f4f] px-4 text-sm font-semibold text-white hover:bg-[#8d5c40] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a] disabled:cursor-wait disabled:opacity-60"
                        disabled={isSaving}
                    >
                        {isSaving ? 'Saving...' : editingId === null ? 'Save Promo' : 'Save Changes'}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

{#if promoPendingDelete}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 px-4 py-6 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="delete-promo-title">
        <div class="w-full max-w-md rounded-lg border border-border bg-background p-6 text-foreground shadow-2xl">
            <h2 id="delete-promo-title" class="text-lg font-semibold">Delete {promoPendingDelete.title}?</h2>
            <p class="mt-2 text-sm text-muted-foreground">This promo will be removed from the list and the welcome page.</p>
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="inline-flex h-10 cursor-pointer items-center rounded-md px-4 text-sm font-medium text-foreground hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d7a57a]"
                    onclick={cancelDelete}
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 cursor-pointer items-center rounded-md bg-rose-700 px-4 text-sm font-semibold text-white hover:bg-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 disabled:cursor-wait disabled:opacity-60"
                    disabled={deletingId !== null}
                    onclick={deletePromo}
                >
                    {deletingId !== null ? 'Deleting...' : 'Delete'}
                </button>
            </div>
        </div>
    </div>
{/if}
