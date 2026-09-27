<?php

namespace App\Policies\PermisosLaborales;

use App\Models\PermisosLaborales\TipoPermiso;
use App\Models\User;

class TipoPermisoPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasActiveCompany() && $user->can('tipos_permisos.view');
    }

    public function view(User $user, TipoPermiso $tipoPermiso): bool
    {
        return $user->can('tipos_permisos.view') && $this->belongsToActiveCompany($tipoPermiso);
    }

    public function create(User $user): bool
    {
        return $this->hasActiveCompany() && $user->can('tipos_permisos.create');
    }

    public function update(User $user, TipoPermiso $tipoPermiso): bool
    {
        return $user->can('tipos_permisos.update') && $this->belongsToActiveCompany($tipoPermiso);
    }

    public function delete(User $user, TipoPermiso $tipoPermiso): bool
    {
        return $user->can('tipos_permisos.delete') && $this->belongsToActiveCompany($tipoPermiso);
    }

    public function restore(User $user, TipoPermiso $tipoPermiso): bool
    {
        return $user->can('tipos_permisos.update') && $this->belongsToActiveCompany($tipoPermiso);
    }

    public function forceDelete(User $user, TipoPermiso $tipoPermiso): bool
    {
        return false;
    }

    private function hasActiveCompany(): bool
    {
        return (int) getPermissionsTeamId() > 0;
    }

    private function belongsToActiveCompany(TipoPermiso $tipoPermiso): bool
    {
        return (int) getPermissionsTeamId() === $tipoPermiso->empresa_id;
    }
}
