import {
    BriefcaseBusiness,
    CalendarDays,
    CheckCircle2,
    CircleAlert,
    Clock3,
    Download,
    FileText,
    IdCard,
    Mail,
    Pencil,
    Phone,
    Trash2,
    UserRound,
    WalletCards,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ImagePreview } from '@/components/forms/image-preview';
import { ResourceDetailDrawer } from '@/components/resource-detail-drawer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type {
    Empleado,
    EmpleadoDocumento,
    TipoDocumentoEmpleado,
} from '@/types';

type Props = {
    empleado: Empleado;
    tiposDocumento: TipoDocumentoEmpleado[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit?: () => void;
    onDelete?: () => void;
};

type DetailTab = 'informacion' | 'documentos';

type DocumentRow = {
    key: string;
    tipo: TipoDocumentoEmpleado | null;
    nombre: string;
    documento: EmpleadoDocumento | null;
};

export function EmpleadoDetailDrawer({
    empleado,
    tiposDocumento,
    open,
    onOpenChange,
    onEdit,
    onDelete,
}: Props) {
    const [activeTab, setActiveTab] = useState<DetailTab>('informacion');
    const documentRows = buildDocumentRows(empleado, tiposDocumento);
    const uploadedDocuments = documentRows.filter(
        ({ documento }) => documento !== null,
    ).length;

    return (
        <ResourceDetailDrawer
            open={open}
            onOpenChange={onOpenChange}
            title={empleado.nombre}
            headerExtra={
                <>
                    <Badge
                        variant={empleado.deleted_at ? 'outline' : 'default'}
                    >
                        {empleado.deleted_at ? 'Archivado' : 'Activo'}
                    </Badge>
                    {empleado.puesto?.nombre ? (
                        <Badge variant="secondary">
                            <BriefcaseBusiness />
                            {empleado.puesto.nombre}
                        </Badge>
                    ) : null}
                </>
            }
            footer={
                <>
                    {onDelete ? (
                        <Button
                            type="button"
                            variant="destructive"
                            className="mr-auto"
                            onClick={onDelete}
                        >
                            <Trash2 /> Eliminar
                        </Button>
                    ) : null}
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cerrar
                    </Button>
                    {onEdit ? (
                        <Button type="button" onClick={onEdit}>
                            <Pencil /> Editar expediente
                        </Button>
                    ) : null}
                </>
            }
            bodyClassName="p-0 sm:p-0"
        >
            <div
                role="tablist"
                aria-label="Secciones del expediente"
                className="sticky top-0 z-10 grid grid-cols-2 border-b bg-background/95 px-5 pt-2 backdrop-blur-sm sm:px-6"
            >
                <TabButton
                    active={activeTab === 'informacion'}
                    onClick={() => setActiveTab('informacion')}
                >
                    Información
                </TabButton>
                <TabButton
                    active={activeTab === 'documentos'}
                    onClick={() => setActiveTab('documentos')}
                >
                    Documentos
                    <span className="rounded-full bg-muted px-1.5 py-0.5 text-[0.65rem] leading-none text-muted-foreground">
                        {uploadedDocuments}/{documentRows.length}
                    </span>
                </TabButton>
            </div>

            <div className="px-5 py-5 sm:px-6">
                {activeTab === 'informacion' ? (
                    <EmployeeInformation empleado={empleado} />
                ) : (
                    <EmployeeDocuments rows={documentRows} />
                )}
            </div>
        </ResourceDetailDrawer>
    );
}

function TabButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            className={cn(
                'flex min-h-11 items-center justify-center gap-2 border-b-2 px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                active
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground',
            )}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

function EmployeeInformation({ empleado }: { empleado: Empleado }) {
    return (
        <div className="grid gap-5">
            <section className="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm">
                {empleado.avatar_url ? (
                    <img
                        src={empleado.avatar_url}
                        alt={`Fotografía de ${empleado.nombre}`}
                        className="size-16 rounded-xl border object-cover shadow-sm"
                    />
                ) : (
                    <span className="flex size-16 shrink-0 items-center justify-center rounded-xl bg-accent">
                        <UserRound className="size-7 text-muted-foreground" />
                    </span>
                )}
                <div className="min-w-0">
                    <p className="truncate text-lg font-semibold">
                        {empleado.nombre}
                    </p>
                    <p className="truncate text-sm text-muted-foreground">
                        {empleado.correo}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Ingreso: {formatDate(empleado.fecha_ingreso)}
                    </p>
                </div>
            </section>

            <DetailSection icon={<IdCard />} title="Datos personales">
                <DetailItem label="Nombre completo" value={empleado.nombre} />
                <DetailItem
                    label="Usuario"
                    value={`@${empleado.nombre_usuario}`}
                />
                <DetailItem label="CURP" value={empleado.curp} copyable />
                <DetailItem label="RFC" value={empleado.rfc} copyable />
                <DetailItem label="NSS" value={empleado.nss} copyable />
                <DetailItem
                    label="Clínica IMSS"
                    value={empleado.num_clinica_ss}
                />
                <DetailItem
                    label="Fecha de nacimiento"
                    value={formatDate(empleado.fecha_nacimiento)}
                />
                <DetailItem label="Sexo" value={formatSex(empleado.sexo)} />
                <DetailItem
                    label="Estado civil"
                    value={formatCivilStatus(empleado.estado_civil)}
                />
            </DetailSection>

            <DetailSection icon={<Phone />} title="Contacto">
                <DetailItem
                    label="Correo"
                    value={
                        <a
                            href={`mailto:${empleado.correo}`}
                            className="inline-flex items-center gap-1.5 break-all text-primary hover:underline"
                        >
                            <Mail className="size-3.5" /> {empleado.correo}
                        </a>
                    }
                />
                <DetailItem
                    label="Teléfono"
                    value={
                        <a
                            href={`tel:${empleado.telefono}`}
                            className="text-primary hover:underline"
                        >
                            {formatPhone(empleado.telefono)}
                        </a>
                    }
                />
                <DetailItem
                    label="Domicilio"
                    value={empleado.domicilio}
                    fullWidth
                />
            </DetailSection>

            <DetailSection
                icon={<BriefcaseBusiness />}
                title="Información laboral"
            >
                <DetailItem label="Puesto" value={empleado.puesto?.nombre} />
                <DetailItem
                    label="Fecha de ingreso"
                    value={formatDate(empleado.fecha_ingreso)}
                />
                <DetailItem
                    label="Periodo de prueba"
                    value={formatMonths(empleado.periodo_prueba_meses)}
                />
                <DetailItem
                    label="Días de vacaciones"
                    value={formatDays(empleado.dias_vacaciones)}
                />
                <DetailItem
                    label="Días de liquidación"
                    value={formatDays(empleado.dias_liquidacion)}
                />
                <DetailItem
                    label="Días de descanso"
                    value={formatRestDays(empleado.dias_descanso)}
                    fullWidth
                />
            </DetailSection>

            <DetailSection icon={<WalletCards />} title="Compensación">
                <DetailItem
                    label="Salario diario"
                    value={formatMoney(empleado.salario_dia)}
                />
                <DetailItem
                    label="Salario quincenal"
                    value={formatMoney(empleado.salario_quincena)}
                />
                <DetailItem
                    label="Vacaciones / finiquito"
                    value={formatMoney(empleado.salario_vacaciones_finiquito)}
                />
                <DetailItem
                    label="Aguinaldo"
                    value={formatMoney(empleado.aguinaldo)}
                />
                <DetailItem
                    label="Prima vacacional"
                    value={formatMoney(empleado.prima_vacacional)}
                />
            </DetailSection>

            <DetailSection icon={<CalendarDays />} title="Fechas de contrato">
                <DetailItem
                    label="Inicio de contrato"
                    value={formatDate(empleado.fecha_inicio_contrato)}
                />
                <DetailItem
                    label="Término de contrato"
                    value={formatDate(empleado.fecha_termino_contrato)}
                />
                <DetailItem
                    label="Siguiente contrato"
                    value={formatDate(empleado.fecha_contrato_siguiente)}
                />
                <DetailItem
                    label="Contrato indefinido"
                    value={formatDate(empleado.fecha_contrato_indefinido)}
                />
                <DetailItem
                    label="Último aviso"
                    value={formatDate(empleado.fecha_ultimo_aviso)}
                />
                <DetailItem
                    label="Evaluación"
                    value={formatDate(empleado.fecha_evaluacion)}
                />
            </DetailSection>

            <DetailSection icon={<Clock3 />} title="Registro">
                <DetailItem
                    label="Creado"
                    value={formatDateTime(empleado.created_at)}
                />
                <DetailItem
                    label="Última actualización"
                    value={formatDateTime(empleado.updated_at)}
                />
                {empleado.deleted_at ? (
                    <DetailItem
                        label="Archivado"
                        value={formatDateTime(empleado.deleted_at)}
                    />
                ) : null}
            </DetailSection>
        </div>
    );
}

function DetailSection({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-xl border bg-card shadow-sm">
            <header className="flex items-center gap-2 border-b bg-muted/35 px-4 py-3 font-semibold">
                <span className="text-primary [&_svg]:size-4">{icon}</span>
                <h3>{title}</h3>
            </header>
            <dl className="grid grid-cols-1 gap-x-6 gap-y-4 p-4 sm:grid-cols-2">
                {children}
            </dl>
        </section>
    );
}

function DetailItem({
    label,
    value,
    fullWidth = false,
    copyable = false,
}: {
    label: string;
    value: ReactNode;
    fullWidth?: boolean;
    copyable?: boolean;
}) {
    const renderedValue = hasValue(value) ? value : 'No registrado';

    return (
        <div className={cn('grid gap-1', fullWidth && 'sm:col-span-2')}>
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd
                className={cn(
                    'text-sm break-words',
                    !hasValue(value) && 'text-muted-foreground',
                    copyable && 'font-mono text-xs select-all sm:text-sm',
                )}
            >
                {renderedValue}
            </dd>
        </div>
    );
}

function EmployeeDocuments({ rows }: { rows: DocumentRow[] }) {
    if (rows.length === 0) {
        return (
            <div className="flex min-h-56 flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-8 text-center">
                <FileText className="size-8 text-muted-foreground" />
                <div className="grid gap-1">
                    <p className="font-medium">Sin documentos configurados</p>
                    <p className="text-sm text-muted-foreground">
                        No existen tipos de documento activos para empleados.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="grid gap-3">
            {rows.map(({ key, tipo, nombre, documento }) => {
                const status = documentStatus(documento, tipo);

                return (
                    <article
                        key={key}
                        className="flex items-start gap-3 rounded-xl border bg-card p-4 shadow-sm"
                    >
                        {documento?.preview_url ? (
                            <ImagePreview
                                src={documento.preview_url}
                                active
                                alt={documento.nombre_original ?? nombre}
                                size={64}
                                className="size-16 rounded-lg border"
                            />
                        ) : (
                            <span className="flex size-16 shrink-0 items-center justify-center rounded-lg border bg-muted/40">
                                {documento ? (
                                    <FileText className="size-6 text-primary" />
                                ) : (
                                    <CircleAlert className="size-6 text-muted-foreground" />
                                )}
                            </span>
                        )}

                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <h3 className="font-medium">{nombre}</h3>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {documento?.nombre_original ??
                                            'Falta subir este documento'}
                                    </p>
                                </div>
                                <Badge variant={status.variant}>
                                    {status.icon}
                                    {status.label}
                                </Badge>
                            </div>

                            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground">
                                {documento ? (
                                    <>
                                        <span>
                                            {formatFileSize(documento.tamano)}
                                        </span>
                                        <span>
                                            Vencimiento:{' '}
                                            {formatDate(documento.vence_el)}
                                        </span>
                                    </>
                                ) : (
                                    <span>
                                        Formatos:{' '}
                                        {tipo?.documentos_aceptados.join(
                                            ', ',
                                        ) || 'No configurados'}
                                    </span>
                                )}
                            </div>

                            {documento?.preview_url ? (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Selecciona miniatura para ampliar imagen.
                                </p>
                            ) : documento?.download_url ? (
                                <Button
                                    asChild
                                    size="sm"
                                    variant="outline"
                                    className="mt-3"
                                >
                                    <a href={documento.download_url} download>
                                        <Download /> Descargar archivo
                                    </a>
                                </Button>
                            ) : documento ? (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Archivo no disponible mientras expediente
                                    está archivado.
                                </p>
                            ) : null}
                        </div>
                    </article>
                );
            })}
        </div>
    );
}

function buildDocumentRows(
    empleado: Empleado,
    tiposDocumento: TipoDocumentoEmpleado[],
): DocumentRow[] {
    const documentos = empleado.documentos ?? [];
    const documentosByType = new Map(
        documentos.map((documento) => [
            documento.tipo_documento_empleado_id,
            documento,
        ]),
    );
    const tiposById = new Map(tiposDocumento.map((tipo) => [tipo.id, tipo]));
    const rows: DocumentRow[] = tiposDocumento
        .filter((tipo) => tipo.activo || documentosByType.has(tipo.id))
        .map((tipo) => ({
            key: `tipo-${tipo.id}`,
            tipo,
            nombre: tipo.nombre,
            documento: documentosByType.get(tipo.id) ?? null,
        }));
    const includedTypeIds = new Set(rows.map(({ tipo }) => tipo?.id));

    documentos.forEach((documento) => {
        const typeId = documento.tipo_documento_empleado_id;

        if (includedTypeIds.has(typeId)) {
            return;
        }

        const tipo = typeId ? (tiposById.get(typeId) ?? null) : null;

        rows.push({
            key: `documento-${documento.id}`,
            tipo,
            nombre:
                tipo?.nombre ??
                documento.tipo?.nombre ??
                documento.tipo_documento?.nombre ??
                'Documento sin tipo',
            documento,
        });
    });

    return rows.sort((first, second) =>
        first.nombre.localeCompare(second.nombre, 'es'),
    );
}

function documentStatus(
    documento: EmpleadoDocumento | null,
    tipo: TipoDocumentoEmpleado | null,
): {
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
    icon: ReactNode;
} {
    if (!documento) {
        return {
            label: 'Pendiente',
            variant: 'outline',
            icon: <CircleAlert />,
        };
    }

    if (!documento.vence_el) {
        return {
            label: tipo?.es_renovable ? 'Sin vencimiento' : 'Cargado',
            variant: tipo?.es_renovable ? 'outline' : 'secondary',
            icon: tipo?.es_renovable ? <CircleAlert /> : <CheckCircle2 />,
        };
    }

    const today = startOfToday();
    const expiration = parseLocalDate(documento.vence_el);
    const warningDate = new Date(today);
    warningDate.setDate(warningDate.getDate() + 30);

    if (expiration < today) {
        return {
            label: 'Vencido',
            variant: 'destructive',
            icon: <CircleAlert />,
        };
    }

    if (expiration <= warningDate) {
        return {
            label: 'Vence pronto',
            variant: 'outline',
            icon: <Clock3 />,
        };
    }

    return {
        label: 'Vigente',
        variant: 'default',
        icon: <CheckCircle2 />,
    };
}

function hasValue(value: ReactNode): boolean {
    return value !== null && value !== undefined && value !== '';
}

function parseLocalDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function startOfToday(): Date {
    const today = new Date();

    return new Date(today.getFullYear(), today.getMonth(), today.getDate());
}

function formatDate(value?: string | null): string {
    if (!value) {
        return 'No registrada';
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parseLocalDate(value));
}

function formatDateTime(value?: string | null): string {
    if (!value) {
        return 'No registrado';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'No registrado';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function formatMoney(value?: number | string | null): string {
    if (value === null || value === undefined || value === '') {
        return 'No registrado';
    }

    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return 'No registrado';
    }

    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(amount);
}

function formatFileSize(value?: number | null): string {
    if (!value || value < 1) {
        return 'Tamaño no disponible';
    }

    if (value < 1024 * 1024) {
        return `${Math.max(1, Math.round(value / 1024))} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function formatPhone(value: string): string {
    if (!/^\d{10}$/.test(value)) {
        return value;
    }

    return `${value.slice(0, 2)} ${value.slice(2, 6)} ${value.slice(6)}`;
}

function formatMonths(value?: number | null): string {
    if (value === null || value === undefined) {
        return 'No registrado';
    }

    return `${value} ${value === 1 ? 'mes' : 'meses'}`;
}

function formatDays(value?: number | null): string {
    if (value === null || value === undefined) {
        return 'No registrado';
    }

    return `${value} ${value === 1 ? 'día' : 'días'}`;
}

function formatRestDays(value?: string[]): string {
    if (!value?.length) {
        return 'Sin días configurados';
    }

    return value
        .map((day) => day.charAt(0).toUpperCase() + day.slice(1))
        .join(', ');
}

function formatSex(value: string): string {
    return (
        {
            masculino: 'Masculino',
            femenino: 'Femenino',
            otro: 'Otro',
        }[value] ?? value
    );
}

function formatCivilStatus(value: string): string {
    return (
        {
            soltero: 'Soltero(a)',
            casado: 'Casado(a)',
            divorciado: 'Divorciado(a)',
            union_libre: 'Unión libre',
            viudo: 'Viudo(a)',
        }[value] ?? value
    );
}
