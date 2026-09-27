<?php

namespace App\Policies\FaltasReglamento;

use App\Enums\FaltasReglamento\EstadoFaltaReglamento;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\User;

class FaltaReglamentoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveCompany() && (
            $user->can('faltas_reglamento.view')
            || $user->can('faltas_reglamento.create')
            || $user->can('faltas_reglamento.review')
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FaltaReglamento $faltaReglamento): bool
    {
        if (! $this->belongsToActiveCompany($faltaReglamento)) {
            return false;
        }

        if ($user->can('faltas_reglamento.review')) {
            return true;
        }

        return ($user->can('faltas_reglamento.view') || $user->can('faltas_reglamento.create')) && (
            $faltaReglamento->solicitante_user_id === $user->id
            || $faltaReglamento->empleado?->user_id === $user->id
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('faltas_reglamento.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FaltaReglamento $faltaReglamento): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FaltaReglamento $faltaReglamento): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FaltaReglamento $faltaReglamento): bool
    {
        return false;
    }

    public function review(User $user, FaltaReglamento $faltaReglamento): bool
    {
        return $this->belongsToActiveCompany($faltaReglamento)
            && $faltaReglamento->estado === EstadoFaltaReglamento::Pendiente
            && $user->can('faltas_reglamento.review');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FaltaReglamento $faltaReglamento): bool
    {
        return false;
    }

    public function createForOthers(User $user): bool
    {
        return $this->belongsToActiveCompany() && $user->can('faltas_reglamento.create_for_others');
    }

    private function belongsToActiveCompany(?FaltaReglamento $faltaReglamento = null): bool
    {
        $empresaId = getPermissionsTeamId();

        return is_numeric($empresaId)
            && (int) $empresaId > 0
            && ($faltaReglamento === null || $faltaReglamento->empresa_id === (int) $empresaId);
    }
}
