<?php

namespace Database\Seeders;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\Models\Empresa;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function __construct(
        private CrearRolesPredeterminadosEmpresa $crearRolesPredeterminadosEmpresa,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->call([
            GrupoEmpresarialSeeder::class,
            EmpresaSeeder::class,
            ModuloSeeder::class,
        ]);

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

        $permissionNames->each(
            static fn (string $permission) => Permission::findOrCreate($permission, 'web'),
        );

        Empresa::query()
            ->select('id')
            ->each(fn (Empresa $empresa) => $this->crearRolesPredeterminadosEmpresa->handle($empresa));

        $empresaInicial = Empresa::query()->where('slug', 'pixel-perfect')->first();
        setPermissionsTeamId($empresaInicial?->id);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
