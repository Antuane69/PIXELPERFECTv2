<?php

namespace App\Services\Empresas;

use App\EstadoMembresiaEmpresa;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ObtenerAdministradoresEmpresa
{
    /** @return Collection<int, User> */
    public function para(Empresa $empresa): Collection
    {
        $previousTeamId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($empresa->id);

            return User::query()
                ->select(['users.id', 'users.name', 'users.email'])
                ->whereHas('membresiasEmpresa', fn ($query) => $query
                    ->where('empresa_id', $empresa->id)
                    ->where('estado', EstadoMembresiaEmpresa::Activa->value))
                ->whereHas('roles', fn ($query) => $query
                    ->where('roles.empresa_id', $empresa->id)
                    ->where('roles.name', 'Administrador')
                    ->where('roles.guard_name', 'web'))
                ->whereNotNull('email')
                ->orderBy('users.id')
                ->get();
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
