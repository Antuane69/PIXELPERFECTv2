import { Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash2, UserRound } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/PlatformUserController';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/platform/usuarios';
import type { EmpresaResumen, LaravelPaginator } from '@/types';

type PlatformUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    es_superadministrador_plataforma: boolean;
    two_factor_enabled: boolean;
    empresas_count: number;
    empresas: PlatformUserCompany[];
    created_at: string | null;
};

type PlatformUserCompany = {
    id: number;
    nombre: string;
};

type Props = {
    users: LaravelPaginator<PlatformUser>;
    passwordRules: string;
    filters: {
        search: string;
        perPage: number;
    };
};

export default function PlatformUsersIndex({
    users,
    filters,
    passwordRules,
}: Props) {
    const { auth, empresas } = usePage().props;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<PlatformUser | null>(null);
    const [deleting, setDeleting] = useState<PlatformUser | null>(null);
    const openCreate = () => {
        setEditing(null);
        setDialogOpen(true);
    };
    const columns: ResourceColumn<PlatformUser>[] = [
        {
            key: 'usuario',
            header: 'Usuario',
            cell: (user) => (
                <div className="flex items-center gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        {user.es_superadministrador_plataforma ? (
                            <ShieldCheck className="size-4" />
                        ) : (
                            <UserRound className="size-4" />
                        )}
                    </span>
                    <div className="grid min-w-0 gap-0.5">
                        <span className="truncate font-medium">
                            {user.name}
                        </span>
                        <span className="truncate text-xs text-muted-foreground">
                            {user.email}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'tipo',
            header: 'Tipo',
            cell: (user) => (
                <Badge
                    variant={
                        user.es_superadministrador_plataforma
                            ? 'default'
                            : 'secondary'
                    }
                >
                    {user.es_superadministrador_plataforma
                        ? 'Superadministrador'
                        : 'Usuario empresarial'}
                </Badge>
            ),
        },
        {
            key: 'empresas',
            header: 'Empresas',
            cell: (user) => user.empresas_count.toLocaleString('es-MX'),
        },
        {
            key: 'seguridad',
            header: 'Seguridad',
            cell: (user) => (
                <span className="text-sm text-muted-foreground">
                    {user.email_verified_at
                        ? 'Correo verificado'
                        : 'Correo pendiente'}
                    {' · '}
                    {user.two_factor_enabled ? '2FA activa' : '2FA inactiva'}
                </span>
            ),
        },
        {
            key: 'acciones',
            header: 'Acciones',
            className: 'md:w-28',
            cell: (user) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        onClick={() => {
                            setEditing(user);
                            setDialogOpen(true);
                        }}
                        aria-label={`Editar a ${user.name}`}
                    >
                        <Pencil />
                    </Button>
                    {user.id !== auth.user?.id ? (
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
                    ) : null}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Usuarios de plataforma" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Usuarios"
                    description="Consulta global de cuentas y acceso empresarial."
                    actions={
                        <Button
                            onClick={openCreate}
                            disabled={empresas.disponibles.length === 0}
                        >
                            <Plus /> Nuevo administrador
                        </Button>
                    }
                />
                <ResourceSearch
                    route={index()}
                    defaultValue={filters.search}
                    placeholder="Buscar nombre o correo"
                    query={{ per_page: filters.perPage }}
                />
                <ResourceTable
                    data={users.data}
                    columns={columns}
                    getRowKey={(user) => user.id}
                    emptyTitle="No hay usuarios"
                    emptyDescription="No se encontraron cuentas con filtros actuales."
                />
                <ResourcePagination paginator={users} />
            </main>
            {dialogOpen ? (
                <PlatformUserDialog
                    key={editing?.id ?? 'new'}
                    open
                    onOpenChange={setDialogOpen}
                    user={editing}
                    empresas={empresas.disponibles}
                    passwordRules={passwordRules}
                />
            ) : null}
            {deleting ? (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el usuario “${deleting.name}”`}
                    description="Se eliminará su cuenta y acceso a todas las empresas asignadas."
                />
            ) : null}
        </>
    );
}

PlatformUsersIndex.layout = {
    breadcrumbs: [
        { title: 'Plataforma', href: index() },
        { title: 'Usuarios', href: index() },
    ],
};

function PlatformUserDialog({
    open,
    onOpenChange,
    user,
    empresas,
    passwordRules,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: PlatformUser | null;
    empresas: EmpresaResumen[];
    passwordRules: string;
}) {
    const assignedEmpresaIds = new Set(
        (user?.empresas ?? []).map((empresa) => empresa.id),
    );
    const canManageCompanies = !user?.es_superadministrador_plataforma;

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={user ? 'Editar usuario' : 'Nuevo administrador empresarial'}
            description={
                user
                    ? 'Actualiza sus datos y empresas administradas.'
                    : 'Crea usuario inicial y asígnalo como Administrador de una o más empresas.'
            }
            formId="platform-user-form"
            form={user ? update.form(user.id) : store.form()}
            submitLabel={user ? 'Guardar cambios' : 'Crear administrador'}
            resetOnSuccess={!user}
        >
            {(errors) => (
                <div className="grid gap-5">
                    {canManageCompanies ? (
                        <fieldset className="grid gap-3">
                            <legend className="text-sm font-medium">
                                Empresas administradas
                            </legend>
                            <p className="text-xs text-muted-foreground">
                                Selecciona una o más empresas. El usuario tendrá
                                rol Administrador en cada una.
                            </p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {empresas.map((empresa) => (
                                    <label
                                        key={empresa.id}
                                        className="flex cursor-pointer items-center gap-3 rounded-md border p-3 hover:bg-accent/60"
                                    >
                                        <Checkbox
                                            name="empresa_ids[]"
                                            value={String(empresa.id)}
                                            defaultChecked={assignedEmpresaIds.has(
                                                empresa.id,
                                            )}
                                        />
                                        <span className="text-sm">
                                            {empresa.nombre}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <InputError
                                message={
                                    errors.empresa_ids ??
                                    errors['empresa_ids.0']
                                }
                            />
                        </fieldset>
                    ) : (
                        <p className="rounded-md border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                            Superadministrador de plataforma: no requiere
                            asignación empresarial.
                        </p>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="platform-user-name">Nombre</Label>
                        <Input
                            id="platform-user-name"
                            name="name"
                            defaultValue={user?.name}
                            required
                            autoFocus
                            autoComplete="name"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="platform-user-email">
                            Correo electrónico
                        </Label>
                        <Input
                            id="platform-user-email"
                            name="email"
                            type="email"
                            defaultValue={user?.email}
                            required
                            autoComplete="email"
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="platform-user-password">
                            Contraseña {user ? '(opcional)' : ''}
                        </Label>
                        <PasswordStrengthInput
                            id="platform-user-password"
                            name="password"
                            required={!user}
                            autoComplete="new-password"
                            passwordrules={passwordRules}
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="platform-user-password-confirmation">
                            Confirmar contraseña {user ? '(opcional)' : ''}
                        </Label>
                        <PasswordInput
                            id="platform-user-password-confirmation"
                            name="password_confirmation"
                            required={!user}
                            autoComplete="new-password"
                            passwordrules={passwordRules}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
                </div>
            )}
        </ResourceFormDialog>
    );
}
