import { Head } from '@inertiajs/react';
import { Mail, Pencil, Plus, Trash2, UserRound } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    restore,
    store,
    update,
} from '@/actions/App/Http/Controllers/EmpleadoController';
import { ArchivedRecordsToggle } from '@/components/archived-records-toggle';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { EmpleadoFormFields } from '@/components/empleados/empleado-form-fields';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceSearch } from '@/components/resource-search';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { RestoreButton } from '@/components/restore-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import type {
    Empleado,
    LaravelPaginator,
    Puesto,
    TipoDocumentoEmpleado,
} from '@/types';

type Props = {
    empleados: LaravelPaginator<Empleado>;
    puestos: Puesto[];
    tiposDocumento: TipoDocumentoEmpleado[];
    filters?: { search?: string; archivados?: boolean };
};

export default function EmpleadosIndex({
    empleados,
    puestos,
    tiposDocumento,
    filters,
}: Props) {
    const { can } = usePermissions();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Empleado | null>(null);
    const [deleting, setDeleting] = useState<Empleado | null>(null);
    const showingArchived = filters?.archivados ?? false;

    const columns: ResourceColumn<Empleado>[] = [
        {
            key: 'nombre',
            header: 'Empleado',
            cell: (empleado) => (
                <div className="flex items-center gap-3">
                    {empleado.avatar_url ? (
                        <img
                            src={empleado.avatar_url}
                            alt=""
                            className="size-9 rounded-full border object-cover"
                        />
                    ) : (
                        <span className="flex size-9 items-center justify-center rounded-full bg-accent">
                            <UserRound className="size-4 text-muted-foreground" />
                        </span>
                    )}
                    <div className="grid min-w-0 gap-0.5">
                        <span className="truncate font-medium">
                            {empleado.nombre}
                        </span>
                        <span className="truncate text-xs text-muted-foreground">
                            @{empleado.nombre_usuario}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'contacto',
            header: 'Contacto',
            cell: (empleado) => (
                <div className="grid gap-1">
                    <span className="inline-flex items-center gap-1.5">
                        <Mail className="size-3.5 text-muted-foreground" />
                        {empleado.correo}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {empleado.telefono}
                    </span>
                </div>
            ),
        },
        {
            key: 'puesto',
            header: 'Puesto',
            cell: (empleado) =>
                empleado.puesto?.nombre ? (
                    <Badge variant="secondary">{empleado.puesto.nombre}</Badge>
                ) : (
                    <span className="text-muted-foreground">Sin puesto</span>
                ),
        },
        {
            key: 'ingreso',
            header: 'Ingreso',
            cell: (empleado) => empleado.fecha_ingreso ?? '—',
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'md:w-32',
            cell: (empleado) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    {showingArchived && can('empleados.update') && (
                        <RestoreButton
                            form={restore.form(empleado.id)}
                            subject={`a ${empleado.nombre}`}
                        />
                    )}
                    {!showingArchived && can('empleados.update') && (
                        <Button
                            size="icon"
                            variant="outline"
                            onClick={() => {
                                setEditing(empleado);
                                setDialogOpen(true);
                            }}
                            aria-label={`Editar a ${empleado.nombre}`}
                        >
                            <Pencil />
                        </Button>
                    )}
                    {!showingArchived && can('empleados.delete') && (
                        <Button
                            size="icon"
                            variant="outline"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeleting(empleado)}
                            aria-label={`Eliminar a ${empleado.nombre}`}
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
            <Head title="Empleados" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Empleados"
                    description="Gestiona datos laborales, documentos y vigencias del personal."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <ArchivedRecordsToggle
                                route={
                                    showingArchived
                                        ? index()
                                        : index({
                                              query: { archivados: true },
                                          })
                                }
                                showingArchived={showingArchived}
                                activeLabel="Ver vigentes"
                                archivedLabel="Ver archivados"
                            />
                            {!showingArchived && can('empleados.create') && (
                                <Button
                                    onClick={() => {
                                        setEditing(null);
                                        setDialogOpen(true);
                                    }}
                                >
                                    <Plus /> Nuevo empleado
                                </Button>
                            )}
                        </div>
                    }
                />
                <Card className="py-4">
                    <CardContent>
                        <ResourceSearch
                            route={index()}
                            defaultValue={filters?.search}
                            placeholder="Buscar por nombre, correo, CURP o RFC"
                            query={showingArchived ? { archivados: true } : {}}
                        />
                    </CardContent>
                </Card>
                <ResourceTable
                    data={empleados.data}
                    columns={columns}
                    getRowKey={(empleado) => empleado.id}
                    emptyTitle={
                        showingArchived
                            ? 'No hay empleados archivados'
                            : 'No hay empleados'
                    }
                />
                <ResourcePagination paginator={empleados} />
            </main>

            {dialogOpen && (
                <ResourceFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    title={editing ? 'Editar empleado' : 'Nuevo empleado'}
                    description="Completa el expediente. Los errores se muestran junto a cada campo."
                    formId="empleado-form"
                    form={editing ? update.form(editing.id) : store.form()}
                    resetOnSuccess={!editing}
                    submitLabel={
                        editing ? 'Actualizar empleado' : 'Crear empleado'
                    }
                    className="sm:max-w-6xl"
                >
                    {(errors) => (
                        <EmpleadoFormFields
                            empleado={editing}
                            puestos={puestos}
                            tiposDocumento={tiposDocumento}
                            errors={errors}
                        />
                    )}
                </ResourceFormDialog>
            )}

            {deleting && (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el empleado “${deleting.nombre}”`}
                    description="El expediente dejará de aparecer en la operación; sus archivos se conservarán para auditoría."
                />
            )}
        </>
    );
}

EmpleadosIndex.layout = {
    breadcrumbs: [{ title: 'Empleados', href: index() }],
};
