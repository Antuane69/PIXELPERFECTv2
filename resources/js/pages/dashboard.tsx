import { Head, Link } from '@inertiajs/react';
import {
    BadgeDollarSign,
    Boxes,
    Building2,
    FileText,
    KeyRound,
    UserRoundCog,
} from 'lucide-react';
import { ResourceHeader } from '@/components/resource-header';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as logsIndex } from '@/routes/logs';
import { index as empresasIndex } from '@/routes/platform/empresas';
import { index as modulosIndex } from '@/routes/platform/modulos';
import { index as permisosIndex } from '@/routes/platform/permisos';
import { index as planesIndex } from '@/routes/platform/planes';
import { index as usuariosIndex } from '@/routes/platform/usuarios';
import type { DashboardStats } from '@/types';

type Props = {
    stats: DashboardStats;
};

export default function Dashboard({ stats }: Props) {
    const cards = [
        {
            title: 'Empresas',
            description: 'Organizaciones registradas',
            value: stats.empresas,
            icon: Building2,
            href: empresasIndex(),
            accent: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
        },
        {
            title: 'Usuarios',
            description: 'Cuentas con acceso al sistema',
            value: stats.users,
            icon: UserRoundCog,
            href: usuariosIndex(),
            accent: 'bg-violet-500/12 text-violet-700 dark:text-violet-300',
        },
        {
            title: 'Planes',
            description: 'Oferta comercial configurada',
            value: stats.planes,
            icon: BadgeDollarSign,
            href: planesIndex(),
            accent: 'bg-teal-500/12 text-teal-700 dark:text-teal-300',
        },
        {
            title: 'Módulos',
            description: 'Dominios asignables a empresas',
            value: stats.modules,
            icon: Boxes,
            href: modulosIndex(),
            accent: 'bg-blue-500/12 text-blue-700 dark:text-blue-300',
        },
        {
            title: 'Permisos',
            description: 'Capacidades disponibles',
            value: stats.permissions,
            icon: KeyRound,
            href: permisosIndex(),
            accent: 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Dashboard"
                    description="Una vista rápida de la operación de Pixel Perfect."
                />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {cards.map((card) => {
                        return (
                            <Card
                                key={card.title}
                                className="group overflow-hidden py-0 transition-transform hover:-translate-y-0.5"
                            >
                                <Link
                                    href={card.href}
                                    prefetch
                                    className="grid gap-5 p-6"
                                >
                                    <CardHeader className="flex-row items-start justify-between gap-4 px-0">
                                        <div className="grid gap-1.5">
                                            <CardDescription>
                                                {card.title}
                                            </CardDescription>
                                            <CardTitle className="text-3xl tabular-nums">
                                                {card.value.toLocaleString(
                                                    'es-MX',
                                                )}
                                            </CardTitle>
                                        </div>
                                        <span
                                            className={`flex size-11 items-center justify-center rounded-xl ${card.accent}`}
                                        >
                                            <card.icon className="size-5" />
                                        </span>
                                    </CardHeader>
                                    <CardContent className="px-0 text-sm text-muted-foreground">
                                        {card.description}
                                    </CardContent>
                                </Link>
                            </Card>
                        );
                    })}
                </section>

                <Card>
                    <Link
                        href={logsIndex()}
                        className="flex items-center gap-4 p-6"
                    >
                        <span className="flex size-11 items-center justify-center rounded-xl bg-rose-500/12 text-rose-700 dark:text-rose-300">
                            <FileText className="size-5" />
                        </span>
                        <div className="grid gap-1">
                            <CardTitle className="text-base">
                                Logs del sistema
                            </CardTitle>
                            <CardDescription>
                                Consulta actividad técnica y errores de
                                plataforma.
                            </CardDescription>
                        </div>
                    </Link>
                </Card>

                <Card className="border-primary/15 bg-gradient-to-br from-card via-card to-accent/45">
                    <CardHeader>
                        <CardTitle>Centro de administración</CardTitle>
                        <CardDescription>
                            Usa el menú lateral para gestionar personal, accesos
                            y catálogos desde un solo lugar.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </main>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
