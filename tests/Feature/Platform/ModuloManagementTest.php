<?php

namespace Tests\Feature\Platform;

use App\Models\Empresa;
use App\Models\Modulo;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuloManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_platform_administrator_can_manage_modules(): void
    {
        $regularUser = User::factory()->create();
        $module = Modulo::factory()->create();
        $payload = $this->validModuleData();

        $this->actingAs($regularUser)->get(route('platform.modulos.index'))->assertForbidden();
        $this->actingAs($regularUser)->post(route('platform.modulos.store'), $payload)->assertForbidden();
        $this->actingAs($regularUser)->put(route('platform.modulos.update', $module), $payload)->assertForbidden();
        $this->actingAs($regularUser)->delete(route('platform.modulos.destroy', $module))->assertForbidden();
    }

    public function test_platform_administrator_can_create_update_and_delete_unassigned_module(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        $indexWithContext = route('platform.modulos.index', [
            'search' => 'inventarios',
            'activo' => 1,
            'per_page' => 25,
        ]);

        $this->actingAs($administrator)
            ->from($indexWithContext)
            ->post(route('platform.modulos.store'), $this->validModuleData([
                'clave' => '  inventarios  ',
                'nombre' => '  Gestión   de inventarios ',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect($indexWithContext);

        $module = Modulo::query()->where('clave', 'inventarios')->firstOrFail();
        $this->assertSame('Gestión de inventarios', $module->nombre);

        $this->actingAs($administrator)
            ->put(route('platform.modulos.update', $module), $this->validModuleData([
                'clave' => 'inventario-central',
                'nombre' => 'Inventario central',
                'activo' => false,
                'orden' => 55,
            ]))
            ->assertSessionHasNoErrors();

        $module->refresh();
        $this->assertSame('inventario-central', $module->clave);
        $this->assertFalse($module->activo);
        $this->assertSame(55, $module->orden);

        $this->actingAs($administrator)
            ->delete(route('platform.modulos.destroy', $module))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($module);
    }

    public function test_module_validation_and_listing_filters_are_enforced(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        Modulo::factory()->create(['clave' => 'reservado', 'nombre' => 'Reservado', 'activo' => true]);
        Modulo::factory()->count(11)->create(['nombre' => 'Módulo Needle', 'activo' => true]);

        $this->actingAs($administrator)
            ->post(route('platform.modulos.store'), $this->validModuleData([
                'clave' => 'reservado',
                'nombre' => '',
                'orden' => 70000,
            ]))
            ->assertSessionHasErrors(['clave', 'nombre', 'orden']);

        $this->actingAs($administrator)
            ->get(route('platform.modulos.index', [
                'search' => 'Needle',
                'activo' => 1,
                'per_page' => 5,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/modulos/index')
                ->where('filters.search', 'Needle')
                ->where('filters.activo', true)
                ->where('modules.current_page', 2)
                ->where('modules.per_page', 5)
                ->where('modules.total', 11));
    }

    public function test_assigned_module_cannot_be_deleted(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        $company = Empresa::factory()->activa()->create();
        $module = Modulo::factory()->create();
        $company->modulos()->attach($module, ['habilitado' => true]);

        $this->actingAs($administrator)
            ->delete(route('platform.modulos.destroy', $module))
            ->assertSessionHasErrors('modulo');

        $this->assertModelExists($module);
    }

    /** @param array<string, mixed> $overrides */
    private function validModuleData(array $overrides = []): array
    {
        return [
            'clave' => 'almacenes',
            'nombre' => 'Almacenes',
            'descripcion' => 'Control de almacenes empresariales.',
            'activo' => true,
            'orden' => 50,
            ...$overrides,
        ];
    }
}
