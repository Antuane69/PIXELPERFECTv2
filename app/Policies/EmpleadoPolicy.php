<?php

namespace App\Policies;

use App\Models\Empleado;
use App\Models\User;

class EmpleadoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('empleados.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Empleado $empleado): bool
    {
        return $user->can('empleados.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('empleados.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Empleado $empleado): bool
    {
        return $user->can('empleados.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Empleado $empleado): bool
    {
        return $user->can('empleados.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Empleado $empleado): bool
    {
        return $user->can('empleados.update');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Empleado $empleado): bool
    {
        return false;
    }
}
