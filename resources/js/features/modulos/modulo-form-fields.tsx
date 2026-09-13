import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PlatformModule } from '@/types';

export function ModuloFormFields({
    module,
    errors,
}: {
    module: PlatformModule | null;
    errors: Record<string, string>;
}) {
    return (
        <div className="grid gap-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="modulo-clave">Clave técnica</Label>
                    <Input
                        id="modulo-clave"
                        name="clave"
                        defaultValue={module?.clave}
                        placeholder="recursos-humanos"
                        maxLength={80}
                        required
                        autoFocus
                    />
                    <p className="text-xs text-muted-foreground">
                        Identificador estable usado por middleware y rutas.
                    </p>
                    <InputError message={errors.clave} />
                </div>

                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="modulo-nombre">Nombre visible</Label>
                    <Input
                        id="modulo-nombre"
                        name="nombre"
                        defaultValue={module?.nombre}
                        maxLength={120}
                        required
                    />
                    <InputError message={errors.nombre} />
                </div>

                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="modulo-descripcion">Descripción</Label>
                    <Textarea
                        id="modulo-descripcion"
                        name="descripcion"
                        defaultValue={module?.descripcion ?? ''}
                        maxLength={255}
                        rows={3}
                    />
                    <InputError message={errors.descripcion} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="modulo-orden">Orden</Label>
                    <Input
                        id="modulo-orden"
                        name="orden"
                        type="number"
                        min="0"
                        max="65535"
                        step="1"
                        defaultValue={module?.orden ?? 0}
                        required
                    />
                    <InputError message={errors.orden} />
                </div>

                <div className="flex items-center gap-3 self-end rounded-lg border p-4">
                    <input type="hidden" name="activo" value="0" />
                    <Checkbox
                        id="modulo-activo"
                        name="activo"
                        value="1"
                        defaultChecked={module?.activo ?? true}
                    />
                    <Label htmlFor="modulo-activo">Módulo activo</Label>
                    <InputError message={errors.activo} />
                </div>
            </div>
        </div>
    );
}
