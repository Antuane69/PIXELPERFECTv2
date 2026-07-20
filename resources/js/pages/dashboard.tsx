import { Head, Link } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    FileCheck2,
    UserRoundCog,
    UsersRound,
} from 'lucide-react';
import { index as empleadosIndex } from '@/actions/App/Http/Controllers/EmpleadoController';
import { index as puestosIndex } from '@/actions/App/Http/Controllers/PuestoController';
import { index as tiposDocumentoIndex } from '@/actions/App/Http/Controllers/TipoDocumentoEmpleadoController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/UserController';
import { ResourceHeader } from '@/components/resource-header';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import type { DashboardStats } from '@/types';

type Props = {
    stats: DashboardStats;
};

export default function Dashboard({ stats }: Props) {
    const { can } = usePermissions();
    const cards = [
        {
            title: 'Usuarios',
            description: 'Cuentas con acceso al sistema',
            value: stats.users,
            icon: UserRoundCog,
            href: usersIndex(),
            permission: 'users.view',
            accent: 'bg-violet-500/12 text-violet-700 dark:text-violet-300',
        },
        {
            title: 'Empleados',
            description: 'Expedientes registrados',
            value: stats.empleados,
            icon: UsersRound,
            href: empleadosIndex(),
            permission: 'empleados.view',
            accent: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
        },
        {
            title: 'Puestos',
            description: 'Puestos del catálogo laboral',
            value: stats.puestosActivos,
            icon: BriefcaseBusiness,
            href: puestosIndex(),
            permission: 'puestos.view',
            accent: 'bg-fuchsia-500/12 text-fuchsia-700 dark:text-fuchsia-300',
        },
        {
            title: 'Tipos de documento',
            description: 'Requisitos documentales activos',
            value: stats.tiposDocumentoActivos,
            icon: FileCheck2,
            href: tiposDocumentoIndex(),
            permission: 'tipos_documento.view',
            accent: 'bg-teal-500/12 text-teal-700 dark:text-teal-300',
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

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => {
                        if (card.value === null || !can(card.permission)) {
                            return null;
                        }

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
