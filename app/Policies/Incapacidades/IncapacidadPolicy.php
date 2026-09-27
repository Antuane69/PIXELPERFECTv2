<?php

namespace App\Policies\Incapacidades;

use App\Enums\Incapacidades\EstadoIncapacidad;
use App\Models\Incapacidad;
use App\Models\User;

class IncapacidadPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveCompany() && (
            $user->can('incapacidades.view') || $user->can('incapacidades.review')
        );
    }

    public function view(User $user, Incapacidad $incapacidad): bool
    {
        if (! $this->belongsToActiveCompany($incapacidad)) {
            return false;
        }

        if ($user->can('incapacidades.review')) {
            return true;
        }

        return $user->can('incapacidades.view') && (
            $incapacidad->solicitante_user_id === $user->id
            || $incapacidad->empleado?->user_id === $user->id
        );
    }

    public function create(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('incapacidades.create');
    }

    public function createForOthers(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('incapacidades.create_for_others');
    }

    public function review(User $user, Incapacidad $incapacidad): bool
    {
        return $this->belongsToActiveCompany($incapacidad)
            && $incapacidad->estado === EstadoIncapacidad::Pendiente
            && $incapacidad->solicitante_user_id !== $user->id
            && $user->can('incapacidades.review');
    }

    public function update(User $user, Incapacidad $incapacidad): bool
    {
        return false;
    }

    public function delete(User $user, Incapacidad $incapacidad): bool
    {
        return false;
    }

    public function restore(User $user, Incapacidad $incapacidad): bool
    {
        return false;
    }

    private function belongsToActiveCompany(?Incapacidad $incapacidad = null): bool
    {
        $empresaId = getPermissionsTeamId();

        return is_numeric($empresaId)
            && (int) $empresaId > 0
            && ($incapacidad === null || $incapacidad->empresa_id === (int) $empresaId);
    }
}
