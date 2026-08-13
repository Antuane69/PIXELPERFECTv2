import { setLayoutProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { AppLayoutProps } from '@/types';

type ResourceHeaderProps = {
    title: string;
    description: string;
    actions?: ReactNode;
};

export function ResourceHeader({ description, actions }: ResourceHeaderProps) {
    setLayoutProps<AppLayoutProps>({
        headerDescription: description,
        headerActions: actions,
    });

    return null;
}
