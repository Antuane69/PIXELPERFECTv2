import { useState } from 'react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/FaltasReglamento/FaltaReglamentoCatalogoController';
import InputError from '@/components/input-error';
import { ResourceFormDialog } from '@/components/resource-form-dialog';
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
import type {
    FaltaReglamentoCatalogo,
    TipoFaltaReglamentoOption,
} from '@/types';

type FaltaCatalogoFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    faltaCatalogo: FaltaReglamentoCatalogo | null;
    tiposFalta: TipoFaltaReglamentoOption[];
};

export function FaltaCatalogoFormDialog({
    open,
    onOpenChange,
    faltaCatalogo,
    tiposFalta,
}: FaltaCatalogoFormDialogProps) {
    const [tipoId, setTipoId] = useState(
        faltaCatalogo ? String(faltaCatalogo.tipoFaltaReglamentoId) : '',
    );

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                faltaCatalogo
                    ? 'Editar falta del catálogo'
                    : 'Nueva falta del catálogo'
            }
            description="Relaciona esta falta con el tipo que corresponde."
            formId="falta-reglamento-catalogo-form"
            form={faltaCatalogo ? update.form(faltaCatalogo.id) : store.form()}
            resetOnSuccess={!faltaCatalogo}
        >
            {(errors) => (
                <div className="grid gap-5">
                    {faltaCatalogo ? (
                        <div className="grid gap-1 rounded-lg border border-border bg-muted/30 p-3">
                            <span className="text-sm font-medium">
                                Tipo de falta
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {faltaCatalogo.tipoFalta}
                            </span>
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor="falta-reglamento-tipo">
                                Tipo de falta
                            </Label>
                            <Select
                                name="tipo_falta_reglamento_id"
                                value={tipoId}
                                onValueChange={setTipoId}
                                required
                            >
                                <SelectTrigger
                                    id="falta-reglamento-tipo"
                                    aria-invalid={Boolean(
                                        errors.tipo_falta_reglamento_id,
                                    )}
                                    aria-describedby="falta-reglamento-tipo-error"
                                >
                                    <SelectValue placeholder="Selecciona un tipo" />
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
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="falta-reglamento-nombre">Nombre</Label>
                        <Input
                            id="falta-reglamento-nombre"
                            name="nombre"
                            defaultValue={faltaCatalogo?.nombre}
                            maxLength={180}
                            required
                            autoFocus
                            aria-invalid={Boolean(errors.nombre)}
                            aria-describedby="falta-reglamento-nombre-error"
                        />
                        <InputError
                            id="falta-reglamento-nombre-error"
                            message={errors.nombre}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="falta-reglamento-descripcion">
                            Descripción (opcional)
                        </Label>
                        <Textarea
                            id="falta-reglamento-descripcion"
                            name="descripcion"
                            defaultValue={faltaCatalogo?.descripcion ?? ''}
                            maxLength={2000}
                            rows={4}
                            aria-invalid={Boolean(errors.descripcion)}
                            aria-describedby="falta-reglamento-descripcion-error"
                        />
                        <InputError
                            id="falta-reglamento-descripcion-error"
                            message={errors.descripcion}
                        />
                    </div>
                    <label className="flex items-center gap-3 rounded-lg border border-border p-4">
                        <input type="hidden" name="activo" value="0" />
                        <Checkbox
                            id="falta-reglamento-activo"
                            name="activo"
                            value="1"
                            defaultChecked={faltaCatalogo?.activo ?? true}
                        />
                        <span className="grid gap-0.5">
                            <span className="text-sm font-medium">Activa</span>
                            <span className="text-xs text-muted-foreground">
                                Solo las faltas activas aparecen en los reportes
                                nuevos.
                            </span>
                        </span>
                    </label>
                    <InputError message={errors.activo} />
                </div>
            )}
        </ResourceFormDialog>
    );
}
