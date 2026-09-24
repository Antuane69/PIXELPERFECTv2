<?php

namespace Tests\Feature\Management;

use App\Jobs\SendEmailVerificationEmail;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
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
        $this->addToEmpresa($this->administrator);
        $this->administrator->assignRole('Administrador');
        $this->withEmpresaContext($this->empresa);
    }

    public function test_platform_administrator_can_create_and_update_a_user_with_roles(): void
    {
        Queue::fake();
        $this->administrator->forceFill(['es_superadministrador_plataforma' => true])->save();

        $role = Role::findOrCreate('Recursos Humanos', 'web');
        $password = 'Secure-password1!';
        $filteredIndex = route('empresas.users.index', [
            'search' => 'Gestionado',
            'per_page' => 25,
            'page' => 2,
        ]);

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->post(route('empresas.users.store'), [
                'name' => '  Usuario   Gestionado  ',
                'email' => ' Gestionado@Example.COM ',
                'password' => $password,
                'password_confirmation' => $password,
                'roles' => [(string) $role->id],
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
            ->put(route('empresas.users.update', ['user' => $user]), [
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
        $this->administrator->forceFill(['es_superadministrador_plataforma' => true])->save();

        $user = User::factory()->create();
        $this->addToEmpresa($user);
        $role = Role::findOrCreate('Recursos Humanos', 'web');
        $user->assignRole($role);

        $this->actingAs($this->administrator)
            ->put(route('empresas.users.update', ['user' => $user]), [
                'name' => $user->name,
                'email' => 'nuevo-correo@example.com',
                'password' => null,
                'password_confirmation' => null,
                'roles' => [$role->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.users.index'));

        $this->assertSame('nuevo-correo@example.com', $user->refresh()->email);
        $this->assertNull($user->email_verified_at);
        Queue::assertPushed(
            SendEmailVerificationEmail::class,
            fn (SendEmailVerificationEmail $job): bool => $job->email === $user->email,
        );
    }

    public function test_administrator_can_activate_and_deactivate_two_factor_for_a_user(): void
    {
        $user = User::factory()->create();
        $this->addToEmpresa($user);

        $this->actingAs($this->administrator)
            ->patch(route('empresas.users.two-factor', ['user' => $user]), [
                'enabled' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.users.index'));

        $user->refresh();
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);

        $this->actingAs($this->administrator)
            ->patch(route('empresas.users.two-factor', ['user' => $user]), [
                'enabled' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.users.index'));

        $this->assertFalse($user->refresh()->hasEnabledTwoFactorAuthentication());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_two_factor_management_requires_specific_permission(): void
    {
        $manager = User::factory()->create();
        $this->addToEmpresa($manager);
        $manager->givePermissionTo('users.update');
        $user = User::factory()->create();
        $this->addToEmpresa($user);

        $this->actingAs($manager)
            ->patch(route('empresas.users.two-factor', ['user' => $user]), [
                'enabled' => true,
            ])
            ->assertForbidden();

        $this->assertFalse($user->refresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_password_reset_email_requires_specific_permission(): void
    {
        Notification::fake();
        $manager = User::factory()->create();
        $this->addToEmpresa($manager);
        $manager->givePermissionTo(['users.update', 'users.send_password_reset']);
        $user = User::factory()->create();
        $this->addToEmpresa($user);

        $this->actingAs($manager)
            ->post(route('empresas.users.password-reset', ['user' => $user]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.users.index'));

        Notification::assertSentTo($user, ResetPassword::class);

        $withoutPermission = User::factory()->create();
        $this->addToEmpresa($withoutPermission);
        $withoutPermission->givePermissionTo('users.update');

        $this->actingAs($withoutPermission)
            ->post(route('empresas.users.password-reset', ['user' => $user]))
            ->assertForbidden();
    }

    public function test_user_validation_rejects_duplicate_email_and_invalid_role(): void
    {
        $existing = User::factory()->create();

        $this->actingAs($this->administrator)
            ->post(route('empresas.users.store'), [
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
        $this->addToEmpresa(User::factory()->create(['name' => 'Needle User', 'email' => 'needle@example.com']));
        $this->addToEmpresa(User::factory()->create(['name' => 'Unrelated User', 'email' => 'other@example.com']));

        $this->actingAs($this->administrator)
            ->get(route('empresas.users.index', ['search' => 'Needle', 'per_page' => 500]))
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
            $this->addToEmpresa(User::factory()->create([
                'name' => sprintf('Usuario Paginado %02d', $index),
                'email' => sprintf('paginado-%02d@example.com', $index),
            ]));
        }

        $this->actingAs($this->administrator)
            ->get(route('empresas.users.index', [
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
            ->from(route('empresas.users.index'))
            ->delete(route('empresas.users.destroy', ['user' => $this->administrator]))
            ->assertSessionHasErrors('user')
            ->assertRedirect(route('empresas.users.index'));

        $this->assertModelExists($this->administrator);

        $otherUser = User::factory()->create();
        $this->addToEmpresa($otherUser);

        $this->actingAs($this->administrator)
            ->delete(route('empresas.users.destroy', ['user' => $otherUser]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.users.index'));

        $this->assertModelExists($otherUser);
        $this->assertDatabaseMissing('membresias_empresa', [
            'empresa_id' => $this->empresa->id,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_the_last_administrator_role_cannot_be_removed(): void
    {
        $otherRole = Role::findOrCreate('Operador', 'web');

        $this->actingAs($this->administrator)
            ->from(route('empresas.users.index'))
            ->put(route('empresas.users.update', ['user' => $this->administrator]), [
                'name' => $this->administrator->name,
                'email' => $this->administrator->email,
                'password' => null,
                'password_confirmation' => null,
                'roles' => [$otherRole->id],
            ])
            ->assertSessionHasErrors('roles')
            ->assertRedirect(route('empresas.users.index'));

        $this->assertTrue($this->administrator->fresh()?->hasRole('Administrador'));
    }

    public function test_updating_a_user_without_sending_roles_preserves_their_assignments(): void
    {
        $manager = User::factory()->create();
        $this->addToEmpresa($manager);
        $manager->givePermissionTo('users.update');
        $assignedRole = Role::findOrCreate('Operador', 'web');
        $user = User::factory()->create();
        $this->addToEmpresa($user);
        $user->assignRole($assignedRole);

        $this->actingAs($manager)
            ->put(route('empresas.users.update', ['user' => $user]), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.users.index'));

        $user->refresh();

        $this->assertTrue($user->hasExactRoles([$assignedRole]));
    }

    public function test_updating_a_user_without_roles_keeps_them_without_roles(): void
    {
        $manager = User::factory()->create();
        $this->addToEmpresa($manager);
        $manager->givePermissionTo('users.update');
        $user = User::factory()->create();
        $this->addToEmpresa($user);

        $this->actingAs($manager)
            ->put(route('empresas.users.update', ['user' => $user]), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.users.index'));

        $user->refresh();

        $this->assertCount(0, $user->roles);
    }

    public function test_manager_without_role_assignment_permission_cannot_change_user_roles(): void
    {
        $manager = User::factory()->create();
        $this->addToEmpresa($manager);
        $manager->givePermissionTo('users.update');
        $assignedRole = Role::findOrCreate('Operador', 'web');
        $requestedRole = Role::findOrCreate('Supervisor', 'web');
        $user = User::factory()->create();
        $this->addToEmpresa($user);
        $user->assignRole($assignedRole);

        $this->actingAs($manager)
            ->from(route('empresas.users.index'))
            ->put(route('empresas.users.update', ['user' => $user]), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => null,
                'password_confirmation' => null,
                'roles' => [$requestedRole->id],
            ])
            ->assertSessionHasErrors('roles')
            ->assertRedirect(route('empresas.users.index'));

        $user->refresh();

        $this->assertTrue($user->hasExactRoles([$assignedRole]));
        $this->assertNotSame('Intento de reasignacion', $user->name);
        $this->assertNotSame('intento-reasignacion@example.com', $user->email);
    }

    public function test_a_manager_cannot_delete_the_last_administrator(): void
    {
        $manager = User::factory()->create();
        $this->addToEmpresa($manager);
        $manager->givePermissionTo('users.delete');

        $this->actingAs($manager)
            ->delete(route('empresas.users.destroy', ['user' => $this->administrator]))
            ->assertForbidden();

        $this->assertModelExists($this->administrator);
    }

    public function test_company_administrator_cannot_delete_another_company_administrator(): void
    {
        $otherAdministrator = User::factory()->create();
        $this->addToEmpresa($otherAdministrator);
        $otherAdministrator->assignRole('Administrador');

        $this->actingAs($this->administrator)
            ->delete(route('empresas.users.destroy', ['user' => $otherAdministrator]))
            ->assertForbidden();

        $this->assertModelExists($otherAdministrator);
        $this->assertDatabaseHas('membresias_empresa', [
            'empresa_id' => $this->empresa->id,
            'user_id' => $otherAdministrator->id,
        ]);
    }

    private function addToEmpresa(User $user): User
    {
        MembresiaEmpresa::factory()->for($this->empresa)->for($user)->create();

        return $user;
    }
}
