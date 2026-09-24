<?php

namespace Tests\Feature\Platform;

use App\Jobs\SendEmailVerificationEmail;
use App\Models\Empresa;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_platform_administrator_can_create_company_administrator_without_active_company(): void
    {
        Queue::fake();
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $empresa = Empresa::factory()->activa()->create();
        $password = 'Secure-password1!';

        $this->actingAs($platformAdministrator)
            ->post(route('platform.usuarios.store'), [
                'empresa_id' => $empresa->id,
                'name' => '  Administrador   Inicial  ',
                'email' => ' ADMINISTRADOR@EXAMPLE.COM ',
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertRedirect(route('platform.usuarios.index'))
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'administrador@example.com')->firstOrFail();

        $this->assertSame('Administrador Inicial', $user->name);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertDatabaseHas('membresias_empresa', [
            'empresa_id' => $empresa->id,
            'user_id' => $user->id,
        ]);

        setPermissionsTeamId($empresa->id);
        $this->assertTrue($user->fresh()->hasRole('Administrador'));
        setPermissionsTeamId(null);

        $this->assertNull(getPermissionsTeamId());
        Queue::assertPushed(
            SendEmailVerificationEmail::class,
            fn (SendEmailVerificationEmail $job): bool => $job->email === $user->email,
        );
    }

    public function test_platform_user_creation_rejects_duplicate_email_and_unknown_company(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $existing = User::factory()->create();
        $password = 'Secure-password1!';

        $this->actingAs($platformAdministrator)
            ->post(route('platform.usuarios.store'), [
                'empresa_ids' => [999999],
                'name' => 'Administrador inválido',
                'email' => $existing->email,
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertSessionHasErrors(['empresa_ids.0', 'email']);
    }

    public function test_platform_administrator_can_assign_one_user_to_multiple_companies(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $firstCompany = Empresa::factory()->activa()->create();
        $secondCompany = Empresa::factory()->activa()->create();
        $password = 'Secure-password1!';

        $this->actingAs($platformAdministrator)
            ->post(route('platform.usuarios.store'), [
                'empresa_ids' => [$firstCompany->id, $secondCompany->id],
                'name' => 'Administrador Multiempresa',
                'email' => 'multiempresa@example.com',
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertRedirect(route('platform.usuarios.index'))
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'multiempresa@example.com')->firstOrFail();

        $this->assertDatabaseCount('membresias_empresa', 2);

        foreach ([$firstCompany, $secondCompany] as $company) {
            setPermissionsTeamId($company->id);
            $this->assertTrue($user->fresh()->hasRole('Administrador'));
        }

        setPermissionsTeamId(null);
    }

    public function test_platform_created_company_administrator_appears_in_company_user_catalog(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $companyAdministrator = User::factory()->create();
        $empresa = Empresa::factory()->activa()->create();
        $this->addAdministratorMembership($companyAdministrator, $empresa);

        $this->actingAs($platformAdministrator)
            ->post(route('platform.usuarios.store'), [
                'empresa_ids' => [$empresa->id],
                'name' => 'Administrador Asignado',
                'email' => 'administrador-asignado@example.com',
                'password' => 'Secure-password1!',
                'password_confirmation' => 'Secure-password1!',
            ])
            ->assertRedirect(route('platform.usuarios.index'))
            ->assertSessionHasNoErrors();

        $this->withEmpresaContext($empresa)
            ->actingAs($companyAdministrator)
            ->get(route('empresas.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/index')
                ->where('users.data', fn (mixed $users): bool => collect($users)
                    ->contains(fn (array $user): bool => $user['email'] === 'administrador-asignado@example.com')),
            );
    }

    public function test_platform_administrator_can_update_and_delete_user_from_platform_catalog(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $firstCompany = Empresa::factory()->activa()->create();
        $secondCompany = Empresa::factory()->activa()->create();
        $backupAdministrator = User::factory()->create();
        $target = User::factory()->create();

        foreach ([$firstCompany, $secondCompany] as $company) {
            $this->addAdministratorMembership($backupAdministrator, $company);
            $this->addAdministratorMembership($target, $company);
        }

        $this->actingAs($platformAdministrator)
            ->from(route('platform.usuarios.index', ['search' => 'target']))
            ->put(route('platform.usuarios.update', $target), [
                'empresa_ids' => [$firstCompany->id],
                'name' => 'Usuario Actualizado',
                'email' => 'target-updated@example.com',
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertRedirect(route('platform.usuarios.index', ['search' => 'target']))
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('Usuario Actualizado', $target->name);
        $this->assertSame('target-updated@example.com', $target->email);
        $this->assertDatabaseHas('membresias_empresa', [
            'empresa_id' => $firstCompany->id,
            'user_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('membresias_empresa', [
            'empresa_id' => $secondCompany->id,
            'user_id' => $target->id,
        ]);

        $this->actingAs($platformAdministrator)
            ->from(route('platform.usuarios.index'))
            ->delete(route('platform.usuarios.destroy', $target))
            ->assertRedirect(route('platform.usuarios.index'))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($target);
        $this->assertDatabaseMissing('membresias_empresa', ['user_id' => $target->id]);
    }

    public function test_platform_administrator_cannot_remove_last_company_administrator(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $company = Empresa::factory()->activa()->create();
        $target = User::factory()->create();
        $this->addAdministratorMembership($target, $company);

        $this->actingAs($platformAdministrator)
            ->from(route('platform.usuarios.index'))
            ->delete(route('platform.usuarios.destroy', $target))
            ->assertRedirect(route('platform.usuarios.index'))
            ->assertSessionHasErrors('roles');

        $this->assertModelExists($target);
    }

    public function test_platform_user_listing_exposes_password_rules_and_requires_platform_access(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)
            ->post(route('platform.usuarios.store'), [])
            ->assertForbidden();

        $this->actingAs($platformAdministrator)
            ->get(route('platform.usuarios.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/usuarios/index')
                ->where('passwordRules', fn (mixed $rules): bool => is_string($rules) && $rules !== '')
                ->has('empresas.disponibles'),
            );

        $this->assertDatabaseCount('membresias_empresa', 0);
    }

    private function addAdministratorMembership(User $user, Empresa $empresa): void
    {
        $role = $empresa->roles()->firstOrCreate([
            'name' => 'Administrador',
            'guard_name' => 'web',
        ]);

        $user->membresiasEmpresa()->create([
            'empresa_id' => $empresa->id,
            'estado' => 'ACTIVA',
            'fecha_incorporacion' => now(),
        ]);

        setPermissionsTeamId($empresa->id);
        $user->assignRole($role);
        setPermissionsTeamId(null);
    }
}
