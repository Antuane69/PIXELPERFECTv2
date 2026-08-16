import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type ResourceForm = {
    action: string;
    method: 'post';
};

type ResourceFormDrawerProps = {
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

export function ResourceFormDrawer({
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
}: ResourceFormDrawerProps) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className={cn(
                    'w-full gap-0 overflow-hidden p-0 sm:max-w-2xl lg:max-w-4xl',
                    className,
                )}
            >
                <SheetHeader className="shrink-0 gap-1.5 border-b bg-muted/35 px-5 py-5 pr-12 text-left sm:px-6">
                    <SheetTitle className="text-xl leading-tight">
                        {title}
                    </SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>

                <Form
                    {...form}
                    id={formId}
                    className="flex min-h-0 flex-1 flex-col"
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess={resetOnSuccess}
                    disableWhileProcessing
                    noValidate={noValidate}
                >
                    {({ processing, errors, hasErrors }) => (
                        <>
                            <div className="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                                {children(errors as Record<string, string>)}
                            </div>

                            <SheetFooter className="shrink-0 gap-3 border-t bg-background/95 px-5 py-4 backdrop-blur-sm sm:px-6">
                                {hasErrors ? (
                                    <p
                                        className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                        role="alert"
                                    >
                                        No se pudo guardar. Revisa campos
                                        marcados.
                                    </p>
                                ) : null}
                                <div className="flex flex-wrap justify-end gap-2">
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
                                </div>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
