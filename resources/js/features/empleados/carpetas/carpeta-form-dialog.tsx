import {
    store,
    update,
} from '@/actions/App/Http/Controllers/EmpleadoCarpetaController';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EmpleadoCarpeta } from '@/types';

type EmpresaUserOption = {
    id: number;
    name: string;
    email: string;
};

type CarpetaFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    carpeta: EmpleadoCarpeta | null;
    usuarios: EmpresaUserOption[];
};

export function CarpetaFormDialog({
    open,
    onOpenChange,
    carpeta,
    usuarios,
}: CarpetaFormDialogProps) {
    const formId = 'empleado-carpeta-form';

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={carpeta ? 'Editar carpeta' : 'Nueva carpeta'}
            description="Asigna un nombre y define quién puede acceder a la carpeta."
            formId={formId}
            form={carpeta ? update.form(carpeta.id) : store.form()}
            resetOnSuccess={!carpeta}
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="empleado-carpeta-nombre">Nombre</Label>
                        <Input
                            id="empleado-carpeta-nombre"
                            name="nombre"
                            defaultValue={carpeta?.nombre}
                            maxLength={255}
                            required
                            autoFocus
                        />
                        <InputError message={errors.nombre} />
                    </div>

                    <div className="flex items-center gap-3 rounded-lg border p-4">
                        <input type="hidden" name="activo" value="0" />
                        <Checkbox
                            id="empleado-carpeta-activo"
                            name="activo"
                            value="1"
                            defaultChecked={carpeta?.activo ?? true}
                        />
                        <Label htmlFor="empleado-carpeta-activo">
                            Carpeta activa
                        </Label>
                    </div>
                    <InputError message={errors.activo} />

                    <input type="hidden" name="user_ids_present" value="1" />
                    <fieldset className="grid gap-2">
                        <legend className="text-sm leading-none font-medium">
                            Personas con acceso (opcional)
                        </legend>
                        <p className="text-sm text-muted-foreground">
                            Quien crea la carpeta conserva acceso
                            automáticamente.
                        </p>
                        {usuarios.length ? (
                            <div className="grid max-h-56 gap-2 overflow-y-auto rounded-md border border-border p-3">
                                {usuarios.map((usuario) => {
                                    const checked =
                                        carpeta?.usuarios_con_acceso.some(
                                            (asignado) =>
                                                asignado.id === usuario.id,
                                        ) ?? false;

                                    return (
                                        <label
                                            key={usuario.id}
                                            htmlFor={`empleado-carpeta-user-${usuario.id}`}
                                            className="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-muted/50"
                                        >
                                            <Checkbox
                                                id={`empleado-carpeta-user-${usuario.id}`}
                                                name="user_ids[]"
                                                value={String(usuario.id)}
                                                defaultChecked={checked}
                                            />
                                            <span className="grid min-w-0 gap-0.5">
                                                <span className="truncate text-sm font-medium">
                                                    {usuario.name}
                                                </span>
                                                <span className="truncate text-xs text-muted-foreground">
                                                    {usuario.email}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="rounded-md border border-dashed border-border px-3 py-4 text-sm text-muted-foreground">
                                No hay otras personas activas en esta empresa.
                            </p>
                        )}
                        <InputError
                            message={errors.user_ids ?? errors['user_ids.0']}
                        />
                    </fieldset>
                </div>
            )}
        </ResourceFormDialog>
    );
}
