<?php

namespace Tests\Feature\Multiempresa;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Modulo;
use App\Models\Puesto;
use App\Models\User;
use Database\Seeders\ModuloSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EmpresaModuleEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_existing_and_new_companies_receive_active_modules_by_default(): void
    {
        $initial = Empresa::query()->where('slug', 'pixel-perfect')->firstOrFail();
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();

        $this->assertSame(
            ['usuarios', 'roles', 'puestos', 'empleados'],
            $initial->modulos()->orderBy('orden')->pluck('clave')->all(),
        );

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), [
                'nombre_legal' => 'Empresa modular SA de CV',
                'nombre_comercial' => 'Empresa modular',
                'grupo_empresarial_id' => null,
                'rfc' => null,
                'correo_contacto' => 'modular@example.com',
                'telefono_contacto' => '5555555555',
                'zona_horaria' => 'America/Mexico_City',
                'moneda' => 'MXN',
                'estado' => 'ACTIVA',
                'demo_ends_at' => null,
            ])
            ->assertSessionHasNoErrors();

        $created = Empresa::query()->where('slug', 'empresa-modular')->firstOrFail();

        $this->assertSame(
            ['usuarios', 'roles', 'puestos', 'empleados'],
            $created->modulos()->orderBy('orden')->pluck('clave')->all(),
        );
    }

    public function test_disabled_module_blocks_server_navigation_export_and_dashboard_without_deleting_data(): void
    {
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        $firstAdministrator = $this->companyAdministrator($first);
        $secondAdministrator = $this->companyAdministrator($second);
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $position = Puesto::factory()->for($first)->create();
        $enabledModuleIds = Modulo::query()
            ->where('clave', '!=', 'puestos')
            ->pluck('id')
            ->all();

        $this->actingAs($platformAdministrator)
            ->put(route('platform.empresas.modulos.update', $first), [
                'modulos' => $enabledModuleIds,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($platformAdministrator)
            ->get(route('platform.empresas.index', ['search' => $first->nombre_legal]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('modulosDisponibles', 4)
                ->has('empresasPaginadas.data', 1)
                ->where('empresasPaginadas.data.0.modulos', fn (mixed $modules): bool => collect($modules)->contains(
                    fn (mixed $module): bool => is_array($module)
                        && $module['clave'] === 'puestos'
                        && $module['habilitado'] === false,
                )),
            );

        $this->actingAs($firstAdministrator)
            ->get(route('empresas.puestos.index', $first))
            ->assertForbidden();

        $this->actingAs($firstAdministrator)
            ->post(route('empresas.reportes.puestos.exportar', $first), ['formato' => 'xlsx'])
            ->assertForbidden();

        $this->actingAs($firstAdministrator)
            ->get(route('empresas.inicio', $first))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('empresas.activa.modulos', ['usuarios', 'roles', 'empleados'])
                ->has('modulos', 3)
                ->where('stats.puestosActivos', null),
            );

        $this->actingAs($secondAdministrator)
            ->get(route('empresas.puestos.index', $second))
            ->assertOk();

        $this->assertModelExists($position);
        $this->assertFalse($first->fresh()->moduloHabilitado('puestos'));
        $this->assertTrue($second->fresh()->moduloHabilitado('puestos'));

        $activity = Activity::query()->where('log_name', 'modulos_empresa')->firstOrFail();
        $this->assertSame($first->id, $activity->empresa_id);
        $this->assertSame($platformAdministrator->id, $activity->causer_id);
        $this->assertSame(
            ['usuarios', 'roles', 'empleados'],
            $activity->properties->get('attributes')['modulos'],
        );
    }

    public function test_module_enabled_without_user_permission_does_not_grant_access(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

        $this->assertTrue($empresa->moduloHabilitado('empleados'));

        $this->actingAs($user)
            ->get(route('empresas.empleados.index', $empresa))
            ->assertForbidden();
    }

    public function test_only_platform_administrator_can_change_company_modules(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $companyAdministrator = $this->companyAdministrator($empresa);
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $inactiveModule = Modulo::factory()->inactive()->create();

        $this->actingAs($companyAdministrator)
            ->put(route('platform.empresas.modulos.update', $empresa), ['modulos' => []])
            ->assertForbidden();

        $this->actingAs($platformAdministrator)
            ->put(route('platform.empresas.modulos.update', $empresa), [
                'modulos' => [$inactiveModule->id],
            ])
            ->assertSessionHasErrors('modulos.0');

        $this->assertCount(4, $empresa->modulos()->wherePivot('habilitado', true)->get());
    }

    public function test_module_seeder_is_idempotent_and_preserves_company_overrides(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $positionModule = Modulo::query()->where('clave', 'puestos')->firstOrFail();
        $empresa->modulos()->updateExistingPivot($positionModule->id, ['habilitado' => false]);
        $positionModule->update(['activo' => false]);

        $this->seed(ModuloSeeder::class);
        $this->seed(ModuloSeeder::class);

        $this->assertSame(4, Modulo::query()->whereIn('clave', [
            'usuarios',
            'roles',
            'puestos',
            'empleados',
        ])->count());
        $this->assertFalse($positionModule->fresh()->activo);
        $this->assertFalse($empresa->fresh()->moduloHabilitado('puestos'));
    }

    private function companyAdministrator(Empresa $empresa): User
    {
        $role = app(CrearRolesPredeterminadosEmpresa::class)->handle($empresa);
        $administrator = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($administrator)->create();

        setPermissionsTeamId($empresa->id);
        $administrator->assignRole($role);

        return $administrator;
    }
}
