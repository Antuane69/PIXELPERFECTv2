import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    restore,
    store,
    update,
} from '@/actions/App/Http/Controllers/PuestoController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import InputError from '@/components/input-error';
import { ResourceExportDialog } from '@/components/resource-export-dialog';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { RestoreButton } from '@/components/restore-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import type { LaravelPaginator, Puesto } from '@/types';

type Props = {
    puestos: LaravelPaginator<Puesto>;
    filters?: {
        search?: string;
        activo?: boolean | null;
        archivados?: boolean;
        perPage?: number;
    };
};

const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export default function PuestosIndex({ puestos, filters }: Props) {
    const { can } = usePermissions();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Puesto | null>(null);
    const [deleting, setDeleting] = useState<Puesto | null>(null);
    const showingArchived = filters?.archivados ?? false;
    const filterFacets: FilterFacet[] = [
        {
            key: 'activo',
            label: 'Estado del puesto',
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

    const columns: ResourceColumn<Puesto>[] = [
        {
            key: 'nombre',
            header: 'Nombre',
            cell: (puesto) => (
                <span className="font-medium">{puesto.nombre}</span>
            ),
        },
        {
            key: 'salario_dia',
            header: 'Salario diario',
            cell: (puesto) => money.format(Number(puesto.salario_dia ?? 0)),
        },
        {
            key: 'salario_quincena',
            header: 'Salario quincenal',
            cell: (puesto) =>
                money.format(Number(puesto.salario_quincena ?? 0)),
        },
        {
            key: 'activo',
            header: 'Estado',
            cell: (puesto) => (
                <Badge
                    variant={
                        showingArchived
                            ? 'outline'
                            : puesto.activo
                              ? 'default'
                              : 'secondary'
                    }
                >
                    {showingArchived
                        ? 'Archivado'
                        : puesto.activo
                          ? 'Activo'
                          : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'md:w-32',
            cell: (puesto) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    {showingArchived && can('puestos.update') && (
                        <RestoreButton
                            form={restore.form(puesto.id)}
                            subject={`el puesto ${puesto.nombre}`}
                        />
                    )}
                    {!showingArchived && can('puestos.update') && (
                        <Button
                            size="icon"
                            variant="outline"
                            onClick={() => {
                                setEditing(puesto);
                                setDialogOpen(true);
                            }}
                            aria-label={`Editar puesto ${puesto.nombre}`}
                        >
                            <Pencil />
                        </Button>
                    )}
                    {!showingArchived && can('puestos.delete') && (
                        <Button
                            size="icon"
                            variant="outline"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeleting(puesto)}
                            aria-label={`Eliminar puesto ${puesto.nombre}`}
                        >
                            <Trash2 />
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Puestos" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Puestos"
                    description="Mantén actualizado el catálogo salarial y de puestos."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <ResourceExportDialog
                                report="puestos"
                                filters={{
                                    search: filters?.search,
                                    activo: filters?.activo,
                                    archivados: showingArchived,
                                }}
                            />
                            {!showingArchived && can('puestos.create') && (
                                <Button
                                    onClick={() => {
                                        setEditing(null);
                                        setDialogOpen(true);
                                    }}
                                >
                                    <Plus /> Nuevo puesto
                                </Button>
                            )}
                        </div>
                    }
                />
                <FiltrosBase
                    route={index()}
                    defaultSearch={filters?.search}
                    placeholder="Buscar puesto"
                    facets={filterFacets}
                    query={{
                        activo: filters?.activo,
                        archivados: showingArchived,
                        per_page: filters?.perPage ?? 15,
                    }}
                />
                <ResourceTable
                    data={puestos.data}
                    columns={columns}
                    getRowKey={(puesto) => puesto.id}
                    emptyTitle={
                        showingArchived
                            ? 'No hay puestos archivados'
                            : 'No hay puestos'
                    }
                />
                <ResourcePagination paginator={puestos} />
            </main>

            {dialogOpen && (
                <PuestoDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    puesto={editing}
                />
            )}
            {deleting && (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el puesto “${deleting.nombre}”`}
                />
            )}
        </>
    );
}

function PuestoDialog({
    open,
    onOpenChange,
    puesto,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    puesto: Puesto | null;
}) {
    const formId = 'puesto-form';

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={puesto ? 'Editar puesto' : 'Nuevo puesto'}
            description="Define el nombre, importes y disponibilidad del puesto."
            formId={formId}
            form={puesto ? update.form(puesto.id) : store.form()}
            resetOnSuccess={!puesto}
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="puesto-nombre">Nombre</Label>
                        <Input
                            id="puesto-nombre"
                            name="nombre"
                            defaultValue={puesto?.nombre}
                            required
                            autoFocus
                        />
                        <InputError message={errors.nombre} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="puesto-salario-dia">
                                Salario diario
                            </Label>
                            <Input
                                id="puesto-salario-dia"
                                type="number"
                                name="salario_dia"
                                min="0"
                                step="0.01"
                                defaultValue={puesto?.salario_dia ?? ''}
                                required
                            />
                            <InputError message={errors.salario_dia} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="puesto-salario-quincena">
                                Salario quincenal
                            </Label>
                            <Input
                                id="puesto-salario-quincena"
                                type="number"
                                name="salario_quincena"
                                min="0"
                                step="0.01"
                                defaultValue={puesto?.salario_quincena ?? ''}
                                required
                            />
                            <InputError message={errors.salario_quincena} />
                        </div>
                    </div>
                    <div className="flex items-center gap-3 rounded-lg border p-4">
                        <input type="hidden" name="activo" value="0" />
                        <Checkbox
                            id="puesto-activo"
                            name="activo"
                            value="1"
                            defaultChecked={puesto?.activo ?? true}
                        />
                        <Label htmlFor="puesto-activo">Puesto activo</Label>
                    </div>
                    <InputError message={errors.activo} />
                </div>
            )}
        </ResourceFormDialog>
    );
}

PuestosIndex.layout = {
    breadcrumbs: [{ title: 'Puestos', href: index() }],
};
