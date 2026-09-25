import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

const rootNavButtonClassName =
    'h-auto min-h-8 overflow-visible! [&>span:last-child]:flex-1 [&>span:last-child]:overflow-visible! [&>span:last-child]:text-clip! [&>span:last-child]:whitespace-normal!';
const nestedNavButtonClassName =
    'h-auto min-h-7 w-full overflow-visible! [&>span:last-child]:flex-1 [&>span:last-child]:overflow-visible! [&>span:last-child]:text-clip! [&>span:last-child]:whitespace-normal!';
const navLabelClassName =
    'min-w-0 flex-1 break-words whitespace-normal group-data-[collapsible=icon]:hidden';
const nestedNavLabelClassName = 'min-w-0 flex-1 break-words whitespace-normal';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Administración</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <NavEntry key={item.title} item={item} depth={0} />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function NavEntry({ item, depth }: { item: NavItem; depth: number }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { state: sidebarState, isMobile } = useSidebar();
    const hasActiveRoute = (entry: NavItem): boolean =>
        (entry.href ? isCurrentOrParentUrl(entry.href) : false) ||
        (entry.children?.some(hasActiveRoute) ?? false);
    const active = item.href ? isCurrentUrl(item.href) : false;
    const branchActive = hasActiveRoute(item);
    const [openState, setOpenState] = useState({
        activeRoute: branchActive,
        open: branchActive,
    });
    const open =
        openState.activeRoute === branchActive
            ? openState.open
            : branchActive || openState.open;
    const updateOpen = (isOpen: boolean) => {
        setOpenState({ activeRoute: branchActive, open: isOpen });
    };

    if (item.children?.length) {
        if (depth === 0 && sidebarState === 'collapsed' && !isMobile) {
            return (
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <SidebarMenuButton
                                isActive={branchActive}
                                tooltip={{ children: item.title }}
                                aria-label={item.title}
                                className={rootNavButtonClassName}
                            >
                                {item.icon && <item.icon />}
                                <span className={navLabelClassName}>
                                    {item.title}
                                </span>
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            side="right"
                            align="start"
                            className="max-h-[calc(100vh-2rem)] w-56 overflow-y-auto"
                        >
                            <DropdownMenuLabel>{item.title}</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {item.children.map((child) => (
                                <NavFlyoutEntry
                                    key={child.title}
                                    item={child}
                                />
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            );
        }

        const contents = (
            <>
                <CollapsibleTrigger asChild>
                    {depth === 0 ? (
                        <SidebarMenuButton
                            isActive={branchActive}
                            tooltip={{ children: item.title }}
                            aria-expanded={open}
                            className={rootNavButtonClassName}
                        >
                            {item.icon && <item.icon />}
                            <span className={navLabelClassName}>
                                {item.title}
                            </span>
                            <ChevronDown className="ml-auto transition-transform data-[state=open]:rotate-180" />
                        </SidebarMenuButton>
                    ) : (
                        <SidebarMenuSubButton
                            asChild
                            isActive={branchActive}
                            className={nestedNavButtonClassName}
                        >
                            <button type="button" aria-expanded={open}>
                                {item.icon && <item.icon />}
                                <span className={nestedNavLabelClassName}>
                                    {item.title}
                                </span>
                                <ChevronDown className="ml-auto transition-transform data-[state=open]:rotate-180" />
                            </button>
                        </SidebarMenuSubButton>
                    )}
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {item.children.map((child) => (
                            <NavEntry
                                key={child.title}
                                item={child}
                                depth={depth + 1}
                            />
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </>
        );

        return (
            <Collapsible asChild open={open} onOpenChange={updateOpen}>
                {depth === 0 ? (
                    <SidebarMenuItem>{contents}</SidebarMenuItem>
                ) : (
                    <SidebarMenuSubItem>{contents}</SidebarMenuSubItem>
                )}
            </Collapsible>
        );
    }

    if (!item.href) {
        return null;
    }

    return depth === 0 ? (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: item.title }}
                className={rootNavButtonClassName}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span className={navLabelClassName}>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    ) : (
        <SidebarMenuSubItem>
            <SidebarMenuSubButton
                asChild
                isActive={active}
                className={nestedNavButtonClassName}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span className={nestedNavLabelClassName}>
                        {item.title}
                    </span>
                </Link>
            </SidebarMenuSubButton>
        </SidebarMenuSubItem>
    );
}

function NavFlyoutEntry({ item }: { item: NavItem }) {
    const { isCurrentUrl } = useCurrentUrl();

    if (item.children?.length) {
        return (
            <DropdownMenuSub>
                <DropdownMenuSubTrigger className="gap-2 [&>svg]:size-4">
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </DropdownMenuSubTrigger>
                <DropdownMenuSubContent className="w-56">
                    {item.children.map((child) => (
                        <NavFlyoutEntry key={child.title} item={child} />
                    ))}
                </DropdownMenuSubContent>
            </DropdownMenuSub>
        );
    }

    if (!item.href) {
        return null;
    }

    const active = isCurrentUrl(item.href);

    return (
        <DropdownMenuItem
            asChild
            className={active ? 'bg-accent text-accent-foreground' : undefined}
        >
            <Link href={item.href} prefetch>
                {item.icon && <item.icon />}
                <span>{item.title}</span>
            </Link>
        </DropdownMenuItem>
    );
}
