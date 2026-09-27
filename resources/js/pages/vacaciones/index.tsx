import { Head, router } from '@inertiajs/react';
import { CalendarDays, Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import {
    autorizar as autorizarVacacion,
    cambiarPagina as cambiarPaginaVacaciones,
    filtrar as filtrarVacaciones,
    index as vacacionesIndex,
    rechazar as rechazarVacacion,
    store as storeVacacion,
} from '@/actions/App/Http/Controllers/VacacionController';
import {
    destroy as destroyHoliday,
    store as storeHoliday,
    update as updateHoliday,
} from '@/actions/App/Http/Controllers/Vacaciones/DiaFestivoController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { VacacionFormDialog } from '@/features/vacaciones/vacacion-form-dialog';
import type { VacacionEmpleadoOption } from '@/features/vacaciones/vacacion-form-dialog';
import { VacacionRechazarDialog } from '@/features/vacaciones/vacacion-rechazar-dialog';
import type {
    DiaFestivo,
    LaravelPaginator,
    Vacacion,
    VacacionFilters,
    VacacionPermissionProps,
} from '@/types';

type VacacionesIndexProps = {
    vacaciones: LaravelPaginator<Vacacion>;
    proximas: LaravelPaginator<Vacacion> | null;
    diasFestivos: LaravelPaginator<DiaFestivo>;
    filters: VacacionFilters;
    empleados: VacacionEmpleadoOption[];
    selfEmployeeId: number | null;
    permissions: VacacionPermissionProps;
    timezone: string;
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
    }).format(new Date(`${value}T00:00:00Z`));
}

function StatusBadge({ vacation }: { vacation: Vacacion }) {
    const variant =
        vacation.estado === 'RECHAZADA'
            ? 'destructive'
            : vacation.estado === 'AUTORIZADA'
              ? 'secondary'
              : 'outline';

    return <Badge variant={variant}>{vacation.estadoLabel}</Badge>;
}

function VacationDetails({ vacation }: { vacation: Vacacion }) {
    return (
        <div className="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-3">
            <div className="grid gap-1">
                <span className="text-muted-foreground">
                    Saldo al solicitar
                </span>
                <span>{vacation.saldoAlSolicitar} días</span>
            </div>
            <div className="grid gap-1">
                <span className="text-muted-foreground">
                    Últimas vacaciones
                </span>
                <span>
                    {vacation.ultimaVacacion
                        ? formatDate(vacation.ultimaVacacion)
                        : 'Sin registro anterior'}
                </span>
            </div>
            <div className="grid gap-1">
                <span className="text-muted-foreground">Solicitante</span>
                <span>{vacation.solicitante}</span>
            </div>
            <div className="grid gap-1">
                <span className="text-muted-foreground">Cobertura</span>
                <span>{vacation.empleadosCobertura.join(', ')}</span>
            </div>
            {vacation.comentarios ? (
                <div className="grid gap-1 md:col-span-2">
                    <span className="text-muted-foreground">
                        Comentarios de la solicitud
                    </span>
                    <p className="whitespace-pre-wrap">
                        {vacation.comentarios}
                    </p>
                </div>
            ) : null}
            {vacation.comentariosRechazo ? (
                <div className="grid gap-1 md:col-span-2">
                    <span className="text-muted-foreground">
                        Motivo del rechazo
                    </span>
                    <p className="whitespace-pre-wrap">
                        {vacation.comentariosRechazo}
                    </p>
                </div>
            ) : null}
            {vacation.resueltoPor ? (
                <div className="grid gap-1">
                    <span className="text-muted-foreground">Revisó</span>
                    <span>{vacation.resueltoPor}</span>
                </div>
            ) : null}
        </div>
    );
}

export default function VacacionesIndex({
    vacaciones,
    proximas,
    diasFestivos,
    filters,
    empleados,
    selfEmployeeId,
    permissions,
}: VacacionesIndexProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [holidayFormOpen, setHolidayFormOpen] = useState(false);
    const [editingHoliday, setEditingHoliday] = useState<DiaFestivo | null>(
        null,
    );
    const [deletingHoliday, setDeletingHoliday] = useState<DiaFestivo | null>(
        null,
    );
    const [rejectingVacation, setRejectingVacation] = useState<Vacacion | null>(
        null,
    );
    const [expandedVacation, setExpandedVacation] = useState<number | null>(
        null,
    );
    const [expandedUpcoming, setExpandedUpcoming] = useState<number | null>(
        null,
    );
    const [resolvingIds, setResolvingIds] = useState<number[]>([]);

    const canSubmitForSelf =
        permissions.canCreateForOthers || selfEmployeeId !== null;
    const canOpenRequest =
        permissions.canCreate && canSubmitForSelf && empleados.length > 0;

    const setPage = (
        page: number,
        listado: 'solicitudes' | 'proximas' | 'festivos',
    ) => {
        router.post(
            cambiarPaginaVacaciones().url,
            { page, listado },
            { preserveScroll: true, preserveState: true },
        );
    };

    const authorize = (vacation: Vacacion) => {
        setResolvingIds((current) => [...current, vacation.id]);
        router.post(
            autorizarVacacion(vacation.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () =>
                    setResolvingIds((current) =>
                        current.filter((id) => id !== vacation.id),
                    ),
            },
        );
    };

    const columns: ResourceColumn<Vacacion>[] = [
        {
            key: 'empleado',
            header: 'Empleado',
            cell: (vacation) => (
                <span className="font-medium">{vacation.empleado}</span>
            ),
        },
        {
            key: 'periodo',
            header: 'Periodo',
            cell: (vacation) => (
                <span className="whitespace-nowrap">
                    {formatDate(vacation.fechaInicio)} –{' '}
                    {formatDate(vacation.fechaFin)}
                </span>
            ),
        },
        {
            key: 'dias',
            header: 'Días',
            cell: (vacation) => vacation.diasSolicitados,
        },
        {
            key: 'cobertura',
            header: 'Cobertura',
            mobileHidden: true,
            cell: (vacation) => (
                <span className="block max-w-48 truncate">
                    {vacation.empleadosCobertura.join(', ')}
                </span>
            ),
        },
        {
            key: 'estado',
            header: 'Estatus',
            cell: (vacation) => <StatusBadge vacation={vacation} />,
        },
        {
            key: 'solicitante',
            header: 'Solicitante',
            mobileHidden: true,
            cell: (vacation) => vacation.solicitante,
        },
        {
            key: 'acciones',
            header: 'Acciones',
            cell: (vacation) =>
                vacation.puedeResolver ? (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => authorize(vacation)}
                            disabled={resolvingIds.includes(vacation.id)}
                            data-row-click-ignore
                        >
                            <Check aria-hidden="true" />
                            Autorizar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setRejectingVacation(vacation)}
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

    const holidayColumns: ResourceColumn<DiaFestivo>[] = [
        {
            key: 'fecha',
            header: 'Fecha',
            cell: (holiday) => (
                <span className="font-medium whitespace-nowrap">
                    {formatDate(holiday.fecha)}
                </span>
            ),
        },
        {
            key: 'nombre',
            header: 'Día festivo',
            cell: (holiday) => holiday.nombre,
        },
        {
            key: 'acciones',
            header: 'Acciones',
            cell: (holiday) =>
                permissions.canManageHolidays ? (
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            aria-label={`Editar ${holiday.nombre}`}
                            onClick={() => {
                                setEditingHoliday(holiday);
                                setHolidayFormOpen(true);
                            }}
                        >
                            <Pencil aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            aria-label={`Eliminar ${holiday.nombre}`}
                            onClick={() => setDeletingHoliday(holiday)}
                        >
                            <Trash2 aria-hidden="true" />
                        </Button>
                    </div>
                ) : (
                    <span aria-label="Solo consulta">—</span>
                ),
        },
    ];

    return (
        <>
            <Head title="Vacaciones" />
            <ResourceHeader
                title="Vacaciones"
                description="Consulta saldos, registra solicitudes y revisa los periodos próximos."
                actions={
                    permissions.canCreate ? (
                        <Button
                            type="button"
                            onClick={() => setFormOpen(true)}
                            disabled={!canOpenRequest}
                        >
                            <Plus aria-hidden="true" />
                            Solicitar vacaciones
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
                    expediente de empleado para solicitar vacaciones.
                </p>
            ) : null}

            <div className="grid gap-5">
                <FiltrosBase
                    route={vacacionesIndex()}
                    defaultSearch={filters.search}
                    placeholder="Buscar por empleado o solicitante…"
                    query={{ estado: filters.estado }}
                    facets={[statusFacet]}
                    title="Buscar solicitudes"
                    description="Filtra por estatus o localiza una solicitud por empleado."
                    onApply={(query) =>
                        router.post(filtrarVacaciones().url, query, {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                />

                <section
                    aria-label="Solicitudes de vacaciones"
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
                        data={vacaciones.data}
                        getRowKey={(vacation) => vacation.id}
                        getRowAriaLabel={(vacation) =>
                            `${vacation.empleado}, ${vacation.estadoLabel}, ${formatDate(vacation.fechaInicio)} al ${formatDate(vacation.fechaFin)}`
                        }
                        onRowClick={(vacation) =>
                            setExpandedVacation((current) =>
                                current === vacation.id ? null : vacation.id,
                            )
                        }
                        expandedRowKey={expandedVacation}
                        renderExpandedRow={(vacation) => (
                            <VacationDetails vacation={vacation} />
                        )}
                        emptyTitle="No hay solicitudes"
                        emptyDescription="Las solicitudes aparecerán aquí cuando se envíen."
                    />
                    <ResourcePagination
                        paginator={vacaciones}
                        onPageChange={(page) => setPage(page, 'solicitudes')}
                    />
                </section>

                {permissions.canReview && proximas ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Próximas vacaciones</CardTitle>
                            <CardDescription>
                                Periodos pendientes y autorizados de la empresa
                                para identificar traslapes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <ResourceTable
                                columns={columns}
                                data={proximas.data}
                                getRowKey={(vacation) => vacation.id}
                                getRowAriaLabel={(vacation) =>
                                    `${vacation.empleado}, ${vacation.estadoLabel}, ${formatDate(vacation.fechaInicio)} al ${formatDate(vacation.fechaFin)}`
                                }
                                onRowClick={(vacation) =>
                                    setExpandedUpcoming((current) =>
                                        current === vacation.id
                                            ? null
                                            : vacation.id,
                                    )
                                }
                                expandedRowKey={expandedUpcoming}
                                renderExpandedRow={(vacation) => (
                                    <VacationDetails vacation={vacation} />
                                )}
                                emptyTitle="Sin vacaciones próximas"
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

                {diasFestivos ? (
                    <Card>
                        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="grid gap-1.5">
                                <CardTitle>Catálogo de días festivos</CardTitle>
                                <CardDescription>
                                    Configura los feriados de esta empresa. Las
                                    fechas registradas no descuentan días del
                                    saldo de vacaciones.
                                </CardDescription>
                            </div>
                            {permissions.canManageHolidays ? (
                                <Button
                                    type="button"
                                    className="shrink-0"
                                    onClick={() => {
                                        setEditingHoliday(null);
                                        setHolidayFormOpen(true);
                                    }}
                                >
                                    <Plus aria-hidden="true" />
                                    Agregar día festivo
                                </Button>
                            ) : null}
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <ResourceTable
                                columns={holidayColumns}
                                data={diasFestivos.data}
                                getRowKey={(holiday) => holiday.id}
                                getRowAriaLabel={(holiday) =>
                                    `${holiday.nombre}, ${formatDate(holiday.fecha)}`
                                }
                                emptyTitle="No hay días festivos"
                                emptyDescription="Agrega las fechas que deben excluirse del cálculo de vacaciones."
                            />
                            <ResourcePagination
                                paginator={diasFestivos}
                                onPageChange={(page) =>
                                    setPage(page, 'festivos')
                                }
                            />
                        </CardContent>
                    </Card>
                ) : null}
            </div>

            <VacacionFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                form={storeVacacion.form()}
                empleados={empleados}
                selfEmployeeId={selfEmployeeId}
                canCreateForOthers={permissions.canCreateForOthers}
            />

            {rejectingVacation ? (
                <VacacionRechazarDialog
                    key={rejectingVacation.id}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setRejectingVacation(null);
                        }
                    }}
                    empleado={rejectingVacation.empleado}
                    form={rechazarVacacion.form(rejectingVacation.id)}
                />
            ) : null}

            {permissions.canManageHolidays && holidayFormOpen ? (
                <DiaFestivoFormDialog
                    key={editingHoliday?.id ?? 'new'}
                    open
                    onOpenChange={(open) => {
                        setHolidayFormOpen(open);

                        if (!open) {
                            setEditingHoliday(null);
                        }
                    }}
                    diaFestivo={editingHoliday}
                    form={
                        editingHoliday
                            ? updateHoliday.form(editingHoliday.id)
                            : storeHoliday.form()
                    }
                />
            ) : null}

            {deletingHoliday ? (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeletingHoliday(null)}
                    form={destroyHoliday.form(deletingHoliday.id)}
                    subject={`el día festivo “${deletingHoliday.nombre}”`}
                />
            ) : null}
        </>
    );
}

function DiaFestivoFormDialog({
    open,
    onOpenChange,
    diaFestivo,
    form,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    diaFestivo: DiaFestivo | null;
    form: { action: string; method: 'post' };
}) {
    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={diaFestivo ? 'Editar día festivo' : 'Agregar día festivo'}
            description="Registra una fecha específica de descanso para esta empresa."
            formId="dia-festivo-form"
            form={form}
            submitLabel={diaFestivo ? 'Guardar cambios' : 'Agregar'}
            resetOnSuccess={!diaFestivo}
        >
            {(errors) => (
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="dia-festivo-nombre">Nombre</Label>
                        <Input
                            id="dia-festivo-nombre"
                            name="nombre"
                            defaultValue={diaFestivo?.nombre ?? ''}
                            maxLength={120}
                            required
                            autoFocus
                            aria-invalid={Boolean(errors.nombre)}
                            aria-describedby={
                                errors.nombre
                                    ? 'dia-festivo-nombre-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="dia-festivo-nombre-error"
                            message={errors.nombre}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="dia-festivo-fecha">Fecha</Label>
                        <Input
                            id="dia-festivo-fecha"
                            type="date"
                            name="fecha"
                            defaultValue={diaFestivo?.fecha ?? ''}
                            required
                            aria-invalid={Boolean(errors.fecha)}
                            aria-describedby={
                                errors.fecha
                                    ? 'dia-festivo-fecha-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="dia-festivo-fecha-error"
                            message={errors.fecha}
                        />
                    </div>
                </div>
            )}
        </ResourceFormDialog>
    );
}
