<?php

namespace Tests\Feature\Multiempresa;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmpresaPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_same_user_has_different_roles_and_permissions_in_each_company(): void
    {
        $user = User::factory()->create();
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        MembresiaEmpresa::factory()->for($first)->for($user)->create();
        MembresiaEmpresa::factory()->for($second)->for($user)->create();

        $firstRole = $this->createRole($first, 'Gestor de usuarios', ['users.view']);
        $secondRole = $this->createRole($second, 'Gestor de empleados', ['empleados.view']);

        setPermissionsTeamId($first->id);
        $user->assignRole($firstRole);
        setPermissionsTeamId($second->id);
        $user->unsetRelation('roles')->unsetRelation('permissions')->assignRole($secondRole);

        $this->actingAs($user)
            ->get(route('empresas.inicio', $first))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.roles', ['Gestor de usuarios'])
                ->where('auth.user.permissions', ['users.view']),
            );

        $this->actingAs($user)
            ->get(route('empresas.inicio', $second))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.roles', ['Gestor de empleados'])
                ->where('auth.user.permissions', ['empleados.view']),
            );
    }

    public function test_default_company_administrator_role_is_scoped_and_excludes_platform_permissions(): void
    {
        $empresa = Empresa::factory()->activa()->create();

        $role = app(CrearRolesPredeterminadosEmpresa::class)->handle($empresa);

        $this->assertSame($empresa->id, $role->empresa_id);
        $this->assertTrue($role->esProtegido());
        $this->assertCount(17, $role->permissions);
        $this->assertFalse($role->hasPermissionTo('logs.view'));
        $this->assertFalse($role->hasPermissionTo('logs.delete'));
        $this->assertFalse($role->hasPermissionTo('tipos_documento.view'));
        $this->assertFalse($role->hasPermissionTo('tipos_documento.create'));
        $this->assertFalse($role->hasPermissionTo('tipos_documento.update'));
        $this->assertFalse($role->hasPermissionTo('tipos_documento.delete'));
    }

    public function test_same_role_name_can_exist_in_different_companies(): void
    {
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();

        $firstRole = $this->createRole($first, 'Supervisor');
        $secondRole = $this->createRole($second, 'Supervisor');

        $this->assertNotSame($firstRole->id, $secondRole->id);
        $this->assertSame($first->id, $firstRole->empresa_id);
        $this->assertSame($second->id, $secondRole->empresa_id);
    }

    public function test_database_rejects_role_for_unknown_company(): void
    {
        $this->expectException(QueryException::class);

        Role::query()->create([
            'empresa_id' => 999999,
            'name' => 'Empresa inexistente',
            'guard_name' => 'web',
        ]);
    }

    public function test_company_administrator_cannot_manage_users_or_roles_from_another_company(): void
    {
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        $firstAdministratorRole = app(CrearRolesPredeterminadosEmpresa::class)->handle($first);
        $secondRole = $this->createRole($second, 'Supervisor');
        $administrator = User::factory()->create();
        $foreignUser = User::factory()->create();
        MembresiaEmpresa::factory()->for($first)->for($administrator)->create();
        MembresiaEmpresa::factory()->for($second)->for($foreignUser)->create();

        setPermissionsTeamId($first->id);
        $administrator->assignRole($firstAdministratorRole);

        $this->actingAs($administrator)
            ->put(route('empresas.users.update', [
                'empresa' => $first,
                'user' => $foreignUser,
            ]), [
                'name' => 'No permitido',
                'email' => $foreignUser->email,
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertNotFound();

        $this->actingAs($administrator)
            ->delete(route('empresas.roles.destroy', [
                'empresa' => $first,
                'role' => $secondRole,
            ]))
            ->assertNotFound();

        $this->assertNotSame('No permitido', $foreignUser->fresh()?->name);
        $this->assertModelExists($secondRole);
    }

    public function test_company_role_form_rejects_foreign_roles_and_platform_permissions(): void
    {
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        $administratorRole = app(CrearRolesPredeterminadosEmpresa::class)->handle($first);
        $foreignRole = $this->createRole($second, 'Rol ajeno');
        $administrator = User::factory()->create();
        MembresiaEmpresa::factory()->for($first)->for($administrator)->create();

        setPermissionsTeamId($first->id);
        $administrator->assignRole($administratorRole);

        $this->actingAs($administrator)
            ->post(route('empresas.users.store', $first), [
                'name' => 'Usuario aislado',
                'email' => 'aislado@example.com',
                'password' => 'Secure-password1!',
                'password_confirmation' => 'Secure-password1!',
                'roles' => [$foreignRole->id],
            ])
            ->assertSessionHasErrors('roles.0');

        $platformPermission = Permission::findByName('logs.view', 'web');

        $this->actingAs($administrator)
            ->post(route('empresas.roles.store', $first), [
                'name' => 'Rol inseguro',
                'permissions' => [$platformPermission->id],
            ])
            ->assertSessionHasErrors('permissions.0');

        $globalCatalogPermission = Permission::findByName('tipos_documento.update', 'web');

        $this->actingAs($administrator)
            ->post(route('empresas.roles.store', $first), [
                'name' => 'Rol global inseguro',
                'permissions' => [$globalCatalogPermission->id],
            ])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('users', ['email' => 'aislado@example.com']);
        $this->assertDatabaseMissing('roles', [
            'empresa_id' => $first->id,
            'name' => 'Rol inseguro',
        ]);
    }

    public function test_request_without_company_resets_previous_permission_context(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $role = $this->createRole($empresa, 'Consulta', ['users.view']);
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

        setPermissionsTeamId($empresa->id);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('empresas.inicio', $empresa))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.permissions', ['users.view']),
            );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.permissions', [])
                ->where('empresas.activa', null),
            );
    }

    public function test_platform_superadministrator_can_access_any_company_without_membership(): void
    {
        $empresa = Empresa::factory()->vencida()->create();
        $user = User::factory()->superadministradorPlataforma()->create();

        $this->actingAs($user)
            ->get(route('empresas.inicio', $empresa))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('empresas.activa.id', $empresa->id)
                ->where('auth.user.permissions', ['*'])
                ->where('auth.user.es_superadministrador_plataforma', true),
            );
    }

    public function test_new_company_can_access_migrated_positions_and_employee_modules(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $administratorRole = app(CrearRolesPredeterminadosEmpresa::class)->handle($empresa);
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

        setPermissionsTeamId($empresa->id);
        $user->assignRole($administratorRole);

        $this->actingAs($user)
            ->get(route('empresas.puestos.index', $empresa))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('empresas.empleados.index', $empresa))
            ->assertOk();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function createRole(Empresa $empresa, string $name, array $permissions = []): Role
    {
        setPermissionsTeamId($empresa->id);

        $role = Role::query()->create([
            'empresa_id' => $empresa->id,
            'name' => $name,
            'guard_name' => 'web',
        ]);

        $role->syncPermissions(Permission::query()->whereIn('name', $permissions)->get());

        return $role;
    }
}
