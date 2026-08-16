<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = collect([
            'users',
            'roles',
            'empleados',
            'puestos',
            'tipos_documento',
        ])->crossJoin(['view', 'create', 'update', 'delete'])
            ->map(static fn (array $parts): string => implode('.', $parts))
            ->merge(['logs.view', 'logs.delete'])
            ->push('users.assign_roles');

        $permissions = $permissionNames->map(
            static fn (string $permission): PermissionContract => Permission::findOrCreate($permission, 'web'),
        );

        Role::findOrCreate('Administrador', 'web')->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
