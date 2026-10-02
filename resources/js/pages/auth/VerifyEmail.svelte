<script module lang="ts">
    export const layout = {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { logout } from '@/routes';
</script>

<AppHead title="Email verification" />

<div class="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center gap-4 px-6 text-center">
    <h1 class="text-2xl font-semibold">Email verification is not required</h1>
    <p class="text-sm text-muted-foreground">
        Your account is ready to use. You can continue to the app or log out.
    </p>
    <div class="flex items-center gap-4">
        <Link href="/book-a-service" class="text-sm font-medium underline underline-offset-4">
            Continue
        </Link>
        <Link href={logout()} method="post" as="button" class="text-sm font-medium underline underline-offset-4">
            Log out
        </Link>
    </div>
</div>
