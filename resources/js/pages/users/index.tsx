import { Head } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/UserController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordStrengthInput from '@/components/password-strength-input';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
import { ResourceHeader } from '@/components/resource-header';
import { ResourcePagination } from '@/components/resource-pagination';
import { ResourceSearch } from '@/components/resource-search';
import { ResourceTable } from '@/components/resource-table';
import type { ResourceColumn } from '@/components/resource-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import type { LaravelPaginator, ManagedUser, Role } from '@/types';

type Props = {
    users: LaravelPaginator<ManagedUser>;
    roles: Role[];
    filters?: { search?: string };
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
    const canAssignRoles = can('users.assign_roles');
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
                    {can('users.delete') && (
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
                    description="Administra las cuentas, credenciales y roles de acceso."
                    actions={
                        can('users.create') && canAssignRoles ? (
                            <Button onClick={openCreate}>
                                <Plus /> Nuevo usuario
                            </Button>
                        ) : undefined
                    }
                />
                <Card className="py-4">
                    <CardContent>
                        <ResourceSearch
                            route={index()}
                            defaultValue={filters?.search}
                            placeholder="Buscar por nombre o correo"
                        />
                    </CardContent>
                </Card>
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
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    user={editing}
                    roles={roles}
                    assignedRoles={assignedRoles}
                    canAssignRoles={canAssignRoles}
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

function UserDialog({
    open,
    onOpenChange,
    user,
    roles,
    assignedRoles,
    canAssignRoles,
    passwordRules,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: ManagedUser | null;
    roles: Role[];
    assignedRoles: Set<string | number>;
    canAssignRoles: boolean;
    passwordRules: string;
}) {
    const formId = 'user-form';
    const route = user ? update.form(user.id) : store.form();

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={user ? 'Editar usuario' : 'Nuevo usuario'}
            description="Asigna los datos de acceso y los roles correspondientes."
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
                            required
                            autoComplete="email"
                        />
                        <InputError message={errors.email} />
                    </div>
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
                            Confirmar contraseña {user ? '(opcional)' : ''}
                        </Label>
                        <PasswordInput
                            id="user-password-confirmation"
                            name="password_confirmation"
                            required={!user}
                            autoComplete="new-password"
                            passwordrules={passwordRules}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
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
                </div>
            )}
        </ResourceFormDialog>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Usuarios', href: index() }],
};
