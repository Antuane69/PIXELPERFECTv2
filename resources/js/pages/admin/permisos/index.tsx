import { Head } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/PermissionController';
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
import { PermissionFormFields } from '@/features/permisos/permission-form-fields';
import type {
    LaravelPaginator,
    Modulo,
    PermissionScope,
    PlatformPermission,
} from '@/types';

type SelectableModule = Modulo & { activo: boolean };

type Props = {
    permissions: LaravelPaginator<PlatformPermission>;
    modules: SelectableModule[];
    scopes: PermissionScope[];
    filters: {
        search: string;
        alcance: PermissionScope | null;
        moduloId: number | null;
        perPage: number;
    };
};

export default function PlatformPermissionsIndex({
    permissions,
    modules,
    scopes,
    filters,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<PlatformPermission | null>(null);
    const [deleting, setDeleting] = useState<PlatformPermission | null>(null);
    const filterFacets: FilterFacet[] = [
        {
            key: 'alcance',
            label: 'Alcance',
            options: scopes.map((scope) => ({
                value: scope,
                label: scope === 'EMPRESA' ? 'Empresa' : 'Plataforma',
            })),
        },
        {
            key: 'modulo_id',
            label: 'Módulo',
            options: modules.map((module) => ({
                value: module.id,
                label: module.nombre,
            })),
        },
    ];
    const columns: ResourceColumn<PlatformPermission>[] = [
        {
            key: 'permission',
            header: 'Permiso',
            cell: (permission) => (
                <div className="flex items-center gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <KeyRound className="size-4" aria-hidden="true" />
                    </span>
                    <code className="font-medium break-all">
                        {permission.name}
                    </code>
                </div>
            ),
        },
        {
            key: 'scope',
            header: 'Alcance',
            cell: (permission) => (
                <Badge
                    variant={
                        permission.alcance === 'PLATAFORMA'
                            ? 'default'
                            : 'secondary'
                    }
                >
                    {permission.alcance === 'PLATAFORMA'
                        ? 'Plataforma'
                        : 'Empresa'}
                </Badge>
            ),
        },
        {
            key: 'module',
            header: 'Módulo',
            cell: (permission) =>
                permission.modulo?.nombre ?? 'Administración global',
        },
        {
            key: 'roles',
            header: 'Roles asignados',
            mobileHidden: true,
            cell: (permission) =>
                permission.roles_count.toLocaleString('es-MX'),
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'md:w-28',
            cell: (permission) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        onClick={() => {
                            setEditing(permission);
                            setDialogOpen(true);
                        }}
                        aria-label={`Editar permiso ${permission.name}`}
                    >
                        <Pencil />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        className="text-destructive hover:text-destructive"
                        onClick={() => setDeleting(permission)}
                        aria-label={`Eliminar permiso ${permission.name}`}
                    >
                        <Trash2 />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Permisos" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Permisos"
                    description="Controla capacidades disponibles y dominio empresarial al que pertenecen."
                    actions={
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setDialogOpen(true);
                            }}
                        >
                            <Plus /> Nuevo permiso
                        </Button>
                    }
                />
                <FiltrosBase
                    route={index()}
                    defaultSearch={filters.search}
                    placeholder="Buscar permiso"
                    facets={filterFacets}
                    query={{
                        alcance: filters.alcance,
                        modulo_id: filters.moduloId,
                        per_page: filters.perPage,
                    }}
                />
                <ResourceTable
                    data={permissions.data}
                    columns={columns}
                    getRowKey={(permission) => permission.id}
                    emptyTitle="No hay permisos"
                    emptyDescription="Crea primer permiso para nuevo flujo de autorización."
                />
                <ResourcePagination paginator={permissions} />
            </main>

            {dialogOpen ? (
                <ResourceFormDialog
                    open
                    onOpenChange={setDialogOpen}
                    title={editing ? 'Editar permiso' : 'Nuevo permiso'}
                    description="Nombre debe coincidir exactamente con validación usada por sistema."
                    formId="permission-form"
                    form={editing ? update.form(editing.id) : store.form()}
                    submitLabel={editing ? 'Guardar cambios' : 'Crear permiso'}
                    resetOnSuccess={!editing}
                >
                    {(errors) => (
                        <PermissionFormFields
                            permission={editing}
                            modules={modules}
                            errors={errors}
                        />
                    )}
                </ResourceFormDialog>
            ) : null}

            {deleting ? (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el permiso “${deleting.name}”`}
                    description="Sólo puede eliminarse cuando ningún rol o usuario lo tiene asignado."
                />
            ) : null}
        </>
    );
}

PlatformPermissionsIndex.layout = {
    breadcrumbs: [
        { title: 'Plataforma', href: index() },
        { title: 'Permisos', href: index() },
    ],
};
