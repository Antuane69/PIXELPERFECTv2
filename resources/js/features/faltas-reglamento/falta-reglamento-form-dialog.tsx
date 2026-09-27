import { useState } from 'react';
import { FileAttachments } from '@/components/forms/file-attachments';
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
import type {
    FaltaReglamentoCatalogoOption,
    FaltaReglamentoEmpleadoOption,
    TipoFaltaReglamentoOption,
} from '@/types';

type FaltaReglamentoFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    form: { action: string; method: 'post' };
    empleados: FaltaReglamentoEmpleadoOption[];
    tiposFalta: TipoFaltaReglamentoOption[];
    faltasCatalogo: FaltaReglamentoCatalogoOption[];
    selfEmployeeId: number | null;
    canCreateForOthers: boolean;
};

export function FaltaReglamentoFormDialog({
    open,
    onOpenChange,
    form,
    empleados,
    tiposFalta,
    faltasCatalogo,
    selfEmployeeId,
    canCreateForOthers,
}: FaltaReglamentoFormDialogProps) {
    const initialEmployeeId = selfEmployeeId ? String(selfEmployeeId) : '';
    const [selectedEmployeeId, setSelectedEmployeeId] =
        useState(initialEmployeeId);
    const [selectedTipoId, setSelectedTipoId] = useState('');
    const [selectedFaltaId, setSelectedFaltaId] = useState('');
    const selectedEmployee = empleados.find(
        (empleado) => empleado.id === Number(selectedEmployeeId),
    );
    const selectedFaltas = faltasCatalogo.filter(
        (falta) => falta.tipoFaltaReglamentoId === Number(selectedTipoId),
    );

    const closeAndReset = (nextOpen: boolean): void => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setSelectedEmployeeId(initialEmployeeId);
            setSelectedTipoId('');
            setSelectedFaltaId('');
        }
    };

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={closeAndReset}
            title="Reportar falta al reglamento"
            description="Registra lo ocurrido para que el administrador de la empresa pueda revisarlo."
            formId="falta-reglamento-form"
            form={form}
            submitLabel="Enviar a revisión"
            resetOnSuccess
            className="sm:max-w-2xl"
        >
            {(errors) => {
                const evidenceError =
                    Object.entries(errors).find(([field]) =>
                        field.startsWith('archivos.'),
                    )?.[1] ?? errors.archivos;

                return (
                    <div className="grid gap-5">
                        <InputError message={errors.faltaReglamento} />
                        {canCreateForOthers ? (
                            <div className="grid gap-2">
                                <Label htmlFor="falta-reglamento-empleado">
                                    Empleado
                                </Label>
                                <Select
                                    name="empleado_id"
                                    value={selectedEmployeeId}
                                    onValueChange={setSelectedEmployeeId}
                                    required
                                >
                                    <SelectTrigger
                                        id="falta-reglamento-empleado"
                                        aria-invalid={Boolean(
                                            errors.empleado_id,
                                        )}
                                        aria-describedby="falta-reglamento-empleado-error"
                                    >
                                        <SelectValue placeholder="Selecciona un empleado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {empleados.map((empleado) => (
                                            <SelectItem
                                                key={empleado.id}
                                                value={String(empleado.id)}
                                            >
                                                {empleado.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="falta-reglamento-empleado-error"
                                    message={errors.empleado_id}
                                />
                            </div>
                        ) : (
                            <div className="grid gap-1 rounded-lg border border-border bg-muted/30 p-3">
                                <span className="text-sm font-medium">
                                    Empleado
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {selectedEmployee?.nombre ??
                                        'No hay un expediente vinculado a tu cuenta'}
                                </span>
                                {!selectedEmployee ? (
                                    <InputError message={errors.empleado_id} />
                                ) : null}
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="falta-reglamento-tipo">
                                Tipo de falta
                            </Label>
                            <Select
                                name="tipo_falta_reglamento_id"
                                value={selectedTipoId}
                                onValueChange={(value) => {
                                    setSelectedTipoId(value);
                                    setSelectedFaltaId('');
                                }}
                                required
                            >
                                <SelectTrigger
                                    id="falta-reglamento-tipo"
                                    aria-invalid={Boolean(
                                        errors.tipo_falta_reglamento_id,
                                    )}
                                    aria-describedby="falta-reglamento-tipo-error"
                                >
                                    <SelectValue placeholder="Selecciona un tipo de falta" />
                                </SelectTrigger>
                                <SelectContent>
                                    {tiposFalta.map((tipo) => (
                                        <SelectItem
                                            key={tipo.id}
                                            value={String(tipo.id)}
                                        >
                                            {tipo.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                id="falta-reglamento-tipo-error"
                                message={errors.tipo_falta_reglamento_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="falta-reglamento-falta">
                                Falta cometida
                            </Label>
                            <Select
                                name="falta_reglamento_catalogo_id"
                                value={selectedFaltaId}
                                onValueChange={setSelectedFaltaId}
                                disabled={selectedFaltas.length === 0}
                                required
                            >
                                <SelectTrigger
                                    id="falta-reglamento-falta"
                                    aria-invalid={Boolean(
                                        errors.falta_reglamento_catalogo_id,
                                    )}
                                    aria-describedby="falta-reglamento-falta-error"
                                >
                                    <SelectValue placeholder="Selecciona una falta" />
                                </SelectTrigger>
                                <SelectContent>
                                    {selectedFaltas.map((falta) => (
                                        <SelectItem
                                            key={falta.id}
                                            value={String(falta.id)}
                                        >
                                            {falta.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                id="falta-reglamento-falta-error"
                                message={errors.falta_reglamento_catalogo_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="falta-reglamento-fecha">
                                Fecha de la falta
                            </Label>
                            <Input
                                id="falta-reglamento-fecha"
                                type="date"
                                name="fecha_ocurrencia"
                                required
                                aria-invalid={Boolean(errors.fecha_ocurrencia)}
                                aria-describedby="falta-reglamento-fecha-error"
                            />
                            <InputError
                                id="falta-reglamento-fecha-error"
                                message={errors.fecha_ocurrencia}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="falta-reglamento-comentarios">
                                Comentarios (opcional)
                            </Label>
                            <Textarea
                                id="falta-reglamento-comentarios"
                                name="comentarios"
                                maxLength={2000}
                                rows={4}
                                placeholder="Agrega contexto que ayude a revisar el reporte."
                                aria-invalid={Boolean(errors.comentarios)}
                                aria-describedby="falta-reglamento-comentarios-error"
                            />
                            <InputError
                                id="falta-reglamento-comentarios-error"
                                message={errors.comentarios}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="falta-reglamento-evidencias">
                                Evidencias (opcional)
                            </Label>
                            <FileAttachments
                                id="falta-reglamento-evidencias"
                                name="archivos[]"
                                accept=".pdf,.doc,.docx,image/*"
                                describedBy="falta-reglamento-evidencias-error"
                            />
                            <InputError
                                id="falta-reglamento-evidencias-error"
                                message={evidenceError}
                            />
                        </div>
                    </div>
                );
            }}
        </ResourceFormDialog>
    );
}
