import { Head, router, useHttp } from '@inertiajs/react';
import {
    Download,
    FilePlus2,
    Folder,
    LoaderCircle,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { index as empleadosIndex } from '@/actions/App/Http/Controllers/EmpleadoController';
import {
    destroy,
    download,
    index,
    restore,
    show as showDocumento,
} from '@/actions/App/Http/Controllers/EmpleadoDocumentoCatalogoController';
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
import { DocumentoFormDialog } from '@/features/empleados/documentos-catalogo/documento-form-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { getBackendErrorMessage } from '@/lib/app-alerts';
import { inicio as empresaInicio } from '@/routes/empresas';
import type {
    EmpleadoDocumentoCatalogo,
    EmpleadoDocumentoCatalogoVariable,
    LaravelPaginator,
    Modulo,
} from '@/types';

type FolderOption = {
    id: number;
    nombre: string;
    documentos_count: number;
};

type Props = {
    carpetas: FolderOption[];
    modulosDisponibles: Pick<Modulo, 'id' | 'clave' | 'nombre'>[];
    carpetaSeleccionada: { id: number; nombre: string } | null;
    documentos: LaravelPaginator<EmpleadoDocumentoCatalogo>;
    variables: EmpleadoDocumentoCatalogoVariable[];
    filters: {
        carpetaId: number | null;
        search: string;
        archivados: boolean;
        perPage: number;
    };
};

type DocumentoResponse = {
    documento: {
        id: number;
        nombre: string;
        empleado_carpeta_id: number;
        contenido_html: string;
        modulo_ids: number[];
    };
};

export default function EmpleadoDocumentosCatalogoIndex({
    carpetas,
    carpetaSeleccionada,
    documentos,
    modulosDisponibles,
    variables,
    filters,
}: Props) {
    const { can } = usePermissions();
    const { get: getDocumento } = useHttp<
        Record<string, never>,
        DocumentoResponse
    >();
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<EmpleadoDocumentoCatalogo | null>(
        null,
    );
    const [deleting, setDeleting] = useState<EmpleadoDocumentoCatalogo | null>(
        null,
    );
    const [loadingDocumentId, setLoadingDocumentId] = useState<number | null>(
        null,
    );
    const [documentLoadError, setDocumentLoadError] = useState<string | null>(
        null,
    );
    const showingArchived = filters.archivados;
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

    const selectFolder = (folderId: number) => {
        router.get(
            index.url(),
            {
                carpeta_id: folderId,
                search: filters.search || undefined,
                archivados: showingArchived || undefined,
                per_page: filters.perPage,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const openEdit = async (documento: EmpleadoDocumentoCatalogo) => {
        setLoadingDocumentId(documento.id);
        setDocumentLoadError(null);

        try {
            const response = await getDocumento(
                showDocumento.url(documento.id),
            );

            setEditing({ ...documento, ...response.documento });
            setFormOpen(true);
        } catch (error: unknown) {
            setDocumentLoadError(
                getBackendErrorMessage(
                    error,
                    'No se pudo cargar el documento para editarlo.',
                ),
            );
        } finally {
            setLoadingDocumentId(null);
        }
    };

    const columns: ResourceColumn<EmpleadoDocumentoCatalogo>[] = [
        {
            key: 'nombre',
            header: 'Documento',
            cell: (documento) => (
                <div className="grid gap-1">
                    <span className="font-medium">{documento.nombre}</span>
                    <span className="text-xs text-muted-foreground">
                        Actualizado {formatDate(documento.updated_at)}
                    </span>
                </div>
            ),
        },
        {
            key: 'folder',
            header: 'Carpeta',
            cell: (documento) => (
                <Badge variant="secondary">
                    <Folder />
                    {documento.carpeta_nombre ?? 'Carpeta no disponible'}
                </Badge>
            ),
        },
        {
            key: 'modulos',
            header: 'Módulos',
            cell: (documento) => (
                <div className="flex flex-wrap gap-1">
                    {documento.modulos?.length ? (
                        documento.modulos.map((modulo) => (
                            <Badge key={modulo.id} variant="outline">
                                {modulo.nombre}
                            </Badge>
                        ))
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            Sin módulos visibles
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'md:w-36',
            cell: (documento) => (
                <div className="flex flex-wrap justify-end gap-2 md:justify-start">
                    {showingArchived ? (
                        can('empleados_documentos_catalogo.update') ? (
                            <RestoreButton
                                form={restore.form(documento.id)}
                                subject={`el documento ${documento.nombre}`}
                            />
                        ) : null
                    ) : (
                        <>
                            <Button
                                asChild
                                size="icon"
                                variant="outline"
                                aria-label={`Descargar ${documento.nombre} como PDF`}
                            >
                                <a href={download.url(documento.id)}>
                                    <Download />
                                </a>
                            </Button>
                            {can('empleados_documentos_catalogo.update') ? (
                                <Button
                                    size="icon"
                                    variant="outline"
                                    onClick={() => void openEdit(documento)}
                                    disabled={loadingDocumentId !== null}
                                    aria-busy={
                                        loadingDocumentId === documento.id
                                    }
                                    aria-label={
                                        loadingDocumentId === documento.id
                                            ? `Cargando ${documento.nombre}`
                                            : `Editar ${documento.nombre}`
                                    }
                                >
                                    {loadingDocumentId === documento.id ? (
                                        <LoaderCircle className="animate-spin" />
                                    ) : (
                                        <Pencil />
                                    )}
                                </Button>
                            ) : null}
                            {can('empleados_documentos_catalogo.delete') ? (
                                <Button
                                    size="icon"
                                    variant="outline"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => setDeleting(documento)}
                                    aria-label={`Archivar ${documento.nombre}`}
                                >
                                    <Trash2 />
                                </Button>
                            ) : null}
                        </>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Documentos de empleados" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Documentos de empleados"
                    description="Administra plantillas HTML por carpeta y genera PDF con los datos del expediente."
                    actions={
                        carpetaSeleccionada &&
                        !showingArchived &&
                        can('empleados_documentos_catalogo.create') ? (
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setDocumentLoadError(null);
                                    setFormOpen(true);
                                }}
                            >
                                <FilePlus2 /> Nuevo documento
                            </Button>
                        ) : undefined
                    }
                />

                {documentLoadError ? (
                    <p className="text-sm text-destructive" role="alert">
                        {documentLoadError}
                    </p>
                ) : null}

                <section className="grid gap-3" aria-labelledby="folders-title">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2
                            id="folders-title"
                            className="text-base font-semibold"
                        >
                            Carpetas
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Solo aparecen carpetas propias o compartidas
                            contigo.
                        </p>
                    </div>
                    {carpetas.length ? (
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {carpetas.map((carpeta) => {
                                const selected =
                                    carpeta.id === carpetaSeleccionada?.id;

                                return (
                                    <Button
                                        key={carpeta.id}
                                        type="button"
                                        variant={
                                            selected ? 'secondary' : 'outline'
                                        }
                                        className="h-auto justify-start gap-3 px-4 py-3 text-left"
                                        aria-pressed={selected}
                                        onClick={() => selectFolder(carpeta.id)}
                                    >
                                        <Folder
                                            className="size-5 shrink-0 text-primary"
                                            aria-hidden="true"
                                        />
                                        <span className="grid min-w-0 flex-1 gap-1">
                                            <span className="truncate font-medium">
                                                {carpeta.nombre}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {carpeta.documentos_count}{' '}
                                                {carpeta.documentos_count === 1
                                                    ? 'documento'
                                                    : 'documentos'}
                                            </span>
                                        </span>
                                    </Button>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed border-border p-6 text-sm text-muted-foreground">
                            No tienes carpetas vigentes disponibles. Crea una
                            carpeta o solicita acceso desde el catálogo de
                            Carpetas.
                        </div>
                    )}
                </section>

                {carpetaSeleccionada ? (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <Folder className="size-4 text-primary" />
                                <h2 className="text-base font-semibold">
                                    {carpetaSeleccionada.nombre}
                                </h2>
                            </div>
                            <FiltrosBase
                                route={index()}
                                defaultSearch={filters.search}
                                placeholder="Buscar documento"
                                facets={filterFacets}
                                query={{
                                    carpeta_id: carpetaSeleccionada.id,
                                    archivados: showingArchived,
                                    per_page: filters.perPage,
                                }}
                            />
                        </div>
                        <ResourceTable
                            data={documentos.data}
                            columns={columns}
                            getRowKey={(documento) => documento.id}
                            emptyTitle={
                                showingArchived
                                    ? 'No hay documentos archivados'
                                    : 'No hay documentos en esta carpeta'
                            }
                            emptyDescription={
                                showingArchived
                                    ? 'No existen plantillas archivadas que coincidan con estos filtros.'
                                    : 'Crea una plantilla HTML para generar PDF desde el expediente de una persona empleada.'
                            }
                        />
                        <ResourcePagination paginator={documentos} />
                    </>
                ) : (
                    <div className="rounded-xl border border-dashed border-border p-8 text-center">
                        <Folder className="mx-auto size-8 text-muted-foreground" />
                        <p className="mt-3 font-medium">
                            Selecciona una carpeta
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Sus documentos aparecerán aquí, ordenados por
                            nombre.
                        </p>
                    </div>
                )}
            </main>

            {formOpen && carpetaSeleccionada ? (
                <DocumentoFormDialog
                    key={editing?.id ?? 'nuevo'}
                    open
                    onOpenChange={setFormOpen}
                    documento={editing}
                    carpetaIdInicial={carpetaSeleccionada.id}
                    carpetas={carpetas}
                    modulosDisponibles={modulosDisponibles}
                    variables={variables}
                />
            ) : null}
            {deleting ? (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el documento “${deleting.nombre}”`}
                    description="El documento quedará archivado y dejará de aparecer al imprimir expedientes."
                />
            ) : null}
        </>
    );
}

EmpleadoDocumentosCatalogoIndex.layout = {
    breadcrumbs: [
        { title: 'Inicio', href: empresaInicio() },
        { title: 'Empleados', href: empleadosIndex() },
        { title: 'Documentos', href: index() },
    ],
};

function formatDate(value: string | null): string {
    if (!value) {
        return 'No registrada';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'No registrada';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
    }).format(date);
}
