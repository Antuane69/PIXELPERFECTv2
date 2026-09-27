import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ChevronDown,
    ChevronUp,
    Download,
    HeartPulse,
    Plus,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    autorizar as autorizarIncapacidad,
    cambiarPagina as cambiarPaginaIncapacidades,
    descargarArchivo as descargarArchivoIncapacidad,
    filtrar as filtrarIncapacidades,
    index as incapacidadesIndex,
    rechazar as rechazarIncapacidad,
    store as storeIncapacidad,
} from '@/actions/App/Http/Controllers/Incapacidades/IncapacidadController';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { IncapacidadFormDialog } from '@/features/incapacidades/incapacidad-form-dialog';
import { IncapacidadRechazarDialog } from '@/features/incapacidades/incapacidad-rechazar-dialog';
import type {
    Incapacidad,
    IncapacidadEmpleadoOption,
    IncapacidadFilters,
    IncapacidadPermissionProps,
    LaravelPaginator,
} from '@/types';

type IncapacidadesIndexProps = {
    incapacidades: LaravelPaginator<Incapacidad>;
    filters: IncapacidadFilters;
    empleados: IncapacidadEmpleadoOption[];
    selfEmployeeId: number | null;
    permissions: IncapacidadPermissionProps;
};

const statusFacet: FilterFacet = {
    key: 'estado',
    label: 'Estatus',
    options: [
        { value: 'PENDIENTE', label: 'Pendientes' },
        { value: 'AUTORIZADA', label: 'Autorizadas' },
        { value: 'VENCIDA', label: 'Vencidas' },
        { value: 'RECHAZADA', label: 'Rechazadas' },
    ],
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(value + 'T00:00:00Z'));
}

function StatusBadge({ incapacidad }: { incapacidad: Incapacidad }) {
    const variant =
        incapacidad.estado === 'RECHAZADA'
            ? 'destructive'
            : incapacidad.estado === 'AUTORIZADA'
              ? 'secondary'
              : 'outline';

    return <Badge variant={variant}>{incapacidad.estadoLabel}</Badge>;
}

function ArchivoIncapacidad({ incapacidad }: { incapacidad: Incapacidad }) {
    if (!incapacidad.nombreArchivo) {
        return (
            <span className="text-sm text-muted-foreground">Sin archivo</span>
        );
    }

    return (
        <a
            href={descargarArchivoIncapacidad(incapacidad.id).url}
            download
            aria-label={'Descargar justificante ' + incapacidad.nombreArchivo}
            data-row-click-ignore
            className="inline-flex max-w-56 items-center gap-2 truncate text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <Download aria-hidden="true" className="size-4 shrink-0" />
            <span className="truncate">{incapacidad.nombreArchivo}</span>
        </a>
    );
}

function IncapacidadDetails({ incapacidad }: { incapacidad: Incapacidad }) {
    return (
        <div
            id={`incapacidad-detalles-${incapacidad.id}`}
            className="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-3"
        >
            <div className="grid gap-1">
                <span className="text-muted-foreground">Solicitante</span>
                <span>{incapacidad.solicitante}</span>
            </div>
            <div className="grid gap-1">
                <span className="text-muted-foreground">Justificante</span>
                <ArchivoIncapacidad incapacidad={incapacidad} />
            </div>
            <div className="grid gap-1 md:col-span-2">
                <span className="text-muted-foreground">Motivo</span>
                <p className="whitespace-pre-wrap">{incapacidad.motivo}</p>
            </div>
            {incapacidad.comentariosRechazo ? (
                <div className="grid gap-1 md:col-span-2">
                    <span className="text-muted-foreground">
                        Motivo del rechazo
                    </span>
                    <p className="whitespace-pre-wrap">
                        {incapacidad.comentariosRechazo}
                    </p>
                </div>
            ) : null}
            {incapacidad.resueltoPor ? (
                <div className="grid gap-1">
                    <span className="text-muted-foreground">Revisó</span>
                    <span>{incapacidad.resueltoPor}</span>
                </div>
            ) : null}
        </div>
    );
}

export default function IncapacidadesIndex({
    incapacidades,
    filters,
    empleados,
    selfEmployeeId,
    permissions,
}: IncapacidadesIndexProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [rejectingIncapacidad, setRejectingIncapacidad] =
        useState<Incapacidad | null>(null);
    const [expandedIncapacidad, setExpandedIncapacidad] = useState<
        number | null
    >(null);
    const [resolvingIds, setResolvingIds] = useState<number[]>([]);

    const canSubmitForSelf =
        permissions.canCreateForOthers || selfEmployeeId !== null;
    const canOpenRequest =
        permissions.canCreate && canSubmitForSelf && empleados.length > 0;
    const emptyDescription =
        filters.search || filters.estado
            ? 'No hay solicitudes que coincidan con los filtros seleccionados.'
            : permissions.canReview
              ? 'Cuando un empleado envíe una solicitud, aparecerá aquí.'
              : 'Cuando envíes una solicitud, aparecerá aquí.';
    const employeeOptionsKey = `${selfEmployeeId ?? 'no-self'}-${empleados.length}-${empleados[0]?.id ?? 'no-options'}`;

    const setPage = (page: number) => {
        router.post(
            cambiarPaginaIncapacidades().url,
            { page },
            { preserveScroll: true, preserveState: true },
        );
    };

    const authorize = (incapacidad: Incapacidad) => {
        setResolvingIds((current) => [...current, incapacidad.id]);
        router.post(
            autorizarIncapacidad(incapacidad.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () =>
                    setResolvingIds((current) =>
                        current.filter((id) => id !== incapacidad.id),
                    ),
            },
        );
    };

    const columns: ResourceColumn<Incapacidad>[] = [
        {
            key: 'empleado',
            header: 'Empleado',
            cell: (incapacidad) => (
                <span className="font-medium">{incapacidad.empleado}</span>
            ),
        },
        {
            key: 'periodo',
            header: 'Periodo',
            cell: (incapacidad) => (
                <span className="whitespace-nowrap">
                    {formatDate(incapacidad.fechaInicio)} –{' '}
                    {formatDate(incapacidad.fechaFin)}
                </span>
            ),
        },
        {
            key: 'motivo',
            header: 'Motivo',
            mobileHidden: true,
            cell: (incapacidad) => (
                <span className="block max-w-64 truncate">
                    {incapacidad.motivo}
                </span>
            ),
        },
        {
            key: 'archivo',
            header: 'Justificante',
            mobileHidden: true,
            cell: (incapacidad) => (
                <ArchivoIncapacidad incapacidad={incapacidad} />
            ),
        },
        {
            key: 'estado',
            header: 'Estatus',
            cell: (incapacidad) => <StatusBadge incapacidad={incapacidad} />,
        },
        {
            key: 'solicitante',
            header: 'Solicitante',
            mobileHidden: true,
            cell: (incapacidad) => incapacidad.solicitante,
        },
        {
            key: 'acciones',
            header: 'Acciones',
            cell: (incapacidad) => {
                const isExpanded = expandedIncapacidad === incapacidad.id;

                return (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                setExpandedIncapacidad((current) =>
                                    current === incapacidad.id
                                        ? null
                                        : incapacidad.id,
                                )
                            }
                            aria-expanded={isExpanded}
                            aria-controls={`incapacidad-detalles-${incapacidad.id}`}
                            data-row-click-ignore
                        >
                            {isExpanded ? (
                                <ChevronUp aria-hidden="true" />
                            ) : (
                                <ChevronDown aria-hidden="true" />
                            )}
                            {isExpanded ? 'Ocultar' : 'Detalles'}
                        </Button>
                        {incapacidad.puedeResolver ? (
                            <>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => authorize(incapacidad)}
                                    disabled={resolvingIds.includes(
                                        incapacidad.id,
                                    )}
                                    data-row-click-ignore
                                >
                                    <Check aria-hidden="true" />
                                    Autorizar
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        setRejectingIncapacidad(incapacidad)
                                    }
                                    data-row-click-ignore
                                >
                                    <X aria-hidden="true" />
                                    Rechazar
                                </Button>
                            </>
                        ) : null}
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Head title="Incapacidades" />
            <ResourceHeader
                title="Incapacidades"
                description="Envía justificantes y consulta el seguimiento de las incapacidades del empleado."
                actions={
                    permissions.canCreate ? (
                        <Button
                            type="button"
                            onClick={() => setFormOpen(true)}
                            disabled={!canOpenRequest}
                        >
                            <Plus aria-hidden="true" />
                            Solicitar incapacidad
                        </Button>
                    ) : null
                }
            />

            {!canSubmitForSelf && permissions.canCreate ? (
                <p
                    className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                    role="status"
                >
                    Pide al administrador que vincule tu cuenta con tu
                    expediente para registrar una incapacidad.
                </p>
            ) : null}

            {permissions.canCreate && empleados.length === 0 ? (
                <p
                    className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                    role="status"
                >
                    No hay empleados activos disponibles para registrar una
                    incapacidad.
                </p>
            ) : null}

            <div className="grid gap-6">
                <FiltrosBase
                    route={incapacidadesIndex()}
                    defaultSearch={filters.search}
                    query={filters}
                    facets={[statusFacet]}
                    placeholder="Buscar por empleado o solicitante"
                    onApply={(query) =>
                        router.post(filtrarIncapacidades().url, query, {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                />

                <section
                    aria-label="Solicitudes de incapacidades"
                    className="grid gap-3"
                >
                    <div className="flex items-center gap-2">
                        {permissions.canReview ? (
                            <CalendarDays
                                aria-hidden="true"
                                className="size-5 text-muted-foreground"
                            />
                        ) : (
                            <HeartPulse
                                aria-hidden="true"
                                className="size-5 text-muted-foreground"
                            />
                        )}
                        <h2 className="text-lg font-semibold">
                            {permissions.canReview
                                ? 'Solicitudes de la empresa'
                                : 'Mis incapacidades'}
                        </h2>
                    </div>
                    <ResourceTable
                        columns={columns}
                        data={incapacidades.data}
                        getRowKey={(incapacidad) => incapacidad.id}
                        expandedRowKey={expandedIncapacidad}
                        renderExpandedRow={(incapacidad) => (
                            <IncapacidadDetails incapacidad={incapacidad} />
                        )}
                        emptyTitle="No hay incapacidades"
                        emptyDescription={emptyDescription}
                    />
                    <ResourcePagination
                        paginator={incapacidades}
                        onPageChange={setPage}
                    />
                </section>
            </div>

            {permissions.canCreate ? (
                <IncapacidadFormDialog
                    key={employeeOptionsKey}
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    form={storeIncapacidad.form()}
                    empleados={empleados}
                    selfEmployeeId={selfEmployeeId}
                    canCreateForOthers={permissions.canCreateForOthers}
                />
            ) : null}

            {rejectingIncapacidad ? (
                <IncapacidadRechazarDialog
                    key={rejectingIncapacidad.id}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setRejectingIncapacidad(null);
                        }
                    }}
                    empleado={rejectingIncapacidad.empleado}
                    form={rechazarIncapacidad.form(rejectingIncapacidad.id)}
                />
            ) : null}
        </>
    );
}
