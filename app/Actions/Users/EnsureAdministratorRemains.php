<?php

namespace App\Actions\Users;

use App\EstadoMembresiaEmpresa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnsureAdministratorRemains
{
    /**
     * @param  array<int, int|string>  $retainedRoleIds
     */
    public function handle(User $user, array $retainedRoleIds = []): void
    {
        $administratorRole = Role::query()
            ->where('empresa_id', getPermissionsTeamId())
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

        if ($keepsAdministratorRole || $this->hasOtherActiveAdministrator($administratorRole, $user)) {
            return;
        }

        throw ValidationException::withMessages([
            'roles' => 'Debe existir al menos un usuario con el rol Administrador.',
        ]);
    }

    public function handleAcrossCompanies(User $user): void
    {
        if ($user->es_superadministrador_plataforma) {
            $platformAdministratorIds = User::query()
                ->where('es_superadministrador_plataforma', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            if ($platformAdministratorIds->reject(fn (int $id): bool => $id === $user->id)->isEmpty()) {
                throw ValidationException::withMessages([
                    'user' => 'Debe existir al menos un superadministrador de plataforma.',
                ]);
            }
        }

        $administratorRoles = Role::query()
            ->select('roles.*')
            ->join('model_has_roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->whereColumn('model_has_roles.empresa_id', 'roles.empresa_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->where('roles.name', 'Administrador')
            ->where('roles.guard_name', 'web')
            ->orderBy('roles.id')
            ->lockForUpdate()
            ->get();

        foreach ($administratorRoles as $administratorRole) {
            if (! $this->hasOtherActiveAdministrator($administratorRole, $user)) {
                throw ValidationException::withMessages([
                    'roles' => 'Debe existir al menos un usuario con el rol Administrador en cada empresa.',
                ]);
            }
        }
    }

    private function hasOtherActiveAdministrator(Role $role, User $user): bool
    {
        return DB::table('model_has_roles')
            ->join('membresias_empresa', function (JoinClause $join): void {
                $join->on('membresias_empresa.user_id', '=', 'model_has_roles.model_id')
                    ->on('membresias_empresa.empresa_id', '=', 'model_has_roles.empresa_id');
            })
            ->where('model_has_roles.empresa_id', $role->empresa_id)
            ->where('model_has_roles.role_id', $role->id)
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', '!=', $user->id)
            ->where('membresias_empresa.estado', EstadoMembresiaEmpresa::Activa->value)
            ->exists();
    }
}
