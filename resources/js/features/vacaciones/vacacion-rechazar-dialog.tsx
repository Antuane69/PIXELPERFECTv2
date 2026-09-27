import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type VacacionRechazarDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    empleado: string;
    form: { action: string; method: 'post' };
};

export function VacacionRechazarDialog({
    open,
    onOpenChange,
    empleado,
    form,
}: VacacionRechazarDialogProps) {
    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Rechazar solicitud"
            description={`Escribe el motivo del rechazo para la solicitud de ${empleado}.`}
            formId="rechazar-vacaciones-form"
            form={form}
            submitLabel="Rechazar vacaciones"
            className="sm:max-w-lg"
            resetOnSuccess
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor="vacacion-motivo-rechazo">
                        Motivo del rechazo
                    </Label>
                    <Textarea
                        id="vacacion-motivo-rechazo"
                        name="comentarios_rechazo"
                        required
                        minLength={3}
                        maxLength={2000}
                        autoFocus
                        aria-invalid={Boolean(errors.comentarios_rechazo)}
                        aria-describedby="vacacion-motivo-error"
                        placeholder="Explica por qué no se autoriza el periodo solicitado."
                    />
                    <InputError
                        id="vacacion-motivo-error"
                        message={errors.comentarios_rechazo}
                    />
                    <InputError message={errors.vacacion} />
                </div>
            )}
        </ResourceFormDialog>
    );
}
