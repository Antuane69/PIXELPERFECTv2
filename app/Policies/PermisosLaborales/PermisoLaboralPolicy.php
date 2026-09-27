<?php

namespace App\Policies\PermisosLaborales;

use App\Enums\PermisosLaborales\EstadoPermisoLaboral;
use App\Models\PermisosLaborales\PermisoLaboral;
use App\Models\User;

class PermisoLaboralPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveCompany() && (
            $user->can('permisos_laborales.view') || $user->can('permisos_laborales.review')
        );
    }

    public function view(User $user, PermisoLaboral $permisoLaboral): bool
    {
        if (! $this->belongsToActiveCompany($permisoLaboral)) {
            return false;
        }

        if ($user->can('permisos_laborales.review')) {
            return true;
        }

        return $user->can('permisos_laborales.view') && (
            $permisoLaboral->solicitante_user_id === $user->id
            || $permisoLaboral->empleado?->user_id === $user->id
        );
    }

    public function create(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('permisos_laborales.create');
    }

    public function createForOthers(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('permisos_laborales.create_for_others');
    }

    public function review(User $user, PermisoLaboral $permisoLaboral): bool
    {
        return $this->belongsToActiveCompany($permisoLaboral)
            && $permisoLaboral->estado === EstadoPermisoLaboral::Pendiente
            && $permisoLaboral->solicitante_user_id !== $user->id
            && $user->can('permisos_laborales.review');
    }

    public function update(User $user, PermisoLaboral $permisoLaboral): bool
    {
        return false;
    }

    public function delete(User $user, PermisoLaboral $permisoLaboral): bool
    {
        return false;
    }

    public function restore(User $user, PermisoLaboral $permisoLaboral): bool
    {
        return false;
    }

    private function belongsToActiveCompany(?PermisoLaboral $permisoLaboral = null): bool
    {
        $empresaId = getPermissionsTeamId();

        return is_numeric($empresaId)
            && (int) $empresaId > 0
            && ($permisoLaboral === null || $permisoLaboral->empresa_id === (int) $empresaId);
    }
}
