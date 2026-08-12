import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BriefcaseBusiness,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { SiteFooter } from '@/components/site-footer';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Inicio" />
            <div className="flex min-h-svh flex-col">
                <main className="relative flex flex-1 items-center overflow-hidden p-6 sm:p-10">
                    <div className="absolute -top-40 -left-32 size-96 rounded-full bg-primary/10 blur-3xl" />
                    <div className="absolute -right-36 -bottom-44 size-[30rem] rounded-full bg-accent/60 blur-3xl" />

                    <div className="relative mx-auto grid w-full max-w-6xl items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
                        <section className="grid gap-7">
                            <img
                                src="/brand/pixel-perfect-banner.png"
                                alt="Pixel Perfect"
                                className="h-28 w-auto max-w-full object-contain object-left"
                            />
                            <div className="grid gap-4">
                                <p className="text-sm font-semibold tracking-[0.18em] text-primary uppercase">
                                    Administración de negocios
                                </p>
                                <h1 className="max-w-3xl text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-6xl">
                                    Tu operación, clara y organizada.
                                </h1>
                                <p className="max-w-2xl text-lg text-pretty text-muted-foreground">
                                    Gestiona personal, accesos y catálogos desde
                                    una experiencia segura, rápida y
                                    consistente.
                                </p>
                            </div>
                            <div>
                                <Button asChild size="lg">
                                    <Link
                                        href={auth.user ? dashboard() : login()}
                                        prefetch
                                    >
                                        {auth.user
                                            ? 'Ir al dashboard'
                                            : 'Iniciar sesión'}
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            </div>
                        </section>

                        <Card className="border-primary/15 bg-card/88 shadow-xl shadow-primary/5 backdrop-blur">
                            <CardContent className="grid gap-4">
                                {[
                                    {
                                        icon: UsersRound,
                                        title: 'Expedientes centralizados',
                                        description:
                                            'Personal y documentación en un solo lugar.',
                                    },
                                    {
                                        icon: ShieldCheck,
                                        title: 'Acceso por permisos',
                                        description:
                                            'Cada persona ve sólo lo que necesita.',
                                    },
                                    {
                                        icon: BriefcaseBusiness,
                                        title: 'Catálogos consistentes',
                                        description:
                                            'Puestos y requisitos siempre actualizados.',
                                    },
                                ].map((feature) => (
                                    <article
                                        key={feature.title}
                                        className="flex gap-4 rounded-xl border bg-background/65 p-4"
                                    >
                                        <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                            <feature.icon className="size-5" />
                                        </span>
                                        <div className="grid gap-1">
                                            <h2 className="font-medium">
                                                {feature.title}
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                {feature.description}
                                            </p>
                                        </div>
                                    </article>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </main>
                <SiteFooter />
            </div>
        </>
    );
}
