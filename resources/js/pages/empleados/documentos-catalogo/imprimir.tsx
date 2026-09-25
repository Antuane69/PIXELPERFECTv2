import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Folder, Printer } from 'lucide-react';
import { useMemo, useState } from 'react';
import { index as empleadosIndex } from '@/actions/App/Http/Controllers/EmpleadoController';
import { index as documentosIndex } from '@/actions/App/Http/Controllers/EmpleadoDocumentoCatalogoController';
import {
    descargar,
    seleccionar,
} from '@/actions/App/Http/Controllers/EmpleadoDocumentoImpresionController';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { inicio as empresaInicio } from '@/routes/empresas';
import type { LaravelPaginator } from '@/types';

const MAX_DOCUMENTOS = 25;

type DocumentoOption = {
    id: number;
    nombre: string;
};

type CarpetaDocumentos = {
    id: number;
    nombre: string;
    documentos_count: number;
};

type Props = {
    empleado: { id: number; nombre: string };
    carpetas: CarpetaDocumentos[];
    carpetaSeleccionada: Pick<CarpetaDocumentos, 'id' | 'nombre'> | null;
    documentos: LaravelPaginator<DocumentoOption>;
};

export default function ImprimirDocumentosEmpleado({
    empleado,
    carpetas,
    carpetaSeleccionada,
    documentos,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const downloadUrl = useMemo(() => {
        if (selectedIds.length === 0) {
            return null;
        }

        const url = new URL(descargar.url(empleado.id), window.location.origin);
        selectedIds.forEach((id) =>
            url.searchParams.append('documento_ids[]', String(id)),
        );

        return url.toString();
    }, [empleado.id, selectedIds]);

    const toggleDocument = (documentoId: number, checked: boolean) => {
        setSelectedIds((current) => {
            if (checked) {
                return current.includes(documentoId) ||
                    current.length >= MAX_DOCUMENTOS
                    ? current
                    : [...current, documentoId];
            }

            return current.filter((id) => id !== documentoId);
        });
    };

    const selectFolder = (folderId: number) => {
        router.get(
            seleccionar.url(empleado.id),
            { carpeta_id: folderId, per_page: documentos.per_page },
            { preserveScroll: true, preserveState: true },
        );
    };

    const totalDocuments = carpetas.reduce(
        (count, carpeta) => count + carpeta.documentos_count,
        0,
    );

    return (
        <>
            <Head title={`Imprimir documentos · ${empleado.nombre}`} />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title={`Imprimir documentos · ${empleado.nombre}`}
                    description="Selecciona plantillas accesibles para generar documentos con los datos del expediente."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={empleadosIndex()}>
                                <ArrowLeft /> Volver a empleados
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {selectedIds.length} de {MAX_DOCUMENTOS} seleccionados
                    </Badge>
                    {selectedIds.length === 1 ? (
                        <span className="text-sm text-muted-foreground">
                            Se descargará un PDF.
                        </span>
                    ) : selectedIds.length > 1 ? (
                        <span className="text-sm text-muted-foreground">
                            Se descargará un ZIP con un PDF por documento.
                        </span>
                    ) : null}
                </div>

                {totalDocuments > 0 ? (
                    <>
                        <section
                            className="grid gap-3"
                            aria-labelledby="folders-title"
                        >
                            <h2
                                id="folders-title"
                                className="text-base font-semibold"
                            >
                                Carpetas disponibles
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {carpetas.map((carpeta) => {
                                    const selected =
                                        carpeta.id === carpetaSeleccionada?.id;

                                    return (
                                        <Button
                                            key={carpeta.id}
                                            type="button"
                                            variant={
                                                selected
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            className="h-auto justify-start gap-3 px-4 py-3 text-left"
                                            aria-pressed={selected}
                                            onClick={() =>
                                                selectFolder(carpeta.id)
                                            }
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
                                                    {carpeta.documentos_count ===
                                                    1
                                                        ? 'documento'
                                                        : 'documentos'}
                                                </span>
                                            </span>
                                        </Button>
                                    );
                                })}
                            </div>
                        </section>

                        {carpetaSeleccionada ? (
                            <section
                                className="grid gap-3 rounded-xl border bg-card p-4 shadow-sm sm:p-5"
                                aria-labelledby="documents-title"
                            >
                                <header className="flex items-center gap-2 border-b pb-3">
                                    <FileText
                                        className="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                    <h2
                                        id="documents-title"
                                        className="font-semibold"
                                    >
                                        {carpetaSeleccionada.nombre}
                                    </h2>
                                    <span className="text-sm text-muted-foreground">
                                        {documentos.total} documentos
                                    </span>
                                </header>
                                {documentos.data.length > 0 ? (
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {documentos.data.map((documento) => {
                                            const checked =
                                                selectedIds.includes(
                                                    documento.id,
                                                );
                                            const selectionLimitReached =
                                                selectedIds.length >=
                                                MAX_DOCUMENTOS;

                                            return (
                                                <label
                                                    key={documento.id}
                                                    htmlFor={`documento-${documento.id}`}
                                                    className="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-border px-3 py-2 hover:bg-muted/50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                                                >
                                                    <Checkbox
                                                        id={`documento-${documento.id}`}
                                                        checked={checked}
                                                        disabled={
                                                            !checked &&
                                                            selectionLimitReached
                                                        }
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleDocument(
                                                                documento.id,
                                                                value === true,
                                                            )
                                                        }
                                                    />
                                                    <span className="min-w-0 truncate text-sm font-medium">
                                                        {documento.nombre}
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p className="py-4 text-sm text-muted-foreground">
                                        Esta carpeta no tiene documentos
                                        vigentes.
                                    </p>
                                )}
                                <ResourcePagination paginator={documentos} />
                            </section>
                        ) : (
                            <section className="grid justify-items-center gap-2 rounded-xl border border-dashed border-border p-8 text-center">
                                <Folder className="size-8 text-muted-foreground" />
                                <p className="font-medium">
                                    Selecciona una carpeta
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Sus documentos aparecerán aquí, ordenados
                                    por nombre.
                                </p>
                            </section>
                        )}
                    </>
                ) : (
                    <section className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border p-8 text-center">
                        <FileText className="size-8 text-muted-foreground" />
                        <div className="grid gap-1">
                            <h2 className="font-medium">
                                No hay documentos disponibles
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Crea una plantilla dentro de una carpeta a la
                                que tengas acceso.
                            </p>
                        </div>
                        <Button asChild variant="outline">
                            <Link href={documentosIndex()}>
                                Abrir catálogo de documentos
                            </Link>
                        </Button>
                    </section>
                )}

                <div className="flex flex-wrap justify-end gap-2 border-t pt-4">
                    <Button asChild variant="outline">
                        <Link href={empleadosIndex()}>Cancelar</Link>
                    </Button>
                    {downloadUrl ? (
                        <Button asChild>
                            <a href={downloadUrl}>
                                <Printer />
                                {selectedIds.length > 1
                                    ? 'Generar y descargar ZIP'
                                    : 'Generar y descargar PDF'}
                                <Download />
                            </a>
                        </Button>
                    ) : (
                        <Button disabled>
                            <Printer /> Selecciona documentos
                        </Button>
                    )}
                </div>
            </main>
        </>
    );
}

ImprimirDocumentosEmpleado.layout = {
    breadcrumbs: [
        { title: 'Inicio', href: empresaInicio() },
        { title: 'Empleados', href: empleadosIndex() },
        { title: 'Documentos', href: documentosIndex() },
        { title: 'Imprimir', href: empleadosIndex() },
    ],
};
