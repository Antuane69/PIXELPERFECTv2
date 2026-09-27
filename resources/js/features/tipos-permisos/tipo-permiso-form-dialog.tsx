import {
    store,
    update,
} from '@/actions/App/Http/Controllers/PermisosLaborales/TipoPermisoController';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { TipoPermiso } from '@/types';

type TipoPermisoFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tipoPermiso: TipoPermiso | null;
};

export function TipoPermisoFormDialog({
    open,
    onOpenChange,
    tipoPermiso,
}: TipoPermisoFormDialogProps) {
    const formId = 'tipo-permiso-form';

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                tipoPermiso ? 'Editar tipo de permiso' : 'Nuevo tipo de permiso'
            }
            description="Define nombre, descripción y disponibilidad del tipo."
            formId={formId}
            form={tipoPermiso ? update.form(tipoPermiso.id) : store.form()}
            resetOnSuccess={!tipoPermiso}
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="tipo-permiso-nombre">Nombre</Label>
                        <Input
                            id="tipo-permiso-nombre"
                            name="nombre"
                            defaultValue={tipoPermiso?.nombre}
                            maxLength={120}
                            required
                            autoFocus
                            aria-invalid={Boolean(errors.nombre)}
                            aria-describedby={
                                errors.nombre
                                    ? 'tipo-permiso-nombre-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="tipo-permiso-nombre-error"
                            message={errors.nombre}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tipo-permiso-descripcion">
                            Descripción (opcional)
                        </Label>
                        <Textarea
                            id="tipo-permiso-descripcion"
                            name="descripcion"
                            defaultValue={tipoPermiso?.descripcion ?? ''}
                            maxLength={2000}
                            rows={4}
                            aria-invalid={Boolean(errors.descripcion)}
                            aria-describedby={
                                errors.descripcion
                                    ? 'tipo-permiso-descripcion-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="tipo-permiso-descripcion-error"
                            message={errors.descripcion}
                        />
                    </div>
                    <label className="flex items-center gap-3 rounded-lg border border-border p-4">
                        <input type="hidden" name="activo" value="0" />
                        <Checkbox
                            id="tipo-permiso-activo"
                            name="activo"
                            value="1"
                            defaultChecked={tipoPermiso?.activo ?? true}
                        />
                        <span className="grid gap-0.5">
                            <span className="text-sm font-medium">Activo</span>
                            <span className="text-xs text-muted-foreground">
                                Solo los tipos activos estarán disponibles en
                                nuevas solicitudes.
                            </span>
                        </span>
                    </label>
                    <InputError message={errors.activo} />
                </div>
            )}
        </ResourceFormDialog>
    );
}
