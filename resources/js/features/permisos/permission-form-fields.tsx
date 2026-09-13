import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Modulo, PermissionScope, PlatformPermission } from '@/types';

type SelectableModule = Modulo & { activo: boolean };

export function PermissionFormFields({
    permission,
    modules,
    errors,
}: {
    permission: PlatformPermission | null;
    modules: SelectableModule[];
    errors: Record<string, string>;
}) {
    const [scope, setScope] = useState<PermissionScope>(
        permission?.alcance ?? 'EMPRESA',
    );

    return (
        <div className="grid gap-5">
            <div className="grid gap-2">
                <Label htmlFor="permission-name">Nombre técnico</Label>
                <Input
                    id="permission-name"
                    name="name"
                    defaultValue={permission?.name}
                    placeholder="facturas.exportar"
                    maxLength={125}
                    required
                    autoFocus
                />
                <p className="text-xs text-muted-foreground">
                    Usa formato recurso.accion. Código debe validar mismo
                    nombre.
                </p>
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="permission-alcance">Alcance</Label>
                    <Select
                        name="alcance"
                        value={scope}
                        onValueChange={(value) =>
                            setScope(value as PermissionScope)
                        }
                    >
                        <SelectTrigger
                            id="permission-alcance"
                            className="w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="EMPRESA">Empresa</SelectItem>
                            <SelectItem value="PLATAFORMA">
                                Plataforma
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.alcance} />
                </div>

                {scope === 'EMPRESA' ? (
                    <div className="grid gap-2">
                        <Label htmlFor="permission-modulo">Módulo</Label>
                        <Select
                            name="modulo_id"
                            defaultValue={
                                permission?.modulo_id
                                    ? String(permission.modulo_id)
                                    : undefined
                            }
                        >
                            <SelectTrigger
                                id="permission-modulo"
                                className="w-full"
                            >
                                <SelectValue placeholder="Selecciona módulo" />
                            </SelectTrigger>
                            <SelectContent>
                                {modules.map((module) => (
                                    <SelectItem
                                        key={module.id}
                                        value={String(module.id)}
                                    >
                                        {module.nombre}
                                        {!module.activo ? ' (inactivo)' : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.modulo_id} />
                    </div>
                ) : (
                    <input type="hidden" name="modulo_id" value="" />
                )}
            </div>
        </div>
    );
}
