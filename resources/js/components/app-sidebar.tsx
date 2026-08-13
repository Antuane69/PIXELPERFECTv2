import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { mainNavItems, visibleNavItems } from '@/components/app-navigation';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

export function AppSidebar() {
    const permissions = usePage().props.auth.user?.permissions ?? [];
    const items = visibleNavItems(mainNavItems, permissions);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="group-data-[collapsible=icon]/sidebar-wrapper:p-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="transition-[width,height,padding,background-color] duration-300 ease-in-out group-data-[collapsible=icon]:h-16! group-data-[collapsible=icon]:w-full! group-data-[collapsible=icon]:justify-center! group-data-[collapsible=icon]:overflow-visible! group-data-[collapsible=icon]:rounded-none! group-data-[collapsible=icon]:p-0! hover:bg-transparent! hover:text-sidebar-foreground! motion-reduce:transition-none"
                        >
                            <Link href={mainNavItems[0].href} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
