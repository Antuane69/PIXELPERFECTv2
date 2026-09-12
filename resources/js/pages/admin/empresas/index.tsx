import { Head } from '@inertiajs/react';
import { Plus, Settings2 } from 'lucide-react';
import { useState } from 'react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Admin/EmpresaController';
import { default as updateModules } from '@/actions/App/Http/Controllers/Admin/EmpresaModuloController';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    EmpresaAdministrada,
    GrupoEmpresarial,
    LaravelPaginator,
    Modulo,
} from '@/types';

type Props = {
    empresasPaginadas: LaravelPaginator<EmpresaAdministrada>;
    grupos: GrupoEmpresarial[];
    modulosDisponibles: Modulo[];
    filters: {
        search: string;
        estado: EmpresaAdministrada['estado'] | null;
        grupoEmpresarialId: number | null;
        perPage: number;
    };
};

const stateLabels: Record<EmpresaAdministrada['estado'], string> = {
    PROSPECTO: 'Prospecto',
    DEMO: 'Demo',
    ACTIVA: 'Activa',
    VENCIDA: 'Vencida',
    DESACTIVADA: 'Desactivada',
};

const stateVariants: Record<
    EmpresaAdministrada['estado'],
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    PROSPECTO: 'outline',
    DEMO: 'secondary',
    ACTIVA: 'default',
    VENCIDA: 'destructive',
    DESACTIVADA: 'outline',
};

export default function EmpresasIndex({
    empresasPaginadas,
    grupos,
    modulosDisponibles,
    filters,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [configuringModules, setConfiguringModules] =
        useState<EmpresaAdministrada | null>(null);
    const filterFacets: FilterFacet[] = [
        {
            key: 'estado',
            label: 'Estado de acceso',
            options: Object.entries(stateLabels).map(([value, label]) => ({
                value,
                label,
            })),
        },
        {
            key: 'grupo_empresarial_id',
            label: 'Grupo empresarial',
            options: grupos.map((grupo) => ({
                value: grupo.id,
                label: grupo.nombre,
            })),
        },
    ];
    const columns: ResourceColumn<EmpresaAdministrada>[] = [
        {
            key: 'empresa',
            header: 'Empresa',
            cell: (empresa) => (
                <div className="grid gap-1">
                    <span className="font-medium">
                        {empresa.nombre_comercial ?? empresa.nombre_legal}
                    </span>
                    {empresa.nombre_comercial ? (
                        <span className="text-xs text-muted-foreground">
                            {empresa.nombre_legal}
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'grupo',
            header: 'Grupo empresarial',
            cell: (empresa) => empresa.grupo_empresarial.nombre,
        },
        {
            key: 'estado',
            header: 'Estado',
            cell: (empresa) => (
                <Badge variant={stateVariants[empresa.estado]}>
                    {stateLabels[empresa.estado]}
                </Badge>
            ),
        },
        {
            key: 'miembros',
            header: 'Miembros',
            cell: (empresa) => empresa.membresias_count.toLocaleString('es-MX'),
        },
        {
            key: 'alta',
            header: 'Alta',
            mobileHidden: true,
            cell: (empresa) =>
                new Intl.DateTimeFormat('es-MX', {
                    dateStyle: 'medium',
                }).format(new Date(empresa.created_at)),
        },
        {
            key: 'acciones',
            header: 'Acciones',
            className: 'md:w-24',
            cell: (empresa) => (
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    onClick={() => setConfiguringModules(empresa)}
                    aria-label={`Configurar módulos de ${empresa.nombre_comercial ?? empresa.nombre_legal}`}
                >
                    <Settings2 />
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Empresas" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <ResourceHeader
                    title="Empresas"
                    description="Administra tenants, grupos y estado inicial de acceso."
                    actions={
                        <Button onClick={() => setDialogOpen(true)}>
                            <Plus /> Nueva empresa
                        </Button>
                    }
                />

                <FiltrosBase
                    route={index()}
                    defaultSearch={filters.search}
                    placeholder="Buscar empresa o RFC"
                    facets={filterFacets}
                    query={{
                        estado: filters.estado,
                        grupo_empresarial_id: filters.grupoEmpresarialId,
                        per_page: filters.perPage,
                    }}
                />
                <ResourceTable
                    data={empresasPaginadas.data}
                    columns={columns}
                    getRowKey={(empresa) => empresa.id}
                    emptyTitle="No hay empresas"
                    emptyDescription="Crea primera empresa para iniciar operación multiempresa."
                />
                <ResourcePagination paginator={empresasPaginadas} />
            </main>

            {dialogOpen ? (
                <EmpresaDialog
                    open
                    onOpenChange={setDialogOpen}
                    grupos={grupos}
                />
            ) : null}
            {configuringModules ? (
                <EmpresaModulesDialog
                    empresa={configuringModules}
                    modulos={modulosDisponibles}
                    onOpenChange={(open) =>
                        !open && setConfiguringModules(null)
                    }
                />
            ) : null}
        </>
    );
}

function EmpresaModulesDialog({
    empresa,
    modulos,
    onOpenChange,
}: {
    empresa: EmpresaAdministrada;
    modulos: Modulo[];
    onOpenChange: (open: boolean) => void;
}) {
    const enabledModuleIds = new Set(
        empresa.modulos
            .filter((module) => module.habilitado)
            .map((module) => module.id),
    );

    return (
        <ResourceFormDialog
            open
            onOpenChange={onOpenChange}
            title="Módulos de empresa"
            description={`Controla acceso contratado para ${empresa.nombre_comercial ?? empresa.nombre_legal}. Los permisos del usuario siguen siendo obligatorios.`}
            formId="empresa-modulos-form"
            form={updateModules.form(empresa.slug)}
            submitLabel="Guardar módulos"
        >
            {(errors) => (
                <fieldset className="grid gap-3">
                    <legend className="sr-only">Módulos habilitados</legend>
                    {modulos.map((module) => (
                        <label
                            key={module.id}
                            className="flex cursor-pointer items-start gap-3 rounded-lg border p-4 hover:bg-accent/50"
                        >
                            <Checkbox
                                name="modulos[]"
                                value={String(module.id)}
                                defaultChecked={enabledModuleIds.has(module.id)}
                            />
                            <span className="grid gap-1">
                                <span className="text-sm font-medium">
                                    {module.nombre}
                                </span>
                                {module.descripcion ? (
                                    <span className="text-xs text-muted-foreground">
                                        {module.descripcion}
                                    </span>
                                ) : null}
                            </span>
                        </label>
                    ))}
                    <InputError message={errors.modulos} />
                </fieldset>
            )}
        </ResourceFormDialog>
    );
}

function EmpresaDialog({
    open,
    onOpenChange,
    grupos,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    grupos: GrupoEmpresarial[];
}) {
    const [group, setGroup] = useState('independiente');
    const [state, setState] =
        useState<EmpresaAdministrada['estado']>('PROSPECTO');

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Nueva empresa"
            description="Crea tenant y grupo exclusivo, o vincúlalo con grupo existente."
            formId="empresa-form"
            form={store.form()}
            submitLabel="Crear empresa"
            resetOnSuccess
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="empresa-nombre-legal">
                                Nombre legal
                            </Label>
                            <Input
                                id="empresa-nombre-legal"
                                name="nombre_legal"
                                maxLength={180}
                                required
                                autoFocus
                            />
                            <InputError message={errors.nombre_legal} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="empresa-nombre-comercial">
                                Nombre comercial
                            </Label>
                            <Input
                                id="empresa-nombre-comercial"
                                name="nombre_comercial"
                                maxLength={180}
                            />
                            <InputError message={errors.nombre_comercial} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="empresa-grupo">
                                Grupo empresarial
                            </Label>
                            <input
                                type="hidden"
                                name="grupo_empresarial_id"
                                value={group === 'independiente' ? '' : group}
                            />
                            <Select value={group} onValueChange={setGroup}>
                                <SelectTrigger
                                    id="empresa-grupo"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="independiente">
                                        Crear grupo exclusivo
                                    </SelectItem>
                                    {grupos.map((grupo) => (
                                        <SelectItem
                                            key={grupo.id}
                                            value={String(grupo.id)}
                                        >
                                            {grupo.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.grupo_empresarial_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="empresa-rfc">RFC</Label>
                            <Input
                                id="empresa-rfc"
                                name="rfc"
                                minLength={12}
                                maxLength={13}
                                className="uppercase"
                            />
                            <InputError message={errors.rfc} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="empresa-correo">
                                Correo de contacto
                            </Label>
                            <Input
                                id="empresa-correo"
                                name="correo_contacto"
                                type="email"
                                maxLength={180}
                            />
                            <InputError message={errors.correo_contacto} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="empresa-telefono">Teléfono</Label>
                            <Input
                                id="empresa-telefono"
                                name="telefono_contacto"
                                maxLength={30}
                            />
                            <InputError message={errors.telefono_contacto} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="empresa-estado">
                                Estado inicial
                            </Label>
                            <Select
                                name="estado"
                                value={state}
                                onValueChange={(value) =>
                                    setState(
                                        value as EmpresaAdministrada['estado'],
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="empresa-estado"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(stateLabels).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.estado} />
                        </div>
                        {state === 'DEMO' ? (
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="empresa-demo-ends-at">
                                    Fin de demo
                                </Label>
                                <Input
                                    id="empresa-demo-ends-at"
                                    name="demo_ends_at"
                                    type="datetime-local"
                                    required
                                />
                                <InputError message={errors.demo_ends_at} />
                            </div>
                        ) : null}
                    </div>
                    <input
                        type="hidden"
                        name="zona_horaria"
                        value="America/Mexico_City"
                    />
                    <input type="hidden" name="moneda" value="MXN" />
                </div>
            )}
        </ResourceFormDialog>
    );
}

EmpresasIndex.layout = {
    breadcrumbs: [
        { title: 'Plataforma', href: index() },
        { title: 'Empresas', href: index() },
    ],
};
