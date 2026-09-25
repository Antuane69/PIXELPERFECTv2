<?php

namespace App\Policies;

use App\Models\EmpleadoCarpeta;
use App\Models\User;

class EmpleadoCarpetaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('empleados_carpetas.view');
    }

    public function view(User $user, EmpleadoCarpeta $carpeta): bool
    {
        return $user->can('empleados_carpetas.view')
            && $this->belongsToActiveCompany($carpeta)
            && $this->canAccess($user, $carpeta);
    }

    public function create(User $user): bool
    {
        return $user->can('empleados_carpetas.create');
    }

    public function update(User $user, EmpleadoCarpeta $carpeta): bool
    {
        return $user->can('empleados_carpetas.update')
            && $this->belongsToActiveCompany($carpeta)
            && $carpeta->creado_por_id === $user->id;
    }

    public function delete(User $user, EmpleadoCarpeta $carpeta): bool
    {
        return $user->can('empleados_carpetas.delete')
            && $this->belongsToActiveCompany($carpeta)
            && $carpeta->creado_por_id === $user->id;
    }

    public function restore(User $user, EmpleadoCarpeta $carpeta): bool
    {
        return $user->can('empleados_carpetas.update')
            && $this->belongsToActiveCompany($carpeta)
            && $carpeta->creado_por_id === $user->id;
    }

    public function forceDelete(User $user, EmpleadoCarpeta $carpeta): bool
    {
        return false;
    }

    private function belongsToActiveCompany(EmpleadoCarpeta $carpeta): bool
    {
        return (int) getPermissionsTeamId() === $carpeta->empresa_id;
    }

    private function canAccess(User $user, EmpleadoCarpeta $carpeta): bool
    {
        return $carpeta->creado_por_id === $user->id
            || $carpeta->usuariosConAcceso()->whereKey($user->id)->exists();
    }
}
