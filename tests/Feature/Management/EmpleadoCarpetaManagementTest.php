<?php

namespace Tests\Feature\Management;

use App\Models\EmpleadoCarpeta;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Modulo;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EmpleadoCarpetaManagementTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->empresa = Empresa::factory()->activa()->create();
        $this->propietario = $this->companyUser($this->empresa, [
            'empleados_carpetas.view',
            'empleados_carpetas.create',
            'empleados_carpetas.update',
            'empleados_carpetas.delete',
        ]);
        $this->withEmpresaContext($this->empresa);
    }

    public function test_company_user_can_create_update_archive_and_restore_a_folder(): void
    {
        $sharedUser = $this->companyUser($this->empresa, ['empleados_carpetas.view']);
        $filteredIndex = route('empresas.empleados.carpetas.index', [
            'search' => 'Expedientes',
            'archivados' => false,
            'per_page' => 25,
            'page' => 2,
        ]);

        $this->actingAs($this->propietario)
            ->from($filteredIndex)
            ->post(route('empresas.empleados.carpetas.store'), [
                'nombre' => '  Expedientes   de personal  ',
                'user_ids' => [$sharedUser->id],
                'empresa_id' => Empresa::factory()->create()->id,
                'creado_por_id' => $sharedUser->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $carpeta = EmpleadoCarpeta::query()->where('nombre', 'Expedientes de personal')->firstOrFail();

        $this->assertSame($this->empresa->id, $carpeta->empresa_id);
        $this->assertSame($this->propietario->id, $carpeta->creado_por_id);
        $this->assertDatabaseHas('empleados_carpetas_usuarios', [
            'empresa_id' => $this->empresa->id,
            'empleado_carpeta_id' => $carpeta->id,
            'user_id' => $sharedUser->id,
        ]);
        $accessActivity = Activity::query()
            ->where('log_name', 'accesos_empresa')
            ->where('subject_type', EmpleadoCarpeta::class)
            ->where('subject_id', $carpeta->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->empresa->id, $accessActivity->empresa_id);
        $this->assertSame(
            [$sharedUser->id],
            $accessActivity->properties->get('attributes')['usuarios_con_acceso'],
        );

        $this->actingAs($this->propietario)
            ->from($filteredIndex)
            ->put(route('empresas.empleados.carpetas.update', $carpeta), [
                'nombre' => 'Expedientes activos',
                'user_ids' => [],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $this->assertDatabaseHas('empleados_carpetas', [
            'id' => $carpeta->id,
            'nombre' => 'Expedientes activos',
            'creado_por_id' => $this->propietario->id,
        ]);
        $this->assertDatabaseMissing('empleados_carpetas_usuarios', [
            'empleado_carpeta_id' => $carpeta->id,
            'user_id' => $sharedUser->id,
        ]);
        $revocationActivity = Activity::query()
            ->where('log_name', 'accesos_empresa')
            ->where('subject_type', EmpleadoCarpeta::class)
            ->where('subject_id', $carpeta->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            [$sharedUser->id],
            $revocationActivity->properties->get('old')['usuarios_con_acceso'],
        );
        $this->assertSame([], $revocationActivity->properties->get('attributes')['usuarios_con_acceso']);

        $this->actingAs($this->propietario)
            ->from($filteredIndex)
            ->delete(route('empresas.empleados.carpetas.destroy', $carpeta))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($carpeta);

        $archivedIndex = route('empresas.empleados.carpetas.index', [
            'archivados' => true,
            'search' => 'Expedientes',
        ]);

        $this->actingAs($this->propietario)
            ->get($archivedIndex)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empleados/carpetas/index')
                ->has('carpetas.data', 1)
                ->where('carpetas.data.0.id', $carpeta->id)
                ->where('filters.archivados', true));

        $this->actingAs($this->propietario)
            ->from($archivedIndex)
            ->patch(route('empresas.empleados.carpetas.restore', $carpeta))
            ->assertSessionHasNoErrors()
            ->assertRedirect($archivedIndex);

        $this->assertNotSoftDeleted($carpeta);
    }

    public function test_listing_shows_only_folders_created_by_or_shared_with_current_user(): void
    {
        $assignedUser = $this->companyUser($this->empresa, ['empleados_carpetas.view']);
        $otherUser = $this->companyUser($this->empresa, ['empleados_carpetas.view']);
        $owned = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $assignedUser->id,
            'nombre' => 'Carpeta propia',
        ]);
        $shared = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $otherUser->id,
            'nombre' => 'Carpeta compartida',
        ]);
        $shared->usuariosConAcceso()->attach($assignedUser->id, ['empresa_id' => $this->empresa->id]);
        EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $otherUser->id,
            'nombre' => 'Carpeta privada',
        ]);

        $otraEmpresa = Empresa::factory()->activa()->create();
        EmpleadoCarpeta::factory()->create([
            'empresa_id' => $otraEmpresa->id,
            'creado_por_id' => $assignedUser->id,
            'nombre' => 'Carpeta de otra empresa',
        ]);

        $this->actingAs($assignedUser)
            ->get(route('empresas.empleados.carpetas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empleados/carpetas/index')
                ->has('carpetas.data', 2)
                ->where('carpetas.data.0.id', $shared->id)
                ->where('carpetas.data.1.id', $owned->id));
    }

    public function test_folder_can_be_created_without_optional_assignees(): void
    {
        $this->actingAs($this->propietario)
            ->post(route('empresas.empleados.carpetas.store'), [
                'nombre' => 'Carpeta privada',
            ])
            ->assertSessionHasNoErrors();

        $carpeta = EmpleadoCarpeta::query()->where('nombre', 'Carpeta privada')->firstOrFail();

        $this->assertSame($this->propietario->id, $carpeta->creado_por_id);
        $this->assertDatabaseMissing('empleados_carpetas_usuarios', [
            'empleado_carpeta_id' => $carpeta->id,
        ]);
    }

    public function test_user_selector_contains_only_other_active_users_of_current_company(): void
    {
        $activeUser = $this->companyUser($this->empresa, ['empleados_carpetas.view']);
        $suspendedUser = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($suspendedUser)->suspendida()->create();
        $foreignCompany = Empresa::factory()->activa()->create();
        $foreignUser = $this->companyUser($foreignCompany, ['empleados_carpetas.view']);

        $this->actingAs($this->propietario)
            ->get(route('empresas.empleados.carpetas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('usuarios', 1)
                ->where('usuarios.0.id', $activeUser->id));
    }

    public function test_partial_update_preserves_assignees_when_user_ids_are_omitted(): void
    {
        $sharedUser = $this->companyUser($this->empresa, ['empleados_carpetas.view']);
        $carpeta = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $this->propietario->id,
        ]);
        $carpeta->usuariosConAcceso()->attach($sharedUser->id, ['empresa_id' => $this->empresa->id]);

        $this->actingAs($this->propietario)
            ->put(route('empresas.empleados.carpetas.update', $carpeta), [
                'nombre' => 'Nombre actualizado',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('empleados_carpetas', [
            'id' => $carpeta->id,
            'nombre' => 'Nombre actualizado',
        ]);
        $this->assertDatabaseHas('empleados_carpetas_usuarios', [
            'empleado_carpeta_id' => $carpeta->id,
            'user_id' => $sharedUser->id,
        ]);
    }

    public function test_folder_access_can_only_be_assigned_to_active_users_of_current_company(): void
    {
        $activeUser = $this->companyUser($this->empresa, ['empleados_carpetas.view']);
        $suspendedUser = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($suspendedUser)->suspendida()->create();
        $foreignCompany = Empresa::factory()->activa()->create();
        $foreignUser = $this->companyUser($foreignCompany, ['empleados_carpetas.view']);

        $this->actingAs($this->propietario)
            ->post(route('empresas.empleados.carpetas.store'), [
                'nombre' => 'Carpeta protegida',
                'user_ids' => [$activeUser->id, $suspendedUser->id, $foreignUser->id],
            ])
            ->assertSessionHasErrors(['user_ids.1', 'user_ids.2']);

        $this->assertDatabaseMissing('empleados_carpetas', [
            'nombre' => 'Carpeta protegida',
        ]);
    }

    public function test_shared_user_cannot_manage_folder_owned_by_another_person(): void
    {
        $sharedUser = $this->companyUser($this->empresa, [
            'empleados_carpetas.view',
            'empleados_carpetas.create',
            'empleados_carpetas.update',
            'empleados_carpetas.delete',
        ]);
        $carpeta = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $this->propietario->id,
        ]);
        $carpeta->usuariosConAcceso()->attach($sharedUser->id, ['empresa_id' => $this->empresa->id]);

        $this->actingAs($sharedUser)
            ->get(route('empresas.empleados.carpetas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('carpetas.data', 1)
                ->where('carpetas.data.0.id', $carpeta->id));

        $this->actingAs($sharedUser)
            ->put(route('empresas.empleados.carpetas.update', $carpeta), [
                'nombre' => 'Modificación ajena',
                'user_ids' => [],
            ])
            ->assertForbidden();

        $this->actingAs($sharedUser)
            ->delete(route('empresas.empleados.carpetas.destroy', $carpeta))
            ->assertForbidden();
    }

    public function test_user_without_catalog_permission_cannot_open_folder_listing(): void
    {
        $user = $this->companyUser($this->empresa, []);

        $this->actingAs($user)
            ->get(route('empresas.empleados.carpetas.index'))
            ->assertForbidden();
    }

    public function test_employee_module_entitlement_blocks_folder_route_when_disabled(): void
    {
        $empleadosModule = Modulo::query()->where('clave', 'empleados')->firstOrFail();
        $this->empresa->modulos()->updateExistingPivot($empleadosModule->id, ['habilitado' => false]);

        $this->actingAs($this->propietario)
            ->get(route('empresas.empleados.carpetas.index'))
            ->assertForbidden();
    }

    public function test_foreign_company_folder_cannot_be_updated_in_active_company_context(): void
    {
        $foreignCompany = Empresa::factory()->activa()->create();
        $carpeta = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $foreignCompany->id,
            'creado_por_id' => $this->propietario->id,
        ]);

        $this->actingAs($this->propietario)
            ->put(route('empresas.empleados.carpetas.update', $carpeta), [
                'nombre' => 'Cambio entre empresas',
                'user_ids' => [],
            ])
            ->assertNotFound();
    }

    public function test_listing_caps_page_size_and_preserves_search_on_later_pages(): void
    {
        foreach (range(1, 7) as $index) {
            EmpleadoCarpeta::factory()->create([
                'empresa_id' => $this->empresa->id,
                'creado_por_id' => $this->propietario->id,
                'nombre' => sprintf('Carpeta paginada %02d', $index),
            ]);
        }

        $this->actingAs($this->propietario)
            ->get(route('empresas.empleados.carpetas.index', [
                'search' => 'Carpeta paginada',
                'per_page' => 500,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('carpetas.per_page', 100)
                ->where('carpetas.total', 7));

        $this->actingAs($this->propietario)
            ->get(route('empresas.empleados.carpetas.index', [
                'search' => 'Carpeta paginada',
                'per_page' => 5,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('carpetas.current_page', 2)
                ->where('carpetas.per_page', 5)
                ->has('carpetas.data', 2)
                ->where('carpetas.links.0.url', fn (mixed $url): bool => $this->urlContainsQuery($url, [
                    'search' => 'Carpeta paginada',
                    'per_page' => 5,
                    'page' => 1,
                ])));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function companyUser(Empresa $empresa, array $permissions): User
    {
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

        if ($permissions !== []) {
            setPermissionsTeamId($empresa->id);
            $user->givePermissionTo($permissions);
        }

        return $user;
    }
}
