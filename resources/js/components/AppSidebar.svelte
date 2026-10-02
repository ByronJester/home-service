<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import BookMarked from '@lucide/svelte/icons/book-marked';
    import CalendarRange from '@lucide/svelte/icons/calendar-range';
    import Clock3 from '@lucide/svelte/icons/clock-3';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const user = $derived(page.props.auth.user);
    const isAdmin = $derived(Boolean(user?.is_admin));

    const mainNavItems: NavItem[] = $derived(
        isAdmin
            ? [
                  { title: 'Bookings', href: '/bookings', icon: BookMarked },
                  { title: 'Schedules', href: '/schedules', icon: CalendarRange },
              ]
            : [
                  { title: 'Book a Service', href: '/book-a-service', icon: BookMarked },
                  { title: 'History', href: '/history', icon: Clock3 },
              ],
    );
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
