<?php

namespace App\Policies\FaltasReglamento;

use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\User;

class FaltaReglamentoCatalogoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('faltas_reglamento_catalogo.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FaltaReglamentoCatalogo $faltaReglamentoCatalogo): bool
    {
        return $this->belongsToActiveCompany($faltaReglamentoCatalogo) && $user->can('faltas_reglamento_catalogo.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('faltas_reglamento_catalogo.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FaltaReglamentoCatalogo $faltaReglamentoCatalogo): bool
    {
        return $this->belongsToActiveCompany($faltaReglamentoCatalogo) && $user->can('faltas_reglamento_catalogo.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FaltaReglamentoCatalogo $faltaReglamentoCatalogo): bool
    {
        return $this->belongsToActiveCompany($faltaReglamentoCatalogo) && $user->can('faltas_reglamento_catalogo.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FaltaReglamentoCatalogo $faltaReglamentoCatalogo): bool
    {
        return $this->belongsToActiveCompany($faltaReglamentoCatalogo) && $user->can('faltas_reglamento_catalogo.update');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FaltaReglamentoCatalogo $faltaReglamentoCatalogo): bool
    {
        return false;
    }

    private function belongsToActiveCompany(?FaltaReglamentoCatalogo $catalogo = null): bool
    {
        $empresaId = getPermissionsTeamId();

        return is_numeric($empresaId)
            && (int) $empresaId > 0
            && ($catalogo === null || $catalogo->empresa_id === (int) $empresaId);
    }
}
