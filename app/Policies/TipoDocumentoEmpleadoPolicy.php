<?php

namespace App\Policies;

use App\Models\TipoDocumentoEmpleado;
use App\Models\User;

class TipoDocumentoEmpleadoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tipos_documento.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TipoDocumentoEmpleado $tipoDocumentoEmpleado): bool
    {
        return $user->can('tipos_documento.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tipos_documento.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TipoDocumentoEmpleado $tipoDocumentoEmpleado): bool
    {
        return $user->can('tipos_documento.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TipoDocumentoEmpleado $tipoDocumentoEmpleado): bool
    {
        return $user->can('tipos_documento.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TipoDocumentoEmpleado $tipoDocumentoEmpleado): bool
    {
        return $user->can('tipos_documento.update');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TipoDocumentoEmpleado $tipoDocumentoEmpleado): bool
    {
        return false;
    }
}
