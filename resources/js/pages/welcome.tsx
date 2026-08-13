import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Boxes,
    LockKeyhole,
    Sparkles,
    UsersRound,
} from 'lucide-react';
import { SiteFooter } from '@/components/site-footer';
import { Button } from '@/components/ui/button';
import { dashboard, home, login } from '@/routes';

const features = [
    {
        number: '01',
        icon: UsersRound,
        title: 'Personas en foco',
        description:
            'Expedientes claros, información disponible y una operación que fluye.',
        color: 'bg-[#d9f99d] text-[#26351b]',
    },
    {
        number: '02',
        icon: LockKeyhole,
        title: 'Control sin fricción',
        description:
            'Permisos y accesos definidos para que cada equipo vea lo que necesita.',
        color: 'bg-[#e9d5ff] text-[#421b5c]',
    },
    {
        number: '03',
        icon: Boxes,
        title: 'Sistema que escala',
        description:
            'Catálogos consistentes para construir una base lista para crecer.',
        color: 'bg-[#fed7aa] text-[#5c2b15]',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;
    const appHref = auth.user ? dashboard() : login();
    const appLabel = auth.user ? 'Ir al panel' : 'Iniciar sesión';

    return (
        <>
            <Head title="Inicio" />
            <div className="flex min-h-svh flex-col overflow-hidden bg-[#f8f6f2] text-[#211d29] dark:bg-[#17131c] dark:text-[#f7f2ff]">
                <header className="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-between gap-6 px-6 py-6 sm:px-10 lg:px-12">
                    <Link
                        href={home()}
                        aria-label="Pixel Perfect, inicio"
                        className="group flex items-center gap-3"
                    >
                        <span className="grid size-8 place-items-center rounded-full bg-[#211d29] text-[#f8f6f2] transition-transform group-hover:rotate-12 dark:bg-[#f7f2ff] dark:text-[#211d29]">
                            <Sparkles className="size-4" aria-hidden="true" />
                        </span>
                        <span className="flex items-baseline text-[1.15rem] leading-none tracking-[-0.08em]">
                            <span className="font-black">PIXEL</span>
                            <span className="font-serif font-semibold text-[#a855f7] italic dark:text-[#d8b4fe]">
                                PERFECT
                            </span>
                        </span>
                    </Link>

                    <nav
                        className="hidden items-center gap-8 text-sm font-medium text-[#6d6475] md:flex dark:text-[#b9acbf]"
                        aria-label="Navegación principal"
                    >
                        <a
                            href="#capacidades"
                            className="transition-colors hover:text-[#211d29] dark:hover:text-[#f7f2ff]"
                        >
                            Capacidades
                        </a>
                        <a
                            href="#contacto"
                            className="transition-colors hover:text-[#211d29] dark:hover:text-[#f7f2ff]"
                        >
                            Contacto
                        </a>
                    </nav>

                    <Button
                        asChild
                        variant="outline"
                        className="h-10 rounded-full border-[#211d29]/15 bg-white/50 px-5 text-[#211d29] shadow-none hover:bg-white dark:border-white/15 dark:bg-white/5 dark:text-[#f7f2ff] dark:hover:bg-white/10"
                    >
                        <Link href={appHref} prefetch>
                            {appLabel}
                            <ArrowUpRight aria-hidden="true" />
                        </Link>
                    </Button>
                </header>

                <main className="relative flex-1">
                    <div
                        className="pointer-events-none absolute inset-x-0 top-0 h-[38rem] [background-image:linear-gradient(to_right,rgba(33,29,41,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(33,29,41,0.06)_1px,transparent_1px)] [mask-image:linear-gradient(to_bottom,black,transparent)] [background-size:4rem_4rem] opacity-60 dark:opacity-20"
                        aria-hidden="true"
                    />
                    <div
                        className="pointer-events-none absolute -top-28 right-[-8rem] size-[34rem] rounded-full bg-[#e9d5ff]/70 blur-3xl dark:bg-[#7e22ce]/20"
                        aria-hidden="true"
                    />
                    <div
                        className="pointer-events-none absolute top-[28rem] left-[-12rem] size-[28rem] rounded-full bg-[#d9f99d]/60 blur-3xl dark:bg-[#65a30d]/10"
                        aria-hidden="true"
                    />

                    <section className="relative mx-auto grid w-full max-w-7xl items-center gap-14 px-6 pt-12 pb-20 sm:px-10 lg:grid-cols-[minmax(0,1fr)_minmax(26rem,0.88fr)] lg:gap-16 lg:px-12 lg:pt-20 lg:pb-28">
                        <div className="max-w-2xl">
                            <div className="mb-8 flex items-center gap-3 text-[0.68rem] font-bold tracking-[0.22em] text-[#a855f7] uppercase dark:text-[#d8b4fe]">
                                <span className="size-2 rounded-full bg-[#a855f7] shadow-[0_0_0_5px_rgba(168,85,247,0.12)] dark:bg-[#d8b4fe]" />
                                Operations studio · México
                            </div>

                            <h1 className="max-w-3xl text-[clamp(4rem,9vw,8rem)] leading-[0.84] font-black tracking-[-0.09em] text-balance">
                                Ordena el{' '}
                                <span className="font-serif font-semibold text-[#a855f7] italic dark:text-[#d8b4fe]">
                                    caos.
                                </span>
                            </h1>

                            <p className="mt-8 max-w-xl text-lg leading-8 text-pretty text-[#6d6475] sm:text-xl dark:text-[#b9acbf]">
                                Pixel Perfect convierte la operación diaria en
                                un sistema simple: personas, accesos y catálogos
                                en un mismo lugar.
                            </p>

                            <div className="mt-10 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                                <Button
                                    asChild
                                    size="lg"
                                    className="h-12 rounded-full bg-[#211d29] px-7 text-[#f8f6f2] shadow-xl shadow-[#211d29]/15 hover:bg-[#3c3446] dark:bg-[#f7f2ff] dark:text-[#211d29] dark:hover:bg-white"
                                >
                                    <Link href={appHref} prefetch>
                                        {appLabel}
                                        <ArrowUpRight aria-hidden="true" />
                                    </Link>
                                </Button>
                                <a
                                    href="#capacidades"
                                    className="inline-flex h-12 items-center rounded-full px-5 text-sm font-semibold text-[#6d6475] transition-colors hover:bg-white hover:text-[#211d29] dark:text-[#b9acbf] dark:hover:bg-white/10 dark:hover:text-[#f7f2ff]"
                                >
                                    Explorar el sistema
                                </a>
                            </div>

                            <div className="mt-12 flex flex-wrap items-center gap-x-6 gap-y-3 text-xs font-medium text-[#8d8292] dark:text-[#978a9e]">
                                <span className="flex items-center gap-2">
                                    <span className="size-1.5 rounded-full bg-[#84cc16]" />
                                    Seguro por diseño
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="size-1.5 rounded-full bg-[#a855f7]" />
                                    Hecho para equipos reales
                                </span>
                            </div>
                        </div>

                        <div className="relative mx-auto w-full max-w-xl lg:ml-auto">
                            <div
                                className="absolute -top-5 right-4 h-full w-full rotate-3 rounded-[2rem] bg-[#d9f99d] dark:bg-[#4d7c0f]"
                                aria-hidden="true"
                            />
                            <div
                                className="absolute -bottom-5 -left-4 h-full w-full -rotate-2 rounded-[2rem] bg-[#e9d5ff] dark:bg-[#6b21a8]"
                                aria-hidden="true"
                            />

                            <div className="relative rounded-[2rem] bg-[#211d29] p-2 shadow-2xl shadow-[#211d29]/20 dark:bg-[#302639]">
                                <div className="overflow-hidden rounded-[1.5rem] bg-[#332b3d] p-5 text-[#f8f6f2] sm:p-7">
                                    <div className="flex items-center justify-between border-b border-white/10 pb-5">
                                        <div className="flex items-center gap-2.5">
                                            <span className="grid size-7 place-items-center rounded-lg bg-[#d9f99d] text-[#26351b]">
                                                <Sparkles
                                                    className="size-3.5"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <span className="text-[0.65rem] font-bold tracking-[0.18em] text-white/75 uppercase">
                                                Pixel / Control panel
                                            </span>
                                        </div>
                                        <span className="text-xs text-white/40">
                                            09:41
                                        </span>
                                    </div>

                                    <div className="grid gap-3 py-5 sm:grid-cols-2">
                                        <div className="rounded-2xl bg-[#d9f99d] p-5 text-[#26351b]">
                                            <div className="flex items-start justify-between gap-3">
                                                <span className="text-xs font-semibold uppercase opacity-70">
                                                    Personas activas
                                                </span>
                                                <UsersRound
                                                    className="size-4 opacity-70"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                            <div className="mt-8 text-4xl leading-none font-black tracking-[-0.08em]">
                                                248
                                            </div>
                                            <div className="mt-2 text-xs font-medium opacity-70">
                                                +12% este mes
                                            </div>
                                        </div>
                                        <div className="rounded-2xl bg-[#c4b5fd] p-5 text-[#302056]">
                                            <div className="flex items-start justify-between gap-3">
                                                <span className="text-xs font-semibold uppercase opacity-70">
                                                    Accesos seguros
                                                </span>
                                                <LockKeyhole
                                                    className="size-4 opacity-70"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                            <div className="mt-8 text-4xl leading-none font-black tracking-[-0.08em]">
                                                100%
                                            </div>
                                            <div className="mt-2 text-xs font-medium opacity-70">
                                                Todo bajo control
                                            </div>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-white/10 bg-white/5 p-5">
                                        <div className="mb-5 flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-semibold">
                                                    Actividad reciente
                                                </p>
                                                <p className="mt-1 text-xs text-white/45">
                                                    Vista general de tu
                                                    operación
                                                </p>
                                            </div>
                                            <span className="rounded-full bg-white/10 px-3 py-1 text-[0.65rem] font-semibold text-white/60">
                                                En vivo
                                            </span>
                                        </div>
                                        <div className="grid gap-4">
                                            {[
                                                [
                                                    'Nuevo expediente creado',
                                                    'Hace 4 min',
                                                    'bg-[#f9a8d4]',
                                                ],
                                                [
                                                    'Permiso actualizado',
                                                    'Hace 18 min',
                                                    'bg-[#fdba74]',
                                                ],
                                                [
                                                    'Catálogo sincronizado',
                                                    'Hace 32 min',
                                                    'bg-[#a5b4fc]',
                                                ],
                                            ].map(([label, time, color]) => (
                                                <div
                                                    key={label}
                                                    className="flex items-center justify-between gap-4 text-xs"
                                                >
                                                    <div className="flex min-w-0 items-center gap-3">
                                                        <span
                                                            className={`size-2 shrink-0 rounded-full ${color}`}
                                                        />
                                                        <span className="truncate text-white/80">
                                                            {label}
                                                        </span>
                                                    </div>
                                                    <span className="shrink-0 text-white/35">
                                                        {time}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="absolute -right-3 -bottom-7 rounded-2xl border border-[#211d29]/10 bg-white/90 px-4 py-3 shadow-xl shadow-[#211d29]/10 backdrop-blur sm:-right-8 dark:border-white/10 dark:bg-[#2b2333]/90">
                                <p className="text-[0.6rem] font-bold tracking-[0.18em] text-[#a855f7] uppercase dark:text-[#d8b4fe]">
                                    Designed for clarity
                                </p>
                                <p className="mt-1 text-sm font-semibold">
                                    La operación también puede verse bien.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        id="capacidades"
                        className="relative mx-auto w-full max-w-7xl scroll-mt-8 px-6 pb-20 sm:px-10 lg:px-12 lg:pb-28"
                    >
                        <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                            <div>
                                <p className="text-[0.68rem] font-bold tracking-[0.22em] text-[#a855f7] uppercase dark:text-[#d8b4fe]">
                                    El sistema
                                </p>
                                <h2 className="mt-3 text-3xl font-bold tracking-[-0.06em] sm:text-4xl">
                                    Menos ruido. Más avance.
                                </h2>
                            </div>
                            <p className="max-w-sm text-sm leading-6 text-[#6d6475] dark:text-[#b9acbf]">
                                Una base de operación pensada para quitar pasos
                                innecesarios y hacer visible lo importante.
                            </p>
                        </div>

                        <div className="grid gap-3 md:grid-cols-3">
                            {features.map((feature) => (
                                <article
                                    key={feature.number}
                                    className="group rounded-[1.5rem] border border-[#211d29]/10 bg-white/65 p-6 transition-transform hover:-translate-y-1 dark:border-white/10 dark:bg-white/5"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <span
                                            className={`grid size-11 place-items-center rounded-2xl ${feature.color}`}
                                        >
                                            <feature.icon
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span className="font-mono text-xs text-[#a49aa9] dark:text-[#8f8197]">
                                            / {feature.number}
                                        </span>
                                    </div>
                                    <h3 className="mt-8 text-xl font-bold tracking-[-0.04em]">
                                        {feature.title}
                                    </h3>
                                    <p className="mt-3 text-sm leading-6 text-[#6d6475] dark:text-[#b9acbf]">
                                        {feature.description}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>
                </main>

                <div id="contacto" className="scroll-mt-8">
                    <SiteFooter />
                </div>
            </div>
        </>
    );
}
