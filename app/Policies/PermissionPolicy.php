<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->es_superadministrador_plataforma;
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->es_superadministrador_plataforma;
    }

    public function create(User $user): bool
    {
        return $user->es_superadministrador_plataforma;
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->es_superadministrador_plataforma;
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->es_superadministrador_plataforma;
    }
}
