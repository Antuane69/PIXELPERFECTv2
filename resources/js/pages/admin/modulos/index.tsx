import { Head } from '@inertiajs/react';
import { Boxes, KeyRound, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/ModuloController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ModuloFormFields } from '@/features/modulos/modulo-form-fields';
import { ModuloPermisosDrawer } from '@/features/modulos/modulo-permisos-drawer';
import type { LaravelPaginator, PlatformModule } from '@/types';

type Props = {
    modules: LaravelPaginator<PlatformModule>;
    filters: {
        search: string;
        activo: boolean | null;
        perPage: number;
    };
};

export default function ModulosIndex({ modules, filters }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<PlatformModule | null>(null);
    const [deleting, setDeleting] = useState<PlatformModule | null>(null);
    const [viewing, setViewing] = useState<PlatformModule | null>(null);
    const filterFacets: FilterFacet[] = [
        {
            key: 'activo',
            label: 'Estado del módulo',
            options: [
                { value: true, label: 'Activo' },
                { value: false, label: 'Inactivo' },
            ],
        },
    ];
    const columns: ResourceColumn<PlatformModule>[] = [
        {
            key: 'module',
            header: 'Módulo',
            cell: (module) => (
                <div className="flex items-center gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Boxes className="size-4" aria-hidden="true" />
                    </span>
                    <div className="grid min-w-0 gap-0.5">
                        <span className="truncate font-medium">
                            {module.nombre}
                        </span>
                        <code className="truncate text-xs text-muted-foreground">
                            {module.clave}
                        </code>
                    </div>
                </div>
            ),
        },
        {
            key: 'description',
            header: 'Descripción',
            mobileHidden: true,
            cell: (module) => (
                <span className="block max-w-80 truncate text-muted-foreground">
                    {module.descripcion || 'Sin descripción'}
                </span>
            ),
        },
        {
            key: 'usage',
            header: 'Uso',
            cell: (module) => (
                <span className="text-sm text-muted-foreground">
                    {module.empresas_count.toLocaleString('es-MX')} empresas ·{' '}
                    {module.permisos_count.toLocaleString('es-MX')} permisos
                </span>
            ),
        },
        {
            key: 'order',
            header: 'Orden',
            mobileHidden: true,
            cell: (module) => module.orden,
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (module) => (
                <Badge variant={module.activo ? 'default' : 'secondary'}>
                    {module.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'md:w-40',
            cell: (module) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        onClick={() => setViewing(module)}
                        aria-label={`Administrar permisos de ${module.nombre}`}
                        title="Administrar permisos"
                    >
                        <KeyRound />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        onClick={() => {
                            setEditing(module);
                            setDialogOpen(true);
                        }}
                        aria-label={`Editar módulo ${module.nombre}`}
                    >
                        <Pencil />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        className="text-destructive hover:text-destructive"
                        onClick={() => setDeleting(module)}
                        aria-label={`Eliminar módulo ${module.nombre}`}
                    >
                        <Trash2 />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Módulos" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Módulos"
                    description="Define dominios funcionales disponibles para asignar a empresas."
                    actions={
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setDialogOpen(true);
                            }}
                        >
                            <Plus /> Nuevo módulo
                        </Button>
                    }
                />
                <FiltrosBase
                    route={index()}
                    defaultSearch={filters.search}
                    placeholder="Buscar clave, nombre o descripción"
                    facets={filterFacets}
                    query={{
                        activo: filters.activo,
                        per_page: filters.perPage,
                    }}
                />
                <ResourceTable
                    data={modules.data}
                    columns={columns}
                    getRowKey={(module) => module.id}
                    emptyTitle="No hay módulos"
                    emptyDescription="Crea primer módulo para ampliar dominios empresariales."
                />
                <ResourcePagination paginator={modules} />
            </main>

            {viewing ? (
                <ModuloPermisosDrawer
                    key={viewing.id}
                    module={viewing}
                    open
                    onOpenChange={(open) => !open && setViewing(null)}
                />
            ) : null}

            {dialogOpen ? (
                <ResourceFormDialog
                    open
                    onOpenChange={setDialogOpen}
                    title={editing ? 'Editar módulo' : 'Nuevo módulo'}
                    description="Configura identidad técnica, presentación y disponibilidad."
                    formId="module-form"
                    form={editing ? update.form(editing.id) : store.form()}
                    submitLabel={editing ? 'Guardar cambios' : 'Crear módulo'}
                    resetOnSuccess={!editing}
                >
                    {(errors) => (
                        <ModuloFormFields module={editing} errors={errors} />
                    )}
                </ResourceFormDialog>
            ) : null}

            {deleting ? (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el módulo “${deleting.nombre}”`}
                    description="Sólo puede eliminarse cuando no tiene empresas ni permisos asociados."
                />
            ) : null}
        </>
    );
}

ModulosIndex.layout = {
    breadcrumbs: [
        { title: 'Plataforma', href: index() },
        { title: 'Módulos', href: index() },
    ],
};
