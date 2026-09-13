import { DatePicker, Form as AntForm } from 'antd';
import type { DatePickerProps, FormRule } from 'antd';
import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';
import { Download, Info } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { FileAttachment } from '@/components/forms/file-attachment';
import {
    documentExtensions,
    formatMoney,
    normalizeCurp,
    normalizeDigits,
    normalizeInput,
    normalizeMoney,
    normalizeRfc,
    normalizeUsername,
} from '@/components/forms/form-utils';
import { ImagePreview } from '@/components/forms/image-preview';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    Empleado,
    EmpleadoDocumento,
    Puesto,
    TipoDocumentoEmpleado,
} from '@/types';

type Props = {
    empleado: Empleado | null;
    puestos: Puesto[];
    tiposDocumento: TipoDocumentoEmpleado[];
    errors: Record<string, string>;
};

type FormFieldProps = {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
};

function FormField({ id, label, error, hint, children }: FormFieldProps) {
    return (
        <div className="grid gap-2 self-start">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}

type DateFieldProps = {
    id: string;
    name: string;
    label: string;
    value: string;
    error?: string;
    required?: boolean;
    disabledDate?: DatePickerProps['disabledDate'];
    maxDate?: DatePickerProps['maxDate'];
    onChange: (value: string) => void;
};

function DateField({
    id,
    name,
    label,
    value,
    error,
    required = false,
    disabledDate,
    maxDate,
    onChange,
}: DateFieldProps) {
    const dateValue = value ? dayjs(value) : null;

    return (
        <FormField id={id} label={label} error={error}>
            <DatePicker
                id={id}
                value={dateValue?.isValid() ? dateValue : null}
                defaultPickerValue={!dateValue ? maxDate : undefined}
                format="DD/MM/YYYY"
                placeholder="dd/mm/aaaa"
                disabledDate={disabledDate}
                maxDate={maxDate}
                status={error ? 'error' : undefined}
                className="!h-9 !w-full"
                onChange={(date: Dayjs | null) =>
                    onChange(date ? date.format('YYYY-MM-DD') : '')
                }
            />
            <input
                type="hidden"
                name={name}
                value={value}
                required={required}
            />
        </FormField>
    );
}

function FormSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-5 rounded-xl border p-4 sm:p-5">
            <header className="grid gap-1">
                <h3 className="font-semibold">{title}</h3>
                <p className="text-sm text-muted-foreground">{description}</p>
            </header>
            {children}
        </section>
    );
}

const estadosCiviles = [
    ['soltero', 'Soltero(a)'],
    ['casado', 'Casado(a)'],
    ['divorciado', 'Divorciado(a)'],
    ['union_libre', 'Unión libre'],
    ['viudo', 'Viudo(a)'],
];

const sexos = [
    ['masculino', 'Masculino'],
    ['femenino', 'Femenino'],
    ['otro', 'Otro'],
];

const moneyFields = [
    ['salario_dia', 'Salario diario'],
    ['salario_quincena', 'Salario quincenal'],
    ['salario_vacaciones_finiquito', 'Vacaciones / finiquito'],
    ['aguinaldo', 'Aguinaldo'],
    ['prima_vacacional', 'Prima vacacional'],
] as const;

const employeeEmailRules: FormRule[] = [
    {
        required: true,
        message: 'Ingresa el correo electrónico.',
    },
    {
        type: 'email',
        message: 'Ingresa un correo electrónico válido.',
    },
];

const employeeCurpPattern =
    /^[A-Z][AEIOUX][A-Z]{2}[0-9]{6}[HM][A-Z]{2}[BCDFGHJKLMNPQRSTVWXYZ]{3}[A-Z0-9][0-9]$/u;

const employeeCurpRules: FormRule[] = [
    {
        required: true,
        message: 'Ingresa la CURP.',
    },
    {
        len: 18,
        message: 'La CURP debe tener 18 caracteres.',
    },
    {
        pattern: employeeCurpPattern,
        message: 'Ingresa una CURP válida.',
    },
    {
        validator: async (_, value: unknown) => {
            if (
                typeof value !== 'string' ||
                !employeeCurpPattern.test(value) ||
                hasValidMexicanDate(value, 4)
            ) {
                return;
            }

            throw new Error('La CURP debe incluir una fecha válida.');
        },
    },
];

const employeeRfcPattern = /^[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}$/u;

const employeeRfcRules: FormRule[] = [
    {
        required: true,
        message: 'Ingresa el RFC.',
    },
    {
        len: 13,
        message: 'El RFC de un empleado debe tener 13 caracteres.',
    },
    {
        pattern: employeeRfcPattern,
        message: 'Ingresa un RFC de persona física válido.',
    },
    {
        validator: async (_, value: unknown) => {
            if (
                typeof value !== 'string' ||
                !employeeRfcPattern.test(value) ||
                hasValidMexicanDate(value, 4)
            ) {
                return;
            }

            throw new Error('El RFC debe incluir una fecha válida.');
        },
    },
];

const employeeNssRules: FormRule[] = [
    {
        validator: async (_, value: unknown) => {
            if (value === undefined || value === null || value === '') {
                return;
            }

            if (typeof value !== 'string' || !/^[0-9]{11}$/.test(value)) {
                throw new Error('El NSS debe tener 11 números.');
            }
        },
    },
];

function hasValidMexicanDate(value: string, start: number): boolean {
    const year = 2000 + Number(value.slice(start, start + 2));
    const month = Number(value.slice(start + 2, start + 4));
    const day = Number(value.slice(start + 4, start + 6));
    const date = new Date(year, month - 1, day);

    return (
        month >= 1 &&
        month <= 12 &&
        day >= 1 &&
        day <= date.getDate() &&
        date.getMonth() === month - 1
    );
}

const dayFields = [
    ['dias_vacaciones', 'Días de vacaciones'],
    ['dias_liquidacion', 'Días de liquidación'],
] as const;

const weekDays = [
    ['lunes', 'Lunes'],
    ['martes', 'Martes'],
    ['miercoles', 'Miércoles'],
    ['jueves', 'Jueves'],
    ['viernes', 'Viernes'],
    ['sabado', 'Sábado'],
    ['domingo', 'Domingo'],
] as const;

const documentError = (
    errors: Record<string, string>,
    tipoId: number,
    field: 'archivo' | 'vence_el',
) => errors[`documentos.${tipoId}.${field}`];

const existingDocument = (
    empleado: Empleado | null,
    tipoId: number,
): EmpleadoDocumento | undefined =>
    empleado?.documentos?.find((documento) => {
        const documentTypeId =
            documento.tipo_documento_empleado_id ??
            documento.tipo?.id ??
            documento.tipo_documento?.id;

        return documentTypeId === tipoId;
    });

export function EmpleadoFormFields({
    empleado,
    puestos,
    tiposDocumento,
    errors,
}: Props) {
    const isCreating = empleado === null;
    const today = dayjs().format('YYYY-MM-DD');
    const [selectedPuestoId, setSelectedPuestoId] = useState(
        empleado?.puesto_id ? String(empleado.puesto_id) : '',
    );
    const [salaryValues, setSalaryValues] = useState({
        salario_dia: formatMoney(String(empleado?.salario_dia ?? '')),
        salario_quincena: formatMoney(String(empleado?.salario_quincena ?? '')),
    });
    const [dateValues, setDateValues] = useState({
        fecha_nacimiento: empleado?.fecha_nacimiento ?? '',
        fecha_ingreso: empleado?.fecha_ingreso ?? today,
    });
    const [periodoPruebaMeses, setPeriodoPruebaMeses] = useState(
        String(empleado?.periodo_prueba_meses ?? 3),
    );
    const [documentExpiryValues, setDocumentExpiryValues] = useState<
        Record<number, string>
    >(() =>
        tiposDocumento.reduce<Record<number, string>>((values, tipo) => {
            values[tipo.id] =
                existingDocument(empleado, tipo.id)?.vence_el ?? '';

            return values;
        }, {}),
    );
    const visibleDocumentTypes = tiposDocumento.filter(
        (tipo) => tipo.activo !== false || existingDocument(empleado, tipo.id),
    );
    const periodo = Number(periodoPruebaMeses);
    const trialSchedule =
        dateValues.fecha_ingreso && periodo >= 1 && periodo <= 6
            ? Array.from({ length: periodo }, (_, index) => {
                  const date = dayjs(dateValues.fecha_ingreso).add(
                      index + 1,
                      'month',
                  );

                  return {
                      date: date.format('DD/MM/YYYY'),
                      label:
                          index + 1 === periodo
                              ? 'Contrato definitivo'
                              : `Contrato de prueba ${index + 1}`,
                  };
              })
            : [];

    const updateDate = (field: keyof typeof dateValues, value: string) => {
        setDateValues((current) => ({ ...current, [field]: value }));
    };

    const updatePuesto = (puestoId: string) => {
        setSelectedPuestoId(puestoId);

        const puesto = puestos.find((item) => String(item.id) === puestoId);

        setSalaryValues({
            salario_dia: formatMoney(String(puesto?.salario_dia ?? '')),
            salario_quincena: formatMoney(
                String(puesto?.salario_quincena ?? ''),
            ),
        });
    };

    const updateDocumentExpiry = (tipoId: number, value: string) => {
        setDocumentExpiryValues((current) => ({
            ...current,
            [tipoId]: value,
        }));
    };

    return (
        <AntForm component={false} validateTrigger={['onChange', 'onBlur']}>
            <div className="grid gap-5 pb-1">
                <FormSection
                    title="Datos generales"
                    description="Identidad, contacto y datos personales del empleado."
                >
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <FormField
                            id="empleado-nombre"
                            label="Nombre completo"
                            error={errors.nombre}
                        >
                            <Input
                                id="empleado-nombre"
                                name="nombre"
                                defaultValue={empleado?.nombre}
                                required
                                autoFocus
                                autoComplete="name"
                            />
                        </FormField>
                        <FormField
                            id="empleado-usuario"
                            label="Nombre de usuario"
                            error={errors.nombre_usuario}
                        >
                            <Input
                                id="empleado-usuario"
                                name="nombre_usuario"
                                maxLength={60}
                                pattern="[a-z0-9-]+"
                                title="Usa únicamente letras minúsculas, números y guiones."
                                defaultValue={normalizeUsername(
                                    empleado?.nombre_usuario ?? '',
                                )}
                                required
                                autoComplete="username"
                                autoCapitalize="none"
                                spellCheck={false}
                                onInput={(event) =>
                                    normalizeInput(event, normalizeUsername)
                                }
                            />
                        </FormField>
                        <div className="grid gap-2 self-start">
                            <Label htmlFor="empleado-correo">
                                Correo electrónico
                            </Label>
                            <AntForm.Item
                                name="correo"
                                initialValue={empleado?.correo}
                                rules={employeeEmailRules}
                                validateStatus={
                                    errors.correo ? 'error' : undefined
                                }
                                help={errors.correo}
                                className="!mb-0"
                            >
                                <Input
                                    id="empleado-correo"
                                    type="email"
                                    name="correo"
                                    required
                                    autoComplete="email"
                                />
                            </AntForm.Item>
                        </div>
                        <FormField
                            id="empleado-telefono"
                            label="Teléfono"
                            error={errors.telefono}
                        >
                            <Input
                                id="empleado-telefono"
                                type="tel"
                                name="telefono"
                                inputMode="numeric"
                                maxLength={10}
                                pattern="[0-9]{10}"
                                title="Ingresa un teléfono de 10 dígitos."
                                defaultValue={empleado?.telefono}
                                required
                                autoComplete="tel"
                                onInput={(event) =>
                                    normalizeInput(event, (value) =>
                                        normalizeDigits(value, 10),
                                    )
                                }
                            />
                        </FormField>
                        <div className="grid gap-2 self-start">
                            <Label htmlFor="empleado-curp">CURP</Label>
                            <AntForm.Item
                                name="curp"
                                initialValue={empleado?.curp}
                                rules={employeeCurpRules}
                                validateStatus={
                                    errors.curp ? 'error' : undefined
                                }
                                help={errors.curp}
                                className="!mb-0"
                            >
                                <Input
                                    id="empleado-curp"
                                    name="curp"
                                    maxLength={18}
                                    pattern={employeeCurpPattern.source}
                                    title="Ingresa una CURP válida de 18 caracteres."
                                    className="uppercase"
                                    required
                                    autoCapitalize="characters"
                                    spellCheck={false}
                                    onInput={(event) =>
                                        normalizeInput(event, normalizeCurp)
                                    }
                                />
                            </AntForm.Item>
                        </div>
                        <div className="grid gap-2 self-start">
                            <Label htmlFor="empleado-rfc">RFC</Label>
                            <AntForm.Item
                                name="rfc"
                                initialValue={empleado?.rfc}
                                rules={employeeRfcRules}
                                validateStatus={
                                    errors.rfc ? 'error' : undefined
                                }
                                help={errors.rfc}
                                className="!mb-0"
                            >
                                <Input
                                    id="empleado-rfc"
                                    name="rfc"
                                    maxLength={13}
                                    pattern={employeeRfcPattern.source}
                                    title="Ingresa un RFC de persona física válido de 13 caracteres."
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
                        <div className="grid gap-2 self-start">
                            <Label htmlFor="empleado-nss">NSS</Label>
                            <AntForm.Item
                                name="nss"
                                initialValue={empleado?.nss ?? ''}
                                rules={employeeNssRules}
                                validateStatus={
                                    errors.nss ? 'error' : undefined
                                }
                                help={errors.nss}
                                className="!mb-0"
                            >
                                <Input
                                    id="empleado-nss"
                                    name="nss"
                                    inputMode="numeric"
                                    maxLength={11}
                                    pattern="[0-9]{11}"
                                    title="Ingresa un NSS de 11 dígitos."
                                    onInput={(event) =>
                                        normalizeInput(event, (value) =>
                                            normalizeDigits(value, 11),
                                        )
                                    }
                                />
                            </AntForm.Item>
                        </div>
                        <FormField
                            id="empleado-clinica"
                            label="Clínica del IMSS"
                            error={errors.num_clinica_ss}
                        >
                            <Input
                                id="empleado-clinica"
                                name="num_clinica_ss"
                                defaultValue={empleado?.num_clinica_ss ?? ''}
                            />
                        </FormField>
                        <FormField
                            id="empleado-puesto"
                            label="Puesto"
                            error={errors.puesto_id}
                        >
                            <Select
                                name="puesto_id"
                                value={selectedPuestoId || undefined}
                                onValueChange={updatePuesto}
                                required
                            >
                                <SelectTrigger
                                    id="empleado-puesto"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Selecciona un puesto" />
                                </SelectTrigger>
                                <SelectContent>
                                    {puestos
                                        .filter(
                                            (puesto) =>
                                                puesto.activo !== false ||
                                                puesto.id ===
                                                    empleado?.puesto_id,
                                        )
                                        .map((puesto) => (
                                            <SelectItem
                                                key={puesto.id}
                                                value={String(puesto.id)}
                                            >
                                                {puesto.nombre}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            id="empleado-estado-civil"
                            label="Estado civil"
                            error={errors.estado_civil}
                        >
                            <Select
                                name="estado_civil"
                                defaultValue={
                                    empleado?.estado_civil || undefined
                                }
                                required
                            >
                                <SelectTrigger
                                    id="empleado-estado-civil"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Selecciona" />
                                </SelectTrigger>
                                <SelectContent>
                                    {estadosCiviles.map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            id="empleado-sexo"
                            label="Sexo"
                            error={errors.sexo}
                        >
                            <Select
                                name="sexo"
                                defaultValue={empleado?.sexo || undefined}
                                required
                            >
                                <SelectTrigger
                                    id="empleado-sexo"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Selecciona" />
                                </SelectTrigger>
                                <SelectContent>
                                    {sexos.map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            id="empleado-avatar"
                            label="Avatar"
                            error={errors.avatar}
                            hint="JPG, PNG o WEBP. Máximo según configuración del servidor."
                        >
                            <div className="flex flex-wrap items-center gap-3">
                                {empleado?.avatar_url ? (
                                    <div className="size-12 shrink-0 overflow-hidden rounded-full border">
                                        <ImagePreview
                                            src={empleado.avatar_url}
                                            active={Boolean(
                                                empleado.avatar_url,
                                            )}
                                        />
                                    </div>
                                ) : null}
                                <div className="min-w-0 flex-1">
                                    <FileAttachment
                                        id="empleado-avatar"
                                        name="avatar"
                                        accept="image/jpeg,image/png,image/webp"
                                        acceptedFormats={[
                                            'jpg',
                                            'jpeg',
                                            'png',
                                            'webp',
                                        ]}
                                        maxSizeBytes={3 * 1024 * 1024}
                                        showInputIcon={false}
                                    />
                                </div>
                            </div>
                        </FormField>
                        <div className="sm:col-span-2 lg:col-span-3">
                            <FormField
                                id="empleado-domicilio"
                                label="Domicilio"
                                error={errors.domicilio}
                            >
                                <Textarea
                                    id="empleado-domicilio"
                                    name="domicilio"
                                    defaultValue={empleado?.domicilio ?? ''}
                                />
                            </FormField>
                        </div>
                    </div>
                </FormSection>

                <FormSection
                    title="Importes y prestaciones"
                    description="Información salarial y días acumulados."
                >
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {moneyFields
                            .filter(
                                ([field]) =>
                                    !isCreating ||
                                    field === 'salario_dia' ||
                                    field === 'salario_quincena',
                            )
                            .map(([field, label]) => (
                                <FormField
                                    key={field}
                                    id={`empleado-${field}`}
                                    label={label}
                                    error={errors[field]}
                                >
                                    {field === 'salario_dia' ||
                                    field === 'salario_quincena' ? (
                                        <Input
                                            id={`empleado-${field}`}
                                            type="text"
                                            inputMode="decimal"
                                            name={field}
                                            pattern="[0-9]+([.][0-9]{0,2})?"
                                            title="Ingresa máximo 2 decimales."
                                            value={
                                                field === 'salario_dia'
                                                    ? salaryValues.salario_dia
                                                    : salaryValues.salario_quincena
                                            }
                                            onChange={(event) => {
                                                const value =
                                                    event.currentTarget.value;

                                                setSalaryValues((current) => ({
                                                    ...current,
                                                    [field]:
                                                        normalizeMoney(value),
                                                }));
                                            }}
                                            onBlur={(event) => {
                                                const value =
                                                    event.currentTarget.value;

                                                setSalaryValues((current) => ({
                                                    ...current,
                                                    [field]: formatMoney(value),
                                                }));
                                            }}
                                        />
                                    ) : (
                                        <Input
                                            id={`empleado-${field}`}
                                            type="number"
                                            name={field}
                                            min="0"
                                            step="0.01"
                                            defaultValue={
                                                empleado?.[field] ?? ''
                                            }
                                        />
                                    )}
                                </FormField>
                            ))}
                        {dayFields
                            .filter(
                                ([field]) =>
                                    !isCreating || field === 'dias_vacaciones',
                            )
                            .map(([field, label]) => (
                                <FormField
                                    key={field}
                                    id={`empleado-${field}`}
                                    label={label}
                                    error={errors[field]}
                                >
                                    {isCreating &&
                                    field === 'dias_vacaciones' ? (
                                        <>
                                            <Input
                                                id={`empleado-${field}`}
                                                type="number"
                                                value="2"
                                                disabled
                                                readOnly
                                            />
                                            <input
                                                type="hidden"
                                                name="dias_vacaciones"
                                                value="2"
                                            />
                                        </>
                                    ) : (
                                        <Input
                                            id={`empleado-${field}`}
                                            type="number"
                                            name={field}
                                            min={
                                                field === 'dias_vacaciones'
                                                    ? '2'
                                                    : '0'
                                            }
                                            step="1"
                                            defaultValue={
                                                field === 'dias_vacaciones'
                                                    ? (empleado?.dias_vacaciones ??
                                                      2)
                                                    : (empleado?.dias_liquidacion ??
                                                      '')
                                            }
                                        />
                                    )}
                                </FormField>
                            ))}
                        <fieldset className="grid gap-3 sm:col-span-2 lg:col-span-3">
                            <legend className="text-sm font-medium">
                                Días de descanso
                            </legend>
                            <input
                                type="hidden"
                                name="dias_descanso_present"
                                value="1"
                            />
                            <div className="flex flex-wrap gap-2">
                                {weekDays.map(([value, label]) => (
                                    <label
                                        key={value}
                                        className="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 hover:bg-accent/60"
                                    >
                                        <Checkbox
                                            name="dias_descanso[]"
                                            value={value}
                                            defaultChecked={empleado?.dias_descanso?.includes(
                                                value,
                                            )}
                                        />
                                        <span className="text-sm">{label}</span>
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.dias_descanso} />
                        </fieldset>
                    </div>
                </FormSection>

                <FormSection
                    title="Fechas laborales"
                    description="La fecha de ingreso activa el calendario contractual."
                >
                    <div className="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <DateField
                            id="empleado-fecha-nacimiento"
                            name="fecha_nacimiento"
                            label="Fecha de nacimiento"
                            value={dateValues.fecha_nacimiento}
                            error={errors.fecha_nacimiento}
                            required={!empleado}
                            maxDate={dayjs().subtract(16, 'year')}
                            onChange={(value) =>
                                updateDate('fecha_nacimiento', value)
                            }
                        />
                        <DateField
                            id="empleado-fecha-ingreso"
                            name="fecha_ingreso"
                            label="Fecha de ingreso"
                            value={dateValues.fecha_ingreso}
                            error={errors.fecha_ingreso}
                            required={!empleado}
                            disabledDate={(date) =>
                                date.isAfter(dayjs(), 'day')
                            }
                            onChange={(value) =>
                                updateDate('fecha_ingreso', value)
                            }
                        />
                        <div className="grid gap-2">
                            <div className="flex items-center gap-2">
                                <Label htmlFor="empleado-periodo-prueba">
                                    Periodo de prueba
                                </Label>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <button
                                            type="button"
                                            className="rounded-full text-muted-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            aria-label="Información sobre periodo de prueba"
                                        >
                                            <Info className="size-4" />
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Se genera un contrato mensual de prueba
                                        y el último registro marca el contrato
                                        definitivo.
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                            <Select
                                name="periodo_prueba_meses"
                                value={periodoPruebaMeses}
                                onValueChange={setPeriodoPruebaMeses}
                                required
                            >
                                <SelectTrigger
                                    id="empleado-periodo-prueba"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.from({ length: 6 }, (_, index) => {
                                        const months = index + 1;

                                        return (
                                            <SelectItem
                                                key={months}
                                                value={String(months)}
                                            >
                                                {months}{' '}
                                                {months === 1 ? 'mes' : 'meses'}
                                            </SelectItem>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.periodo_prueba_meses} />
                            {trialSchedule.length > 0 ? (
                                <div className="grid gap-1 rounded-md bg-muted/50 p-3 text-xs text-muted-foreground">
                                    {trialSchedule.map((item) => (
                                        <p key={`${item.label}-${item.date}`}>
                                            <span className="font-medium text-foreground">
                                                {item.label}:
                                            </span>{' '}
                                            {item.date}
                                        </p>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                    </div>
                </FormSection>

                <FormSection
                    title="Documentación"
                    description={
                        empleado
                            ? 'Conserva el archivo actual o selecciona uno nuevo para reemplazarlo.'
                            : 'Adjunta un archivo para cada documento activo requerido.'
                    }
                >
                    {visibleDocumentTypes.length ? (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {visibleDocumentTypes.map((tipo) => {
                                const current = existingDocument(
                                    empleado,
                                    tipo.id,
                                );
                                const acceptedExtensions = documentExtensions(
                                    tipo.documentos_aceptados,
                                );
                                const accepted = acceptedExtensions
                                    .map((format) => `.${format}`)
                                    .join(',');

                                return (
                                    <article
                                        key={tipo.id}
                                        className="grid gap-4 rounded-lg border p-4"
                                    >
                                        <header className="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <h4 className="font-medium">
                                                    {tipo.nombre}
                                                </h4>
                                                <p className="text-xs text-muted-foreground">
                                                    {tipo.documentos_aceptados.join(
                                                        ', ',
                                                    )}
                                                </p>
                                            </div>
                                            {tipo.es_renovable && (
                                                <Badge variant="secondary">
                                                    Renovable
                                                </Badge>
                                            )}
                                        </header>

                                        {current?.download_url ? (
                                            <a
                                                href={current.download_url}
                                                className="inline-flex items-center gap-2 text-sm font-medium text-primary underline-offset-4 hover:underline"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Download className="size-4" />
                                                {current.nombre_original ??
                                                    current.nombre ??
                                                    'Descargar documento actual'}
                                            </a>
                                        ) : null}

                                        <input
                                            type="hidden"
                                            name={`documentos[${tipo.id}][tipo_documento_empleado_id]`}
                                            value={tipo.id}
                                        />
                                        <FormField
                                            id={`documento-${tipo.id}`}
                                            label={
                                                current
                                                    ? 'Reemplazar archivo'
                                                    : 'Archivo'
                                            }
                                            error={documentError(
                                                errors,
                                                tipo.id,
                                                'archivo',
                                            )}
                                        >
                                            <FileAttachment
                                                id={`documento-${tipo.id}`}
                                                name={`documentos[${tipo.id}][archivo]`}
                                                accept={accepted}
                                                acceptedFormats={
                                                    acceptedExtensions
                                                }
                                                required={
                                                    tipo.activo && !current
                                                }
                                            />
                                        </FormField>
                                        {tipo.es_renovable && (
                                            <DateField
                                                id={`documento-vence-${tipo.id}`}
                                                name={`documentos[${tipo.id}][vence_el]`}
                                                label="Fecha de vencimiento"
                                                value={
                                                    documentExpiryValues[
                                                        tipo.id
                                                    ] ??
                                                    current?.vence_el ??
                                                    ''
                                                }
                                                error={documentError(
                                                    errors,
                                                    tipo.id,
                                                    'vence_el',
                                                )}
                                                required
                                                onChange={(value) =>
                                                    updateDocumentExpiry(
                                                        tipo.id,
                                                        value,
                                                    )
                                                }
                                            />
                                        )}
                                    </article>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="rounded-lg border border-dashed p-5 text-sm text-muted-foreground">
                            No hay tipos de documento activos configurados.
                        </p>
                    )}
                    <InputError message={errors.documentos} />
                </FormSection>
            </div>
        </AntForm>
    );
}
