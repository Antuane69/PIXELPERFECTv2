<?php

namespace Tests\Feature\Platform;

use App\AlcancePermiso;
use App\Models\Empresa;
use App\Models\Modulo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_platform_administrator_can_manage_permissions(): void
    {
        $regularUser = User::factory()->create();
        $permission = Permission::query()->firstOrFail();
        $payload = $this->validPermissionData();

        $this->actingAs($regularUser)->get(route('platform.permisos.index'))->assertForbidden();
        $this->actingAs($regularUser)->post(route('platform.permisos.store'), $payload)->assertForbidden();
        $this->actingAs($regularUser)->put(route('platform.permisos.update', $permission), $payload)->assertForbidden();
        $this->actingAs($regularUser)->delete(route('platform.permisos.destroy', $permission))->assertForbidden();
    }

    public function test_platform_administrator_can_create_update_and_delete_permission(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        $company = Empresa::query()->where('slug', 'pixel-perfect')->firstOrFail();
        $module = Modulo::query()->where('clave', 'empleados')->firstOrFail();
        $administratorRole = Role::query()
            ->where('empresa_id', $company->id)
            ->where('name', 'Administrador')
            ->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('platform.permisos.store'), $this->validPermissionData([
                'name' => ' EXPEDIENTES.EXPORTAR ',
                'modulo_id' => $module->id,
            ]))
            ->assertSessionHasNoErrors();

        $permission = Permission::query()->where('name', 'expedientes.exportar')->firstOrFail();
        $this->assertSame(AlcancePermiso::Empresa, $permission->alcance);
        $this->assertSame($module->id, $permission->modulo_id);
        $this->assertTrue($administratorRole->fresh()->hasPermissionTo($permission));

        $this->actingAs($administrator)
            ->put(route('platform.permisos.update', $permission), [
                'name' => 'expedientes.auditar',
                'alcance' => AlcancePermiso::Plataforma->value,
                'modulo_id' => '',
            ])
            ->assertSessionHasNoErrors();

        $permission->refresh();
        $this->assertSame('expedientes.auditar', $permission->name);
        $this->assertSame(AlcancePermiso::Plataforma, $permission->alcance);
        $this->assertNull($permission->modulo_id);
        $this->assertFalse($administratorRole->fresh()->hasPermissionTo($permission));

        $this->actingAs($administrator)
            ->delete(route('platform.permisos.destroy', $permission))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($permission);
    }

    public function test_permission_validation_and_listing_filters_are_enforced(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        $module = Modulo::query()->where('clave', 'usuarios')->firstOrFail();
        Permission::query()->create([
            'name' => 'catalogo.needle',
            'guard_name' => 'web',
            'alcance' => AlcancePermiso::Empresa,
            'modulo_id' => $module->id,
        ]);

        $this->actingAs($administrator)
            ->post(route('platform.permisos.store'), [
                'name' => 'sin-formato',
                'alcance' => AlcancePermiso::Empresa->value,
                'modulo_id' => '',
            ])
            ->assertSessionHasErrors(['name', 'modulo_id']);

        $this->actingAs($administrator)
            ->get(route('platform.permisos.index', [
                'search' => 'needle',
                'alcance' => AlcancePermiso::Empresa->value,
                'modulo_id' => $module->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/permisos/index')
                ->where('filters.search', 'needle')
                ->where('filters.alcance', AlcancePermiso::Empresa->value)
                ->where('filters.moduloId', $module->id)
                ->has('permissions.data', 1)
                ->where('permissions.data.0.name', 'catalogo.needle'));
    }

    public function test_assigned_permission_cannot_be_deleted(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        $permission = Permission::query()->where('name', 'users.view')->firstOrFail();

        $this->actingAs($administrator)
            ->delete(route('platform.permisos.destroy', $permission))
            ->assertSessionHasErrors('permission');

        $this->assertModelExists($permission);
    }

    public function test_json_endpoints_support_permission_crud_from_module_drawer(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        $module = Modulo::factory()->create(['clave' => 'permisos-prueba']);

        $createResponse = $this->actingAs($administrator)
            ->postJson(route('platform.permisos.store'), $this->validPermissionData([
                'name' => 'permisos_prueba.exportar',
                'modulo_id' => $module->id,
            ]));

        $createResponse
            ->assertCreated()
            ->assertJsonPath('permission.name', 'permisos_prueba.exportar')
            ->assertJsonPath('permission.modulo_id', $module->id);

        $permission = Permission::query()->where('name', 'permisos_prueba.exportar')->firstOrFail();

        $this->actingAs($administrator)
            ->getJson(route('platform.permisos.index', [
                'modulo_id' => $module->id,
                'per_page' => 100,
            ]))
            ->assertOk()
            ->assertJsonPath('permissions.per_page', 100)
            ->assertJsonPath('permissions.data.0.modulo_id', $module->id);

        $this->actingAs($administrator)
            ->putJson(route('platform.permisos.update', $permission), $this->validPermissionData([
                'name' => 'permisos_prueba.consultar_exportaciones',
                'modulo_id' => $module->id,
            ]))
            ->assertOk()
            ->assertJsonPath('permission.name', 'permisos_prueba.consultar_exportaciones')
            ->assertJsonPath('permission.modulo_id', $module->id);

        $this->actingAs($administrator)
            ->deleteJson(route('platform.permisos.destroy', $permission))
            ->assertOk()
            ->assertJsonPath('id', $permission->id);

        $this->assertModelMissing($permission);
    }

    /** @param array<string, mixed> $overrides */
    private function validPermissionData(array $overrides = []): array
    {
        return [
            'name' => 'almacenes.view',
            'alcance' => AlcancePermiso::Empresa->value,
            'modulo_id' => Modulo::query()->where('clave', 'puestos')->value('id'),
            ...$overrides,
        ];
    }
}
