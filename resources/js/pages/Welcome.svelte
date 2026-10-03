<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';

    const sampleImages = Array.from(
        { length: 15 },
        (_, index) => `/storage/images/samples/${index + 1}.jpeg`,
    );

    const artistImage = '/storage/images/artist/artist_1.jpeg';
    const socialLinks = [
        {
            name: 'Facebook',
            href: 'https://facebook.com',
            path: 'M13.5 8.5h-2.3V6.7c0-.7.5-.9.9-.9H13V3.7h-2.4c-2.2 0-2.7 1.6-2.7 2.7v2.1H6.5v2.2h1.4V18h2.8v-8.1h1.9L13.5 8.5z',
        },
        {
            name: 'Instagram',
            href: 'https://instagram.com',
            path: 'M12 2.2c2.7 0 3 0 4 .1 1 .1 1.6.2 2 .4.5.2.9.5 1.3.9.4.4.7.8.9 1.3.2.4.4 1 .4 2 .1 1 .1 1.3.1 4s0 3-.1 4c-.1 1-.2 1.6-.4 2-.2.5-.5.9-.9 1.3-.4.4-.8.7-1.3.9-.4.2-1 .4-2 .4-1 .1-1.3.1-4 .1s-3 0-4-.1c-1-.1-1.6-.2-2-.4-.5-.2-.9-.5-1.3-.9-.4-.4-.7-.8-.9-1.3-.2-.4-.4-1-.4-2-.1-1-.1-1.3-.1-4s0-3 .1-4c.1-1 .2-1.6.4-2 .2-.5.5-.9.9-1.3.4-.4.8-.7 1.3-.9.4-.2 1-.4 2-.4 1-.1 1.3-.1 4-.1zm0 1.8c-2.6 0-3 .1-4 .1-.9 0-1.4.2-1.7.3-.4.2-.7.4-1 .8-.3.3-.6.6-.8 1-.1.3-.3.8-.3 1.7 0 1-.1 1.4-.1 4s.1 3 .1 4c0 .9.2 1.4.3 1.7.2.4.4.7.8 1 .3.3.6.6 1 .8.3.1.8.3 1.7.3 1 0 1.4.1 4 .1s3-.1 4-.1c.9 0 1.4-.2 1.7-.3.4-.2.7-.4 1-.8.3-.3.6-.6.8-1 .1-.3.3-.8.3-1.7 0-1 .1-1.4.1-4s-.1-3-.1-4c0-.9-.2-1.4-.3-1.7-.2-.4-.4-.7-.8-1-.3-.3-.6-.6-1-.8-.3-.1-.8-.3-1.7-.3-1 0-1.4-.1-4-.1zm0 3.2A4.8 4.8 0 1 1 12 16.2 4.8 4.8 0 0 1 12 7.2zm0 1.8A3 3 0 1 0 12 15a3 3 0 0 0 0-6zm4.9-3.4a1.1 1.1 0 1 1-1.1 1.1 1.1 0 0 1 1.1-1.1z',
        },
        {
            name: 'Twitter',
            href: 'https://twitter.com',
            path: 'M18.9 5.2c-.6.3-1.3.5-2 .6.7-.4 1.2-1 1.5-1.8-.7.4-1.4.7-2.2.9A3.2 3.2 0 0 0 9.6 8.1c0 .2 0 .5.1.7-2.7-.1-5.1-1.4-6.7-3.4-.3.5-.5 1-.5 1.7 0 1.1.6 2.1 1.5 2.7-.5 0-1-.2-1.5-.4v.1c0 1.6 1.1 2.9 2.6 3.2-.3.1-.6.1-.9.1-.2 0-.4 0-.7-.1.4 1.3 1.7 2.2 3.2 2.2A6.5 6.5 0 0 1 3 16.8c1.4 1 3.1 1.6 5 1.6 6.1 0 9.4-5 9.4-9.4v-.4c.7-.4 1.2-1 1.7-1.7z',
        },
    ];

    type PromoSlide = {
        id: number;
        title: string;
        description: string | null;
        discount: number;
        requirements: string[];
        usage: string;
        image: string;
    };

    let { promos = [] }: { promos?: PromoSlide[] } = $props();

    let currentIndex = $state(0);
    let promoIndex = $state(0);
    let currentSection = $state(0);
    let promoCycleComplete = $state(false);
    let isModalOpen = $state(false);
    let modalType = $state<'login' | 'register'>('login');
    let loginPasswordVisible = $state(false);
    let registerPasswordVisible = $state(false);
    let registerPasswordConfirmVisible = $state(false);
    let loginUsername = $state('');
    let loginPassword = $state('');
    let registerName = $state('');
    let registerUsername = $state('');
    let registerEmail = $state('');
    let registerPassword = $state('');
    let registerConfirmPassword = $state('');
    let registerPasswordError = $state('');
    let formErrors = $state<Record<string, string>>({});
    let errorForm = $state<'login' | 'register' | null>(null);

    const authFields = ['name', 'username', 'email', 'password', 'password_confirmation'] as const;

    function normalizeErrors(errors: Record<string, unknown> | undefined): Record<string, string> {
        const next: Record<string, string> = {};

        for (const [key, value] of Object.entries(errors ?? {})) {
            if (Array.isArray(value)) {
                next[key] = String(value[0] ?? '');
            } else if (typeof value === 'string' && value !== '') {
                next[key] = value;
            }
        }

        return next;
    }

    function readAuthDraft(): { form: 'login' | 'register'; values: Record<string, string> } | null {
        if (typeof sessionStorage === 'undefined') return null;

        const raw = sessionStorage.getItem('welcome-auth');
        if (!raw) return null;

        try {
            const draft = JSON.parse(raw) as { form?: string; values?: Record<string, string> };
            if (draft.form !== 'login' && draft.form !== 'register') return null;

            return { form: draft.form, values: draft.values ?? {} };
        } catch {
            return null;
        }
    }

    function fieldError(field: string): string {
        if (errorForm !== modalType) return '';

        return formErrors[field] ?? '';
    }

    const hasAuthErrors = $derived(authFields.some((field) => (formErrors[field] ?? '') !== ''));

    function applyAuthDraft(draft: { form: 'login' | 'register'; values: Record<string, string> }) {
        const values = draft.values;

        if (draft.form === 'login') {
            loginUsername = values.username ?? '';
            loginPassword = values.password ?? '';
            return;
        }

        registerName = values.name ?? '';
        registerUsername = values.username ?? '';
        registerEmail = values.email ?? '';
        registerPassword = values.password ?? '';
        registerConfirmPassword = values.password_confirmation ?? '';
    }

    $effect(() => {
        const fromPage = normalizeErrors(page.props.errors as Record<string, unknown> | undefined);
        if (Object.keys(fromPage).length === 0 || typeof sessionStorage === 'undefined') return;
        if (sessionStorage.getItem('welcome-auth-dismissed') === '1') return;

        const draft = readAuthDraft();
        errorForm = draft?.form ?? errorForm;
        formErrors = fromPage;

        if (draft) {
            modalType = draft.form;
            applyAuthDraft(draft);
        }

        isModalOpen = true;
    });

    function scrollToSection(sectionIndex: number) {
        const sectionMap = {
            0: 'quote-section',
            1: 'artist-design-section',
            2: 'promo-section',
        } as const;

        const sectionId = sectionMap[sectionIndex as keyof typeof sectionMap];
        const section = document.getElementById(sectionId);
        if (!section) return;

        currentSection = sectionIndex;
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function redirectAuthenticatedUser() {
        const user = page.props.auth?.user;
        if (!user) return;

        const destination = user.is_admin ? '/bookings' : '/book-a-service';
        if (window.location.pathname === destination) return;

        router.visit(destination, { replace: true });
    }

    onMount(() => {
        redirectAuthenticatedUser();
        window.addEventListener('pageshow', redirectAuthenticatedUser);

        const slideInterval = window.setInterval(() => {
            const nextIndex = (currentIndex + 1) % sampleImages.length;
            currentIndex = nextIndex;

            if (currentSection === 1 && nextIndex === 0) {
                window.setTimeout(() => {
                    scrollToSection(2);
                    currentIndex = 0;
                }, 250);
            }
        }, 2600);

        const quoteScrollInterval = window.setInterval(() => {
            if (currentSection === 0) {
                scrollToSection(1);
            }
        }, 7000);

        const promoScrollInterval = window.setInterval(() => {
            if (currentSection !== 2 || promoCycleComplete) return;

            const nextPromoIndex = promoIndex + 1;

            if (promos.length === 0) return;

            if (nextPromoIndex < promos.length) {
                promoIndex = nextPromoIndex;
                return;
            }

            promoIndex = 0;
            promoCycleComplete = true;

            window.setTimeout(() => {
                const footerSection = document.getElementById('footer-section');
                if (footerSection) {
                    footerSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 1200);
        }, 3000);

        return () => {
            window.removeEventListener('pageshow', redirectAuthenticatedUser);
            window.clearInterval(slideInterval);
            window.clearInterval(quoteScrollInterval);
            window.clearInterval(promoScrollInterval);
        };
    });

    function openModal(type: 'login' | 'register') {
        modalType = type;
        isModalOpen = true;
    }

    function closeModal() {
        isModalOpen = false;
        if (hasAuthErrors && typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem('welcome-auth-dismissed', '1');
        }
    }

    function handleBookingSubmit(event: SubmitEvent) {
        event.preventDefault();
        closeModal();
    }

    function updateRegisterPasswordMatch() {
        registerPasswordError = registerPassword !== registerConfirmPassword && registerConfirmPassword.length > 0
            ? 'Passwords do not match.'
            : '';
    }

    function handleAuthSubmit(event: SubmitEvent, endpoint: string) {
        event.preventDefault();

        const target = event.currentTarget as HTMLFormElement | null;
        if (!target) return;

        if (endpoint === '/register') {
            const password = (target.querySelector('input[name="password"]') as HTMLInputElement | null)?.value ?? '';
            const confirmPassword = (target.querySelector('input[name="password_confirmation"]') as HTMLInputElement | null)?.value ?? '';

            if (password !== confirmPassword) {
                registerPasswordError = 'Passwords do not match.';
                return;
            }

            registerPasswordError = '';
        }

        const formData = Object.fromEntries(new FormData(target).entries()) as Record<string, string>;
        const form = endpoint === '/register' ? 'register' : 'login';

        sessionStorage.setItem('welcome-auth', JSON.stringify({ form, values: formData }));
        sessionStorage.removeItem('welcome-auth-dismissed');

        router.post(endpoint, formData, {
            preserveScroll: true,
            onSuccess: (visit) => {
                const errors = normalizeErrors(visit.props.errors as Record<string, unknown> | undefined);
                if (Object.keys(errors).length > 0) {
                    errorForm = form;
                    formErrors = errors;
                    modalType = form;
                    isModalOpen = true;
                    return;
                }

                formErrors = {};
                errorForm = null;
                sessionStorage.removeItem('welcome-auth');
                isModalOpen = false;
            },
            onError: (errors) => {
                errorForm = form;
                formErrors = normalizeErrors(errors as Record<string, unknown>);
                modalType = form;
                isModalOpen = true;
            },
        });
    }
</script>

<AppHead title="ARV_InkTattoos">
    <link rel="preconnect" href="https://rsms.me/" />
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
</AppHead>

<svelte:head>
    <style>
        html {
            scroll-behavior: smooth;
        }
    </style>
</svelte:head>

<div class="min-h-screen bg-[#120d0b] text-[#f7efe8] antialiased">
    <header class="fixed top-5 right-5 z-40 flex items-center gap-3">
        <button
            type="button"
            class="cursor-pointer rounded-full border border-[#d7a57a]/50 bg-[#120d0b]/80 px-5 py-2.5 text-xs font-semibold uppercase tracking-[0.28em] text-[#f7efe8] shadow-[0_8px_18px_rgba(0,0,0,0.18)] backdrop-blur-sm transition hover:border-[#e7bb8d] hover:bg-[#1b120f] hover:text-[#f5d6b2]"
            onclick={() => openModal('login')}
        >
            Login
        </button>
        <button
            type="button"
            class="cursor-pointer rounded-full border border-[#d7a57a] bg-[#d7a57a] px-5 py-2.5 text-xs font-semibold uppercase tracking-[0.28em] text-[#1d120f] shadow-[0_10px_24px_rgba(215,165,122,0.35)] transition hover:scale-[1.02] hover:bg-[#e7bb8d] hover:text-[#1a120f]"
            onclick={() => openModal('register')}
        >
            Register
        </button>
    </header>

    <section
        id="quote-section"
        class="relative flex min-h-screen w-screen items-center justify-center overflow-hidden px-6 py-12"
    >
        <img
            src="/storage/images/backgrounds/1.jpeg"
            alt=""
            aria-hidden="true"
            class="absolute inset-0 h-full w-full object-cover object-center"
        />
        <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(14,9,8,0.78)_0%,rgba(14,9,8,0.56)_48%,rgba(14,9,8,0.68)_100%),linear-gradient(180deg,rgba(14,9,8,0.25)_0%,rgba(14,9,8,0.5)_100%)]"></div>
        <div class="absolute inset-x-0 top-8 flex justify-center">
            <span class="rounded-full border border-[#d7a57a]/40 bg-[#d7a57a]/10 px-4 py-2 text-[10px] font-semibold uppercase tracking-[0.42em] text-[#f5d6b2]">
                ARV_InkTattoos
            </span>
        </div>

        <div class="relative z-10 mx-auto flex max-w-5xl flex-col items-center text-center">
            <p class="mb-8 text-xs font-medium uppercase tracking-[0.55em] text-[#d7a57a]">
                Home Service Tattoo Studio
            </p>

            <blockquote class="max-w-5xl text-3xl font-medium leading-tight text-[#f8f0ea] sm:text-4xl lg:text-6xl">
                “Every tattoo tells a story only you can wear. Turn your memories into art, express who you are, and leave your mark in ink.”
            </blockquote>

            <div class="mt-10 flex items-center gap-4">
                <button
                    type="button"
                    class="cursor-pointer inline-flex items-center justify-center rounded-full bg-[#d7a57a] px-7 py-3 text-sm font-semibold uppercase tracking-[0.24em] text-[#1d120f] transition-transform duration-200 hover:scale-[1.02] hover:bg-[#e7bb8d]"
                    onclick={() => openModal('login')}
                >
                    Wear your story
                </button>
            </div>
        </div>
    </section>

    <section
        id="artist-design-section"
        class="flex min-h-screen w-screen items-center justify-center bg-[#f5efe9] px-5 py-10 text-[#1c1715] sm:px-8 lg:px-16"
    >
        <div class="grid w-full max-w-7xl items-center gap-10 lg:grid-cols-[0.9fr_1.1fr]">
            <div class="flex flex-col items-center lg:items-start">
                <div class="mb-5 flex items-center gap-3 self-start">
                    <span class="h-px w-10 bg-[#8f6755]"></span>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.38em] text-[#8f6755]">
                        Artist
                    </span>
                </div>

                <div class="w-full max-w-md overflow-hidden rounded-[2rem] border border-[#d4b7a0] bg-[#1f1a17] p-3 shadow-[0_25px_60px_rgba(22,13,11,0.18)]">
                    <img
                        src={artistImage}
                        alt="Artist portrait"
                        class="h-[520px] w-full rounded-[1.5rem] object-cover object-center"
                    />
                </div>
            </div>

            <div class="w-full">
                <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.45em] text-[#8f6755]">
                    Signature work
                </p>
                <h2 class="max-w-xl text-4xl font-semibold tracking-[-0.04em] text-[#1f1714] sm:text-5xl">
                    Ink that feels personal, bold, and unforgettable.
                </h2>

                <div class="mt-6 flex flex-wrap gap-3 text-sm text-[#43352f]">
                    <span class="rounded-full border border-[#d8b59b] bg-[#f9efe6] px-4 py-2">Custom designs</span>
                    <span class="rounded-full border border-[#d8b59b] bg-[#f9efe6] px-4 py-2">Fine line</span>
                    <span class="rounded-full border border-[#d8b59b] bg-[#f9efe6] px-4 py-2">Minimalist</span>
                    <span class="rounded-full border border-[#d8b59b] bg-[#f9efe6] px-4 py-2">Home service</span>
                </div>

                <div class="mt-8 overflow-hidden rounded-[2rem] border border-[#d7b7a0] bg-[#1b1715] shadow-[0_30px_70px_rgba(17,13,10,0.16)]">
                    <div class="relative h-[420px] w-full overflow-hidden sm:h-[500px]">
                        <div
                            class="flex h-full transition-transform duration-700 ease-out"
                            style={`transform: translateX(-${currentIndex * 100}%);`}
                        >
                            {#each sampleImages as image}
                                <div class="h-full min-w-full">
                                    <img
                                        src={image}
                                        alt="Tattoo sample design"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                            {/each}
                        </div>

                        <div class="absolute inset-x-0 bottom-4 flex justify-center gap-2">
                            {#each sampleImages as _, index}
                                <button
                                    type="button"
                                    aria-label={`Go to slide ${index + 1}`}
                                    class={`h-2.5 rounded-full transition-all ${
                                        currentIndex === index ? 'w-10 bg-[#f4d3b4]' : 'w-2.5 bg-white/60'
                                    }`}
                                    onclick={() => (currentIndex = index)}
                                ></button>
                            {/each}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section
        id="promo-section"
        class="relative flex min-h-screen w-screen items-center justify-center overflow-hidden bg-[#120d0b] px-6 py-12"
    >
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(215,165,122,0.18),_transparent_30%),linear-gradient(180deg,#120d0b_0%,#1c120f_38%,#0f0c0b_100%)]"></div>

        <div class="relative z-10 mx-auto w-full max-w-7xl">
            <div class="mb-8 text-center">
                <p class="text-[10px] font-semibold uppercase tracking-[0.45em] text-[#d7a57a]">
                    Limited Time Offers
                </p>
                <h2 class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-[#f8f1eb] sm:text-5xl">
                    Promo specials for your next session.
                </h2>
            </div>

            <div class="overflow-hidden rounded-[2rem] border border-[#d7a57a]/50 bg-[#1b120f]/80 shadow-[0_30px_90px_rgba(0,0,0,0.28)]">
                <div class="relative h-[68vh] min-h-[420px] w-full overflow-hidden">
                    <div
                        class="flex h-full transition-transform duration-700 ease-out"
                        style={`transform: translateX(-${promoIndex * 100}%);`}
                    >
                        {#each promos as promo (promo.id)}
                            <div class="h-full min-w-full px-4 py-4 sm:px-6">
                                <div
                                    class="flex h-full flex-col justify-center rounded-[1.75rem] border border-[#d7a57a]/30 bg-[#1a120f] p-6 text-left shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] sm:p-10 lg:p-12"
                                    style={`background-image: linear-gradient(135deg, rgba(17, 12, 11, 0.82), rgba(32, 21, 17, 0.7)), url('${promo.image}'); background-size: cover; background-position: center; background-repeat: no-repeat;`}
                                >
                                    <div class="mb-6 inline-flex w-fit items-center rounded-full border border-[#d7a57a]/50 bg-[#d7a57a]/10 px-4 py-2 text-[10px] font-semibold uppercase tracking-[0.35em] text-[#f5d6b2] backdrop-blur-sm">
                                        {promo.discount}% off
                                    </div>

                                    <h3 class="max-w-lg text-3xl font-semibold tracking-[-0.04em] text-[#fbece0] sm:text-5xl">
                                        {promo.title}
                                    </h3>

                                    {#if promo.description}
                                        <p class="mt-5 max-w-2xl text-base text-[#e7d1c2] sm:text-lg">
                                            {promo.description}
                                        </p>
                                    {/if}

                                    <div class="mt-8 flex flex-wrap gap-3">
                                        {#each promo.requirements as requirement}
                                            <span class="rounded-full border border-[#d7a57a]/40 bg-[#d7a57a]/10 px-4 py-2 text-sm text-[#f8e6d3] backdrop-blur-sm">
                                                {requirement}
                                            </span>
                                        {/each}
                                        <span class="rounded-full border border-[#d7a57a]/40 bg-[#d7a57a]/10 px-4 py-2 text-sm text-[#f8e6d3] backdrop-blur-sm">
                                            {promo.usage}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-center gap-2">
                {#each promos as promo, index (promo.id)}
                    <button
                        type="button"
                        aria-label={`Go to promo ${index + 1}`}
                        class={`h-2.5 rounded-full transition-all ${
                            promoIndex === index ? 'w-10 bg-[#f4d3b4]' : 'w-2.5 bg-white/50'
                        }`}
                        onclick={() => {
                            if (promoCycleComplete) return;
                            promoIndex = index;
                        }}
                    ></button>
                {/each}
            </div>
        </div>
    </section>

    <footer
        id="footer-section"
        class="w-full border-t border-[#d7a57a]/70 bg-[#120d0b] px-6 py-4 text-[#f9efe6]"
    >
        <div class="mx-auto flex max-w-6xl flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="text-xs text-[#f2d8c3] sm:text-sm">
                © {new Date().getFullYear()} ARV_InkTattoos. All rights reserved.
            </div>

            <div class="flex flex-col gap-1 text-xs text-[#f2d8c3] sm:flex-row sm:items-center sm:gap-6 sm:text-sm">
                <a href="mailto:johndoe@gmail.com" class="transition hover:text-[#f4cfaa]">
                    johndoe@gmail.com
                </a>
                <a href="tel:+6397712345678" class="transition hover:text-[#f4cfaa]">
                    +6397712345678
                </a>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                {#each socialLinks as social}
                    <a
                        href={social.href}
                        target="_blank"
                        rel="noreferrer"
                        aria-label={social.name}
                        class="flex h-8 w-8 items-center justify-center rounded-full border border-[#d7a57a]/60 bg-[#1b120f] text-[#f7efe8] transition hover:border-[#f4cfaa] hover:text-[#f4cfaa] hover:shadow-[0_0_18px_rgba(215,165,122,0.28)] sm:h-9 sm:w-9"
                    >
                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current sm:h-4 sm:w-4" aria-hidden="true">
                            <path d={social.path}></path>
                        </svg>
                    </a>
                {/each}
            </div>
        </div>
    </footer>
</div>

{#if isModalOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-[#120d0b]/80 px-4 py-8 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-label={modalType === 'login' ? 'Login' : 'Register'}
        tabindex="0"
    >
        <div
            class="w-full max-w-lg rounded-[2rem] border border-[#d9b496] bg-[#f9f2ec] p-6 text-[#1a120f] shadow-[0_40px_80px_rgba(0,0,0,0.35)]"
            role="document"
        >
            {#if modalType === 'login'}
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.35em] text-[#8f6755]">
                            Welcome back
                        </p>
                        <h3 class="mt-2 text-2xl font-semibold tracking-[-0.04em] text-[#1d120f]">
                            Login to your account
                        </h3>
                    </div>
                    <button
                        type="button"
                        aria-label="Close login modal"
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-[#d7b7a0] bg-white text-lg text-[#33251f] transition-colors hover:bg-[#f4e7de]"
                        onclick={closeModal}
                    >
                        ×
                    </button>
                </div>

                <form class="space-y-4" onsubmit={(event) => handleAuthSubmit(event, '/login')}>
                    <label class="block text-sm font-medium text-[#33251f]">
                        Username
                        <input
                            type="text"
                            name="username"
                            required
                            bind:value={loginUsername}
                            class="mt-2 w-full rounded-xl border border-[#d9c4b2] bg-white px-3 py-2.5 text-base text-[#1d120f] outline-none ring-0 transition focus:border-[#b27d5b]"
                            placeholder="Enter your username"
                        />
                        {#if fieldError('username')}
                            <p class="mt-2 text-xs text-red-600">{fieldError('username')}</p>
                        {/if}
                    </label>

                    <label class="block text-sm font-medium text-[#33251f]">
                        Password
                        <div class="relative mt-2">
                            <input
                                type={loginPasswordVisible ? 'text' : 'password'}
                                name="password"
                                required
                                bind:value={loginPassword}
                                class="w-full rounded-xl border border-[#d9c4b2] bg-white px-3 py-2.5 pr-11 text-base text-[#1d120f] outline-none ring-0 transition focus:border-[#b27d5b]"
                                placeholder="Enter your password"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-3 flex cursor-pointer items-center text-[#5b463d]"
                                aria-label={loginPasswordVisible ? 'Hide password' : 'Show password'}
                                onclick={() => (loginPasswordVisible = !loginPasswordVisible)}
                            >
                                {#if loginPasswordVisible}
                                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="12" cy="12" r="3" stroke-width="1.8"/>
                                    </svg>
                                {:else}
                                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" aria-hidden="true">
                                        <path d="M3 3l18 18" stroke-width="1.8" stroke-linecap="round"/>
                                        <path d="M10.6 10.6A2.5 2.5 0 0 0 13.4 13.4" stroke-width="1.8" stroke-linecap="round"/>
                                        <path d="M9.1 5.7A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a17.7 17.7 0 0 1-4.1 5.1" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M5.9 7.6A16.3 16.3 0 0 0 2 12s3.5 7 10 7a11.4 11.4 0 0 0 5.3-1.4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                {/if}
                            </button>
                        </div>
                        {#if fieldError('password')}
                            <p class="mt-2 text-xs text-red-600">{fieldError('password')}</p>
                        {/if}
                    </label>

                    <button
                        type="submit"
                        class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl bg-[#1d120f] px-5 py-3 text-sm font-semibold uppercase tracking-[0.22em] text-[#f7efe8] transition hover:bg-[#33251f]"
                    >
                        Login
                    </button>
                </form>
            {:else}
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.35em] text-[#8f6755]">
                            New here
                        </p>
                        <h3 class="mt-2 text-2xl font-semibold tracking-[-0.04em] text-[#1d120f]">
                            Create your account
                        </h3>
                    </div>
                    <button
                        type="button"
                        aria-label="Close register form"
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-[#d7b7a0] bg-white text-lg text-[#33251f] transition-colors hover:bg-[#f4e7de]"
                        onclick={closeModal}
                    >
                        ×
                    </button>
                </div>

                <form class="space-y-4" onsubmit={(event) => handleAuthSubmit(event, '/register')}>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-[#33251f]">
                            Name
                            <input
                                type="text"
                                name="name"
                                required
                                bind:value={registerName}
                                class="mt-2 w-full rounded-xl border border-[#d9c4b2] bg-white px-3 py-2.5 text-base text-[#1d120f] outline-none ring-0 transition focus:border-[#b27d5b]"
                                placeholder="Your name"
                            />
                            {#if fieldError('name')}
                                <p class="mt-2 text-xs text-red-600">{fieldError('name')}</p>
                            {/if}
                        </label>

                        <label class="block text-sm font-medium text-[#33251f]">
                            Username
                            <input
                                type="text"
                                name="username"
                                required
                                bind:value={registerUsername}
                                class="mt-2 w-full rounded-xl border border-[#d9c4b2] bg-white px-3 py-2.5 text-base text-[#1d120f] outline-none ring-0 transition focus:border-[#b27d5b]"
                                placeholder="Choose a username"
                            />
                            {#if fieldError('username')}
                                <p class="mt-2 text-xs text-red-600">{fieldError('username')}</p>
                            {/if}
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-[#33251f]">
                        Email
                        <input
                            type="email"
                            name="email"
                            required
                            bind:value={registerEmail}
                            class="mt-2 w-full rounded-xl border border-[#d9c4b2] bg-white px-3 py-2.5 text-base text-[#1d120f] outline-none ring-0 transition focus:border-[#b27d5b]"
                            placeholder="you@example.com"
                        />
                        {#if fieldError('email')}
                            <p class="mt-2 text-xs text-red-600">{fieldError('email')}</p>
                        {/if}
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-[#33251f]">
                            Password
                            <div class="relative mt-2">
                                <input
                                    type={registerPasswordVisible ? 'text' : 'password'}
                                    name="password"
                                    required
                                    bind:value={registerPassword}
                                    oninput={updateRegisterPasswordMatch}
                                    class="w-full rounded-xl border border-[#d9c4b2] bg-white px-3 py-2.5 pr-11 text-base text-[#1d120f] outline-none ring-0 transition focus:border-[#b27d5b]"
                                    placeholder="Password"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-3 flex cursor-pointer items-center text-[#5b463d]"
                                    aria-label={registerPasswordVisible ? 'Hide password' : 'Show password'}
                                    onclick={() => (registerPasswordVisible = !registerPasswordVisible)}
                                >
                                    {#if registerPasswordVisible}
                                        <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" aria-hidden="true">
                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="12" cy="12" r="3" stroke-width="1.8"/>
                                        </svg>
                                    {:else}
                                        <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" aria-hidden="true">
                                            <path d="M3 3l18 18" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M10.6 10.6A2.5 2.5 0 0 0 13.4 13.4" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M9.1 5.7A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a17.7 17.7 0 0 1-4.1 5.1" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M5.9 7.6A16.3 16.3 0 0 0 2 12s3.5 7 10 7a11.4 11.4 0 0 0 5.3-1.4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                {/if}
                            </button>
                        </div>
                        {#if fieldError('password')}
                            <p class="mt-2 text-xs text-red-600">{fieldError('password')}</p>
                        {/if}
                    </label>

                    <label class="block text-sm font-medium text-[#33251f]">
                        Confirm password
                            <div class="relative mt-2">
                                <input
                                    type={registerPasswordConfirmVisible ? 'text' : 'password'}
                                    name="password_confirmation"
                                    required
                                    bind:value={registerConfirmPassword}
                                    oninput={updateRegisterPasswordMatch}
                                    class="w-full rounded-xl border border-[#d9c4b2] bg-white px-3 py-2.5 pr-11 text-base text-[#1d120f] outline-none ring-0 transition focus:border-[#b27d5b]"
                                    placeholder="Confirm password"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-3 flex cursor-pointer items-center text-[#5b463d]"
                                    aria-label={registerPasswordConfirmVisible ? 'Hide password confirmation' : 'Show password confirmation'}
                                    onclick={() => (registerPasswordConfirmVisible = !registerPasswordConfirmVisible)}
                                >
                                    {#if registerPasswordConfirmVisible}
                                        <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" aria-hidden="true">
                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="12" cy="12" r="3" stroke-width="1.8"/>
                                        </svg>
                                    {:else}
                                        <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" aria-hidden="true">
                                            <path d="M3 3l18 18" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M10.6 10.6A2.5 2.5 0 0 0 13.4 13.4" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M9.1 5.7A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a17.7 17.7 0 0 1-4.1 5.1" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M5.9 7.6A16.3 16.3 0 0 0 2 12s3.5 7 10 7a11.4 11.4 0 0 0 5.3-1.4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    {/if}
                                </button>
                            </div>
                            {#if registerPasswordError}
                                <p class="mt-2 text-xs text-red-600">{registerPasswordError}</p>
                            {/if}
                            {#if fieldError('password_confirmation')}
                                <p class="mt-2 text-xs text-red-600">{fieldError('password_confirmation')}</p>
                            {/if}
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl bg-[#1d120f] px-5 py-3 text-sm font-semibold uppercase tracking-[0.22em] text-[#f7efe8] transition hover:bg-[#33251f]"
                    >
                        Register
                    </button>
                </form>
            {/if}
        </div>
    </div>
{/if}
