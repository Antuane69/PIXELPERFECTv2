import { createInertiaApp } from '@inertiajs/react';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout, { TwoFactorAuthLayout } from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { AppAlertsProvider } from '@/lib/app-alerts';
import { GlobalLoaderProvider } from '@/lib/global-loader';

const appName = import.meta.env.VITE_APP_NAME || 'Pixel Perfect';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name === 'auth/two-factor-challenge':
                return TwoFactorAuthLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <AppAlertsProvider>
                    <GlobalLoaderProvider>{app}</GlobalLoaderProvider>
                </AppAlertsProvider>
            </TooltipProvider>
        );
    },
    progress: {
        color: '#9f67b4',
    },
});

// This will set light / dark mode on load...
initializeTheme();
