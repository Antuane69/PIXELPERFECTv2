import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    headerDescription,
    headerActions,
    children,
}: AppLayoutProps) {
    return (
        <AppLayoutTemplate
            breadcrumbs={breadcrumbs}
            headerDescription={headerDescription}
            headerActions={headerActions}
        >
            {children}
        </AppLayoutTemplate>
    );
}
