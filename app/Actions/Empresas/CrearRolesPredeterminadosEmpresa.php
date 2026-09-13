<?php

namespace App\Actions\Empresas;

use App\AlcancePermiso;
use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;

class CrearRolesPredeterminadosEmpresa
{
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
                    ->where('alcance', AlcancePermiso::Empresa)
                    ->whereHas('modulo.empresas', fn ($query) => $query
                        ->where('empresas.id', $empresa->id)
                        ->where('empresa_modulo.habilitado', true))
                    ->get(),
            );

            return $role;
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
