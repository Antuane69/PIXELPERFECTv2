<?php

namespace Database\Seeders;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\AlcancePermiso;
use App\Models\Empresa;
use App\Models\Modulo;
use App\Models\Permission;
use Illuminate\Database\Seeder;
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

        $moduleIds = Modulo::query()->pluck('id', 'clave');
        $permissionDefinitions = [];

        foreach ([
            'users' => 'usuarios',
            'roles' => 'roles',
            'empleados' => 'empleados',
            'puestos' => 'puestos',
            'tipos_documento' => 'empleados',
        ] as $resource => $moduleKey) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissionDefinitions[] = [
                    'name' => "{$resource}.{$action}",
                    'alcance' => AlcancePermiso::Empresa,
                    'module_key' => $moduleKey,
                ];
            }
        }

        $permissionDefinitions[] = [
            'name' => 'users.assign_roles',
            'alcance' => AlcancePermiso::Empresa,
            'module_key' => 'usuarios',
        ];
        $permissionDefinitions[] = [
            'name' => 'users.manage_two_factor',
            'alcance' => AlcancePermiso::Empresa,
            'module_key' => 'usuarios',
        ];
        $permissionDefinitions[] = [
            'name' => 'users.send_password_reset',
            'alcance' => AlcancePermiso::Empresa,
            'module_key' => 'usuarios',
        ];
        $permissionDefinitions[] = [
            'name' => 'logs.view',
            'alcance' => AlcancePermiso::Plataforma,
            'module_key' => null,
        ];
        $permissionDefinitions[] = [
            'name' => 'logs.delete',
            'alcance' => AlcancePermiso::Plataforma,
            'module_key' => null,
        ];

        foreach ($permissionDefinitions as $definition) {
            Permission::query()->firstOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                [
                    'alcance' => $definition['alcance'],
                    'modulo_id' => $definition['module_key'] === null
                        ? null
                        : $moduleIds->get($definition['module_key']),
                ],
            );
        }

        Empresa::query()
            ->select('id')
            ->each(fn (Empresa $empresa) => $this->crearRolesPredeterminadosEmpresa->handle($empresa));

        $empresaInicial = Empresa::query()->where('slug', 'pixel-perfect')->first();
        setPermissionsTeamId($empresaInicial?->id);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
