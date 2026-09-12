<?php

namespace Tests\Feature\Management;

use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->empresa = Empresa::query()->where('slug', 'pixel-perfect')->firstOrFail();
        URL::defaults(['empresa' => $this->empresa->slug]);
        $this->administrator = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($this->administrator)->create();
        $this->administrator->assignRole('Administrador');
    }

    public function test_administrator_can_create_and_update_a_role_with_permissions(): void
    {
        $viewPermission = Permission::findByName('users.view', 'web');
        $createPermission = Permission::findByName('users.create', 'web');
        $filteredIndex = route('empresas.roles.index', [
            'empresa' => $this->empresa,
            'search' => 'Supervisor',
            'per_page' => 25,
            'page' => 2,
        ]);

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->post(route('empresas.roles.store'), [
                'name' => '  Supervisor  ',
                'permissions' => [$viewPermission->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $role = Role::findByName('Supervisor', 'web');
        $this->assertTrue($role->hasPermissionTo($viewPermission));

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->put(route('empresas.roles.update', ['role' => $role]), [
                'name' => 'Supervisor General',
                'permissions' => [$viewPermission->id, $createPermission->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $role->refresh();

        $this->assertSame('Supervisor General', $role->name);
        $this->assertTrue($role->hasAllPermissions([$viewPermission, $createPermission]));
    }

    public function test_role_validation_rejects_duplicate_name_and_unknown_permission(): void
    {
        Role::findOrCreate('Supervisor', 'web');

        $this->actingAs($this->administrator)
            ->post(route('empresas.roles.store'), [
                'name' => 'Supervisor',
                'permissions' => [999999],
            ])
            ->assertSessionHasErrors(['name', 'permissions.0']);
    }

    public function test_role_listing_filters_results_and_caps_page_size(): void
    {
        Role::findOrCreate('Needle Role', 'web');
        Role::findOrCreate('Other Role', 'web');

        $this->actingAs($this->administrator)
            ->get(route('empresas.roles.index', ['search' => 'Needle', 'per_page' => 500]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('roles/index')
                ->has('roles.data', 1)
                ->where('roles.data.0.name', 'Needle Role')
                ->where('roles.per_page', 100),
            );
    }

    public function test_role_pagination_preserves_active_filters_on_the_second_page(): void
    {
        foreach (range(1, 7) as $index) {
            Role::findOrCreate(sprintf('Rol Paginado %02d', $index), 'web');
        }

        $this->actingAs($this->administrator)
            ->get(route('empresas.roles.index', [
                'search' => 'Rol Paginado',
                'per_page' => 5,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('roles/index')
                ->where('roles.current_page', 2)
                ->where('roles.per_page', 5)
                ->has('roles.data', 2)
                ->where('roles.links.0.url', fn (mixed $url): bool => $this->urlContainsQuery($url, [
                    'search' => 'Rol Paginado',
                    'per_page' => 5,
                    'page' => 1,
                ])),
            );
    }

    public function test_administrator_role_cannot_be_modified_or_deleted(): void
    {
        $administratorRole = Role::findByName('Administrador', 'web');
        $permission = Permission::findByName('users.view', 'web');

        $this->actingAs($this->administrator)
            ->from(route('empresas.roles.index'))
            ->put(route('empresas.roles.update', ['role' => $administratorRole]), [
                'name' => 'Renamed Administrator',
                'permissions' => [$permission->id],
            ])
            ->assertSessionHasErrors('role')
            ->assertRedirect(route('empresas.roles.index'));

        $this->actingAs($this->administrator)
            ->from(route('empresas.roles.index'))
            ->delete(route('empresas.roles.destroy', ['role' => $administratorRole]))
            ->assertSessionHasErrors('role')
            ->assertRedirect(route('empresas.roles.index'));

        $this->assertSame('Administrador', $administratorRole->fresh()?->name);
    }

    public function test_role_assigned_to_a_user_cannot_be_deleted(): void
    {
        $role = Role::findOrCreate('Operador', 'web');
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($user)->create();
        $user->assignRole($role);

        $this->actingAs($this->administrator)
            ->from(route('empresas.roles.index'))
            ->delete(route('empresas.roles.destroy', ['role' => $role]))
            ->assertSessionHasErrors('role')
            ->assertRedirect(route('empresas.roles.index'));

        $this->assertModelExists($role);
    }
}
