import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    restore,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/PlanController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { RestoreButton } from '@/components/restore-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PlanFormFields } from '@/features/planes/plan-form-fields';
import { PlanIcon } from '@/features/planes/plan-icons';
import type { PlanIconName } from '@/features/planes/plan-icons';
import type { LaravelPaginator, Plan } from '@/types';

type Props = {
    planes: LaravelPaginator<Plan>;
    iconos: PlanIconName[];
    filters: {
        search: string;
        activo: boolean | null;
        archivados: boolean;
        perPage: number;
    };
};

const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function PlanesIndex({ planes, iconos, filters }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Plan | null>(null);
    const [deleting, setDeleting] = useState<Plan | null>(null);
    const showingArchived = filters.archivados;
    const filterFacets: FilterFacet[] = [
        {
            key: 'activo',
            label: 'Estado del plan',
            options: [
                { value: true, label: 'Activo' },
                { value: false, label: 'Inactivo' },
            ],
        },
        {
            key: 'archivados',
            label: 'Tipo de registro',
            defaultValue: false,
            options: [
                { value: false, label: 'Vigentes' },
                { value: true, label: 'Archivados' },
            ],
        },
    ];
    const columns: ResourceColumn<Plan>[] = [
        {
            key: 'nombre',
            header: 'Plan',
            cell: (plan) => (
                <div className="flex items-center gap-3">
                    <span
                        className="grid size-9 shrink-0 place-items-center rounded-lg text-white"
                        style={{ backgroundColor: plan.color }}
                        aria-hidden
                    >
                        <PlanIcon name={plan.icono} className="text-lg" />
                    </span>
                    <div className="grid min-w-0 gap-0.5">
                        <span className="font-medium">{plan.nombre}</span>
                        <span className="font-mono text-xs text-muted-foreground">
                            {plan.color}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'precio',
            header: 'Precio mensual',
            cell: (plan) => money.format(Number(plan.precio_mensual)),
        },
        {
            key: 'limite',
            header: 'Usuarios',
            cell: (plan) => plan.limite_usuarios ?? 'Sin límite definido',
        },
        {
            key: 'gracia',
            header: 'Gracia',
            mobileHidden: true,
            cell: (plan) => `${plan.periodo_gracia_dias} días`,
        },
        {
            key: 'modulos',
            header: 'Módulos incluidos',
            mobileHidden: true,
            cell: (plan) => (
                <span
                    className="block max-w-64 truncate text-muted-foreground"
                    title={plan.modulos_incluidos ?? undefined}
                >
                    {plan.modulos_incluidos || 'Pendiente de configurar'}
                </span>
            ),
        },
        {
            key: 'estado',
            header: 'Estado',
            cell: (plan) => (
                <Badge
                    variant={
                        showingArchived
                            ? 'outline'
                            : plan.activo
                              ? 'default'
                              : 'secondary'
                    }
                >
                    {showingArchived
                        ? 'Archivado'
                        : plan.activo
                          ? 'Activo'
                          : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            header: 'Acciones',
            className: 'md:w-28',
            cell: (plan) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    {showingArchived ? (
                        <RestoreButton
                            form={restore.form(plan.id)}
                            subject={`el plan ${plan.nombre}`}
                        />
                    ) : (
                        <>
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                onClick={() => {
                                    setEditing(plan);
                                    setDialogOpen(true);
                                }}
                                aria-label={`Editar plan ${plan.nombre}`}
                            >
                                <Pencil />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setDeleting(plan)}
                                aria-label={`Archivar plan ${plan.nombre}`}
                            >
                                <Trash2 />
                            </Button>
                        </>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Planes" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Planes"
                    description="Configura oferta mensual visible para empresas. Cobro y asignación quedan pendientes."
                    actions={
                        !showingArchived ? (
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setDialogOpen(true);
                                }}
                            >
                                <Plus /> Nuevo plan
                            </Button>
                        ) : null
                    }
                />

                <FiltrosBase
                    route={index()}
                    defaultSearch={filters.search}
                    placeholder="Buscar nombre o módulo"
                    facets={filterFacets}
                    query={{
                        activo: filters.activo,
                        archivados: showingArchived,
                        per_page: filters.perPage,
                    }}
                />
                <ResourceTable
                    data={planes.data}
                    columns={columns}
                    getRowKey={(plan) => plan.id}
                    emptyTitle={
                        showingArchived
                            ? 'No hay planes archivados'
                            : 'No hay planes'
                    }
                    emptyDescription={
                        showingArchived
                            ? 'Los planes archivados aparecerán aquí.'
                            : 'Crea primer plan para preparar oferta comercial.'
                    }
                />
                <ResourcePagination paginator={planes} />
            </main>

            {dialogOpen ? (
                <PlanDialog
                    open
                    onOpenChange={setDialogOpen}
                    plan={editing}
                    iconos={iconos}
                />
            ) : null}
            {deleting ? (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el plan “${deleting.nombre}”`}
                />
            ) : null}
        </>
    );
}

function PlanDialog({
    open,
    onOpenChange,
    plan,
    iconos,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    plan: Plan | null;
    iconos: PlanIconName[];
}) {
    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={plan ? 'Editar plan' : 'Nuevo plan'}
            description="Define presentación, precio mensual y condiciones configurables del plan."
            formId="plan-form"
            form={plan ? update.form(plan.id) : store.form()}
            submitLabel={plan ? 'Guardar cambios' : 'Crear plan'}
            resetOnSuccess={!plan}
        >
            {(errors) => (
                <PlanFormFields
                    key={plan?.id ?? 'new'}
                    plan={plan}
                    iconos={iconos}
                    errors={errors}
                />
            )}
        </ResourceFormDialog>
    );
}

PlanesIndex.layout = {
    breadcrumbs: [
        { title: 'Plataforma', href: index() },
        { title: 'Planes', href: index() },
    ],
};
