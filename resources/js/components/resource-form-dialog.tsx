import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
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

type ResourceForm = {
    action: string;
    method: 'post';
};

type ResourceFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    formId: string;
    form: ResourceForm;
    children: (errors: Record<string, string>) => ReactNode;
    submitLabel?: string;
    className?: string;
    resetOnSuccess?: boolean;
    noValidate?: boolean;
};

export function ResourceFormDialog({
    open,
    onOpenChange,
    title,
    description,
    formId,
    form,
    children,
    submitLabel = 'Guardar',
    className,
    resetOnSuccess = false,
    noValidate = false,
}: ResourceFormDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={cnDialogClass(className)}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Form
                    {...form}
                    id={formId}
                    className="flex min-h-0 flex-1 flex-col gap-4"
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess={resetOnSuccess}
                    disableWhileProcessing
                    noValidate={noValidate}
                >
                    {({ processing, errors, hasErrors }) => (
                        <>
                            <div className="min-h-0 flex-1 overflow-y-auto px-1 py-1">
                                {children(errors as Record<string, string>)}
                            </div>
                            {hasErrors ? (
                                <p
                                    className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                    role="alert"
                                >
                                    No se pudo guardar. Revisa los campos
                                    marcados.
                                </p>
                            ) : null}
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                    disabled={processing}
                                >
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {submitLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function cnDialogClass(className?: string) {
    return [
        'flex max-h-[calc(100vh-2rem)] flex-col gap-4 sm:max-w-2xl',
        className,
    ]
        .filter(Boolean)
        .join(' ');
}
