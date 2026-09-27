import { useState } from 'react';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
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
import type { IncapacidadEmpleadoOption } from '@/types';

type IncapacidadFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    form: { action: string; method: 'post' };
    empleados: IncapacidadEmpleadoOption[];
    selfEmployeeId: number | null;
    canCreateForOthers: boolean;
};

export function IncapacidadFormDialog({
    open,
    onOpenChange,
    form,
    empleados,
    selfEmployeeId,
    canCreateForOthers,
}: IncapacidadFormDialogProps) {
    const initialEmployeeId = selfEmployeeId ? String(selfEmployeeId) : '';
    const [selectedEmployeeId, setSelectedEmployeeId] =
        useState(initialEmployeeId);
    const empleado = empleados.find(
        (item) => item.id === Number(selectedEmployeeId),
    );

    const closeAndReset = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setSelectedEmployeeId(initialEmployeeId);
        }
    };

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={closeAndReset}
            title="Solicitar incapacidad"
            description="Registra el periodo de incapacidad, el motivo y, si lo tienes, adjunta un justificante."
            formId="solicitud-incapacidad-form"
            form={form}
            submitLabel="Enviar solicitud"
            resetOnSuccess
        >
            {(errors) => (
                <div className="grid gap-5">
                    <InputError message={errors.solicitud} />

                    {canCreateForOthers ? (
                        <div className="grid gap-2">
                            <Label htmlFor="incapacidad-empleado">
                                Empleado
                            </Label>
                            <Select
                                name="empleado_id"
                                value={selectedEmployeeId}
                                onValueChange={setSelectedEmployeeId}
                                required
                            >
                                <SelectTrigger
                                    id="incapacidad-empleado"
                                    aria-invalid={Boolean(errors.empleado_id)}
                                    aria-describedby={
                                        errors.empleado_id
                                            ? 'incapacidad-empleado-error'
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
                                id="incapacidad-empleado-error"
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

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="incapacidad-fecha-inicio">
                                Fecha de inicio
                            </Label>
                            <Input
                                id="incapacidad-fecha-inicio"
                                type="date"
                                name="fecha_inicio"
                                required
                                aria-invalid={Boolean(errors.fecha_inicio)}
                                aria-describedby={
                                    errors.fecha_inicio
                                        ? 'incapacidad-fecha-inicio-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="incapacidad-fecha-inicio-error"
                                message={errors.fecha_inicio}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="incapacidad-fecha-fin">
                                Fecha de fin
                            </Label>
                            <Input
                                id="incapacidad-fecha-fin"
                                type="date"
                                name="fecha_fin"
                                required
                                aria-invalid={Boolean(errors.fecha_fin)}
                                aria-describedby={
                                    errors.fecha_fin
                                        ? 'incapacidad-fecha-fin-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="incapacidad-fecha-fin-error"
                                message={errors.fecha_fin}
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="incapacidad-motivo">Motivo</Label>
                        <Textarea
                            id="incapacidad-motivo"
                            name="motivo"
                            required
                            minLength={3}
                            maxLength={2000}
                            rows={4}
                            aria-invalid={Boolean(errors.motivo)}
                            aria-describedby={
                                errors.motivo
                                    ? 'incapacidad-motivo-error'
                                    : undefined
                            }
                            placeholder="Describe el motivo de la incapacidad."
                        />
                        <InputError
                            id="incapacidad-motivo-error"
                            message={errors.motivo}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="incapacidad-archivo">
                            Justificante (opcional)
                        </Label>
                        <Input
                            id="incapacidad-archivo"
                            type="file"
                            name="archivo"
                            accept="image/*,.doc,.docx,.pdf"
                            aria-invalid={Boolean(errors.archivo)}
                            aria-describedby="incapacidad-archivo-ayuda incapacidad-archivo-error"
                        />
                        <p
                            id="incapacidad-archivo-ayuda"
                            className="text-sm text-muted-foreground"
                        >
                            Imágenes, documentos Word o PDF; máximo 10 MB. Las
                            imágenes se comprimen al guardar.
                        </p>
                        <InputError
                            id="incapacidad-archivo-error"
                            message={errors.archivo}
                        />
                    </div>
                </div>
            )}
        </ResourceFormDialog>
    );
}
