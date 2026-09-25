import { Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    restore,
} from '@/actions/App/Http/Controllers/EmpleadoCarpetaController';
import { index as empleadosIndex } from '@/actions/App/Http/Controllers/EmpleadoController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { RestoreButton } from '@/components/restore-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CarpetaFormDialog } from '@/features/empleados/carpetas/carpeta-form-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { inicio as empresaInicio } from '@/routes/empresas';
import type { EmpleadoCarpeta, LaravelPaginator } from '@/types';

type EmpresaUserOption = {
    id: number;
    name: string;
    email: string;
};

type Props = {
    carpetas: LaravelPaginator<EmpleadoCarpeta>;
    usuarios: EmpresaUserOption[];
    filters: {
        search: string;
        archivados: boolean;
        perPage: number;
    };
};

export default function EmpleadoCarpetasIndex({
    carpetas,
    usuarios,
    filters,
}: Props) {
    const { can } = usePermissions();
    const { auth, empresas } = usePage().props;
    const currentUserId = auth.user?.id;
    const showingArchived = filters.archivados;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<EmpleadoCarpeta | null>(null);
    const [deleting, setDeleting] = useState<EmpleadoCarpeta | null>(null);

    if (!empresas.activa) {
        throw new Error('Empresa activa requerida para administrar carpetas.');
    }

    const filterFacets: FilterFacet[] = [
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

    const columns: ResourceColumn<EmpleadoCarpeta>[] = [
        {
            key: 'nombre',
            header: 'Nombre',
            cell: (carpeta) => (
                <span className="font-medium">{carpeta.nombre}</span>
            ),
        },
        {
            key: 'creador',
            header: 'Creada por',
            cell: (carpeta) =>
                carpeta.creado_por?.name ?? 'Usuario no disponible',
        },
        {
            key: 'usuarios',
            header: 'Personas con acceso',
            cell: (carpeta) =>
                carpeta.usuarios_con_acceso.length ? (
                    <div className="flex flex-wrap justify-end gap-1 md:justify-start">
                        {carpeta.usuarios_con_acceso.map((usuario) => (
                            <Badge key={usuario.id} variant="outline">
                                {usuario.name}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <span className="text-muted-foreground">
                        Solo la persona creadora
                    </span>
                ),
        },
        {
            key: 'estado',
            header: 'Estado',
            cell: () => (
                <Badge variant={showingArchived ? 'outline' : 'default'}>
                    {showingArchived ? 'Archivada' : 'Vigente'}
                </Badge>
            ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'md:w-32',
            cell: (carpeta) => {
                const isCreator = carpeta.creado_por_id === currentUserId;

                return (
                    <div className="flex justify-end gap-2 md:justify-start">
                        {showingArchived &&
                            isCreator &&
                            can('empleados_carpetas.update') && (
                                <RestoreButton
                                    form={restore.form(carpeta.id)}
                                    subject={`la carpeta ${carpeta.nombre}`}
                                />
                            )}
                        {!showingArchived &&
                            isCreator &&
                            can('empleados_carpetas.update') && (
                                <Button
                                    size="icon"
                                    variant="outline"
                                    onClick={() => {
                                        setEditing(carpeta);
                                        setDialogOpen(true);
                                    }}
                                    aria-label={`Editar carpeta ${carpeta.nombre}`}
                                >
                                    <Pencil />
                                </Button>
                            )}
                        {!showingArchived &&
                            isCreator &&
                            can('empleados_carpetas.delete') && (
                                <Button
                                    size="icon"
                                    variant="outline"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => setDeleting(carpeta)}
                                    aria-label={`Eliminar carpeta ${carpeta.nombre}`}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Head title="Carpetas" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Carpetas"
                    description="Organiza carpetas y comparte el acceso con personas de esta empresa."
                    actions={
                        !showingArchived && can('empleados_carpetas.create') ? (
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setDialogOpen(true);
                                }}
                            >
                                <Plus /> Nueva carpeta
                            </Button>
                        ) : undefined
                    }
                />
                <FiltrosBase
                    route={index()}
                    defaultSearch={filters.search}
                    placeholder="Buscar carpeta"
                    facets={filterFacets}
                    query={{
                        archivados: showingArchived,
                        per_page: filters.perPage,
                    }}
                />
                <ResourceTable
                    data={carpetas.data}
                    columns={columns}
                    getRowKey={(carpeta) => carpeta.id}
                    emptyTitle={
                        showingArchived
                            ? 'No hay carpetas archivadas'
                            : 'No hay carpetas'
                    }
                    emptyDescription={
                        showingArchived
                            ? 'No hay carpetas archivadas que coincidan con los filtros.'
                            : 'Crea una carpeta para organizar documentos y compartir acceso.'
                    }
                />
                <ResourcePagination paginator={carpetas} />
            </main>

            {dialogOpen && (
                <CarpetaFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    carpeta={editing}
                    usuarios={usuarios}
                />
            )}
            {deleting && (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`la carpeta “${deleting.nombre}”`}
                />
            )}
        </>
    );
}

EmpleadoCarpetasIndex.layout = {
    breadcrumbs: [
        { title: 'Inicio', href: empresaInicio() },
        { title: 'Empleados', href: empleadosIndex() },
        { title: 'Carpetas', href: index() },
    ],
};
