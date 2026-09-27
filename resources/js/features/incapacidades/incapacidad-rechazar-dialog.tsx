import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type IncapacidadRechazarDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    empleado: string;
    form: { action: string; method: 'post' };
};

export function IncapacidadRechazarDialog({
    open,
    onOpenChange,
    empleado,
    form,
}: IncapacidadRechazarDialogProps) {
    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Rechazar solicitud"
            description={
                'Escribe el motivo del rechazo para la incapacidad de ' +
                empleado +
                '.'
            }
            formId="rechazar-incapacidad-form"
            form={form}
            submitLabel="Rechazar incapacidad"
            className="sm:max-w-lg"
            resetOnSuccess
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor="incapacidad-motivo-rechazo">
                        Motivo del rechazo
                    </Label>
                    <Textarea
                        id="incapacidad-motivo-rechazo"
                        name="comentarios_rechazo"
                        required
                        minLength={3}
                        maxLength={2000}
                        autoFocus
                        aria-invalid={Boolean(errors.comentarios_rechazo)}
                        aria-describedby="incapacidad-motivo-rechazo-error"
                        placeholder="Explica por qué no se autoriza la solicitud."
                    />
                    <InputError
                        id="incapacidad-motivo-rechazo-error"
                        message={errors.comentarios_rechazo}
                    />
                    <InputError message={errors.incapacidad} />
                </div>
            )}
        </ResourceFormDialog>
    );
}
