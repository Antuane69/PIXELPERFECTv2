<?php

namespace App\Services\Platform;

use App\AlcancePermiso;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class ManagePermissions
{
    public function __construct(private readonly PermissionRegistrar $permissionRegistrar) {}

    /** @param array<string, mixed> $data */
    public function save(array $data, ?Permission $permission = null): Permission
    {
        $permission = DB::transaction(function () use ($data, $permission): Permission {
            if ($permission instanceof Permission) {
                $permission = Permission::query()->lockForUpdate()->findOrFail($permission->id);
                $permission->update([...$data, 'guard_name' => 'web']);
            } else {
                $permission = Permission::query()->create([...$data, 'guard_name' => 'web']);
            }

            $this->reconcileAssignments($permission);

            return $permission;
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $permission;
    }

    public function delete(Permission $permission): void
    {
        DB::transaction(function () use ($permission): void {
            $permission = Permission::query()->lockForUpdate()->findOrFail($permission->id);
            $directAssignmentsTable = (string) config('permission.table_names.model_has_permissions');

            if ($permission->roles()->exists() || DB::table($directAssignmentsTable)->where('permission_id', $permission->id)->exists()) {
                throw ValidationException::withMessages([
                    'permission' => 'No puedes eliminar permiso asignado. Retíralo de roles y usuarios primero.',
                ]);
            }

            $permission->delete();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
    }

    private function reconcileAssignments(Permission $permission): void
    {
        $directAssignmentsTable = (string) config('permission.table_names.model_has_permissions');
        $teamColumn = (string) config('permission.column_names.team_foreign_key');

        if ($permission->alcance === AlcancePermiso::Plataforma || $permission->modulo_id === null) {
            $permission->roles()->detach();
            DB::table($directAssignmentsTable)->where('permission_id', $permission->id)->delete();

            return;
        }

        $eligibleCompanyIds = DB::table('empresa_modulo')
            ->where('modulo_id', $permission->modulo_id)
            ->where('habilitado', true)
            ->pluck('empresa_id');
        $eligibleRoleIds = Role::query()
            ->where('name', 'Administrador')
            ->where('guard_name', 'web')
            ->whereIn('empresa_id', $eligibleCompanyIds)
            ->pluck('id');
        $outOfScopeRoleIds = $permission->roles()
            ->whereNotIn('roles.empresa_id', $eligibleCompanyIds)
            ->pluck('roles.id');

        $permission->roles()->detach($outOfScopeRoleIds);
        $permission->roles()->syncWithoutDetaching($eligibleRoleIds);
        DB::table($directAssignmentsTable)
            ->where('permission_id', $permission->id)
            ->whereNotIn($teamColumn, $eligibleCompanyIds)
            ->delete();
    }
}
