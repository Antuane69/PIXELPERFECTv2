<?php

namespace Tests\Feature\Management;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_users_without_permissions_cannot_access_management_pages(): void
    {
        $user = User::factory()->create();

        foreach ([
            'users.index',
            'roles.index',
            'empleados.index',
            'puestos.index',
            'tipos-documento-empleados.index',
        ] as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    public function test_administrator_bypasses_policies_and_receives_safe_authorization_props(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrador');

        $this->actingAs($administrator)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/index')
                ->where('auth.user.id', $administrator->id)
                ->where('auth.user.roles', ['Administrador'])
                ->has('auth.user.permissions', 21)
                ->missing('auth.user.password')
                ->missing('auth.user.two_factor_secret'),
            );
    }

    public function test_roles_and_permissions_seeder_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrator = Role::findByName('Administrador', 'web');

        $this->assertSame(21, Permission::query()->where('guard_name', 'web')->count());
        $this->assertSame(21, $administrator->permissions()->count());
    }

    public function test_dashboard_does_not_expose_counts_without_resource_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('stats.users', null)
                ->where('stats.empleados', null)
                ->where('stats.puestosActivos', null)
                ->where('stats.tiposDocumentoActivos', null),
            );
    }

    public function test_non_administrator_cannot_assign_or_take_over_the_administrator_role(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(['users.create', 'users.update']);
        $administratorRole = Role::findByName('Administrador', 'web');

        $this->actingAs($manager)
            ->post(route('users.store'), [
                'name' => 'Escalated User',
                'email' => 'escalated@example.com',
                'password' => 'Secure-password1!',
                'password_confirmation' => 'Secure-password1!',
                'roles' => [$administratorRole->id],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'escalated@example.com']);

        $administrator = User::factory()->create();
        $administrator->assignRole($administratorRole);

        $this->actingAs($manager)
            ->put(route('users.update', $administrator), [
                'name' => 'Taken Over',
                'email' => $administrator->email,
                'password' => 'New-secure-password1!',
                'password_confirmation' => 'New-secure-password1!',
                'roles' => [$administratorRole->id],
            ])
            ->assertForbidden();

        $this->assertNotSame('Taken Over', $administrator->fresh()?->name);
    }

    public function test_delegated_role_manager_cannot_grant_permissions_they_do_not_have(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('roles.update');
        $targetRole = Role::findOrCreate('Operador', 'web');
        $privilegedPermission = Permission::findByName('users.delete', 'web');

        $this->actingAs($manager)
            ->from(route('roles.index'))
            ->put(route('roles.update', $targetRole), [
                'name' => $targetRole->name,
                'permissions' => [$privilegedPermission->id],
            ])
            ->assertSessionHasErrors('permissions')
            ->assertRedirect(route('roles.index'));

        $this->assertFalse($targetRole->fresh()?->hasPermissionTo($privilegedPermission));
    }

    public function test_delegated_role_manager_cannot_expand_the_role_that_grants_their_management_permission(): void
    {
        $manager = User::factory()->create();
        $managementPermission = Permission::findByName('roles.update', 'web');
        $privilegedPermission = Permission::findByName('users.delete', 'web');
        $managementRole = Role::findOrCreate('Gestor de roles', 'web');
        $managementRole->givePermissionTo($managementPermission);
        $manager->assignRole($managementRole);

        $this->actingAs($manager)
            ->from(route('roles.index'))
            ->put(route('roles.update', $managementRole), [
                'name' => $managementRole->name,
                'permissions' => [$managementPermission->id, $privilegedPermission->id],
            ])
            ->assertSessionHasErrors('permissions')
            ->assertRedirect(route('roles.index'));

        $managementRole->refresh();

        $this->assertTrue($managementRole->hasPermissionTo($managementPermission));
        $this->assertFalse($managementRole->hasPermissionTo($privilegedPermission));
    }
}
