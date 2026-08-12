import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SiteFooter } from '@/components/site-footer';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh flex-col bg-muted">
            <main className="flex flex-1 items-center justify-center p-6 md:p-10">
                <div className="flex w-full max-w-md flex-col gap-6">
                    <div className="flex flex-col gap-6">
                        <Card className="overflow-hidden rounded-2xl shadow-md">
                            <CardHeader className="items-center gap-4 px-6 pt-8 pb-0 text-center sm:px-10 sm:pt-10">
                                <Link
                                    href={home()}
                                    aria-label="Ir al inicio de Pixel Perfect"
                                    className="group flex size-20 items-center justify-center rounded-full bg-white p-1 shadow-sm ring-1 ring-primary/15 transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <AppLogoIcon className="size-full object-contain" />
                                </Link>
                                <CardTitle className="text-xl">
                                    {title}
                                </CardTitle>
                                <CardDescription>{description}</CardDescription>
                            </CardHeader>
                            <CardContent className="px-10 py-8">
                                {children}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </main>
            <SiteFooter />
        </div>
    );
}
