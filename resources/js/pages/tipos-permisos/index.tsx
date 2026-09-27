import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    cambiarPagina,
    destroy,
    filtrar,
    index,
    restore,
} from '@/actions/App/Http/Controllers/PermisosLaborales/TipoPermisoController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import { ResourceExportDialog } from '@/components/resource-export-dialog';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { RestoreButton } from '@/components/restore-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TipoPermisoFormDialog } from '@/features/tipos-permisos/tipo-permiso-form-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { inicio as empresaInicio } from '@/routes/empresas';
import { exportar as exportarTiposPermisos } from '@/routes/empresas/reportes/tipos-permisos';
import type {
    LaravelPaginator,
    TipoPermiso,
    TipoPermisoFilters,
} from '@/types';

type TiposPermisoIndexProps = {
    tiposPermiso: LaravelPaginator<TipoPermiso>;
    filters: TipoPermisoFilters;
};

export default function TiposPermisoIndex({
    tiposPermiso,
    filters,
}: TiposPermisoIndexProps) {
    const { can } = usePermissions();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TipoPermiso | null>(null);
    const [archiving, setArchiving] = useState<TipoPermiso | null>(null);
    const showingArchived = filters.archivados;

    const filterFacets: FilterFacet[] = [
        {
            key: 'activo',
            label: 'Estado',
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

    const columns: ResourceColumn<TipoPermiso>[] = [
        {
            key: 'nombre',
            header: 'Nombre',
            cell: (tipoPermiso) => (
                <span className="font-medium">{tipoPermiso.nombre}</span>
            ),
        },
        {
            key: 'descripcion',
            header: 'Descripción',
            cell: (tipoPermiso) => (
                <span className="block max-w-xl truncate">
                    {tipoPermiso.descripcion || '—'}
                </span>
            ),
        },
        {
            key: 'estado',
            header: 'Estado',
            cell: (tipoPermiso) => (
                <Badge
                    variant={
                        showingArchived
                            ? 'outline'
                            : tipoPermiso.activo
                              ? 'default'
                              : 'secondary'
                    }
                >
                    {showingArchived
                        ? 'Archivado'
                        : tipoPermiso.activo
                          ? 'Activo'
                          : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            header: 'Acciones',
            className: 'md:w-32',
            cell: (tipoPermiso) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    {showingArchived && can('tipos_permisos.update') ? (
                        <RestoreButton
                            form={restore.form(tipoPermiso.id)}
                            subject={`el tipo ${tipoPermiso.nombre}`}
                        />
                    ) : null}
                    {!showingArchived && can('tipos_permisos.update') ? (
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            onClick={() => {
                                setEditing(tipoPermiso);
                                setDialogOpen(true);
                            }}
                            aria-label={`Editar tipo ${tipoPermiso.nombre}`}
                        >
                            <Pencil aria-hidden="true" />
                        </Button>
                    ) : null}
                    {!showingArchived && can('tipos_permisos.delete') ? (
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setArchiving(tipoPermiso)}
                            aria-label={`Archivar tipo ${tipoPermiso.nombre}`}
                        >
                            <Trash2 aria-hidden="true" />
                        </Button>
                    ) : null}
                </div>
            ),
        },
    ];

    const setPage = (page: number) => {
        router.post(
            cambiarPagina().url,
            { page },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Tipos de permisos" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Tipos de permisos"
                    description="Administra las categorías disponibles en las solicitudes de permisos laborales."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <ResourceExportDialog
                                report="tipos-permisos"
                                exportUrl={exportarTiposPermisos.url()}
                                filters={{
                                    search: filters.search,
                                    activo: filters.activo,
                                    archivados: showingArchived,
                                }}
                            />
                            {!showingArchived &&
                            can('tipos_permisos.create') ? (
                                <Button
                                    type="button"
                                    onClick={() => {
                                        setEditing(null);
                                        setDialogOpen(true);
                                    }}
                                >
                                    <Plus aria-hidden="true" />
                                    Nuevo tipo
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <FiltrosBase
                    route={index()}
                    defaultSearch={filters.search}
                    placeholder="Buscar por nombre o descripción"
                    facets={filterFacets}
                    query={{
                        activo: filters.activo,
                        archivados: showingArchived,
                    }}
                    title="Buscar tipos de permisos"
                    onApply={(query) =>
                        router.post(filtrar().url, query, {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                />

                <ResourceTable
                    data={tiposPermiso.data}
                    columns={columns}
                    getRowKey={(tipoPermiso) => tipoPermiso.id}
                    emptyTitle={
                        showingArchived
                            ? 'No hay tipos de permisos archivados'
                            : 'No hay tipos de permisos'
                    }
                    emptyDescription={
                        showingArchived
                            ? undefined
                            : 'Crea un tipo para habilitar su selección en nuevas solicitudes.'
                    }
                />

                <ResourcePagination
                    paginator={tiposPermiso}
                    onPageChange={setPage}
                />
            </main>

            {dialogOpen ? (
                <TipoPermisoFormDialog
                    open
                    onOpenChange={(open) => {
                        setDialogOpen(open);

                        if (!open) {
                            setEditing(null);
                        }
                    }}
                    tipoPermiso={editing}
                />
            ) : null}

            {archiving ? (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setArchiving(null)}
                    form={destroy.form(archiving.id)}
                    subject={`el tipo “${archiving.nombre}”`}
                    description="Se archivará el tipo. No se puede archivar si existen solicitudes asociadas."
                />
            ) : null}
        </>
    );
}

TiposPermisoIndex.layout = {
    breadcrumbs: [
        { title: 'Inicio', href: empresaInicio() },
        { title: 'Tipos de permisos', href: index() },
    ],
};
