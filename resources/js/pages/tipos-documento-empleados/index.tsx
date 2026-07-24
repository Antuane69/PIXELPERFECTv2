import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    restore,
    store,
    update,
} from '@/actions/App/Http/Controllers/TipoDocumentoEmpleadoController';
import { ArchivedRecordsToggle } from '@/components/archived-records-toggle';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import InputError from '@/components/input-error';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import type { LaravelPaginator, TipoDocumentoEmpleado } from '@/types';

type Props = {
    tiposDocumento: LaravelPaginator<TipoDocumentoEmpleado>;
    filters?: { search?: string; archivados?: boolean };
};

const formats = [
    'PDF',
    'JPG',
    'JPEG',
    'PNG',
    'WEBP',
    'DOC',
    'DOCX',
    'XLS',
    'XLSX',
];
const frequencyOptions = [
    { value: 'dias', label: 'Días' },
    { value: 'semanas', label: 'Semanas' },
    { value: 'meses', label: 'Meses' },
    { value: 'anios', label: 'Años' },
];

export default function TiposDocumentoIndex({
    tiposDocumento,
    filters,
}: Props) {
    const { can } = usePermissions();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TipoDocumentoEmpleado | null>(null);
    const [deleting, setDeleting] = useState<TipoDocumentoEmpleado | null>(
        null,
    );
    const showingArchived = filters?.archivados ?? false;

    const columns: ResourceColumn<TipoDocumentoEmpleado>[] = [
        {
            key: 'nombre',
            header: 'Nombre',
            cell: (tipo) => <span className="font-medium">{tipo.nombre}</span>,
        },
        {
            key: 'formatos',
            header: 'Formatos',
            cell: (tipo) => (
                <div className="flex flex-wrap justify-end gap-1 md:justify-start">
                    {tipo.documentos_aceptados.map((format) => (
                        <Badge key={format} variant="outline">
                            {format}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            key: 'renovacion',
            header: 'Renovación',
            cell: (tipo) =>
                tipo.es_renovable
                    ? `Cada ${tipo.frecuencia_cantidad ?? '—'} ${tipo.frecuencia_tipo ?? ''}`
                    : 'No renovable',
        },
        {
            key: 'activo',
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
            key: 'actions',
            header: 'Acciones',
            className: 'md:w-32',
            cell: (tipo) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    {showingArchived && can('tipos_documento.update') && (
                        <RestoreButton
                            form={restore.form(tipo.id)}
                            subject={`el tipo ${tipo.nombre}`}
                        />
                    )}
                    {!showingArchived && can('tipos_documento.update') && (
                        <Button
                            size="icon"
                            variant="outline"
                            onClick={() => {
                                setEditing(tipo);
                                setDialogOpen(true);
                            }}
                            aria-label={`Editar ${tipo.nombre}`}
                        >
                            <Pencil />
                        </Button>
                    )}
                    {!showingArchived && can('tipos_documento.delete') && (
                        <Button
                            size="icon"
                            variant="outline"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeleting(tipo)}
                            aria-label={`Eliminar ${tipo.nombre}`}
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
            <Head title="Tipos de documento" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Tipos de documento"
                    description="Configura los documentos requeridos para los expedientes."
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
                            {!showingArchived &&
                                can('tipos_documento.create') && (
                                    <Button
                                        onClick={() => {
                                            setEditing(null);
                                            setDialogOpen(true);
                                        }}
                                    >
                                        <Plus /> Nuevo tipo
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
                            placeholder="Buscar tipo de documento"
                            query={showingArchived ? { archivados: true } : {}}
                        />
                    </CardContent>
                </Card>
                <ResourceTable
                    data={tiposDocumento.data}
                    columns={columns}
                    getRowKey={(tipo) => tipo.id}
                    emptyTitle={
                        showingArchived
                            ? 'No hay tipos de documento archivados'
                            : 'No hay tipos de documento'
                    }
                />
                <ResourcePagination paginator={tiposDocumento} />
            </main>

            {dialogOpen && (
                <TipoDocumentoDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    tipo={editing}
                />
            )}
            {deleting && (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el tipo “${deleting.nombre}”`}
                />
            )}
        </>
    );
}

function TipoDocumentoDialog({
    open,
    onOpenChange,
    tipo,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tipo: TipoDocumentoEmpleado | null;
}) {
    const formId = 'tipo-documento-form';
    const [renewable, setRenewable] = useState(tipo?.es_renovable ?? false);
    const accepted = new Set(tipo?.documentos_aceptados ?? []);

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                tipo ? 'Editar tipo de documento' : 'Nuevo tipo de documento'
            }
            description="Define formatos, vigencia y disponibilidad."
            formId={formId}
            form={tipo ? update.form(tipo.id) : store.form()}
            resetOnSuccess={!tipo}
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="tipo-nombre">Nombre</Label>
                        <Input
                            id="tipo-nombre"
                            name="nombre"
                            defaultValue={tipo?.nombre}
                            required
                            autoFocus
                        />
                        <InputError message={errors.nombre} />
                    </div>
                    <fieldset className="grid gap-3 rounded-lg border p-4">
                        <legend className="px-1 text-sm font-medium">
                            Formatos aceptados
                        </legend>
                        <div className="flex flex-wrap gap-2">
                            {formats.map((format) => (
                                <label
                                    key={format}
                                    className="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 hover:bg-accent/60"
                                >
                                    <Checkbox
                                        name="documentos_aceptados[]"
                                        value={format}
                                        defaultChecked={accepted.has(format)}
                                    />
                                    <span className="text-sm">{format}</span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.documentos_aceptados} />
                    </fieldset>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="flex items-center gap-3 rounded-lg border p-4">
                            <input type="hidden" name="activo" value="0" />
                            <Checkbox
                                name="activo"
                                value="1"
                                defaultChecked={tipo?.activo ?? true}
                            />
                            <span className="text-sm font-medium">Activo</span>
                        </label>
                        <label className="flex items-center gap-3 rounded-lg border p-4">
                            <input
                                type="hidden"
                                name="es_renovable"
                                value="0"
                            />
                            <Checkbox
                                name="es_renovable"
                                value="1"
                                checked={renewable}
                                onCheckedChange={(checked) =>
                                    setRenewable(checked === true)
                                }
                            />
                            <span className="text-sm font-medium">
                                Requiere renovación
                            </span>
                        </label>
                    </div>
                    {renewable && (
                        <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="frecuencia-cantidad">
                                    Renovar cada
                                </Label>
                                <Input
                                    id="frecuencia-cantidad"
                                    type="number"
                                    name="frecuencia_cantidad"
                                    min="1"
                                    defaultValue={
                                        tipo?.frecuencia_cantidad ?? 1
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.frecuencia_cantidad}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Unidad</Label>
                                <Select
                                    name="frecuencia_tipo"
                                    defaultValue={
                                        tipo?.frecuencia_tipo ?? 'dias'
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Selecciona" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {frequencyOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.frecuencia_tipo} />
                            </div>
                        </div>
                    )}
                </div>
            )}
        </ResourceFormDialog>
    );
}

TiposDocumentoIndex.layout = {
    breadcrumbs: [{ title: 'Tipos de documento', href: index() }],
};
