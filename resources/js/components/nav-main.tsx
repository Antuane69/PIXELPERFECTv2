import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

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
        const contents = (
            <>
                <CollapsibleTrigger asChild>
                    {depth === 0 ? (
                        <SidebarMenuButton
                            isActive={branchActive}
                            tooltip={{ children: item.title }}
                            aria-expanded={open}
                        >
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                            <ChevronDown className="ml-auto transition-transform data-[state=open]:rotate-180" />
                        </SidebarMenuButton>
                    ) : (
                        <SidebarMenuSubButton asChild isActive={branchActive}>
                            <button type="button" aria-expanded={open}>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
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
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    ) : (
        <SidebarMenuSubItem>
            <SidebarMenuSubButton asChild isActive={active}>
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuSubButton>
        </SidebarMenuSubItem>
    );
}
