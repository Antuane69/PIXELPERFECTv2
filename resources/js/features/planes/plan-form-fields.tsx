import { useState } from 'react';
import InputError from '@/components/input-error';
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
import type { Plan } from '@/types';
import { PlanIcon, planIconLabel } from './plan-icons';
import type { PlanIconName } from './plan-icons';

export function PlanFormFields({
    plan,
    iconos,
    errors,
}: {
    plan: Plan | null;
    iconos: PlanIconName[];
    errors: Record<string, string>;
}) {
    const [nombre, setNombre] = useState(plan?.nombre ?? '');
    const [color, setColor] = useState(plan?.color ?? '#7C3AED');
    const [icono, setIcono] = useState<PlanIconName>(
        plan?.icono ?? 'CrownOutlined',
    );

    return (
        <div className="grid gap-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="plan-nombre">Nombre</Label>
                    <Input
                        id="plan-nombre"
                        name="nombre"
                        value={nombre}
                        onChange={(event) => setNombre(event.target.value)}
                        maxLength={120}
                        required
                        autoFocus
                    />
                    <InputError message={errors.nombre} />
                </div>

                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="plan-precio">Precio mensual (MXN)</Label>
                    <Input
                        id="plan-precio"
                        name="precio_mensual"
                        type="number"
                        min="0"
                        max="9999999999.99"
                        step="0.01"
                        defaultValue={plan?.precio_mensual ?? ''}
                        required
                    />
                    <InputError message={errors.precio_mensual} />
                </div>

                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="plan-limite-usuarios">
                        Límite de usuarios
                    </Label>
                    <Input
                        id="plan-limite-usuarios"
                        name="limite_usuarios"
                        type="number"
                        min="1"
                        max="65535"
                        step="1"
                        placeholder="20 (pendiente de definir)"
                        defaultValue={plan?.limite_usuarios ?? ''}
                    />
                    <p className="text-xs text-muted-foreground">
                        Vacío significa sin límite configurado. Aún no se aplica
                        al alta de usuarios.
                    </p>
                    <InputError message={errors.limite_usuarios} />
                </div>

                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">
                        Color del plan
                    </legend>
                    <input type="hidden" name="color" value={color} />
                    <div className="flex h-9 items-center gap-3">
                        <input
                            type="color"
                            value={color}
                            onChange={(event) =>
                                setColor(event.target.value.toUpperCase())
                            }
                            aria-label="Seleccionar color del plan"
                            className="size-9 cursor-pointer rounded-md border border-input bg-transparent p-1"
                        />
                        <output className="font-mono text-sm">{color}</output>
                    </div>
                    <InputError message={errors.color} />
                </fieldset>

                <div className="grid gap-2">
                    <Label htmlFor="plan-icono">Icono</Label>
                    <Select
                        name="icono"
                        value={icono}
                        onValueChange={(value) =>
                            setIcono(value as PlanIconName)
                        }
                    >
                        <SelectTrigger id="plan-icono" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {iconos.map((name) => (
                                <SelectItem key={name} value={name}>
                                    <span className="flex items-center gap-2">
                                        <PlanIcon name={name} />
                                        {planIconLabel(name)}
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.icono} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="plan-gracia">Días de gracia</Label>
                    <Input
                        id="plan-gracia"
                        name="periodo_gracia_dias"
                        type="number"
                        min="0"
                        max="90"
                        step="1"
                        defaultValue={plan?.periodo_gracia_dias ?? 15}
                        required
                    />
                    <p className="text-xs text-muted-foreground">
                        Ventana para contactar, renovar o exportar información.
                    </p>
                    <InputError message={errors.periodo_gracia_dias} />
                </div>

                <div className="grid gap-2">
                    <span className="text-sm font-medium">Vista previa</span>
                    <div className="flex items-center">
                        <span
                            className="inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold shadow-sm"
                            style={{
                                backgroundColor: color,
                                color: getPlanTagTextColor(color),
                            }}
                            aria-label={`Vista previa del plan ${nombre.trim() || 'sin nombre'}`}
                        >
                            <PlanIcon name={icono} className="text-base" />
                            <span className="truncate">
                                {nombre.trim() || 'Nombre del plan'}
                            </span>
                        </span>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Así se mostrará el identificador visual del plan.
                    </p>
                </div>

                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="plan-modulos">Módulos incluidos</Label>
                    <Textarea
                        id="plan-modulos"
                        name="modulos_incluidos"
                        rows={6}
                        maxLength={4000}
                        placeholder={
                            'Usuarios y roles\nPuestos y salarios\nEmpleados\nReportes'
                        }
                        defaultValue={plan?.modulos_incluidos ?? ''}
                    />
                    <p className="text-xs text-muted-foreground">
                        Texto descriptivo configurable. No modifica permisos ni
                        módulos técnicos todavía.
                    </p>
                    <InputError message={errors.modulos_incluidos} />
                </div>
            </div>

            <div className="flex items-center gap-3 rounded-lg border p-4">
                <input type="hidden" name="activo" value="0" />
                <Checkbox
                    id="plan-activo"
                    name="activo"
                    value="1"
                    defaultChecked={plan?.activo ?? true}
                />
                <Label htmlFor="plan-activo">Plan activo</Label>
            </div>
            <InputError message={errors.activo} />
        </div>
    );
}

function getPlanTagTextColor(color: string): string {
    const hex = color.replace('#', '');
    const normalizedHex =
        hex.length === 3
            ? hex
                  .split('')
                  .map((character) => `${character}${character}`)
                  .join('')
            : hex;

    if (!/^[0-9A-Fa-f]{6}$/.test(normalizedHex)) {
        return '#FFFFFF';
    }

    const red = Number.parseInt(normalizedHex.slice(0, 2), 16);
    const green = Number.parseInt(normalizedHex.slice(2, 4), 16);
    const blue = Number.parseInt(normalizedHex.slice(4, 6), 16);
    const luminance = (red * 299 + green * 587 + blue * 114) / 1000;

    return luminance > 155 ? '#241C2A' : '#FFFFFF';
}
