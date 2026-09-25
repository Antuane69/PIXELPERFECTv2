import { ChevronDown, Eye } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    preview,
    store,
    update,
} from '@/actions/App/Http/Controllers/EmpleadoDocumentoCatalogoController';
import type { CKEditorInstance } from '@/components/EditorTextoCK';
import EditorTextoCK from '@/components/EditorTextoCK';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type {
    EmpleadoDocumentoCatalogo,
    EmpleadoDocumentoCatalogoVariable,
    Modulo,
} from '@/types';

type FolderOption = {
    id: number;
    nombre: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documento: EmpleadoDocumentoCatalogo | null;
    carpetaIdInicial: number | null;
    carpetas: FolderOption[];
    modulosDisponibles: Pick<Modulo, 'id' | 'clave' | 'nombre'>[];
    variables: EmpleadoDocumentoCatalogoVariable[];
};

export function DocumentoFormDialog({
    open,
    onOpenChange,
    documento,
    carpetaIdInicial,
    carpetas,
    modulosDisponibles,
    variables,
}: Props) {
    const [nombre, setNombre] = useState(documento?.nombre ?? '');
    const [contenido, setContenido] = useState(documento?.contenido_html ?? '');
    const [carpetaId, setCarpetaId] = useState(() => {
        const initialFolderId =
            documento?.empleado_carpeta_id ?? carpetaIdInicial;

        return initialFolderId === null ? '' : String(initialFolderId);
    });
    const [moduloIds, setModuloIds] = useState<number[]>(() => {
        const modulosDisponiblesIds = new Set(
            modulosDisponibles.map((modulo) => modulo.id),
        );

        return (documento?.modulo_ids ?? []).filter((id) =>
            modulosDisponiblesIds.has(id),
        );
    });
    const [editorReady, setEditorReady] = useState(false);
    const [previewing, setPreviewing] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const editorElementRef = useRef<HTMLTextAreaElement | null>(null);
    const editorInstanceRef = useRef<CKEditorInstance | null>(null);
    const formId = 'empleado-documento-catalogo-form';

    const previewPdf = async (): Promise<void> => {
        setPreviewError(null);

        const previewWindow = window.open('', '_blank');

        if (!previewWindow) {
            setPreviewError(
                'Permite ventanas emergentes para abrir la vista previa.',
            );

            return;
        }

        previewWindow.opener = null;
        previewWindow.document.body.textContent = 'Generando vista previa…';
        setPreviewing(true);

        try {
            const formData = new FormData();
            const csrfToken = document
                .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.getAttribute('content');

            if (csrfToken) {
                formData.append('_token', csrfToken);
            }

            if (documento) {
                formData.append('documento_id', String(documento.id));
            }

            formData.append('nombre', nombre);
            formData.append('empleado_carpeta_id', carpetaId);
            moduloIds.forEach((id) => {
                formData.append('modulo_ids[]', String(id));
            });
            formData.append('contenido_html', contenido);

            const response = await fetch(preview.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json, application/pdf',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const responseBody = (await response
                    .json()
                    .catch(() => null)) as {
                    errors?: Record<string, string[]>;
                    message?: string;
                } | null;
                const validationMessage = Object.values(
                    responseBody?.errors ?? {},
                )
                    .flat()
                    .find((message) => typeof message === 'string');

                throw new Error(
                    validationMessage ??
                        responseBody?.message ??
                        'No se pudo generar la vista previa.',
                );
            }

            const previewUrl = URL.createObjectURL(await response.blob());
            previewWindow.location.replace(previewUrl);
            window.setTimeout(() => URL.revokeObjectURL(previewUrl), 60_000);
        } catch (error: unknown) {
            previewWindow.close();
            setPreviewError(
                error instanceof Error
                    ? error.message
                    : 'No se pudo generar la vista previa.',
            );
        } finally {
            setPreviewing(false);
        }
    };

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={documento ? 'Editar documento' : 'Nuevo documento'}
            description="Guarda una plantilla HTML, selecciona su carpeta y relaciona módulos opcionales."
            formId={formId}
            form={documento ? update.form(documento.id) : store.form()}
            className="sm:max-w-6xl"
            footerActions={
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void previewPdf()}
                    disabled={previewing}
                >
                    {previewing ? <Spinner /> : <Eye />}
                    Previsualizar PDF
                </Button>
            }
        >
            {(errors) => {
                const moduloError =
                    errors.modulo_ids ??
                    Object.entries(errors).find(([key]) =>
                        key.startsWith('modulo_ids.'),
                    )?.[1];

                return (
                    <div className="grid gap-5">
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="grid content-start gap-2">
                                <Label htmlFor="documento-catalogo-nombre">
                                    Nombre
                                </Label>
                                <Input
                                    id="documento-catalogo-nombre"
                                    name="nombre"
                                    value={nombre}
                                    onChange={(event) =>
                                        setNombre(event.target.value)
                                    }
                                    maxLength={180}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.nombre} />
                            </div>
                            <div className="grid content-start gap-2">
                                <Label htmlFor="documento-catalogo-carpeta">
                                    Carpeta
                                </Label>
                                <Select
                                    name="empleado_carpeta_id"
                                    value={carpetaId}
                                    onValueChange={setCarpetaId}
                                    required
                                >
                                    <SelectTrigger
                                        id="documento-catalogo-carpeta"
                                        className="w-full"
                                        aria-invalid={Boolean(
                                            errors.empleado_carpeta_id,
                                        )}
                                    >
                                        <SelectValue placeholder="Selecciona carpeta" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {carpetas.map((carpeta) => (
                                            <SelectItem
                                                key={carpeta.id}
                                                value={String(carpeta.id)}
                                            >
                                                {carpeta.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.empleado_carpeta_id}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="documento-catalogo-modulos">
                                Módulos relacionados (opcional)
                            </Label>
                            {modulosDisponibles.length > 0 ? (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            id="documento-catalogo-modulos"
                                            type="button"
                                            variant="outline"
                                            className="w-full justify-between font-normal"
                                            aria-invalid={Boolean(moduloError)}
                                        >
                                            <span className="truncate text-left">
                                                {moduloIds.length > 0
                                                    ? modulosDisponibles
                                                          .filter((modulo) =>
                                                              moduloIds.includes(
                                                                  modulo.id,
                                                              ),
                                                          )
                                                          .map(
                                                              (modulo) =>
                                                                  modulo.nombre,
                                                          )
                                                          .join(', ')
                                                    : 'Selecciona módulos'}
                                            </span>
                                            <ChevronDown aria-hidden="true" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="start"
                                        className="max-h-64 w-[var(--radix-dropdown-menu-trigger-width)] overflow-y-auto"
                                    >
                                        {modulosDisponibles.map((modulo) => (
                                            <DropdownMenuCheckboxItem
                                                key={modulo.id}
                                                checked={moduloIds.includes(
                                                    modulo.id,
                                                )}
                                                onCheckedChange={(checked) => {
                                                    setModuloIds((selected) =>
                                                        checked
                                                            ? [
                                                                  ...selected,
                                                                  modulo.id,
                                                              ]
                                                            : selected.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      modulo.id,
                                                              ),
                                                    );
                                                }}
                                            >
                                                {modulo.nombre}
                                            </DropdownMenuCheckboxItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No tienes módulos disponibles para
                                    relacionar.
                                </p>
                            )}
                            {moduloIds.map((id) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name="modulo_ids[]"
                                    value={id}
                                />
                            ))}
                            <InputError message={moduloError} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="documento-catalogo-contenido">
                                Contenido del documento
                            </Label>
                            <div className="overflow-hidden rounded-md border border-border">
                                <EditorTextoCK
                                    setContent={setContenido}
                                    editorRef={editorElementRef}
                                    ckEditorRef={editorInstanceRef}
                                    initialContent={
                                        documento?.contenido_html ?? ''
                                    }
                                    widthProp="100%"
                                    heightProp={420}
                                    ariaLabel="Contenido del documento"
                                    onReady={() => setEditorReady(true)}
                                />
                            </div>
                            <input
                                type="hidden"
                                name="contenido_html"
                                value={contenido}
                            />
                            <InputError message={errors.contenido_html} />
                            {previewError ? (
                                <p
                                    className="text-sm text-destructive"
                                    role="alert"
                                >
                                    {previewError}
                                </p>
                            ) : null}
                        </div>

                        {moduloIds.length > 0 ? (
                            <fieldset className="grid gap-2">
                                <legend className="text-sm font-medium">
                                    Variables disponibles
                                </legend>
                                <p className="text-sm text-muted-foreground">
                                    Inserta una variable en el cursor. Se
                                    sustituirá al imprimir el documento para una
                                    persona empleada.
                                </p>
                                <div className="flex max-h-36 flex-wrap gap-2 overflow-y-auto rounded-md border border-border p-3">
                                    {variables.map((variable) => (
                                        <Button
                                            key={variable.key}
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                editorInstanceRef.current?.focus();
                                                editorInstanceRef.current?.insertText(
                                                    `{{${variable.key}}}`,
                                                );
                                            }}
                                            disabled={!editorReady}
                                            aria-label={`Insertar ${variable.label}`}
                                        >
                                            {`{{${variable.key}}}`}
                                        </Button>
                                    ))}
                                </div>
                            </fieldset>
                        ) : null}
                    </div>
                );
            }}
        </ResourceFormDialog>
    );
}
