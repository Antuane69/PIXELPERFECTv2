<?php

namespace Tests\Feature\Management;

use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\PermisosLaborales\PermisoLaboral;
use App\Models\PermisosLaborales\TipoPermiso;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TipoPermisoManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->empresa = Empresa::query()->where('slug', 'pixel-perfect')->firstOrFail();
        $this->administrator = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($this->administrator)->create();
        setPermissionsTeamId($this->empresa->id);
        $this->administrator->assignRole('Administrador');
        $this->withEmpresaContext($this->empresa);
    }

    public function test_administrator_can_create_update_archive_and_restore_a_permission_type(): void
    {
        $index = route('empresas.tipos-permisos.index');

        $this->actingAs($this->administrator)
            ->post(route('empresas.tipos-permisos.store'), [
                'nombre' => '  Asunto   personal  ',
                'descripcion' => '  Permiso por asunto personal.  ',
                'activo' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($index);

        $tipoPermiso = TipoPermiso::query()->where('nombre', 'Asunto personal')->firstOrFail();
        $this->assertSame('Permiso por asunto personal.', $tipoPermiso->descripcion);

        $this->actingAs($this->administrator)
            ->put(route('empresas.tipos-permisos.update', $tipoPermiso), [
                'nombre' => 'Cita médica',
                'descripcion' => 'Consulta de salud.',
                'activo' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($index);

        $tipoPermiso->refresh();
        $this->assertSame('Cita médica', $tipoPermiso->nombre);
        $this->assertFalse($tipoPermiso->activo);

        $this->actingAs($this->administrator)
            ->delete(route('empresas.tipos-permisos.destroy', $tipoPermiso))
            ->assertSessionHasNoErrors()
            ->assertRedirect($index);

        $this->assertSoftDeleted($tipoPermiso);

        $this->actingAs($this->administrator)
            ->post(route('empresas.tipos-permisos.filtros'), [
                'search' => 'Cita médica',
                'archivados' => true,
            ])
            ->assertRedirect($index);

        $this->actingAs($this->administrator)
            ->get($index)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tipos-permisos/index')
                ->has('tiposPermiso.data', 1)
                ->where('filters.archivados', true),
            );

        $this->actingAs($this->administrator)
            ->patch(route('empresas.tipos-permisos.restore', $tipoPermiso))
            ->assertSessionHasNoErrors()
            ->assertRedirect($index);

        $this->assertNotSoftDeleted($tipoPermiso);
    }

    public function test_permission_type_name_is_unique_within_its_company(): void
    {
        TipoPermiso::factory()->for($this->empresa)->create(['nombre' => 'Cita médica']);

        $this->actingAs($this->administrator)
            ->post(route('empresas.tipos-permisos.store'), [
                'nombre' => 'Cita médica',
                'descripcion' => null,
            ])
            ->assertSessionHasErrors('nombre');
    }

    public function test_catalog_filter_and_pagination_preserve_state_without_query_parameters(): void
    {
        foreach (range(1, 16) as $index) {
            TipoPermiso::factory()->for($this->empresa)->create([
                'nombre' => sprintf('Permiso Paginado %02d', $index),
                'activo' => true,
            ]);
        }

        TipoPermiso::factory()->for($this->empresa)->inactive()->create([
            'nombre' => 'Permiso Paginado Inactivo',
        ]);

        $index = route('empresas.tipos-permisos.index');
        $this->actingAs($this->administrator)
            ->post(route('empresas.tipos-permisos.filtros'), [
                'search' => '  Permiso   Paginado  ',
                'activo' => true,
                'archivados' => false,
            ])
            ->assertRedirect($index);

        $this->actingAs($this->administrator)
            ->get($index)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tipos-permisos/index')
                ->where('filters.search', 'Permiso Paginado')
                ->where('filters.activo', true)
                ->where('tiposPermiso.current_page', 1)
                ->has('tiposPermiso.data', 15)
                ->where('tiposPermiso.links.0.url', null),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.tipos-permisos.pagina'), ['page' => 2])
            ->assertRedirect($index);

        $this->actingAs($this->administrator)
            ->get($index)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Permiso Paginado')
                ->where('filters.activo', true)
                ->where('tiposPermiso.current_page', 2)
                ->has('tiposPermiso.data', 1)
                ->where('tiposPermiso.links.0.url', null),
            );
    }

    public function test_permission_type_cannot_be_archived_when_requests_reference_it(): void
    {
        $tipoPermiso = TipoPermiso::factory()->for($this->empresa)->create();
        PermisoLaboral::factory()->create([
            'empresa_id' => $this->empresa->id,
            'tipo_permiso_id' => $tipoPermiso->id,
        ]);

        $this->actingAs($this->administrator)
            ->from(route('empresas.tipos-permisos.index'))
            ->delete(route('empresas.tipos-permisos.destroy', $tipoPermiso))
            ->assertSessionHasErrors('tipoPermiso')
            ->assertRedirect(route('empresas.tipos-permisos.index'));

        $this->assertNotSoftDeleted($tipoPermiso);
    }

    public function test_catalog_listing_and_routes_are_limited_to_active_company(): void
    {
        $otraEmpresa = Empresa::factory()->activa()->create();
        $otroTipoPermiso = TipoPermiso::factory()->for($otraEmpresa)->create(['nombre' => 'Exclusivo otra empresa']);

        $this->actingAs($this->administrator)
            ->get(route('empresas.tipos-permisos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('tiposPermiso.data.0.nombre')
                ->where('tiposPermiso.total', 0),
            );

        $this->actingAs($this->administrator)
            ->put(route('empresas.tipos-permisos.update', $otroTipoPermiso), [
                'nombre' => 'Cambio ajeno',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('tipos_permisos', [
            'id' => $otroTipoPermiso->id,
            'nombre' => 'Exclusivo otra empresa',
        ]);
    }

    public function test_user_without_catalog_permission_cannot_view_catalog(): void
    {
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($user)->create();

        $this->actingAs($user)
            ->get(route('empresas.tipos-permisos.index'))
            ->assertForbidden();
    }
}
