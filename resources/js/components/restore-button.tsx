import { Form } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type RestoreForm = {
    action: string;
    method: 'post';
};

type RestoreButtonProps = {
    form: RestoreForm;
    subject: string;
};

export function RestoreButton({ form, subject }: RestoreButtonProps) {
    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onError={(errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        `No se pudo restaurar ${subject}.`,
                )
            }
            disableWhileProcessing
        >
            {({ processing }) => (
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    disabled={processing}
                    aria-label={`Restaurar ${subject}`}
                >
                    {processing ? <Spinner /> : <RotateCcw />}
                    Restaurar
                </Button>
            )}
        </Form>
    );
}
