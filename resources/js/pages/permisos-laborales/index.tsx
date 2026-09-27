import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, Check, Plus, X } from 'lucide-react';
import { useState } from 'react';
import {
    autorizar as autorizarPermiso,
    cambiarPagina as cambiarPaginaPermisos,
    filtrar as filtrarPermisos,
    index as permisosLaboralesIndex,
    rechazar as rechazarPermiso,
    store as storePermiso,
} from '@/actions/App/Http/Controllers/PermisosLaborales/PermisoLaboralController';
import { index as tiposPermisosIndex } from '@/actions/App/Http/Controllers/PermisosLaborales/TipoPermisoController';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { PermisoLaboralFormDialog } from '@/features/permisos-laborales/permiso-laboral-form-dialog';
import { PermisoLaboralRechazarDialog } from '@/features/permisos-laborales/permiso-laboral-rechazar-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import type {
    LaravelPaginator,
    PermisoLaboral,
    PermisoLaboralEmpleadoOption,
    PermisoLaboralFilters,
    PermisoLaboralPermissionProps,
    TipoPermisoOption,
} from '@/types';

type PermisosLaboralesIndexProps = {
    permisos: LaravelPaginator<PermisoLaboral>;
    proximas: LaravelPaginator<PermisoLaboral> | null;
    filters: PermisoLaboralFilters;
    empleados: PermisoLaboralEmpleadoOption[];
    empleadosCobertura: PermisoLaboralEmpleadoOption[];
    tiposPermiso: TipoPermisoOption[];
    selfEmployeeId: number | null;
    permissions: PermisoLaboralPermissionProps;
};

const statusFacet: FilterFacet = {
    key: 'estado',
    label: 'Estatus',
    options: [
        { value: 'PENDIENTE', label: 'Pendientes' },
        { value: 'AUTORIZADO', label: 'Autorizados' },
        { value: 'VENCIDO', label: 'Vencidos' },
        { value: 'RECHAZADO', label: 'Rechazados' },
    ],
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function StatusBadge({ permiso }: { permiso: PermisoLaboral }) {
    const variant =
        permiso.estado === 'RECHAZADO'
            ? 'destructive'
            : permiso.estado === 'AUTORIZADO'
              ? 'secondary'
              : 'outline';

    return <Badge variant={variant}>{permiso.estadoLabel}</Badge>;
}

function PermisoLaboralDetails({ permiso }: { permiso: PermisoLaboral }) {
    return (
        <div className="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-3">
            <div className="grid gap-1">
                <span className="text-muted-foreground">Tipo de permiso</span>
                <span>{permiso.tipoPermiso ?? 'Sin tipo registrado'}</span>
            </div>
            <div className="grid gap-1">
                <span className="text-muted-foreground">Solicitante</span>
                <span>{permiso.solicitante}</span>
            </div>
            <div className="grid gap-1">
                <span className="text-muted-foreground">Cobertura</span>
                <span>
                    {permiso.empleadosCobertura.length > 0
                        ? permiso.empleadosCobertura.join(', ')
                        : 'Sin cobertura registrada'}
                </span>
            </div>
            {permiso.comentarios ? (
                <div className="grid gap-1 md:col-span-2">
                    <span className="text-muted-foreground">
                        Comentarios de la solicitud
                    </span>
                    <p className="whitespace-pre-wrap">{permiso.comentarios}</p>
                </div>
            ) : null}
            {permiso.comentariosRechazo ? (
                <div className="grid gap-1 md:col-span-2">
                    <span className="text-muted-foreground">
                        Motivo del rechazo
                    </span>
                    <p className="whitespace-pre-wrap">
                        {permiso.comentariosRechazo}
                    </p>
                </div>
            ) : null}
            {permiso.resueltoPor ? (
                <div className="grid gap-1">
                    <span className="text-muted-foreground">Revisó</span>
                    <span>{permiso.resueltoPor}</span>
                </div>
            ) : null}
        </div>
    );
}

export default function PermisosLaboralesIndex({
    permisos,
    proximas,
    filters,
    empleados,
    empleadosCobertura,
    tiposPermiso,
    selfEmployeeId,
    permissions,
}: PermisosLaboralesIndexProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [rejectingPermiso, setRejectingPermiso] =
        useState<PermisoLaboral | null>(null);
    const [expandedPermiso, setExpandedPermiso] = useState<number | null>(null);
    const [expandedUpcoming, setExpandedUpcoming] = useState<number | null>(
        null,
    );
    const [resolvingIds, setResolvingIds] = useState<number[]>([]);
    const { can } = usePermissions();

    const canSubmitForSelf =
        permissions.canCreateForOthers || selfEmployeeId !== null;
    const canOpenRequest =
        permissions.canCreate &&
        canSubmitForSelf &&
        empleados.length > 0 &&
        tiposPermiso.length > 0;

    const setPage = (page: number, listado: 'solicitudes' | 'proximas') => {
        router.post(
            cambiarPaginaPermisos().url,
            { page, listado },
            { preserveScroll: true, preserveState: true },
        );
    };

    const authorize = (permiso: PermisoLaboral) => {
        setResolvingIds((current) => [...current, permiso.id]);
        router.post(
            autorizarPermiso(permiso.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () =>
                    setResolvingIds((current) =>
                        current.filter((id) => id !== permiso.id),
                    ),
            },
        );
    };

    const columns: ResourceColumn<PermisoLaboral>[] = [
        {
            key: 'empleado',
            header: 'Empleado',
            cell: (permiso) => (
                <span className="font-medium">{permiso.empleado}</span>
            ),
        },
        {
            key: 'tipoPermiso',
            header: 'Tipo de permiso',
            cell: (permiso) => permiso.tipoPermiso ?? 'Sin tipo registrado',
        },
        {
            key: 'periodo',
            header: 'Periodo',
            cell: (permiso) => (
                <span className="whitespace-nowrap">
                    {formatDate(permiso.fechaInicio)} –{' '}
                    {formatDate(permiso.fechaFin)}
                </span>
            ),
        },
        {
            key: 'cobertura',
            header: 'Cobertura',
            mobileHidden: true,
            cell: (permiso) => (
                <span className="block max-w-48 truncate">
                    {permiso.empleadosCobertura.join(', ') || '—'}
                </span>
            ),
        },
        {
            key: 'estado',
            header: 'Estatus',
            cell: (permiso) => <StatusBadge permiso={permiso} />,
        },
        {
            key: 'solicitante',
            header: 'Solicitante',
            mobileHidden: true,
            cell: (permiso) => permiso.solicitante,
        },
        {
            key: 'acciones',
            header: 'Acciones',
            cell: (permiso) =>
                permiso.puedeResolver ? (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => authorize(permiso)}
                            disabled={resolvingIds.includes(permiso.id)}
                            data-row-click-ignore
                        >
                            <Check aria-hidden="true" />
                            Autorizar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setRejectingPermiso(permiso)}
                            data-row-click-ignore
                        >
                            <X aria-hidden="true" />
                            Rechazar
                        </Button>
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <>
            <Head title="Permisos laborales" />
            <ResourceHeader
                title="Permisos laborales"
                description="Consulta y solicita permisos laborales, y revisa los periodos próximos de la empresa."
                actions={
                    permissions.canCreate ? (
                        <Button
                            type="button"
                            onClick={() => setFormOpen(true)}
                            disabled={!canOpenRequest}
                        >
                            <Plus aria-hidden="true" />
                            Solicitar permiso
                        </Button>
                    ) : null
                }
            />

            {!canSubmitForSelf && permissions.canCreate ? (
                <p
                    className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                    role="status"
                >
                    Pide al administrador que vincule tu cuenta de acceso con tu
                    expediente de empleado para solicitar un permiso laboral.
                </p>
            ) : null}

            {permissions.canCreate && tiposPermiso.length === 0 ? (
                <p
                    className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                    role="status"
                >
                    No hay tipos de permiso activos para seleccionar.
                    {can('tipos_permisos.view') ? (
                        <Link
                            href={tiposPermisosIndex()}
                            className="ml-1 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            Configurar catálogo
                        </Link>
                    ) : null}
                </p>
            ) : null}

            <div className="grid gap-5">
                <FiltrosBase
                    route={permisosLaboralesIndex()}
                    defaultSearch={filters.search}
                    placeholder="Buscar por empleado o solicitante…"
                    query={{ estado: filters.estado }}
                    facets={[statusFacet]}
                    title="Buscar solicitudes"
                    description="Filtra por estatus o localiza una solicitud por empleado."
                    onApply={(query) =>
                        router.post(filtrarPermisos().url, query, {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                />

                <section
                    aria-label="Solicitudes de permisos laborales"
                    className="grid gap-3"
                >
                    <div className="flex items-center gap-2">
                        <CalendarDays
                            aria-hidden="true"
                            className="size-5 text-muted-foreground"
                        />
                        <h2 className="text-lg font-semibold">
                            {permissions.canReview
                                ? 'Solicitudes de la empresa'
                                : 'Mis solicitudes'}
                        </h2>
                    </div>
                    <ResourceTable
                        columns={columns}
                        data={permisos.data}
                        getRowKey={(permiso) => permiso.id}
                        getRowAriaLabel={(permiso) =>
                            `${permiso.empleado}, ${permiso.estadoLabel}, ${formatDate(permiso.fechaInicio)} al ${formatDate(permiso.fechaFin)}`
                        }
                        onRowClick={(permiso) =>
                            setExpandedPermiso((current) =>
                                current === permiso.id ? null : permiso.id,
                            )
                        }
                        expandedRowKey={expandedPermiso}
                        renderExpandedRow={(permiso) => (
                            <PermisoLaboralDetails permiso={permiso} />
                        )}
                        emptyTitle="No hay solicitudes"
                        emptyDescription="Las solicitudes aparecerán aquí cuando se envíen."
                    />
                    <ResourcePagination
                        paginator={permisos}
                        onPageChange={(page) => setPage(page, 'solicitudes')}
                    />
                </section>

                {permissions.canReview && proximas ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Próximos permisos laborales</CardTitle>
                            <CardDescription>
                                Periodos pendientes y autorizados para
                                identificar traslapes antes de resolver una
                                solicitud.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <ResourceTable
                                columns={columns}
                                data={proximas.data}
                                getRowKey={(permiso) => permiso.id}
                                getRowAriaLabel={(permiso) =>
                                    `${permiso.empleado}, ${permiso.estadoLabel}, ${formatDate(permiso.fechaInicio)} al ${formatDate(permiso.fechaFin)}`
                                }
                                onRowClick={(permiso) =>
                                    setExpandedUpcoming((current) =>
                                        current === permiso.id
                                            ? null
                                            : permiso.id,
                                    )
                                }
                                expandedRowKey={expandedUpcoming}
                                renderExpandedRow={(permiso) => (
                                    <PermisoLaboralDetails permiso={permiso} />
                                )}
                                emptyTitle="Sin permisos próximos"
                                emptyDescription="No hay solicitudes pendientes o autorizadas para próximos periodos."
                            />
                            <ResourcePagination
                                paginator={proximas}
                                onPageChange={(page) =>
                                    setPage(page, 'proximas')
                                }
                            />
                        </CardContent>
                    </Card>
                ) : null}
            </div>

            <PermisoLaboralFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                form={storePermiso.form()}
                empleados={empleados}
                empleadosCobertura={empleadosCobertura}
                tiposPermiso={tiposPermiso}
                selfEmployeeId={selfEmployeeId}
                canCreateForOthers={permissions.canCreateForOthers}
            />

            {rejectingPermiso ? (
                <PermisoLaboralRechazarDialog
                    key={rejectingPermiso.id}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setRejectingPermiso(null);
                        }
                    }}
                    empleado={rejectingPermiso.empleado}
                    form={rechazarPermiso.form(rejectingPermiso.id)}
                />
            ) : null}
        </>
    );
}
