import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
    description,
    actions,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <header className="flex min-h-16 shrink-0 items-center gap-3 border-b border-sidebar-border/50 px-6 py-3 transition-[width,height] ease-linear md:px-4">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs
                    breadcrumbs={breadcrumbs}
                    description={description}
                />
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                    {actions}
                </div>
            ) : null}
        </header>
    );
}
