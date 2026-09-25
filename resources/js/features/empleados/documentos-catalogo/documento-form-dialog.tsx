import { ChevronDown } from 'lucide-react';
import { useRef, useState } from 'react';
import {
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
    carpetaIdInicial: number;
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
    const [contenido, setContenido] = useState(documento?.contenido_html ?? '');
    const [carpetaId, setCarpetaId] = useState(
        String(documento?.empleado_carpeta_id ?? carpetaIdInicial),
    );
    const [moduloIds, setModuloIds] = useState<number[]>(() => {
        const modulosDisponiblesIds = new Set(
            modulosDisponibles.map((modulo) => modulo.id),
        );

        return (documento?.modulo_ids ?? []).filter((id) =>
            modulosDisponiblesIds.has(id),
        );
    });
    const [editorReady, setEditorReady] = useState(false);
    const editorElementRef = useRef<HTMLTextAreaElement | null>(null);
    const editorInstanceRef = useRef<CKEditorInstance | null>(null);
    const formId = 'empleado-documento-catalogo-form';

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={documento ? 'Editar documento' : 'Nuevo documento'}
            description="Guarda una plantilla HTML, selecciona su carpeta y relaciona módulos opcionales."
            formId={formId}
            form={documento ? update.form(documento.id) : store.form()}
            className="sm:max-w-6xl"
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
                                    defaultValue={documento?.nombre}
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
