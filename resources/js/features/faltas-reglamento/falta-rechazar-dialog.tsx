import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type FaltaRechazarDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    empleado: string;
    form: { action: string; method: 'post' };
};

export function FaltaRechazarDialog({
    open,
    onOpenChange,
    empleado,
    form,
}: FaltaRechazarDialogProps) {
    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Rechazar reporte"
            description={`Explica el motivo del rechazo para el reporte de ${empleado}.`}
            formId="falta-reglamento-rechazo-form"
            form={form}
            submitLabel="Rechazar reporte"
            className="sm:max-w-lg"
            resetOnSuccess
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor="falta-reglamento-motivo-rechazo">
                        Motivo del rechazo
                    </Label>
                    <Textarea
                        id="falta-reglamento-motivo-rechazo"
                        name="comentarios_rechazo"
                        required
                        minLength={3}
                        maxLength={2000}
                        autoFocus
                        aria-invalid={Boolean(errors.comentarios_rechazo)}
                        aria-describedby="falta-reglamento-motivo-error"
                        placeholder="Explica por qué no se autoriza el reporte."
                    />
                    <InputError
                        id="falta-reglamento-motivo-error"
                        message={errors.comentarios_rechazo}
                    />
                    <InputError message={errors.faltaReglamento} />
                </div>
            )}
        </ResourceFormDialog>
    );
}
