<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class EnsureAdministratorRoleAssignmentIsAuthorized
{
    /**
     * @param  array<int, int|string>  $roleIds
     */
    public function handle(User $actor, array $roleIds, ?User $target = null): void
    {
        if ($actor->hasRole('Administrador', 'web')) {
            return;
        }

        $desiredRoleIds = collect($roleIds)
            ->map(static fn (int|string $roleId): int => (int) $roleId)
            ->sort()
            ->values();
        $currentRoleIds = $target?->roles()
            ->pluck('roles.id')
            ->map(static fn (mixed $roleId): int => (int) $roleId)
            ->sort()
            ->values();

        if ($currentRoleIds !== null && $desiredRoleIds->all() === $currentRoleIds->all()) {
            return;
        }

        if (! $actor->can('users.assign_roles')) {
            throw ValidationException::withMessages([
                'roles' => 'No tienes permiso para asignar roles.',
            ]);
        }

        $administratorRoleId = Role::query()
            ->where('name', 'Administrador')
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorRoleId === null) {
            return;
        }

        $assignsAdministrator = $desiredRoleIds->contains((int) $administratorRoleId);

        if (! $assignsAdministrator) {
            return;
        }

        throw ValidationException::withMessages([
            'roles' => 'Sólo un Administrador puede asignar el rol Administrador.',
        ]);
    }
}
