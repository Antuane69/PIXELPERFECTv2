import { Head, setLayoutProps } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import { edit as editAppearance } from '@/routes/appearance';
import type { AppLayoutProps } from '@/types';

export default function Appearance() {
    setLayoutProps<AppLayoutProps>({
        headerDescription: 'Elige cómo quieres ver tu cuenta',
        headerActions: undefined,
    });

    return (
        <>
            <Head title="Configuración de apariencia" />

            <h1 className="sr-only">Configuración de apariencia</h1>

            <div className="space-y-6">
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Configuración de apariencia',
            href: editAppearance(),
        },
    ],
};
