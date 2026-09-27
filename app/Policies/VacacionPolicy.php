<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vacacion;

class VacacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveCompany() && (
            $user->can('vacaciones.view') || $user->can('vacaciones.review')
        );
    }

    public function view(User $user, Vacacion $vacacion): bool
    {
        if (! $this->belongsToActiveCompany($vacacion)) {
            return false;
        }

        if ($user->can('vacaciones.review')) {
            return true;
        }

        return $user->can('vacaciones.view') && (
            $vacacion->solicitante_user_id === $user->id
            || $vacacion->empleado?->correo === $user->email
        );
    }

    public function create(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('vacaciones.create');
    }

    public function createForOthers(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('vacaciones.create_for_others');
    }

    public function review(User $user, Vacacion $vacacion): bool
    {
        return $this->belongsToActiveCompany($vacacion)
            && $vacacion->estado->value === 'PENDIENTE'
            && $user->can('vacaciones.review');
    }

    public function update(User $user, Vacacion $vacacion): bool
    {
        return false;
    }

    public function delete(User $user, Vacacion $vacacion): bool
    {
        return false;
    }

    public function restore(User $user, Vacacion $vacacion): bool
    {
        return false;
    }

    private function belongsToActiveCompany(?Vacacion $vacacion = null): bool
    {
        $empresaId = getPermissionsTeamId();

        return is_numeric($empresaId)
            && (int) $empresaId > 0
            && ($vacacion === null || $vacacion->empresa_id === (int) $empresaId);
    }
}
