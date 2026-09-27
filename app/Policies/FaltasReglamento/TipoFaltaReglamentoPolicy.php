<?php

namespace App\Policies\FaltasReglamento;

use App\Models\FaltasReglamento\TipoFaltaReglamento;
use App\Models\User;

class TipoFaltaReglamentoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('tipos_falta_reglamento.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TipoFaltaReglamento $tipoFaltaReglamento): bool
    {
        return $this->belongsToActiveCompany($tipoFaltaReglamento) && $user->can('tipos_falta_reglamento.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('tipos_falta_reglamento.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TipoFaltaReglamento $tipoFaltaReglamento): bool
    {
        return $this->belongsToActiveCompany($tipoFaltaReglamento) && $user->can('tipos_falta_reglamento.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TipoFaltaReglamento $tipoFaltaReglamento): bool
    {
        return $this->belongsToActiveCompany($tipoFaltaReglamento) && $user->can('tipos_falta_reglamento.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TipoFaltaReglamento $tipoFaltaReglamento): bool
    {
        return $this->belongsToActiveCompany($tipoFaltaReglamento) && $user->can('tipos_falta_reglamento.update');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TipoFaltaReglamento $tipoFaltaReglamento): bool
    {
        return false;
    }

    private function belongsToActiveCompany(?TipoFaltaReglamento $tipoFaltaReglamento = null): bool
    {
        $empresaId = getPermissionsTeamId();

        return is_numeric($empresaId)
            && (int) $empresaId > 0
            && ($tipoFaltaReglamento === null || $tipoFaltaReglamento->empresa_id === (int) $empresaId);
    }
}
