import { Head } from '@inertiajs/react';
import { LockKeyhole, Pencil, Plus, Shield, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/RoleController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import InputError from '@/components/input-error';
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
import type { LaravelPaginator, Permission, Role } from '@/types';

type Props = {
    roles: LaravelPaginator<Role>;
    permissions: Permission[];
    filters?: { search?: string };
};

const permissionName = (permission: Permission | string) =>
    typeof permission === 'string' ? permission : permission.name;

const isAdministrator = (role: Role) =>
    role.name.trim().toLocaleLowerCase('es') === 'administrador';

export default function RolesIndex({ roles, permissions, filters }: Props) {
    const { can } = usePermissions();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Role | null>(null);
    const [deleting, setDeleting] = useState<Role | null>(null);

    const columns: ResourceColumn<Role>[] = [
        {
            key: 'name',
            header: 'Rol',
            cell: (role) => (
                <div className="flex items-center gap-2 font-medium">
                    {isAdministrator(role) ? (
                        <LockKeyhole className="size-4 text-primary" />
                    ) : (
                        <Shield className="size-4 text-muted-foreground" />
                    )}
                    {role.name}
                    {isAdministrator(role) && <Badge>Protegido</Badge>}
                </div>
            ),
        },
        {
            key: 'permissions',
            header: 'Permisos',
            cell: (role) => (
                <div className="flex flex-wrap justify-end gap-1 md:justify-start">
                    {(role.permissions ?? []).slice(0, 5).map((permission) => (
                        <Badge
                            key={permissionName(permission)}
                            variant="outline"
                        >
                            {permissionName(permission)}
                        </Badge>
                    ))}
                    {(role.permissions?.length ?? 0) > 5 && (
                        <Badge variant="secondary">
                            +{(role.permissions?.length ?? 0) - 5}
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'users',
            header: 'Usuarios',
            cell: (role) => role.users_count ?? 0,
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'md:w-32',
            cell: (role) => (
                <div className="flex justify-end gap-2 md:justify-start">
                    {can('roles.update') && !isAdministrator(role) && (
                        <Button
                            size="icon"
                            variant="outline"
                            onClick={() => {
                                setEditing(role);
                                setDialogOpen(true);
                            }}
                            aria-label={`Editar rol ${role.name}`}
                        >
                            <Pencil />
                        </Button>
                    )}
                    {can('roles.delete') && !isAdministrator(role) && (
                        <Button
                            size="icon"
                            variant="outline"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeleting(role)}
                            aria-label={`Eliminar rol ${role.name}`}
                        >
                            <Trash2 />
                        </Button>
                    )}
                    {isAdministrator(role) && (
                        <span className="text-xs text-muted-foreground">
                            Sin cambios
                        </span>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Roles" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Roles"
                    description="Agrupa permisos y define el nivel de acceso de los usuarios."
                    actions={
                        can('roles.create') ? (
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setDialogOpen(true);
                                }}
                            >
                                <Plus /> Nuevo rol
                            </Button>
                        ) : undefined
                    }
                />
                <Card className="py-4">
                    <CardContent>
                        <ResourceSearch
                            route={index()}
                            defaultValue={filters?.search}
                            placeholder="Buscar rol"
                        />
                    </CardContent>
                </Card>
                <ResourceTable
                    data={roles.data}
                    columns={columns}
                    getRowKey={(role) => role.id}
                    emptyTitle="No hay roles"
                />
                <ResourcePagination paginator={roles} />
            </main>

            {dialogOpen && (
                <RoleDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    role={editing}
                    permissions={permissions}
                />
            )}
            {deleting && (
                <ConfirmDeleteDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    form={destroy.form(deleting.id)}
                    subject={`el rol “${deleting.name}”`}
                />
            )}
        </>
    );
}

function RoleDialog({
    open,
    onOpenChange,
    role,
    permissions,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    role: Role | null;
    permissions: Permission[];
}) {
    const formId = 'role-form';
    const assigned = new Set(
        (role?.permissions ?? []).map((permission) =>
            typeof permission === 'string' ? permission : permission.id,
        ),
    );

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={role ? 'Editar rol' : 'Nuevo rol'}
            description="Selecciona únicamente los permisos necesarios."
            formId={formId}
            form={role ? update.form(role.id) : store.form()}
            resetOnSuccess={!role}
            className="sm:max-w-3xl"
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="role-name">Nombre</Label>
                        <Input
                            id="role-name"
                            name="name"
                            defaultValue={role?.name}
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>
                    <fieldset className="grid gap-3 rounded-lg border p-4">
                        <legend className="px-1 text-sm font-medium">
                            Permisos
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {permissions.map((permission) => (
                                <label
                                    key={permission.id}
                                    className="flex cursor-pointer items-start gap-3 rounded-md border p-3 hover:bg-accent/60"
                                >
                                    <Checkbox
                                        name="permissions[]"
                                        value={String(permission.id)}
                                        defaultChecked={
                                            assigned.has(permission.id) ||
                                            assigned.has(permission.name)
                                        }
                                    />
                                    <span className="text-sm break-all">
                                        {permission.name}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.permissions} />
                    </fieldset>
                </div>
            )}
        </ResourceFormDialog>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
