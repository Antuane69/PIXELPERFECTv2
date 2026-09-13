<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('users.update')
            && (! $model->hasRole('Administrador', 'web') || $user->hasRole('Administrador', 'web'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can('users.delete')
            && (! $model->hasRole('Administrador', 'web') || $user->hasRole('Administrador', 'web'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->can('users.update');
    }

    /**
     * Determine whether the user can manage two-factor authentication for the model.
     */
    public function manageTwoFactor(User $user, User $model): bool
    {
        return $user->can('users.manage_two_factor')
            && $this->canManageModel($user, $model);
    }

    /**
     * Determine whether the user can send a password reset link for the model.
     */
    public function sendPasswordReset(User $user, User $model): bool
    {
        return $user->can('users.send_password_reset')
            && $this->canManageModel($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    private function canManageModel(User $user, User $model): bool
    {
        return $user->can('users.update')
            && $model->membresiasEmpresa()
                ->where('empresa_id', getPermissionsTeamId())
                ->where('estado', 'ACTIVA')
                ->exists()
            && (! $model->hasRole('Administrador', 'web') || $user->hasRole('Administrador', 'web'));
    }
}
