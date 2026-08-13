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
    showFooter = true,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
    showFooter?: boolean;
}>) {
    return (
        <div className="relative flex min-h-svh flex-col overflow-hidden bg-[#f8f6f2] text-[#211d29] dark:bg-[#17131c] dark:text-[#f7f2ff]">
            <div
                className="pointer-events-none absolute inset-0 [background-image:linear-gradient(to_right,rgba(33,29,41,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(33,29,41,0.06)_1px,transparent_1px)] [mask-image:linear-gradient(to_bottom,black,transparent)] [background-size:4rem_4rem] opacity-60 dark:opacity-20"
                aria-hidden="true"
            />
            <div
                className="pointer-events-none absolute -top-32 right-[-10rem] size-[34rem] rounded-full bg-[#e9d5ff]/70 blur-3xl dark:bg-[#7e22ce]/20"
                aria-hidden="true"
            />
            <div
                className="pointer-events-none absolute -bottom-40 left-[-10rem] size-[30rem] rounded-full bg-[#d9f99d]/60 blur-3xl dark:bg-[#65a30d]/10"
                aria-hidden="true"
            />

            <main className="relative flex flex-1 items-center justify-center p-6 md:p-10">
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
            {showFooter && <SiteFooter />}
        </div>
    );
}
