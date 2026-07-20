import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type DeleteForm = {
    action: string;
    method: 'post';
};

type ConfirmDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    form: DeleteForm;
    subject: string;
    description?: string;
};

export function ConfirmDeleteDialog({
    open,
    onOpenChange,
    form,
    subject,
    description,
}: ConfirmDeleteDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="mb-1 flex size-10 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <Trash2 className="size-5" />
                    </div>
                    <DialogTitle>Eliminar {subject}</DialogTitle>
                    <DialogDescription>
                        {description ??
                            'Confirma esta acción. El registro dejará de estar disponible para la operación.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    disableWhileProcessing
                >
                    {({ processing, errors }) => {
                        const error =
                            errors.user ??
                            errors.puesto ??
                            errors.tipoDocumentoEmpleado ??
                            errors.roles ??
                            Object.values(errors)[0];

                        return (
                            <div className="grid gap-4">
                                {error && (
                                    <AlertError
                                        errors={[error]}
                                        title="No se pudo eliminar"
                                    />
                                )}
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onOpenChange(false)}
                                        disabled={processing}
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Eliminar
                                    </Button>
                                </DialogFooter>
                            </div>
                        );
                    }}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
