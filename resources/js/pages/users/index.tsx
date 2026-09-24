import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Mail, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    sendPasswordReset,
    store,
    update,
    updateTwoFactor as updateUserTwoFactor,
} from '@/actions/App/Http/Controllers/UserController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { FiltrosBase } from '@/components/filtros-base';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordStrengthInput from '@/components/password-strength-input';
import { ResourceExportDialog } from '@/components/resource-export-dialog';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { usePermissions } from '@/hooks/use-permissions';
import { inicio as empresaInicio } from '@/routes/empresas';
import { exportar as exportarUsuarios } from '@/routes/empresas/reportes/usuarios';
import type { LaravelPaginator, ManagedUser, Role } from '@/types';

type Props = {
    users: LaravelPaginator<ManagedUser>;
    roles: Role[];
    filters?: { search?: string; perPage?: number };
    passwordRules: string;
};

const roleName = (role: Role | string) =>
    typeof role === 'string' ? role : role.name;

export default function UsersIndex({
    users,
    roles,
    filters,
    passwordRules,
}: Props) {
    const { can } = usePermissions();
    const { auth } = usePage().props;
    const empresa = usePage().props.empresas.activa;

    if (!empresa) {
        throw new Error('Empresa activa requerida para administrar usuarios.');
    }

    const canAssignRoles = can('users.assign_roles');
    const canManageTwoFactor = can('users.manage_two_factor');
    const canSendPasswordReset = can('users.send_password_reset');
    const isPlatformAdministrator =
        auth.user?.es_superadministrador_plataforma ?? false;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<ManagedUser | null>(null);
    const [deleting, setDeleting] = useState<ManagedUser | null>(null);

    const openCreate = () => {
        setEditing(null);
        setDialogOpen(true);
    };

    const openEdit = (user: ManagedUser) => {
        setEditing(user);
        setDialogOpen(true);
    };

    const assignedRoles = new Set(
        (editing?.roles ?? []).map((role) =>
            typeof role === 'string' ? role : role.id,
        ),
    );
    const columns: ResourceColumn<ManagedUser>[] = [
        {
            key: 'name',
            header: 'Nombre',
            cell: (user) => <span className="font-medium">{user.name}</span>,
        },
        { key: 'email', header: 'Correo', cell: (user) => user.email },
        {
            key: 'security',
            header: 'Seguridad',
            cell: (user) => (
                <div className="flex flex-wrap justify-end gap-1 md:justify-start">
                    <Badge
                        variant={
                            user.email_verified_at ? 'secondary' : 'outline'
                        }
                    >
                        {user.email_verified_at
                            ? 'Correo verificado'
                            : 'Correo pendiente'}
                    </Badge>
                    <Badge
                        variant={
                            user.two_factor_enabled ? 'secondary' : 'outline'
                        }
                    >
                        {user.two_factor_enabled ? '2FA activo' : 'Sin 2FA'}
                    </Badge>
                </div>
            ),
        },
        {
            key: 'roles',
            header: 'Roles',
            cell: (user) => (
                <div className="flex flex-wrap justify-end gap-1 md:justify-start">
                    {(user.roles ?? []).length ? (
                        user.roles?.map((role) => (
                            <Badge key={roleName(role)} variant="secondary">
                                {roleName(role)}
                            </Badge>
                        ))
                    ) : (
                        <span className="text-muted-foreground">Sin rol</span>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'md:w-32',
            cell: (user) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    {can('users.update') && (
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            onClick={() => openEdit(user)}
                            aria-label={`Editar a ${user.name}`}
                        >
                            <Pencil />
                        </Button>
                    )}
                    {can('users.delete') &&
                        (isPlatformAdministrator ||
                            !(user.roles ?? []).some(
                                (role) => roleName(role) === 'Administrador',
                            )) && (
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setDeleting(user)}
                                aria-label={`Eliminar a ${user.name}`}
                            >
                                <Trash2 />
                            </Button>
                        )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Usuarios" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Usuarios"
                    description="Administra los usuarios y roles de esta empresa."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <ResourceExportDialog
                                report="usuarios"
                                exportUrl={exportarUsuarios.url()}
                                filters={{ search: filters?.search }}
                            />
                            {can('users.create') && canAssignRoles && (
                                <Button onClick={openCreate}>
                                    <Plus /> Nuevo usuario
                                </Button>
                            )}
                        </div>
                    }
                />
                <FiltrosBase
                    route={index()}
                    defaultSearch={filters?.search}
                    placeholder="Buscar por nombre o correo"
                    query={{ per_page: filters?.perPage ?? 15 }}
                />
                <ResourceTable
                    data={users.data}
                    columns={columns}
                    getRowKey={(user) => user.id}
                    emptyTitle="No hay usuarios"
                />
                <ResourcePagination paginator={users} />
            </main>

            {dialogOpen && (
                <UserDialog
                    key={editing?.id ?? 'new'}
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    user={editing}
                    roles={roles}
                    assignedRoles={assignedRoles}
                    canAssignRoles={canAssignRoles}
                    canManageTwoFactor={canManageTwoFactor}
                    canSendPasswordReset={canSendPasswordReset}
                    passwordRules={passwordRules}
                />
            )}

            {deleting && (
                <ConfirmDeleteDialog
                    open={Boolean(deleting)}
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el usuario “${deleting.name}”`}
                />
            )}
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Inicio', href: empresaInicio() },
        { title: 'Usuarios', href: index() },
    ],
};

function UserDialog({
    open,
    onOpenChange,
    user,
    roles,
    assignedRoles,
    canAssignRoles,
    canManageTwoFactor,
    canSendPasswordReset,
    passwordRules,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: ManagedUser | null;
    roles: Role[];
    assignedRoles: Set<string | number>;
    canAssignRoles: boolean;
    canManageTwoFactor: boolean;
    canSendPasswordReset: boolean;
    passwordRules: string;
}) {
    const formId = 'user-form';
    const { auth } = usePage().props;
    const canEditIdentity =
        !user || (auth.user?.es_superadministrador_plataforma ?? false);
    const route = user ? update.form(user.id) : store.form();
    const [twoFactorEnabled, setTwoFactorEnabled] = useState(
        user?.two_factor_enabled ?? false,
    );
    const [twoFactorProcessing, setTwoFactorProcessing] = useState(false);
    const passwordResetForm = useForm({});

    const toggleTwoFactor = (enabled: boolean) => {
        if (!user) {
            return;
        }

        setTwoFactorProcessing(true);
        router.patch(
            updateUserTwoFactor.url(user.id),
            { enabled },
            {
                preserveScroll: true,
                onSuccess: () => setTwoFactorEnabled(enabled),
                onError: () =>
                    setTwoFactorEnabled(user.two_factor_enabled ?? false),
                onFinish: () => setTwoFactorProcessing(false),
            },
        );
    };

    const requestPasswordReset = () => {
        if (!user) {
            return;
        }

        passwordResetForm.post(sendPasswordReset.url(user.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={user ? 'Editar usuario' : 'Nuevo usuario'}
            description={
                canEditIdentity
                    ? 'Asigna los datos de acceso y los roles correspondientes.'
                    : 'Gestiona roles de esta empresa. Nombre, correo y contraseña los actualiza el titular desde su perfil o un superadministrador de plataforma.'
            }
            formId={formId}
            form={route}
            resetOnSuccess={!user}
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="user-name">Nombre</Label>
                        <Input
                            id="user-name"
                            name="name"
                            defaultValue={user?.name}
                            readOnly={!canEditIdentity}
                            required
                            autoFocus
                            autoComplete="name"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="user-email">Correo electrónico</Label>
                        <Input
                            id="user-email"
                            type="email"
                            name="email"
                            defaultValue={user?.email}
                            readOnly={!canEditIdentity}
                            required
                            autoComplete="email"
                        />
                        <InputError message={errors.email} />
                    </div>
                    {canEditIdentity && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="user-password">
                                    Contraseña {user ? '(opcional)' : ''}
                                </Label>
                                <PasswordStrengthInput
                                    id="user-password"
                                    name="password"
                                    required={!user}
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-password-confirmation">
                                    Confirmar contraseña{' '}
                                    {user ? '(opcional)' : ''}
                                </Label>
                                <PasswordInput
                                    id="user-password-confirmation"
                                    name="password_confirmation"
                                    required={!user}
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                        </>
                    )}
                    <fieldset className="grid gap-3 rounded-lg border p-4">
                        <legend className="flex items-center gap-2 px-1 text-sm font-medium">
                            <ShieldCheck className="size-4" /> Roles
                        </legend>
                        {canAssignRoles ? (
                            <div className="grid gap-3 sm:grid-cols-2">
                                {roles.map((role) => (
                                    <label
                                        key={role.id}
                                        className="flex cursor-pointer items-center gap-3 rounded-md border p-3 hover:bg-accent/60"
                                    >
                                        <Checkbox
                                            name="roles[]"
                                            value={String(role.id)}
                                            defaultChecked={
                                                assignedRoles.has(role.id) ||
                                                assignedRoles.has(role.name)
                                            }
                                        />
                                        <span className="text-sm">
                                            {role.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {roles
                                    .filter(
                                        (role) =>
                                            assignedRoles.has(role.id) ||
                                            assignedRoles.has(role.name),
                                    )
                                    .map((role) => (
                                        <span key={role.id}>
                                            <input
                                                type="hidden"
                                                name="roles[]"
                                                value={role.id}
                                            />
                                            <Badge variant="secondary">
                                                {role.name}
                                            </Badge>
                                        </span>
                                    ))}
                                <p className="w-full text-xs text-muted-foreground">
                                    No tienes permiso para cambiar roles.
                                </p>
                            </div>
                        )}
                        <InputError message={errors.roles} />
                    </fieldset>
                    {user && canManageTwoFactor ? (
                        <fieldset className="grid gap-3 rounded-lg border p-4">
                            <legend className="flex items-center gap-2 px-1 text-sm font-medium">
                                <ShieldCheck className="size-4" /> Seguridad
                            </legend>
                            <label
                                htmlFor="user-two-factor"
                                className="flex cursor-pointer items-start gap-3 rounded-md border p-3 hover:bg-accent/60"
                            >
                                <Checkbox
                                    id="user-two-factor"
                                    checked={twoFactorEnabled}
                                    disabled={twoFactorProcessing}
                                    onCheckedChange={(checked) =>
                                        toggleTwoFactor(checked === true)
                                    }
                                />
                                <span className="grid gap-1 text-sm">
                                    <span className="font-medium">
                                        Autenticación de dos factores
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Activa o desactiva 2FA para este
                                        usuario.
                                    </span>
                                </span>
                            </label>
                            <InputError message={errors.enabled} />
                        </fieldset>
                    ) : null}
                    {user && canSendPasswordReset ? (
                        <fieldset className="grid gap-3 rounded-lg border p-4">
                            <legend className="px-1 text-sm font-medium">
                                Restablecimiento de contraseña
                            </legend>
                            <p className="text-sm text-muted-foreground">
                                Envía a {user.email} un enlace para crear una
                                nueva contraseña.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-fit"
                                onClick={requestPasswordReset}
                                disabled={passwordResetForm.processing}
                            >
                                {passwordResetForm.processing ? (
                                    <Spinner />
                                ) : (
                                    <Mail />
                                )}
                                Enviar correo de restablecimiento
                            </Button>
                            <InputError
                                message={
                                    (
                                        passwordResetForm.errors as Record<
                                            string,
                                            string
                                        >
                                    ).user
                                }
                            />
                        </fieldset>
                    ) : null}
                </div>
            )}
        </ResourceFormDialog>
    );
}
