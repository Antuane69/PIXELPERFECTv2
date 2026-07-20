<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class EnsureAdministratorRemains
{
    /**
     * @param  array<int, int|string>  $retainedRoleIds
     */
    public function handle(User $user, array $retainedRoleIds = []): void
    {
        $administratorRole = Role::query()
            ->where('name', 'Administrador')
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->first();

        if ($administratorRole === null || ! $user->hasRole($administratorRole)) {
            return;
        }

        $keepsAdministratorRole = collect($retainedRoleIds)
            ->map(static fn (int|string $roleId): int => (int) $roleId)
            ->contains($administratorRole->id);

        if ($keepsAdministratorRole || $administratorRole->users()->count() > 1) {
            return;
        }

        throw ValidationException::withMessages([
            'roles' => 'Debe existir al menos un usuario con el rol Administrador.',
        ]);
    }
}
