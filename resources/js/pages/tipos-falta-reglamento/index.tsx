import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    cambiarPagina,
    destroy,
    filtrar,
    index,
    restore,
} from '@/actions/App/Http/Controllers/FaltasReglamento/TipoFaltaReglamentoController';
import { ArchivedRecordsToggle } from '@/components/archived-records-toggle';
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
import { TipoFaltaFormDialog } from '@/features/faltas-reglamento/tipo-falta-form-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { inicio as empresaInicio } from '@/routes/empresas';
import type { LaravelPaginator, TipoFaltaReglamento } from '@/types';

type Filters = {
    search: string;
    activo: boolean | null;
    archivados: boolean;
};

type Props = {
    tiposFalta: LaravelPaginator<TipoFaltaReglamento>;
    filters: Filters;
};

const statusFacet: FilterFacet = {
    key: 'activo',
    label: 'Estado',
    options: [
        { value: true, label: 'Activos' },
        { value: false, label: 'Inactivos' },
    ],
};

export default function TiposFaltaReglamentoIndex({
    tiposFalta,
    filters,
}: Props) {
    const { can } = usePermissions();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TipoFaltaReglamento | null>(null);
    const [archiving, setArchiving] = useState<TipoFaltaReglamento | null>(
        null,
    );
    const showingArchived = filters.archivados;

    const submitFilters = (
        query: Record<
            string,
            string | number | boolean | Array<string | number | boolean>
        >,
    ) =>
        router.post(
            filtrar().url,
            { ...query, archivados: showingArchived },
            { preserveScroll: true, preserveState: true },
        );

    const toggleArchived = () =>
        router.post(
            filtrar().url,
            {
                search: filters.search,
                activo: filters.activo,
                archivados: !showingArchived,
            },
            { preserveScroll: true, preserveState: true },
        );

    const columns: ResourceColumn<TipoFaltaReglamento>[] = [
        {
            key: 'nombre',
            header: 'Tipo de falta',
            cell: (tipo) => <span className="font-medium">{tipo.nombre}</span>,
        },
        {
            key: 'descripcion',
            header: 'Descripción',
            cell: (tipo) => (
                <span className="block max-w-xl truncate">
                    {tipo.descripcion || '—'}
                </span>
            ),
            mobileHidden: true,
        },
        {
            key: 'estado',
            header: 'Estado',
            cell: (tipo) => (
                <Badge
                    variant={
                        showingArchived
                            ? 'outline'
                            : tipo.activo
                              ? 'default'
                              : 'secondary'
                    }
                >
                    {showingArchived
                        ? 'Archivado'
                        : tipo.activo
                          ? 'Activo'
                          : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            header: 'Acciones',
            className: 'md:w-32',
            cell: (tipo) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    {showingArchived && can('tipos_falta_reglamento.update') ? (
                        <RestoreButton
                            form={restore.form(tipo.id)}
                            subject={`el tipo ${tipo.nombre}`}
                        />
                    ) : null}
                    {!showingArchived &&
                    can('tipos_falta_reglamento.update') ? (
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            onClick={() => {
                                setEditing(tipo);
                                setDialogOpen(true);
                            }}
                            aria-label={`Editar tipo ${tipo.nombre}`}
                        >
                            <Pencil aria-hidden="true" />
                        </Button>
                    ) : null}
                    {!showingArchived &&
                    can('tipos_falta_reglamento.delete') ? (
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setArchiving(tipo)}
                            aria-label={`Archivar tipo ${tipo.nombre}`}
                        >
                            <Trash2 aria-hidden="true" />
                        </Button>
                    ) : null}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Tipos de falta" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Tipos de falta"
                    description="Administra las categorías de faltas disponibles para tu empresa."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <ArchivedRecordsToggle
                                route={index()}
                                showingArchived={showingArchived}
                                activeLabel="Ver vigentes"
                                archivedLabel="Ver archivados"
                                onToggle={toggleArchived}
                            />
                            {!showingArchived &&
                            can('tipos_falta_reglamento.create') ? (
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
                    facets={[statusFacet]}
                    query={{ activo: filters.activo }}
                    title="Buscar tipos de falta"
                    onApply={submitFilters}
                />
                <ResourceTable
                    data={tiposFalta.data}
                    columns={columns}
                    getRowKey={(tipo) => tipo.id}
                    emptyTitle={
                        showingArchived
                            ? 'No hay tipos archivados'
                            : 'No hay tipos de falta'
                    }
                    emptyDescription="Agrega tipos de falta para organizarlos en el catálogo de tu empresa."
                />
                <ResourcePagination
                    paginator={tiposFalta}
                    onPageChange={(page) =>
                        router.post(
                            cambiarPagina().url,
                            { page },
                            { preserveScroll: true, preserveState: true },
                        )
                    }
                />
            </main>
            {dialogOpen ? (
                <TipoFaltaFormDialog
                    open
                    onOpenChange={(open) => {
                        setDialogOpen(open);

                        if (!open) {
                            setEditing(null);
                        }
                    }}
                    tipoFalta={editing}
                />
            ) : null}
            {archiving ? (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setArchiving(null)}
                    form={destroy.form(archiving.id)}
                    subject={`el tipo “${archiving.nombre}”`}
                    actionVerb="Archivar"
                    description="El tipo quedará fuera de las opciones para reportes nuevos. Los reportes anteriores conservarán su historial."
                />
            ) : null}
        </>
    );
}

TiposFaltaReglamentoIndex.layout = {
    breadcrumbs: [
        { title: 'Inicio', href: empresaInicio() },
        { title: 'Tipos de falta', href: index() },
    ],
};
