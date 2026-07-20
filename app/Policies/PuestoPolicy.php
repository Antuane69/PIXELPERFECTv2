<?php

namespace App\Policies;

use App\Models\Puesto;
use App\Models\User;

class PuestoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('puestos.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Puesto $puesto): bool
    {
        return $user->can('puestos.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('puestos.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Puesto $puesto): bool
    {
        return $user->can('puestos.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Puesto $puesto): bool
    {
        return $user->can('puestos.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Puesto $puesto): bool
    {
        return $user->can('puestos.update');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Puesto $puesto): bool
    {
        return false;
    }
}
