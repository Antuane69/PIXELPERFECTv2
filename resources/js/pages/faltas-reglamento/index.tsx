import { Head, Link, router } from '@inertiajs/react';
import { Check, FileDown, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { index as catalogoFaltasIndex } from '@/actions/App/Http/Controllers/FaltasReglamento/FaltaReglamentoCatalogoController';
import {
    autorizar,
    cambiarPagina,
    filtrar,
    index as faltasReglamentoIndex,
    rechazar,
    store,
} from '@/actions/App/Http/Controllers/FaltasReglamento/FaltaReglamentoController';
import { download as downloadEvidence } from '@/actions/App/Http/Controllers/FaltasReglamento/FaltaReglamentoEvidenciaController';
import { index as tiposFaltaIndex } from '@/actions/App/Http/Controllers/FaltasReglamento/TipoFaltaReglamentoController';
import { FiltrosBase } from '@/components/filtros-base';
import type {
    DateFilter,
    FilterFacet,
    FilterQueryValue,
} from '@/components/filtros-base';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { FaltaRechazarDialog } from '@/features/faltas-reglamento/falta-rechazar-dialog';
import { FaltaReglamentoFormDialog } from '@/features/faltas-reglamento/falta-reglamento-form-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { inicio as empresaInicio } from '@/routes/empresas';
import type {
    FaltaReglamento,
    FaltaReglamentoCatalogoOption,
    FaltaReglamentoEmpleadoOption,
    FaltaReglamentoFilters,
    FaltaReglamentoPermissionProps,
    LaravelPaginator,
    TipoFaltaReglamentoOption,
} from '@/types';

type TipoFaltaFilterOption = {
    id: number;
    nombre: string;
};

type Props = {
    faltas: LaravelPaginator<FaltaReglamento>;
    filters: FaltaReglamentoFilters;
    empleados: FaltaReglamentoEmpleadoOption[];
    empleadosFiltro: FaltaReglamentoEmpleadoOption[];
    tiposFalta: TipoFaltaFilterOption[];
    tiposFaltaDisponibles: TipoFaltaReglamentoOption[];
    faltasDisponibles: FaltaReglamentoCatalogoOption[];
    selfEmployeeId: number | null;
    permissions: FaltaReglamentoPermissionProps;
};

const statusFacet: FilterFacet = {
    key: 'estado',
    label: 'Estatus',
    options: [
        { value: 'PENDIENTE', label: 'Pendientes' },
        { value: 'AUTORIZADA', label: 'Autorizadas' },
        { value: 'RECHAZADA', label: 'Rechazadas' },
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

function StatusBadge({ falta }: { falta: FaltaReglamento }) {
    const variant =
        falta.estado === 'RECHAZADA'
            ? 'destructive'
            : falta.estado === 'AUTORIZADA'
              ? 'secondary'
              : 'outline';

    return <Badge variant={variant}>{falta.estadoLabel}</Badge>;
}

function FaltaReglamentoDetails({ falta }: { falta: FaltaReglamento }) {
    return (
        <div className="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-3">
            <div className="grid gap-1">
                <span className="text-muted-foreground">Tipo de falta</span>
                <span>{falta.tipoFalta}</span>
            </div>
            <div className="grid gap-1">
                <span className="text-muted-foreground">Falta cometida</span>
                <span>{falta.falta}</span>
            </div>
            <div className="grid gap-1">
                <span className="text-muted-foreground">Reportó</span>
                <span>{falta.solicitante}</span>
            </div>
            {falta.estado === 'AUTORIZADA' ? (
                <div className="grid gap-1">
                    <span className="text-muted-foreground">
                        Total autorizado de este tipo para el empleado
                    </span>
                    <span>{falta.conteoTipo}</span>
                </div>
            ) : null}
            {falta.comentarios ? (
                <div className="grid gap-1 md:col-span-2">
                    <span className="text-muted-foreground">Comentarios</span>
                    <p className="whitespace-pre-wrap">{falta.comentarios}</p>
                </div>
            ) : null}
            {falta.comentariosRechazo ? (
                <div className="grid gap-1 md:col-span-2">
                    <span className="text-muted-foreground">
                        Motivo del rechazo
                    </span>
                    <p className="whitespace-pre-wrap">
                        {falta.comentariosRechazo}
                    </p>
                </div>
            ) : null}
            {falta.resueltoPor ? (
                <div className="grid gap-1">
                    <span className="text-muted-foreground">Revisó</span>
                    <span>{falta.resueltoPor}</span>
                </div>
            ) : null}
            {falta.evidencias.length > 0 ? (
                <div className="grid gap-2 md:col-span-2 xl:col-span-3">
                    <span className="text-muted-foreground">Evidencias</span>
                    <ul className="flex flex-wrap gap-2">
                        {falta.evidencias.map((evidencia) => (
                            <li key={evidencia.id}>
                                <a
                                    href={
                                        downloadEvidence({
                                            faltaReglamento: falta.id,
                                            evidencia: evidencia.id,
                                        }).url
                                    }
                                    className="inline-flex min-h-9 items-center gap-2 rounded-md border border-border bg-background px-3 py-2 font-medium hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <FileDown
                                        aria-hidden="true"
                                        className="size-4 shrink-0"
                                    />
                                    <span className="max-w-64 truncate">
                                        {evidencia.nombre}
                                    </span>
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}

export default function FaltasReglamentoIndex({
    faltas,
    filters,
    empleados,
    empleadosFiltro,
    tiposFalta,
    tiposFaltaDisponibles,
    faltasDisponibles,
    selfEmployeeId,
    permissions,
}: Props) {
    const { can } = usePermissions();
    const [formOpen, setFormOpen] = useState(false);
    const [rejectingFalta, setRejectingFalta] =
        useState<FaltaReglamento | null>(null);
    const [expandedFalta, setExpandedFalta] = useState<number | null>(null);
    const [resolvingIds, setResolvingIds] = useState<number[]>([]);
    const canSubmit =
        permissions.canCreate &&
        (permissions.canCreateForOthers
            ? empleados.length > 0
            : selfEmployeeId !== null);
    const canReport =
        canSubmit &&
        tiposFaltaDisponibles.length > 0 &&
        faltasDisponibles.length > 0;

    const facets: FilterFacet[] = [
        statusFacet,
        ...(permissions.canReview
            ? [
                  {
                      key: 'empleado_id',
                      label: 'Empleado',
                      options: empleadosFiltro.map((empleado) => ({
                          value: empleado.id,
                          label: empleado.nombre,
                      })),
                      layout: 'list' as const,
                  },
              ]
            : []),
        {
            key: 'tipo_falta_reglamento_id',
            label: 'Tipo de falta',
            options: tiposFalta.map((tipo) => ({
                value: tipo.id,
                label: tipo.nombre,
            })),
            layout: 'list',
        },
    ];

    const dates: DateFilter = {
        startKey: 'fecha_desde',
        endKey: 'fecha_hasta',
        startValue: filters.fechaDesde,
        endValue: filters.fechaHasta,
        startLabel: 'Desde',
        endLabel: 'Hasta',
    };

    const submitFilters = (query: Record<string, FilterQueryValue>) =>
        router.post(filtrar().url, query, {
            preserveScroll: true,
            preserveState: true,
        });

    const submitPage = (page: number): void => {
        router.post(
            cambiarPagina().url,
            { page },
            { preserveScroll: true, preserveState: true },
        );
    };

    const authorize = (falta: FaltaReglamento): void => {
        setResolvingIds((current) => [...current, falta.id]);
        router.post(
            autorizar(falta.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () =>
                    setResolvingIds((current) =>
                        current.filter((id) => id !== falta.id),
                    ),
            },
        );
    };

    const columns: ResourceColumn<FaltaReglamento>[] = [
        {
            key: 'empleado',
            header: 'Empleado',
            cell: (falta) => (
                <span className="font-medium">{falta.empleado}</span>
            ),
        },
        {
            key: 'falta',
            header: 'Falta',
            cell: (falta) => (
                <span className="block max-w-64 truncate">{falta.falta}</span>
            ),
        },
        {
            key: 'fecha',
            header: 'Fecha',
            cell: (falta) => (
                <span className="whitespace-nowrap">
                    {formatDate(falta.fechaOcurrencia)}
                </span>
            ),
        },
        {
            key: 'estado',
            header: 'Estatus',
            cell: (falta) => <StatusBadge falta={falta} />,
        },
        {
            key: 'conteoTipo',
            header: 'Acumuladas',
            mobileHidden: true,
            cell: (falta) =>
                falta.estado === 'AUTORIZADA' ? falta.conteoTipo : '—',
        },
        {
            key: 'acciones',
            header: 'Acciones',
            cell: (falta) =>
                falta.puedeResolver ? (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => authorize(falta)}
                            disabled={resolvingIds.includes(falta.id)}
                            data-row-click-ignore
                        >
                            <Check aria-hidden="true" />
                            Autorizar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setRejectingFalta(falta)}
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
            <Head title="Faltas al reglamento" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Faltas al reglamento"
                    description="Registra reportes, consulta el histórico y revisa las faltas de la empresa."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canReport ? (
                                <Button
                                    type="button"
                                    onClick={() => setFormOpen(true)}
                                >
                                    <Plus aria-hidden="true" />
                                    Reportar falta
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                {permissions.canCreate && !canSubmit ? (
                    <p
                        className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                        role="status"
                    >
                        {permissions.canCreateForOthers
                            ? 'No hay empleados activos disponibles en esta empresa para reportar una falta.'
                            : 'No hay un expediente de empleado vinculado a tu cuenta. Pide al administrador que configure la relación para reportar faltas.'}
                    </p>
                ) : null}
                {permissions.canCreate && canSubmit && !canReport ? (
                    <Card>
                        <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                            <p className="text-sm text-muted-foreground">
                                Configura tipos de falta y faltas activas para
                                habilitar nuevos reportes.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {can('tipos_falta_reglamento.view') ||
                                can('tipos_falta_reglamento.create') ? (
                                    <Link
                                        href={tiposFaltaIndex()}
                                        className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        Administrar tipos
                                    </Link>
                                ) : null}
                                {can('faltas_reglamento_catalogo.view') ||
                                can('faltas_reglamento_catalogo.create') ? (
                                    <Link
                                        href={catalogoFaltasIndex()}
                                        className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        Administrar faltas
                                    </Link>
                                ) : null}
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                <FiltrosBase
                    route={faltasReglamentoIndex()}
                    defaultSearch={filters.search}
                    placeholder="Buscar por empleado, solicitante o falta"
                    facets={facets}
                    query={{
                        estado: filters.estado,
                        empleado_id: filters.empleadoId,
                        tipo_falta_reglamento_id: filters.tipoFaltaReglamentoId,
                    }}
                    showDates
                    dates={dates}
                    title="Buscar reportes"
                    description="Filtra el histórico por estado, empleado, tipo y fecha."
                    onApply={submitFilters}
                />

                <section
                    aria-label="Histórico de faltas al reglamento"
                    className="grid gap-3"
                >
                    <h2 className="text-lg font-semibold">
                        {permissions.canReview
                            ? 'Reportes de la empresa'
                            : 'Mi histórico'}
                    </h2>
                    <ResourceTable
                        columns={columns}
                        data={faltas.data}
                        getRowKey={(falta) => falta.id}
                        getRowAriaLabel={(falta) =>
                            `${falta.empleado}, ${falta.falta}, ${falta.estadoLabel}, ${formatDate(falta.fechaOcurrencia)}`
                        }
                        onRowClick={(falta) =>
                            setExpandedFalta((current) =>
                                current === falta.id ? null : falta.id,
                            )
                        }
                        expandedRowKey={expandedFalta}
                        renderExpandedRow={(falta) => (
                            <FaltaReglamentoDetails falta={falta} />
                        )}
                        emptyTitle="No hay reportes"
                        emptyDescription="Los reportes aparecerán aquí cuando se registren."
                    />
                    <ResourcePagination
                        paginator={faltas}
                        onPageChange={submitPage}
                    />
                </section>
            </main>

            {formOpen ? (
                <FaltaReglamentoFormDialog
                    open
                    onOpenChange={setFormOpen}
                    form={store.form()}
                    empleados={empleados}
                    tiposFalta={tiposFaltaDisponibles}
                    faltasCatalogo={faltasDisponibles}
                    selfEmployeeId={selfEmployeeId}
                    canCreateForOthers={permissions.canCreateForOthers}
                />
            ) : null}

            {rejectingFalta ? (
                <FaltaRechazarDialog
                    key={rejectingFalta.id}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setRejectingFalta(null);
                        }
                    }}
                    empleado={rejectingFalta.empleado}
                    form={rechazar.form(rejectingFalta.id)}
                />
            ) : null}
        </>
    );
}

FaltasReglamentoIndex.layout = {
    breadcrumbs: [
        { title: 'Inicio', href: empresaInicio() },
        { title: 'Faltas al reglamento', href: faltasReglamentoIndex() },
    ],
};
