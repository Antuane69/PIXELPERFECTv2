import { Head } from '@inertiajs/react';
import { DatePicker, Form as AntForm } from 'antd';
import type { FormRule } from 'antd';
import dayjs from 'dayjs';
import { Plus, Settings2 } from 'lucide-react';
import { useState } from 'react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Admin/EmpresaController';
import { default as updateModules } from '@/actions/App/Http/Controllers/Admin/EmpresaModuloController';
import { FiltrosBase } from '@/components/filtros-base';
import type { FilterFacet } from '@/components/filtros-base';
import {
    normalizeDigits,
    normalizeInput,
    normalizeRfc,
} from '@/components/forms/form-utils';
import { ImagePreview } from '@/components/forms/image-preview';
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

const creatableStates: EmpresaAdministrada['estado'][] = [
    'PROSPECTO',
    'DEMO',
    'ACTIVA',
];

const companyRfcPattern = /^[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}$/u;

function hasValidCompanyRfcDate(value: string): boolean {
    const year = 2000 + Number(value.slice(3, 5));
    const month = Number(value.slice(5, 7));
    const day = Number(value.slice(7, 9));
    const date = new Date(Date.UTC(year, month - 1, day));

    return (
        date.getUTCFullYear() === year &&
        date.getUTCMonth() === month - 1 &&
        date.getUTCDate() === day
    );
}

const companyRfcRules: FormRule[] = [
    {
        required: true,
        message: 'Ingresa el RFC de la empresa.',
    },
    {
        len: 12,
        message: 'El RFC de una empresa debe tener 12 caracteres.',
    },
    {
        pattern: companyRfcPattern,
        message: 'Ingresa un RFC de persona moral válido.',
    },
    {
        validator: async (_, value: unknown) => {
            if (typeof value !== 'string' || !companyRfcPattern.test(value)) {
                return;
            }

            if (!hasValidCompanyRfcDate(value)) {
                throw new Error(
                    'El RFC debe incluir una fecha de constitución válida.',
                );
            }
        },
    },
];

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
                <div className="flex min-w-0 items-center gap-3">
                    {empresa.logo_url ? (
                        <ImagePreview
                            src={empresa.logo_url}
                            active
                            alt={`Logo de ${empresa.nombre_comercial ?? empresa.nombre_legal}`}
                            size={48}
                            className="size-12 rounded-md border"
                        />
                    ) : (
                        <span
                            className="flex size-12 shrink-0 items-center justify-center rounded-md border bg-muted/40"
                            aria-hidden="true"
                        />
                    )}
                    <div className="grid min-w-0 gap-1">
                        <span className="truncate font-medium">
                            {empresa.nombre_comercial ?? empresa.nombre_legal}
                        </span>
                        {empresa.nombre_comercial ? (
                            <span className="truncate text-xs text-muted-foreground">
                                {empresa.nombre_legal}
                            </span>
                        ) : null}
                    </div>
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
            form={updateModules.form(empresa.id)}
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
    const [demoEndsAt, setDemoEndsAt] = useState('');

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
                <AntForm
                    component={false}
                    validateTrigger={['onChange', 'onBlur']}
                >
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
                                <Label htmlFor="empresa-logo">
                                    Logo de la empresa
                                </Label>
                                <Input
                                    id="empresa-logo"
                                    name="logo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                />
                                <p className="text-xs text-muted-foreground">
                                    JPG, PNG o WebP. Máximo 5 MB.
                                </p>
                                <InputError message={errors.logo} />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="empresa-grupo">
                                    Grupo empresarial
                                </Label>
                                <input
                                    type="hidden"
                                    name="grupo_empresarial_id"
                                    value={
                                        group === 'independiente' ? '' : group
                                    }
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
                                <InputError
                                    message={errors.grupo_empresarial_id}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="empresa-rfc">RFC</Label>
                                <AntForm.Item
                                    name="rfc"
                                    normalize={normalizeRfc}
                                    rules={companyRfcRules}
                                    validateStatus={
                                        errors.rfc ? 'error' : undefined
                                    }
                                    help={errors.rfc}
                                    className="!mb-0"
                                >
                                    <Input
                                        id="empresa-rfc"
                                        name="rfc"
                                        minLength={12}
                                        maxLength={12}
                                        pattern="[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}"
                                        title="RFC de persona moral de 12 caracteres"
                                        className="uppercase"
                                        required
                                        autoCapitalize="characters"
                                        spellCheck={false}
                                        onInput={(event) =>
                                            normalizeInput(event, normalizeRfc)
                                        }
                                    />
                                </AntForm.Item>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="empresa-correo">
                                    Correo de contacto
                                </Label>
                                <AntForm.Item
                                    name="correo_contacto"
                                    rules={[
                                        {
                                            type: 'email',
                                            message:
                                                'Ingresa un correo electrónico válido.',
                                        },
                                    ]}
                                    validateStatus={
                                        errors.correo_contacto
                                            ? 'error'
                                            : undefined
                                    }
                                    help={errors.correo_contacto}
                                    className="!mb-0"
                                >
                                    <Input
                                        id="empresa-correo"
                                        name="correo_contacto"
                                        type="email"
                                        maxLength={180}
                                    />
                                </AntForm.Item>
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="empresa-telefono">
                                    Teléfono de contacto
                                </Label>
                                <div className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-2">
                                    <AntForm.Item
                                        name="codigo_pais_contacto"
                                        rules={[
                                            {
                                                pattern: /^[0-9]{1,3}$/,
                                                message:
                                                    'Usa de 1 a 3 números.',
                                            },
                                        ]}
                                        validateStatus={
                                            errors.codigo_pais_contacto
                                                ? 'error'
                                                : undefined
                                        }
                                        help={errors.codigo_pais_contacto}
                                        className="!mb-0"
                                    >
                                        <Input
                                            id="empresa-codigo-pais"
                                            name="codigo_pais_contacto"
                                            prefix="+"
                                            aria-label="Código de país"
                                            placeholder="52"
                                            inputMode="numeric"
                                            maxLength={3}
                                            onInput={(event) =>
                                                normalizeInput(event, (value) =>
                                                    normalizeDigits(value, 3),
                                                )
                                            }
                                        />
                                    </AntForm.Item>
                                    <AntForm.Item
                                        name="telefono_contacto"
                                        rules={[
                                            {
                                                pattern: /^[0-9]{7,15}$/,
                                                message:
                                                    'Usa únicamente de 7 a 15 números.',
                                            },
                                        ]}
                                        validateStatus={
                                            errors.telefono_contacto
                                                ? 'error'
                                                : undefined
                                        }
                                        help={errors.telefono_contacto}
                                        className="!mb-0"
                                    >
                                        <Input
                                            id="empresa-telefono"
                                            name="telefono_contacto"
                                            type="tel"
                                            inputMode="numeric"
                                            maxLength={15}
                                            pattern="[0-9]{7,15}"
                                            title="Teléfono de 7 a 15 números"
                                            onInput={(event) =>
                                                normalizeInput(event, (value) =>
                                                    normalizeDigits(value, 15),
                                                )
                                            }
                                        />
                                    </AntForm.Item>
                                </div>
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
                                        {creatableStates.map((value) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {stateLabels[value]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.estado} />
                            </div>
                            {state === 'DEMO' ? (
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="empresa-demo-ends-at">
                                        Fin de demo
                                    </Label>
                                    <DatePicker
                                        id="empresa-demo-ends-at"
                                        value={
                                            demoEndsAt
                                                ? dayjs(demoEndsAt)
                                                : null
                                        }
                                        showTime={{ format: 'HH:mm' }}
                                        format="DD/MM/YYYY HH:mm"
                                        placeholder="dd/mm/aaaa hh:mm"
                                        status={
                                            errors.demo_ends_at
                                                ? 'error'
                                                : undefined
                                        }
                                        className="!h-9 !w-full"
                                        onChange={(date) =>
                                            setDemoEndsAt(
                                                date
                                                    ? date.format(
                                                          'YYYY-MM-DD HH:mm:ss',
                                                      )
                                                    : '',
                                            )
                                        }
                                    />
                                    <input
                                        type="hidden"
                                        name="demo_ends_at"
                                        value={demoEndsAt}
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
                </AntForm>
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
