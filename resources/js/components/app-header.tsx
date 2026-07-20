import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { mainNavItems, visibleNavItems } from '@/components/app-navigation';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const activeItemStyles =
    'text-foreground bg-accent/80 dark:bg-accent dark:text-accent-foreground';

export function AppHeader({ breadcrumbs = [] }: Props) {
    const { auth } = usePage().props;
    const items = visibleNavItems(mainNavItems, auth.user?.permissions ?? []);
    const getInitials = useInitials();
    const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();

    return (
        <>
            <div className="border-b border-sidebar-border/80 bg-card/85 backdrop-blur">
                <div className="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mr-2 size-9"
                                    aria-label="Abrir navegación"
                                >
                                    <Menu className="size-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="flex h-full w-72 flex-col bg-sidebar"
                            >
                                <SheetTitle className="sr-only">
                                    Menú de navegación
                                </SheetTitle>
                                <SheetHeader className="border-b pb-4 text-left">
                                    <AppLogo />
                                </SheetHeader>
                                <nav className="grid gap-1 p-4">
                                    {items.map((item) => (
                                        <Link
                                            key={item.title}
                                            href={item.href}
                                            prefetch
                                            className={cn(
                                                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium',
                                                whenCurrentUrl(
                                                    item.href,
                                                    activeItemStyles,
                                                    'hover:bg-accent/60',
                                                ),
                                            )}
                                        >
                                            {item.icon && (
                                                <item.icon className="size-5" />
                                            )}
                                            {item.title}
                                        </Link>
                                    ))}
                                </nav>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link
                        href={mainNavItems[0].href}
                        prefetch
                        className="flex items-center gap-2"
                    >
                        <AppLogo />
                    </Link>

                    <NavigationMenu className="ml-6 hidden h-full items-stretch lg:flex">
                        <NavigationMenuList className="flex h-full items-stretch gap-1">
                            {items.map((item) => (
                                <NavigationMenuItem
                                    key={item.title}
                                    className="relative flex h-full items-center"
                                >
                                    <Link
                                        href={item.href}
                                        prefetch
                                        className={cn(
                                            navigationMenuTriggerStyle(),
                                            whenCurrentUrl(
                                                item.href,
                                                activeItemStyles,
                                            ),
                                            'h-9 cursor-pointer px-3',
                                        )}
                                    >
                                        {item.icon && (
                                            <item.icon className="mr-2 size-4" />
                                        )}
                                        {item.title}
                                    </Link>
                                    {isCurrentUrl(item.href) && (
                                        <span className="absolute right-2 bottom-0 left-2 h-0.5 rounded-full bg-primary" />
                                    )}
                                </NavigationMenuItem>
                            ))}
                        </NavigationMenuList>
                    </NavigationMenu>

                    <div className="ml-auto">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="size-10 rounded-full p-1"
                                >
                                    <Avatar className="size-8">
                                        <AvatarImage
                                            src={auth.user?.avatar}
                                            alt={auth.user?.name}
                                        />
                                        <AvatarFallback>
                                            {getInitials(auth.user?.name ?? '')}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                {auth.user && (
                                    <UserMenuContent user={auth.user} />
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
            {breadcrumbs.length > 1 && (
                <div className="flex w-full border-b border-sidebar-border/70">
                    <div className="mx-auto flex h-12 w-full items-center px-4 md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}
