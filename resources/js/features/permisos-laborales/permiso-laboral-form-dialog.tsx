import { useState } from 'react';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
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
import type { PermisoLaboralEmpleadoOption, TipoPermisoOption } from '@/types';

type PermisoLaboralFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    form: { action: string; method: 'post' };
    empleados: PermisoLaboralEmpleadoOption[];
    empleadosCobertura: PermisoLaboralEmpleadoOption[];
    tiposPermiso: TipoPermisoOption[];
    selfEmployeeId: number | null;
    canCreateForOthers: boolean;
};

export function PermisoLaboralFormDialog({
    open,
    onOpenChange,
    form,
    empleados,
    empleadosCobertura: empleadosCoberturaDisponibles,
    tiposPermiso,
    selfEmployeeId,
    canCreateForOthers,
}: PermisoLaboralFormDialogProps) {
    const initialEmployeeId = selfEmployeeId ? String(selfEmployeeId) : '';
    const [selectedEmployeeId, setSelectedEmployeeId] =
        useState(initialEmployeeId);
    const [selectedCoverIds, setSelectedCoverIds] = useState<number[]>([]);
    const [selectedTipoPermisoId, setSelectedTipoPermisoId] = useState('');
    const empleado = empleados.find(
        (item) => item.id === Number(selectedEmployeeId),
    );
    const empleadosCobertura = empleadosCoberturaDisponibles.filter(
        (item) => item.id !== empleado?.id,
    );
    const closeAndReset = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setSelectedEmployeeId(initialEmployeeId);
            setSelectedCoverIds([]);
            setSelectedTipoPermisoId('');
        }
    };

    const toggleCover = (employeeId: number, checked: boolean) => {
        setSelectedCoverIds((current) =>
            checked
                ? [...current, employeeId]
                : current.filter((id) => id !== employeeId),
        );
    };

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={closeAndReset}
            title="Solicitar permiso laboral"
            description="Registra el periodo y la información para revisión del administrador."
            formId="solicitud-permiso-laboral-form"
            form={form}
            submitLabel="Enviar solicitud"
            resetOnSuccess
            className="sm:max-w-2xl"
        >
            {(errors) => {
                const coverageError =
                    errors.empleados_cubre_ids ??
                    Object.entries(errors).find(([key]) =>
                        key.startsWith('empleados_cubre_ids.'),
                    )?.[1];

                return (
                    <div className="grid gap-5">
                        <InputError message={errors.solicitud} />
                        {canCreateForOthers ? (
                            <div className="grid gap-2">
                                <Label htmlFor="permiso-laboral-empleado">
                                    Empleado
                                </Label>
                                <Select
                                    name="empleado_id"
                                    value={selectedEmployeeId}
                                    onValueChange={setSelectedEmployeeId}
                                    required
                                >
                                    <SelectTrigger
                                        id="permiso-laboral-empleado"
                                        aria-invalid={Boolean(
                                            errors.empleado_id,
                                        )}
                                        aria-describedby={
                                            errors.empleado_id
                                                ? 'permiso-laboral-empleado-error'
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Selecciona un empleado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {empleados.map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={String(item.id)}
                                            >
                                                {item.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="permiso-laboral-empleado-error"
                                    message={errors.empleado_id}
                                />
                            </div>
                        ) : (
                            <div className="grid gap-1 rounded-lg border border-border bg-muted/30 p-3">
                                <span className="text-sm font-medium">
                                    Empleado
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {empleado?.nombre ??
                                        'No hay un expediente vinculado a tu cuenta'}
                                </span>
                                {!empleado ? (
                                    <InputError message={errors.empleado_id} />
                                ) : null}
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="permiso-laboral-tipo">
                                Tipo de permiso
                            </Label>
                            <Select
                                name="tipo_permiso_id"
                                value={selectedTipoPermisoId}
                                onValueChange={setSelectedTipoPermisoId}
                                required
                            >
                                <SelectTrigger
                                    id="permiso-laboral-tipo"
                                    aria-invalid={Boolean(
                                        errors.tipo_permiso_id,
                                    )}
                                    aria-describedby={
                                        errors.tipo_permiso_id
                                            ? 'permiso-laboral-tipo-error'
                                            : undefined
                                    }
                                >
                                    <SelectValue placeholder="Selecciona un tipo de permiso" />
                                </SelectTrigger>
                                <SelectContent>
                                    {tiposPermiso.map((tipoPermiso) => (
                                        <SelectItem
                                            key={tipoPermiso.id}
                                            value={String(tipoPermiso.id)}
                                        >
                                            {tipoPermiso.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                id="permiso-laboral-tipo-error"
                                message={errors.tipo_permiso_id}
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="permiso-laboral-fecha-inicio">
                                    Fecha inicial
                                </Label>
                                <Input
                                    id="permiso-laboral-fecha-inicio"
                                    type="date"
                                    name="fecha_inicio"
                                    required
                                    aria-invalid={Boolean(errors.fecha_inicio)}
                                    aria-describedby={
                                        errors.fecha_inicio
                                            ? 'permiso-laboral-fecha-inicio-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="permiso-laboral-fecha-inicio-error"
                                    message={errors.fecha_inicio}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="permiso-laboral-fecha-fin">
                                    Fecha final
                                </Label>
                                <Input
                                    id="permiso-laboral-fecha-fin"
                                    type="date"
                                    name="fecha_fin"
                                    required
                                    aria-invalid={Boolean(errors.fecha_fin)}
                                    aria-describedby={
                                        errors.fecha_fin
                                            ? 'permiso-laboral-fecha-fin-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="permiso-laboral-fecha-fin-error"
                                    message={errors.fecha_fin}
                                />
                            </div>
                        </div>

                        <fieldset className="grid gap-3">
                            <legend className="flex items-center gap-2 text-sm font-medium">
                                Empleados que cubrirán el turno
                                <Badge variant="outline">Opcional</Badge>
                            </legend>
                            <p className="text-sm text-muted-foreground">
                                La cobertura solo se guarda como referencia en
                                la solicitud.
                            </p>
                            {empleadosCobertura.length > 0 ? (
                                <div className="grid max-h-48 gap-2 overflow-y-auto rounded-lg border border-border p-3 sm:grid-cols-2">
                                    {empleadosCobertura.map((item) => {
                                        const checked =
                                            selectedCoverIds.includes(item.id);
                                        const id = `permiso-laboral-cobertura-${item.id}`;

                                        return (
                                            <div
                                                key={item.id}
                                                className="flex items-start gap-2"
                                            >
                                                <Checkbox
                                                    id={id}
                                                    checked={checked}
                                                    onCheckedChange={(value) =>
                                                        toggleCover(
                                                            item.id,
                                                            value === true,
                                                        )
                                                    }
                                                    aria-invalid={Boolean(
                                                        coverageError,
                                                    )}
                                                    aria-describedby={
                                                        coverageError
                                                            ? 'permiso-laboral-cobertura-error'
                                                            : undefined
                                                    }
                                                />
                                                <Label
                                                    htmlFor={id}
                                                    className="cursor-pointer font-normal"
                                                >
                                                    {item.nombre}
                                                </Label>
                                                {checked ? (
                                                    <input
                                                        type="hidden"
                                                        name="empleados_cubre_ids[]"
                                                        value={item.id}
                                                    />
                                                ) : null}
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                                    No hay otros empleados disponibles para
                                    registrar como cobertura.
                                </p>
                            )}
                            <InputError
                                id="permiso-laboral-cobertura-error"
                                message={coverageError}
                            />
                        </fieldset>

                        <div className="grid gap-2">
                            <Label htmlFor="permiso-laboral-comentarios">
                                Comentarios (opcional)
                            </Label>
                            <Textarea
                                id="permiso-laboral-comentarios"
                                name="comentarios"
                                maxLength={2000}
                                placeholder="Agrega información para el administrador."
                                aria-invalid={Boolean(errors.comentarios)}
                                aria-describedby={
                                    errors.comentarios
                                        ? 'permiso-laboral-comentarios-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="permiso-laboral-comentarios-error"
                                message={errors.comentarios}
                            />
                        </div>
                    </div>
                );
            }}
        </ResourceFormDialog>
    );
}
