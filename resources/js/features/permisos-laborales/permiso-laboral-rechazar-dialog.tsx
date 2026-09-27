import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type PermisoLaboralRechazarDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    empleado: string;
    form: { action: string; method: 'post' };
};

export function PermisoLaboralRechazarDialog({
    open,
    onOpenChange,
    empleado,
    form,
}: PermisoLaboralRechazarDialogProps) {
    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Rechazar solicitud"
            description={`Escribe el motivo del rechazo para la solicitud de ${empleado}.`}
            formId="rechazar-permiso-laboral-form"
            form={form}
            submitLabel="Rechazar permiso"
            className="sm:max-w-lg"
            resetOnSuccess
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor="permiso-laboral-motivo-rechazo">
                        Motivo del rechazo
                    </Label>
                    <Textarea
                        id="permiso-laboral-motivo-rechazo"
                        name="comentarios_rechazo"
                        required
                        minLength={3}
                        maxLength={2000}
                        autoFocus
                        aria-invalid={Boolean(errors.comentarios_rechazo)}
                        aria-describedby={
                            errors.comentarios_rechazo
                                ? 'permiso-laboral-motivo-error'
                                : undefined
                        }
                        placeholder="Explica por qué no se autoriza el permiso solicitado."
                    />
                    <InputError
                        id="permiso-laboral-motivo-error"
                        message={errors.comentarios_rechazo}
                    />
                    <InputError message={errors.permisoLaboral} />
                </div>
            )}
        </ResourceFormDialog>
    );
}
