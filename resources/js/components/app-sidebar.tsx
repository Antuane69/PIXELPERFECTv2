import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { mainNavItems, visibleNavItems } from '@/components/app-navigation';
import { EmpresaSwitcher } from '@/components/empresa-switcher';
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
    const { auth } = usePage().props;
    const { empresas } = usePage().props;
    const permissions = auth.user?.permissions ?? [];
    const navigationItems = mainNavItems(empresas.activa?.slug);
    const items = visibleNavItems(
        navigationItems,
        permissions,
        auth.user?.es_superadministrador_plataforma ?? false,
        empresas.activa?.modulos ?? [],
    );

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
                            <Link href={navigationItems[0].href} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <EmpresaSwitcher />
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
