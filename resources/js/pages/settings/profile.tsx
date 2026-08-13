import { Form, Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ChangeEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { AppLayoutProps, Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;
    const getInitials = useInitials();
    const [avatarPreview, setAvatarPreview] = useState<string | null>(
        user?.avatar ?? null,
    );

    useEffect(() => {
        return () => {
            if (avatarPreview?.startsWith('blob:')) {
                URL.revokeObjectURL(avatarPreview);
            }
        };
    }, [avatarPreview]);

    if (!user) {
        return null;
    }

    setLayoutProps<AppLayoutProps>({
        headerDescription: 'Actualiza tu nombre, correo electrónico y avatar',
        headerActions: undefined,
    });

    const handleAvatarChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setAvatarPreview(URL.createObjectURL(file));
    };

    return (
        <>
            <Head title="Configuración del perfil" />

            <h1 className="sr-only">Configuración del perfil</h1>

            <div className="space-y-6">
                <Form
                    {...ProfileController.update.form()}
                    id="profile-form"
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-3">
                                <Label htmlFor="avatar">Avatar</Label>
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                    <Avatar className="size-20 rounded-2xl">
                                        <AvatarImage
                                            src={avatarPreview ?? undefined}
                                            alt={'Avatar de ' + user.name}
                                        />
                                        <AvatarFallback className="rounded-2xl bg-primary/10 text-xl font-semibold text-primary">
                                            {getInitials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="grid gap-2">
                                        <Input
                                            id="avatar"
                                            name="avatar"
                                            type="file"
                                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                            onChange={handleAvatarChange}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            JPG, PNG o WEBP. Máximo 2 MB.
                                        </p>
                                    </div>
                                </div>
                                <InputError message={errors.avatar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Nombre completo"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Correo electrónico
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Correo electrónico"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Tu correo electrónico aún no está
                                            verificado.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Haz clic aquí para reenviar el
                                                correo de verificación.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                Enviamos un nuevo enlace de
                                                verificación a tu correo
                                                electrónico.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Guardar
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Configuración del perfil',
            href: edit(),
        },
    ],
};
