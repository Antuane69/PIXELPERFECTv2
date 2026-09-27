import {
    store,
    update,
} from '@/actions/App/Http/Controllers/FaltasReglamento/TipoFaltaReglamentoController';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { TipoFaltaReglamento } from '@/types';

type TipoFaltaFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tipoFalta: TipoFaltaReglamento | null;
};

export function TipoFaltaFormDialog({
    open,
    onOpenChange,
    tipoFalta,
}: TipoFaltaFormDialogProps) {
    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={tipoFalta ? 'Editar tipo de falta' : 'Nuevo tipo de falta'}
            description="Administra una categoría disponible en los reportes de faltas al reglamento."
            formId="tipo-falta-reglamento-form"
            form={tipoFalta ? update.form(tipoFalta.id) : store.form()}
            resetOnSuccess={!tipoFalta}
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="tipo-falta-reglamento-nombre">
                            Nombre
                        </Label>
                        <Input
                            id="tipo-falta-reglamento-nombre"
                            name="nombre"
                            defaultValue={tipoFalta?.nombre}
                            maxLength={120}
                            required
                            autoFocus
                            aria-invalid={Boolean(errors.nombre)}
                            aria-describedby="tipo-falta-reglamento-nombre-error"
                        />
                        <InputError
                            id="tipo-falta-reglamento-nombre-error"
                            message={errors.nombre}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tipo-falta-reglamento-descripcion">
                            Descripción (opcional)
                        </Label>
                        <Textarea
                            id="tipo-falta-reglamento-descripcion"
                            name="descripcion"
                            defaultValue={tipoFalta?.descripcion ?? ''}
                            maxLength={2000}
                            rows={4}
                            aria-invalid={Boolean(errors.descripcion)}
                            aria-describedby="tipo-falta-reglamento-descripcion-error"
                        />
                        <InputError
                            id="tipo-falta-reglamento-descripcion-error"
                            message={errors.descripcion}
                        />
                    </div>
                    <label className="flex items-center gap-3 rounded-lg border border-border p-4">
                        <input type="hidden" name="activo" value="0" />
                        <Checkbox
                            id="tipo-falta-reglamento-activo"
                            name="activo"
                            value="1"
                            defaultChecked={tipoFalta?.activo ?? true}
                        />
                        <span className="grid gap-0.5">
                            <span className="text-sm font-medium">Activo</span>
                            <span className="text-xs text-muted-foreground">
                                Solo los tipos activos estarán disponibles en
                                nuevos reportes.
                            </span>
                        </span>
                    </label>
                    <InputError message={errors.activo} />
                </div>
            )}
        </ResourceFormDialog>
    );
}
