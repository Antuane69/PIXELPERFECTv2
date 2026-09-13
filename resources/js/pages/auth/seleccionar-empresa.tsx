import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, Building2, LogOut } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { store } from '@/routes/empresa-contexto';
import type { EmpresaResumen } from '@/types';

type Props = {
    empresas: EmpresaResumen[];
};

export default function SeleccionarEmpresa({ empresas }: Props) {
    return (
        <>
            <Head title="Seleccionar empresa" />

            <Form {...store.form()} className="grid gap-4">
                {({ processing, errors }) => (
                    <>
                        <fieldset className="grid gap-3" disabled={processing}>
                            <legend className="sr-only">
                                Empresas disponibles
                            </legend>
                            {empresas.map((empresa) => (
                                <button
                                    key={empresa.id}
                                    type="submit"
                                    name="empresa_id"
                                    value={empresa.id}
                                    className="group flex w-full items-center gap-4 rounded-xl border border-border bg-background p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary/45 hover:bg-primary/5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:pointer-events-none disabled:opacity-60 motion-reduce:transform-none"
                                >
                                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Building2 className="size-5" />
                                        )}
                                    </span>
                                    <span className="grid min-w-0 flex-1 gap-0.5">
                                        <span className="truncate font-medium">
                                            {empresa.nombre}
                                        </span>
                                        <span className="truncate text-sm text-muted-foreground">
                                            {empresa.nombre_legal}
                                        </span>
                                    </span>
                                    <ArrowRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-primary motion-reduce:transform-none" />
                                </button>
                            ))}
                        </fieldset>

                        <InputError message={errors.empresa_id} />

                        {empresas.length === 0 ? (
                            <div className="grid gap-3 rounded-xl border border-dashed border-border bg-muted/35 p-5 text-center">
                                <p className="font-medium">
                                    Sin empresas disponibles
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Tu cuenta no tiene membresías activas.
                                    Contacta al administrador de plataforma.
                                </p>
                            </div>
                        ) : null}

                        <Button variant="ghost" asChild className="w-full">
                            <Link href={logout()} method="post" as="button">
                                <LogOut /> Cerrar sesión
                            </Link>
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

SeleccionarEmpresa.layout = {
    title: 'Elige tu empresa',
    description:
        'Selecciona contexto empresarial para cargar módulos, permisos y datos correspondientes.',
};
