import { useHttp } from '@inertiajs/react';
import {
    AlertCircle,
    KeyRound,
    Pencil,
    Plus,
    Trash2,
    UsersRound,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import {
    destroy as destroyPermission,
    index as permissionsIndex,
    store as storePermission,
    update as updatePermission,
} from '@/actions/App/Http/Controllers/Admin/PermissionController';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { ResourceDetailDrawer } from '@/components/resource-detail-drawer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { getBackendErrorMessage, showAppAlert } from '@/lib/app-alerts';
import type {
    LaravelPaginator,
    PlatformModule,
    PlatformPermission,
} from '@/types';

type PermissionListResponse = {
    permissions: LaravelPaginator<PlatformPermission>;
};

type PermissionMutationResponse = {
    permission: PlatformPermission;
    message: string;
};

type PermissionDeleteResponse = {
    id: number;
    message: string;
};

type PermissionFormData = {
    name: string;
    alcance: 'EMPRESA';
    modulo_id: string;
};

type EmptyRequest = Record<string, never>;

type Props = {
    module: PlatformModule;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ModuloPermisosDrawer({ module, open, onOpenChange }: Props) {
    const [permissions, setPermissions] = useState<PlatformPermission[]>([]);
    const [listError, setListError] = useState<string | null>(null);
    const [editorOpen, setEditorOpen] = useState(false);
    const [editing, setEditing] = useState<PlatformPermission | null>(null);
    const [deleting, setDeleting] = useState<PlatformPermission | null>(null);
    const { get, processing: loading } = useHttp<
        EmptyRequest,
        PermissionListResponse
    >({});

    const loadPermissions = useCallback(async (): Promise<void> => {
        setListError(null);

        try {
            const response = await get(
                permissionsIndex.url({
                    query: {
                        modulo_id: module.id,
                        per_page: 100,
                    },
                }),
            );

            setPermissions(response.permissions.data);
        } catch (error: unknown) {
            setListError(
                getBackendErrorMessage(
                    error,
                    'No se pudieron cargar los permisos del módulo.',
                ),
            );
        }
    }, [get, module.id]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            void loadPermissions();
        }, 0);

        return () => window.clearTimeout(timeoutId);
    }, [loadPermissions, open]);

    const openCreateEditor = (): void => {
        setEditing(null);
        setEditorOpen(true);
    };

    const openEditEditor = (permission: PlatformPermission): void => {
        setEditing(permission);
        setEditorOpen(true);
    };

    const closeEditor = (nextOpen: boolean): void => {
        setEditorOpen(nextOpen);

        if (!nextOpen) {
            setEditing(null);
        }
    };

    const handleSaved = (
        permission: PlatformPermission,
        message: string,
    ): void => {
        setPermissions((current) => {
            const exists = current.some((item) => item.id === permission.id);

            const nextPermissions = exists
                ? current.map((item) =>
                      item.id === permission.id ? permission : item,
                  )
                : [...current, permission];

            return nextPermissions.sort((first, second) =>
                first.name.localeCompare(second.name, 'es'),
            );
        });
        closeEditor(false);
        showAppAlert({ type: 'success', message });
    };

    const handleDeleted = (id: number, message: string): void => {
        setPermissions((current) =>
            current.filter((permission) => permission.id !== id),
        );
        setDeleting(null);
        showAppAlert({ type: 'success', message });
    };

    return (
        <>
            <ResourceDetailDrawer
                open={open}
                onOpenChange={onOpenChange}
                title={`Permisos de ${module.nombre}`}
                description="Administra las capacidades disponibles para este módulo."
                headerExtra={
                    <>
                        <Badge variant={module.activo ? 'default' : 'outline'}>
                            {module.activo
                                ? 'Módulo activo'
                                : 'Módulo inactivo'}
                        </Badge>
                        <Badge variant="secondary">
                            <KeyRound />{' '}
                            {loading
                                ? 'Cargando…'
                                : `${permissions.length.toLocaleString('es-MX')} permisos`}
                        </Badge>
                        <Button
                            type="button"
                            size="sm"
                            className="ml-auto"
                            onClick={openCreateEditor}
                        >
                            <Plus /> Nuevo permiso
                        </Button>
                    </>
                }
                footer={
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cerrar
                    </Button>
                }
                bodyClassName="p-0 sm:p-0"
            >
                {/* <div className="border-b bg-background/95 px-5 py-4 sm:px-6">
                    <div className="flex items-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <KeyRound className="size-5" aria-hidden="true" />
                        </span>
                        <div className="min-w-0">
                            <p className="font-semibold">Catálogo del módulo</p>
                            <code className="text-sm break-all text-muted-foreground">
                                {module.clave}
                            </code>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Cada permiso se guarda con alcance Empresa y
                                queda vinculado a este módulo.
                            </p>
                        </div>
                    </div>
                </div> */}

                <div className="grid gap-3 px-5 py-5 sm:px-6">
                    {loading ? <PermissionListSkeleton /> : null}

                    {!loading && listError ? (
                        <div className="grid gap-3">
                            <AlertError
                                errors={[listError]}
                                title="No se pudieron cargar los permisos"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                className="justify-self-start"
                                onClick={() => void loadPermissions()}
                            >
                                Reintentar
                            </Button>
                        </div>
                    ) : null}

                    {!loading && !listError && permissions.length === 0 ? (
                        <div className="grid min-h-48 place-items-center rounded-xl border border-dashed bg-muted/20 p-8 text-center">
                            <div className="grid justify-items-center gap-3">
                                <span className="flex size-11 items-center justify-center rounded-full bg-accent text-accent-foreground">
                                    <KeyRound
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div className="grid gap-1">
                                    <p className="font-medium">
                                        Módulo sin permisos
                                    </p>
                                    <p className="max-w-sm text-sm text-muted-foreground">
                                        Crea primer permiso para habilitar
                                        acciones específicas en este módulo.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={openCreateEditor}
                                >
                                    <Plus /> Crear permiso
                                </Button>
                            </div>
                        </div>
                    ) : null}

                    {!loading && !listError
                        ? permissions.map((permission) => (
                              <PermissionCard
                                  key={permission.id}
                                  permission={permission}
                                  onEdit={() => openEditEditor(permission)}
                                  onDelete={() => setDeleting(permission)}
                              />
                          ))
                        : null}
                </div>
            </ResourceDetailDrawer>

            {editorOpen ? (
                <PermissionEditorDialog
                    key={editing?.id ?? 'new'}
                    open
                    onOpenChange={closeEditor}
                    module={module}
                    permission={editing}
                    onSaved={handleSaved}
                />
            ) : null}

            {deleting ? (
                <PermissionDeleteDialog
                    open
                    permission={deleting}
                    onOpenChange={(nextOpen) => {
                        if (!nextOpen) {
                            setDeleting(null);
                        }
                    }}
                    onDeleted={handleDeleted}
                />
            ) : null}
        </>
    );
}

function PermissionCard({
    permission,
    onEdit,
    onDelete,
}: {
    permission: PlatformPermission;
    onEdit: () => void;
    onDelete: () => void;
}) {
    return (
        <article className="grid gap-4 rounded-xl border bg-card p-4 shadow-sm sm:flex sm:items-center sm:justify-between">
            <div className="min-w-0">
                <div className="flex items-start gap-3">
                    <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <KeyRound className="size-4" aria-hidden="true" />
                    </span>
                    <div className="min-w-0">
                        <code className="block font-medium break-all">
                            {permission.name}
                        </code>
                        <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="secondary">
                                <UsersRound />
                                {permission.roles_count.toLocaleString(
                                    'es-MX',
                                )}{' '}
                                {permission.roles_count === 1
                                    ? 'rol asignado'
                                    : 'roles asignados'}
                            </Badge>
                            <span>Alcance Empresa</span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex shrink-0 justify-end gap-2">
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    onClick={onEdit}
                    aria-label={`Editar permiso ${permission.name}`}
                >
                    <Pencil />
                </Button>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="text-destructive hover:text-destructive"
                    onClick={onDelete}
                    aria-label={`Eliminar permiso ${permission.name}`}
                >
                    <Trash2 />
                </Button>
            </div>
        </article>
    );
}

function PermissionListSkeleton() {
    return (
        <div
            className="grid gap-3"
            aria-label="Cargando permisos"
            role="status"
        >
            {[0, 1, 2].map((item) => (
                <div
                    key={item}
                    className="h-24 animate-pulse rounded-xl border bg-muted/35"
                />
            ))}
        </div>
    );
}

function PermissionEditorDialog({
    open,
    onOpenChange,
    module,
    permission,
    onSaved,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    module: PlatformModule;
    permission: PlatformPermission | null;
    onSaved: (permission: PlatformPermission, message: string) => void;
}) {
    const [requestError, setRequestError] = useState<string | null>(null);
    const { data, setData, post, put, processing, errors, hasErrors } = useHttp<
        PermissionFormData,
        PermissionMutationResponse
    >({
        name: permission?.name ?? '',
        alcance: 'EMPRESA',
        modulo_id: String(module.id),
    });

    const handleSubmit = async (
        event: FormEvent<HTMLFormElement>,
    ): Promise<void> => {
        event.preventDefault();
        setRequestError(null);

        try {
            const response = permission
                ? await put(updatePermission.url(permission.id))
                : await post(storePermission.url());

            if (response?.permission) {
                onSaved(response.permission, response.message);
            }
        } catch (error: unknown) {
            setRequestError(
                getBackendErrorMessage(
                    error,
                    'No se pudo guardar el permiso. Inténtalo nuevamente.',
                ),
            );
        }
    };

    const nameError = typeof errors.name === 'string' ? errors.name : undefined;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {permission ? 'Editar permiso' : 'Nuevo permiso'}
                    </DialogTitle>
                    <DialogDescription>
                        Define nombre técnico para acciones de {module.nombre}.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="module-permission-name">
                            Nombre técnico
                        </Label>
                        <Input
                            id="module-permission-name"
                            value={data.name}
                            onChange={(event) => {
                                setRequestError(null);
                                setData('name', event.target.value);
                            }}
                            placeholder="facturas.exportar"
                            maxLength={125}
                            required
                            autoFocus
                            aria-invalid={nameError ? 'true' : undefined}
                        />
                        <p className="text-xs text-muted-foreground">
                            Usa formato recurso.accion con minúsculas, números o
                            guion bajo.
                        </p>
                        <InputError message={nameError} />
                    </div>

                    <div className="grid gap-1 rounded-xl border bg-muted/35 p-4">
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-sm font-medium">Módulo</span>
                            <Badge variant="secondary">Empresa</Badge>
                        </div>
                        <p className="font-medium">{module.nombre}</p>
                        <code className="text-xs break-all text-muted-foreground">
                            {module.clave}
                        </code>
                    </div>

                    {requestError ? (
                        <AlertError
                            errors={[requestError]}
                            title="No se pudo guardar"
                        />
                    ) : null}
                    {hasErrors && !requestError ? (
                        <p
                            className="flex items-center gap-2 text-sm text-destructive"
                            role="alert"
                        >
                            <AlertCircle
                                className="size-4"
                                aria-hidden="true"
                            />
                            Revisa el campo marcado.
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
                            {processing ? <Spinner /> : null}
                            {permission ? 'Guardar cambios' : 'Crear permiso'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PermissionDeleteDialog({
    open,
    permission,
    onOpenChange,
    onDeleted,
}: {
    open: boolean;
    permission: PlatformPermission;
    onOpenChange: (open: boolean) => void;
    onDeleted: (id: number, message: string) => void;
}) {
    const [requestError, setRequestError] = useState<string | null>(null);
    const {
        delete: deletePermission,
        processing,
        errors,
    } = useHttp<EmptyRequest, PermissionDeleteResponse>({});

    const handleDelete = async (): Promise<void> => {
        setRequestError(null);

        try {
            const response = await deletePermission(
                destroyPermission.url(permission.id),
            );

            if (response?.id) {
                onDeleted(response.id, response.message);
            }
        } catch (error: unknown) {
            setRequestError(
                getBackendErrorMessage(
                    error,
                    'No se pudo eliminar el permiso. Inténtalo nuevamente.',
                ),
            );
        }
    };

    const validationError =
        typeof errors.permission === 'string' ? errors.permission : undefined;
    const error = requestError ?? validationError;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-t-[3px] border-t-destructive sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Eliminar permiso</DialogTitle>
                    <DialogDescription>
                        Esta acción quitará <code>{permission.name}</code> del
                        catálogo. No se podrá eliminar si está asignado.
                    </DialogDescription>
                </DialogHeader>

                {error ? (
                    <AlertError errors={[error]} title="No se pudo eliminar" />
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
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() => void handleDelete()}
                        disabled={processing}
                    >
                        {processing ? <Spinner /> : <Trash2 />}
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
