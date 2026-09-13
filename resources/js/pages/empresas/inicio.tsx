import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Building2,
    Layers3,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { index as empleadosIndex } from '@/actions/App/Http/Controllers/EmpleadoController';
import { index as puestosIndex } from '@/actions/App/Http/Controllers/PuestoController';
import { ResourceHeader } from '@/components/resource-header';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import type { EmpresaResumen, Modulo } from '@/types';

type Props = {
    empresa: EmpresaResumen;
    modulos: Modulo[];
    stats: {
        puestosActivos: number | null;
        empleados: number | null;
    };
};

export default function EmpresaInicio({ empresa, modulos, stats }: Props) {
    const { can } = usePermissions();

    return (
        <>
            <Head title={empresa.nombre} />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title={empresa.nombre}
                    description={`Contexto activo: ${empresa.nombre_legal}`}
                />

                <section className="grid gap-4 md:grid-cols-3">
                    <StatusCard
                        icon={Building2}
                        title="Empresa activa"
                        description={empresa.nombre_legal}
                    />
                    <StatusCard
                        icon={ShieldCheck}
                        title="Acceso validado"
                        description="Membresía y estado empresarial verificados en servidor."
                    />
                    <StatusCard
                        icon={Layers3}
                        title={`${modulos.length.toLocaleString('es-MX')} módulos habilitados`}
                        description="Acceso contratado validado en servidor antes de cada operación."
                    />
                </section>

                <section className="grid gap-4 md:grid-cols-2">
                    {stats.puestosActivos !== null && can('puestos.view') && (
                        <Card>
                            <Link
                                href={puestosIndex()}
                                prefetch
                                className="grid gap-4 p-6"
                            >
                                <CardHeader className="flex-row items-start justify-between gap-4 p-0">
                                    <div className="grid gap-1">
                                        <CardDescription>
                                            Puestos activos
                                        </CardDescription>
                                        <CardTitle className="text-3xl tabular-nums">
                                            {stats.puestosActivos.toLocaleString(
                                                'es-MX',
                                            )}
                                        </CardTitle>
                                    </div>
                                    <span className="flex size-11 items-center justify-center rounded-xl bg-fuchsia-500/12 text-fuchsia-700 dark:text-fuchsia-300">
                                        <BriefcaseBusiness className="size-5" />
                                    </span>
                                </CardHeader>
                                <CardContent className="p-0 text-sm text-muted-foreground">
                                    Catálogo laboral y salarios exclusivos de
                                    esta empresa.
                                </CardContent>
                            </Link>
                        </Card>
                    )}

                    {stats.empleados !== null && can('empleados.view') && (
                        <Card>
                            <Link
                                href={empleadosIndex()}
                                prefetch
                                className="grid gap-4 p-6"
                            >
                                <CardHeader className="flex-row items-start justify-between gap-4 p-0">
                                    <div className="grid gap-1">
                                        <CardDescription>
                                            Empleados
                                        </CardDescription>
                                        <CardTitle className="text-3xl tabular-nums">
                                            {stats.empleados.toLocaleString(
                                                'es-MX',
                                            )}
                                        </CardTitle>
                                    </div>
                                    <span className="flex size-11 items-center justify-center rounded-xl bg-emerald-500/12 text-emerald-700 dark:text-emerald-300">
                                        <UsersRound className="size-5" />
                                    </span>
                                </CardHeader>
                                <CardContent className="p-0 text-sm text-muted-foreground">
                                    Expedientes y documentos exclusivos de esta
                                    empresa.
                                </CardContent>
                            </Link>
                        </Card>
                    )}
                </section>

                <Card className="border-primary/20 bg-primary/5">
                    <CardHeader>
                        <CardTitle>Módulos disponibles</CardTitle>
                        <CardDescription>
                            Habilitación empresarial y permisos personales son
                            controles independientes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2">
                        {modulos.map((module) => (
                            <div
                                key={module.id}
                                className="rounded-lg border bg-background/70 p-4"
                            >
                                <p className="font-medium">{module.nombre}</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {module.descripcion}
                                </p>
                            </div>
                        ))}
                        {modulos.length === 0 ? (
                            <p className="text-sm text-muted-foreground sm:col-span-2">
                                Empresa sin módulos habilitados. Contacta al
                                administrador de plataforma.
                            </p>
                        ) : null}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function StatusCard({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof Building2;
    title: string;
    description: string;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Icon className="size-5" />
                </span>
                <div className="grid gap-1">
                    <CardTitle className="text-base">{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
            </CardHeader>
        </Card>
    );
}
