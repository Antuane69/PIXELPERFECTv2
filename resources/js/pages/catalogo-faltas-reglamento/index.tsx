import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    cambiarPagina,
    destroy,
    filtrar,
    index,
    restore,
} from '@/actions/App/Http/Controllers/FaltasReglamento/FaltaReglamentoCatalogoController';
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
import { FaltaCatalogoFormDialog } from '@/features/faltas-reglamento/falta-catalogo-form-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { inicio as empresaInicio } from '@/routes/empresas';
import type {
    FaltaReglamentoCatalogo,
    LaravelPaginator,
    TipoFaltaReglamentoOption,
} from '@/types';

type Filters = {
    search: string;
    activo: boolean | null;
    archivados: boolean;
    tipoFaltaReglamentoId: number | null;
};

type TipoFilterOption = {
    id: number;
    nombre: string;
    activo: boolean;
    archivadoAt: string | null;
};

type Props = {
    faltasCatalogo: LaravelPaginator<FaltaReglamentoCatalogo>;
    tiposFalta: TipoFaltaReglamentoOption[];
    tiposFaltaFiltro: TipoFilterOption[];
    filters: Filters;
};

export default function CatalogoFaltasReglamentoIndex({
    faltasCatalogo,
    tiposFalta,
    tiposFaltaFiltro,
    filters,
}: Props) {
    const { can } = usePermissions();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<FaltaReglamentoCatalogo | null>(
        null,
    );
    const [archiving, setArchiving] = useState<FaltaReglamentoCatalogo | null>(
        null,
    );
    const showingArchived = filters.archivados;
    const facets: FilterFacet[] = [
        {
            key: 'activo',
            label: 'Estado',
            options: [
                { value: true, label: 'Activas' },
                { value: false, label: 'Inactivas' },
            ],
        },
        {
            key: 'tipo_falta_reglamento_id',
            label: 'Tipo de falta',
            options: tiposFaltaFiltro.map((tipo) => ({
                value: tipo.id,
                label: tipo.archivadoAt
                    ? `${tipo.nombre} (archivado)`
                    : tipo.nombre,
            })),
        },
    ];

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
                tipo_falta_reglamento_id: filters.tipoFaltaReglamentoId,
                archivados: !showingArchived,
            },
            { preserveScroll: true, preserveState: true },
        );

    const columns: ResourceColumn<FaltaReglamentoCatalogo>[] = [
        {
            key: 'tipo',
            header: 'Tipo de falta',
            cell: (falta) => <span>{falta.tipoFalta}</span>,
        },
        {
            key: 'nombre',
            header: 'Falta del reglamento',
            cell: (falta) => (
                <span className="font-medium">{falta.nombre}</span>
            ),
        },
        {
            key: 'descripcion',
            header: 'Descripción',
            cell: (falta) => (
                <span className="block max-w-xl truncate">
                    {falta.descripcion || '—'}
                </span>
            ),
            mobileHidden: true,
        },
        {
            key: 'estado',
            header: 'Estado',
            cell: (falta) => (
                <Badge
                    variant={
                        showingArchived
                            ? 'outline'
                            : falta.activo
                              ? 'default'
                              : 'secondary'
                    }
                >
                    {showingArchived
                        ? 'Archivada'
                        : falta.activo
                          ? 'Activa'
                          : 'Inactiva'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            header: 'Acciones',
            className: 'md:w-32',
            cell: (falta) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    {showingArchived &&
                    can('faltas_reglamento_catalogo.update') ? (
                        <RestoreButton
                            form={restore.form(falta.id)}
                            subject={`la falta ${falta.nombre}`}
                        />
                    ) : null}
                    {!showingArchived &&
                    can('faltas_reglamento_catalogo.update') ? (
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            onClick={() => {
                                setEditing(falta);
                                setDialogOpen(true);
                            }}
                            aria-label={`Editar falta ${falta.nombre}`}
                        >
                            <Pencil aria-hidden="true" />
                        </Button>
                    ) : null}
                    {!showingArchived &&
                    can('faltas_reglamento_catalogo.delete') ? (
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setArchiving(falta)}
                            aria-label={`Archivar falta ${falta.nombre}`}
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
            <Head title="Faltas del catálogo" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Faltas del catálogo"
                    description="Define qué conductas corresponden a cada tipo de falta de tu empresa."
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
                            can('faltas_reglamento_catalogo.create') ? (
                                <Button
                                    type="button"
                                    onClick={() => {
                                        setEditing(null);
                                        setDialogOpen(true);
                                    }}
                                    disabled={tiposFalta.length === 0}
                                >
                                    <Plus aria-hidden="true" />
                                    Nueva falta
                                </Button>
                            ) : null}
                        </div>
                    }
                />
                {tiposFalta.length === 0 &&
                can('faltas_reglamento_catalogo.create') ? (
                    <p
                        className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                        role="status"
                    >
                        Primero crea un tipo de falta activo para organizar este
                        catálogo.
                    </p>
                ) : null}
                <FiltrosBase
                    route={index()}
                    defaultSearch={filters.search}
                    placeholder="Buscar falta o descripción"
                    facets={facets}
                    query={{
                        activo: filters.activo,
                        tipo_falta_reglamento_id: filters.tipoFaltaReglamentoId,
                    }}
                    title="Buscar faltas del catálogo"
                    onApply={submitFilters}
                />
                <ResourceTable
                    data={faltasCatalogo.data}
                    columns={columns}
                    getRowKey={(falta) => falta.id}
                    emptyTitle={
                        showingArchived
                            ? 'No hay faltas archivadas'
                            : 'No hay faltas del reglamento'
                    }
                    emptyDescription="Agrega faltas y relaciónalas con los tipos definidos para tu empresa."
                />
                <ResourcePagination
                    paginator={faltasCatalogo}
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
                <FaltaCatalogoFormDialog
                    open
                    onOpenChange={(open) => {
                        setDialogOpen(open);

                        if (!open) {
                            setEditing(null);
                        }
                    }}
                    faltaCatalogo={editing}
                    tiposFalta={tiposFalta}
                />
            ) : null}
            {archiving ? (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setArchiving(null)}
                    form={destroy.form(archiving.id)}
                    subject={`la falta “${archiving.nombre}”`}
                    actionVerb="Archivar"
                    description="La falta dejará de estar disponible para reportes nuevos; los registros históricos la conservarán."
                />
            ) : null}
        </>
    );
}

CatalogoFaltasReglamentoIndex.layout = {
    breadcrumbs: [
        { title: 'Inicio', href: empresaInicio() },
        { title: 'Faltas del catálogo', href: index() },
    ],
};
