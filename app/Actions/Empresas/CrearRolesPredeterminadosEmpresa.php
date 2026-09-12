<?php

namespace App\Actions\Empresas;

use App\Models\Empresa;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

class CrearRolesPredeterminadosEmpresa
{
    private const PERMISOS_EXCLUSIVOS_PLATAFORMA = [
        'logs.view',
        'logs.delete',
        'tipos_documento.view',
        'tipos_documento.create',
        'tipos_documento.update',
        'tipos_documento.delete',
    ];

    public function handle(Empresa $empresa): Role
    {
        $previousTeamId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($empresa->id);

            $role = Role::query()->firstOrCreate([
                'empresa_id' => $empresa->id,
                'name' => 'Administrador',
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'web')
                    ->whereNotIn('name', self::PERMISOS_EXCLUSIVOS_PLATAFORMA)
                    ->get(),
            );

            return $role;
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
