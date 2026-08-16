<?php

namespace Tests\Feature\Management;

use App\Jobs\SendEmailVerificationEmail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->administrator = User::factory()->create();
        $this->administrator->assignRole('Administrador');
    }

    public function test_administrator_can_create_and_update_a_user_with_roles(): void
    {
        Queue::fake();

        $role = Role::findOrCreate('Recursos Humanos', 'web');
        $password = 'Secure-password1!';
        $filteredIndex = route('users.index', [
            'search' => 'Gestionado',
            'per_page' => 25,
            'page' => 2,
        ]);

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->post(route('users.store'), [
                'name' => '  Usuario   Gestionado  ',
                'email' => ' Gestionado@Example.COM ',
                'password' => $password,
                'password_confirmation' => $password,
                'roles' => [$role->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $user = User::query()->where('email', 'gestionado@example.com')->firstOrFail();

        $this->assertSame('Usuario Gestionado', $user->name);
        $this->assertTrue($user->hasRole($role));
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNull($user->email_verified_at);
        Queue::assertPushed(
            SendEmailVerificationEmail::class,
            fn (SendEmailVerificationEmail $job): bool => $job->email === $user->email,
        );

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->put(route('users.update', $user), [
                'name' => 'Usuario Actualizado',
                'email' => 'actualizado@example.com',
                'password' => null,
                'password_confirmation' => null,
                'roles' => [$role->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $user->refresh();

        $this->assertSame('Usuario Actualizado', $user->name);
        $this->assertSame('actualizado@example.com', $user->email);
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_changing_a_user_email_requires_verification_again(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $role = Role::findOrCreate('Recursos Humanos', 'web');
        $user->assignRole($role);

        $this->actingAs($this->administrator)
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => 'nuevo-correo@example.com',
                'password' => null,
                'password_confirmation' => null,
                'roles' => [$role->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $this->assertSame('nuevo-correo@example.com', $user->refresh()->email);
        $this->assertNull($user->email_verified_at);
        Queue::assertPushed(
            SendEmailVerificationEmail::class,
            fn (SendEmailVerificationEmail $job): bool => $job->email === $user->email,
        );
    }

    public function test_user_validation_rejects_duplicate_email_and_invalid_role(): void
    {
        $existing = User::factory()->create();

        $this->actingAs($this->administrator)
            ->post(route('users.store'), [
                'name' => '',
                'email' => $existing->email,
                'password' => 'short',
                'password_confirmation' => 'different',
                'roles' => [999999],
            ])
            ->assertSessionHasErrors(['name', 'email', 'password', 'roles.0']);
    }

    public function test_user_listing_filters_results_and_caps_page_size(): void
    {
        User::factory()->create(['name' => 'Needle User', 'email' => 'needle@example.com']);
        User::factory()->create(['name' => 'Unrelated User', 'email' => 'other@example.com']);

        $this->actingAs($this->administrator)
            ->get(route('users.index', ['search' => 'Needle', 'per_page' => 500]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Needle User')
                ->where('users.per_page', 100)
                ->where('filters.search', 'Needle')
                ->where('filters.perPage', 100),
            );
    }

    public function test_user_pagination_preserves_active_filters_on_the_second_page(): void
    {
        foreach (range(1, 7) as $index) {
            User::factory()->create([
                'name' => sprintf('Usuario Paginado %02d', $index),
                'email' => sprintf('paginado-%02d@example.com', $index),
            ]);
        }

        $this->actingAs($this->administrator)
            ->get(route('users.index', [
                'search' => 'Usuario Paginado',
                'per_page' => 5,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/index')
                ->where('users.current_page', 2)
                ->where('users.per_page', 5)
                ->has('users.data', 2)
                ->where('users.links.0.url', fn (mixed $url): bool => $this->urlContainsQuery($url, [
                    'search' => 'Usuario Paginado',
                    'per_page' => 5,
                    'page' => 1,
                ])),
            );
    }

    public function test_administrator_cannot_delete_their_own_account_but_can_delete_another_user(): void
    {
        $this->actingAs($this->administrator)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $this->administrator))
            ->assertSessionHasErrors('user')
            ->assertRedirect(route('users.index'));

        $this->assertModelExists($this->administrator);

        $otherUser = User::factory()->create();

        $this->actingAs($this->administrator)
            ->delete(route('users.destroy', $otherUser))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $this->assertModelMissing($otherUser);
    }

    public function test_the_last_administrator_role_cannot_be_removed(): void
    {
        $otherRole = Role::findOrCreate('Operador', 'web');

        $this->actingAs($this->administrator)
            ->from(route('users.index'))
            ->put(route('users.update', $this->administrator), [
                'name' => $this->administrator->name,
                'email' => $this->administrator->email,
                'password' => null,
                'password_confirmation' => null,
                'roles' => [$otherRole->id],
            ])
            ->assertSessionHasErrors('roles')
            ->assertRedirect(route('users.index'));

        $this->assertTrue($this->administrator->fresh()?->hasRole('Administrador'));
    }

    public function test_updating_a_user_without_sending_roles_preserves_their_assignments(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('users.update');
        $assignedRole = Role::findOrCreate('Operador', 'web');
        $user = User::factory()->create();
        $user->assignRole($assignedRole);

        $this->actingAs($manager)
            ->put(route('users.update', $user), [
                'name' => 'Usuario sin cambio de rol',
                'email' => 'sin-cambio@example.com',
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertSame('Usuario sin cambio de rol', $user->name);
        $this->assertSame('sin-cambio@example.com', $user->email);
        $this->assertTrue($user->hasExactRoles([$assignedRole]));
    }

    public function test_updating_a_user_without_roles_keeps_them_without_roles(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('users.update');
        $user = User::factory()->create();

        $this->actingAs($manager)
            ->put(route('users.update', $user), [
                'name' => 'Usuario actualizado sin roles',
                'email' => 'actualizado-sin-roles@example.com',
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertSame('Usuario actualizado sin roles', $user->name);
        $this->assertSame('actualizado-sin-roles@example.com', $user->email);
        $this->assertCount(0, $user->roles);
    }

    public function test_manager_without_role_assignment_permission_cannot_change_user_roles(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('users.update');
        $assignedRole = Role::findOrCreate('Operador', 'web');
        $requestedRole = Role::findOrCreate('Supervisor', 'web');
        $user = User::factory()->create();
        $user->assignRole($assignedRole);

        $this->actingAs($manager)
            ->from(route('users.index'))
            ->put(route('users.update', $user), [
                'name' => 'Intento de reasignacion',
                'email' => 'intento-reasignacion@example.com',
                'password' => null,
                'password_confirmation' => null,
                'roles' => [$requestedRole->id],
            ])
            ->assertSessionHasErrors('roles')
            ->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertTrue($user->hasExactRoles([$assignedRole]));
        $this->assertNotSame('Intento de reasignacion', $user->name);
        $this->assertNotSame('intento-reasignacion@example.com', $user->email);
    }

    public function test_a_manager_cannot_delete_the_last_administrator(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('users.delete');

        $this->actingAs($manager)
            ->delete(route('users.destroy', $this->administrator))
            ->assertForbidden();

        $this->assertModelExists($this->administrator);
    }
}
