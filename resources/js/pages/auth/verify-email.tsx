import { Form, Head } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const statusMessage =
        status === 'verification-link-sent'
            ? 'Te enviamos un nuevo enlace de verificación.'
            : status;

    return (
        <>
            <Head title="Verificar correo electrónico" />

            <div className="rounded-2xl border border-primary/15 bg-white p-6 shadow-lg shadow-primary/5">
                <div className="mb-5 flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <MailCheck className="size-7" aria-hidden="true" />
                </div>

                <h2 className="text-xl font-semibold text-foreground">
                    Confirma tu correo electrónico
                </h2>
                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                    Revisa tu bandeja de entrada y abre el enlace que enviamos
                    para activar tu cuenta de Pixel Perfect.
                </p>

                {statusMessage && (
                    <div className="mt-5 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                        {statusMessage}
                    </div>
                )}

                <Form {...send.form()} className="mt-6">
                    {({ processing }) => (
                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full"
                            data-test="send-verification-button"
                        >
                            {processing && <Spinner />}
                            Reenviar enlace de verificación
                        </Button>
                    )}
                </Form>

                <p className="mt-5 text-center text-xs leading-5 text-muted-foreground">
                    Revisa también tu carpeta de spam o correo no deseado.
                </p>

                <div className="mt-4 text-center">
                    <TextLink href={logout()} className="text-sm">
                        Cerrar sesión
                    </TextLink>
                </div>
            </div>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verificación de cuenta',
    description: 'Un paso más para proteger tu acceso a Pixel Perfect.',
};
