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

export type VacacionEmpleadoOption = {
    id: number;
    nombre: string;
    diasVacaciones: number | null;
    ultimaVacacion: string | null;
};

type VacacionFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    form: { action: string; method: 'post' };
    empleados: VacacionEmpleadoOption[];
    selfEmployeeId: number | null;
    canCreateForOthers: boolean;
};

function formatDate(value: string | null): string {
    if (!value) {
        return 'Sin registro anterior';
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

export function VacacionFormDialog({
    open,
    onOpenChange,
    form,
    empleados,
    selfEmployeeId,
    canCreateForOthers,
}: VacacionFormDialogProps) {
    const initialEmployeeId = selfEmployeeId ? String(selfEmployeeId) : '';
    const [selectedEmployeeId, setSelectedEmployeeId] =
        useState(initialEmployeeId);
    const [selectedCoverIds, setSelectedCoverIds] = useState<number[]>([]);
    const empleado = empleados.find(
        (item) => item.id === Number(selectedEmployeeId),
    );
    const empleadosCobertura = empleados.filter(
        (item) => item.id !== empleado?.id,
    );
    const saldoInsuficiente =
        !empleado ||
        empleado.diasVacaciones === null ||
        empleado.diasVacaciones < 1;

    const closeAndReset = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setSelectedEmployeeId(initialEmployeeId);
            setSelectedCoverIds([]);
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
            title="Solicitar vacaciones"
            description="Revisa tu saldo y registra quién cubrirá el turno durante tu ausencia."
            formId="solicitud-vacaciones-form"
            form={form}
            submitLabel="Enviar solicitud"
            submitDisabled={saldoInsuficiente}
            resetOnSuccess
            className="sm:max-w-2xl"
        >
            {(errors) => (
                <div className="grid gap-5">
                    <InputError message={errors.solicitud} />
                    {canCreateForOthers ? (
                        <div className="grid gap-2">
                            <Label htmlFor="vacacion-empleado">Empleado</Label>
                            <Select
                                name="empleado_id"
                                value={selectedEmployeeId}
                                onValueChange={setSelectedEmployeeId}
                                required
                            >
                                <SelectTrigger
                                    id="vacacion-empleado"
                                    aria-invalid={Boolean(errors.empleado_id)}
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
                            <InputError message={errors.empleado_id} />
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

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="grid gap-1 rounded-lg border border-border bg-muted/30 p-3">
                            <span className="text-sm text-muted-foreground">
                                Saldo actual
                            </span>
                            <span className="text-lg font-semibold">
                                {empleado?.diasVacaciones ?? 'Sin configurar'}{' '}
                                {empleado?.diasVacaciones === null
                                    ? ''
                                    : 'días'}
                            </span>
                        </div>
                        <div className="grid gap-1 rounded-lg border border-border bg-muted/30 p-3">
                            <span className="text-sm text-muted-foreground">
                                Últimas vacaciones
                            </span>
                            <span className="text-sm font-medium">
                                {formatDate(empleado?.ultimaVacacion ?? null)}
                            </span>
                        </div>
                    </div>

                    {saldoInsuficiente ? (
                        <p
                            className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                            role="status"
                        >
                            {empleado?.diasVacaciones === 0
                                ? 'El empleado no tiene días disponibles.'
                                : 'Completa el saldo de vacaciones en el expediente del empleado antes de enviar la solicitud.'}
                        </p>
                    ) : null}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="vacacion-fecha-inicio">
                                Fecha inicial
                            </Label>
                            <Input
                                id="vacacion-fecha-inicio"
                                type="date"
                                name="fecha_inicio"
                                required
                                aria-invalid={Boolean(errors.fecha_inicio)}
                            />
                            <InputError message={errors.fecha_inicio} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="vacacion-fecha-fin">
                                Fecha final
                            </Label>
                            <Input
                                id="vacacion-fecha-fin"
                                type="date"
                                name="fecha_fin"
                                required
                                aria-invalid={Boolean(errors.fecha_fin)}
                            />
                            <InputError message={errors.fecha_fin} />
                        </div>
                    </div>

                    <fieldset className="grid gap-3">
                        <legend className="text-sm font-medium">
                            Empleados que cubrirán el turno{' '}
                            <Badge variant="outline">Opcional</Badge>
                        </legend>
                        <p className="text-sm text-muted-foreground">
                            Puedes indicar quién cubrirá el turno. Esta
                            información se guarda como referencia en la
                            solicitud.
                        </p>
                        {empleadosCobertura.length > 0 ? (
                            <div className="grid max-h-48 gap-2 overflow-y-auto rounded-lg border border-border p-3 sm:grid-cols-2">
                                {empleadosCobertura.map((item) => {
                                    const checked = selectedCoverIds.includes(
                                        item.id,
                                    );
                                    const id = `vacacion-cobertura-${item.id}`;

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
                                                    errors.empleados_cubre_ids,
                                                )}
                                                aria-describedby="vacacion-cobertura-error"
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
                                No hay otros empleados activos que puedan cubrir
                                el turno.
                            </p>
                        )}
                        <InputError
                            id="vacacion-cobertura-error"
                            message={errors.empleados_cubre_ids}
                        />
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="vacacion-comentarios">
                            Comentarios (opcional)
                        </Label>
                        <Textarea
                            id="vacacion-comentarios"
                            name="comentarios"
                            maxLength={2000}
                            placeholder="Agrega información para el administrador."
                            aria-invalid={Boolean(errors.comentarios)}
                        />
                        <InputError message={errors.comentarios} />
                    </div>
                </div>
            )}
        </ResourceFormDialog>
    );
}
