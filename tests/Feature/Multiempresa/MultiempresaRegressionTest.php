<?php

namespace Tests\Feature\Multiempresa;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\EstadoMembresiaEmpresa;
use App\Jobs\SendEmailVerificationEmail;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Role;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use App\Services\Reportes\Definiciones\RolesReporte;
use App\Services\Reportes\Definiciones\UsuariosReporte;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MultiempresaRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();
    }

    #[DataProvider('identityChanges')]
    public function test_company_administrator_cannot_change_global_identity(string $targetType, string $field): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $actor = $this->administrator($empresa);
        $target = User::factory()->create(['es_superadministrador_plataforma' => $targetType === 'platform']);
        $this->membership($empresa, $target);
        if ($targetType === 'shared') {
            $this->membership(Empresa::factory()->activa()->create(), $target);
        }
        $before = $target->fresh()->getAttributes();
        $changes = ['name' => 'Changed Name', 'email' => 'changed@example.com', 'password' => 'Changed-password1!'];
        $this->withEmpresaContext($empresa)
            ->actingAs($actor)->put(route('empresas.users.update', ['user' => $target]), [
                $field => $changes[$field], 'password_confirmation' => 'Changed-password1!',
            ])->assertSessionHasErrors($field);
        $target->refresh();
        foreach (['name', 'email', 'password', 'email_verified_at', 'es_superadministrador_plataforma'] as $attribute) {
            $this->assertSame($before[$attribute], $target->getRawOriginal($attribute));
        }
        Queue::assertNothingPushed();
    }

    /** @return iterable<string, array{string, string}> */
    public static function identityChanges(): iterable
    {
        foreach (['platform', 'shared', 'ordinary'] as $target) {
            foreach (['name', 'email', 'password'] as $field) {
                yield $target.'-'.$field => [$target, $field];
            }
        }
    }

    public function test_company_administrator_can_update_only_local_roles_of_shared_user(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $other = Empresa::factory()->activa()->create();
        $actor = $this->administrator($empresa);
        $target = User::factory()->create();
        $this->membership($empresa, $target);
        $this->membership($other, $target);
        setPermissionsTeamId($other->id);
        $foreignRole = Role::create(['empresa_id' => $other->id, 'name' => 'Foreign', 'guard_name' => 'web']);
        $target->assignRole($foreignRole);
        setPermissionsTeamId($empresa->id);
        $localRole = Role::create(['empresa_id' => $empresa->id, 'name' => 'Local', 'guard_name' => 'web']);
        $before = $target->fresh()->getAttributes();
        $this->withEmpresaContext($empresa)
            ->actingAs($actor)->put(route('empresas.users.update', ['user' => $target]), [
                'roles' => [$localRole->id],
            ])->assertSessionHasNoErrors()->assertRedirect(route('empresas.users.index'));
        $this->assertDatabaseHas('model_has_roles', ['empresa_id' => $empresa->id, 'role_id' => $localRole->id, 'model_id' => $target->id]);
        $this->assertDatabaseHas('model_has_roles', ['empresa_id' => $other->id, 'role_id' => $foreignRole->id, 'model_id' => $target->id]);
        $this->assertSame($before, $target->fresh()->getAttributes());
        Queue::assertNothingPushed();
    }

    public function test_platform_administrator_can_create_first_company_administrator(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $role = app(CrearRolesPredeterminadosEmpresa::class)->handle($empresa);
        $actor = User::factory()->superadministradorPlataforma()->create();
        $this->withEmpresaContext($empresa)
            ->actingAs($actor)->post(route('empresas.users.store'), [
                'name' => 'First Admin', 'email' => 'first-admin@example.com',
                'password' => 'Secure-password1!', 'password_confirmation' => 'Secure-password1!', 'roles' => [$role->id],
            ])->assertSessionHasNoErrors()->assertRedirect(route('empresas.users.index'));
        $target = User::where('email', 'first-admin@example.com')->firstOrFail();
        $this->assertDatabaseHas('membresias_empresa', ['empresa_id' => $empresa->id, 'user_id' => $target->id, 'estado' => 'ACTIVA']);
        $this->assertDatabaseHas('model_has_roles', ['empresa_id' => $empresa->id, 'model_id' => $target->id, 'role_id' => $role->id]);
        $this->assertFalse($target->es_superadministrador_plataforma);
        Queue::assertPushed(SendEmailVerificationEmail::class, fn (SendEmailVerificationEmail $job): bool => $job->email === $target->email);
    }

    public function test_platform_administrator_can_update_credentials_without_changing_platform_flag(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $actor = User::factory()->superadministradorPlataforma()->create();
        $target = User::factory()->create();
        $this->membership($empresa, $target);

        $this->withEmpresaContext($empresa)
            ->actingAs($actor)->put(route('empresas.users.update', ['user' => $target]), [
                'name' => 'Updated Identity',
                'email' => 'updated-identity@example.com',
                'password' => 'Changed-password1!',
                'password_confirmation' => 'Changed-password1!',
                'es_superadministrador_plataforma' => true,
            ])->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('Updated Identity', $target->name);
        $this->assertSame('updated-identity@example.com', $target->email);
        $this->assertTrue(Hash::check('Changed-password1!', $target->password));
        $this->assertNull($target->email_verified_at);
        $this->assertFalse($target->es_superadministrador_plataforma);
        Queue::assertPushed(SendEmailVerificationEmail::class, fn (SendEmailVerificationEmail $job): bool => $job->email === $target->email);
    }

    public function test_platform_role_management_keeps_company_restrictions(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $administratorRole = app(CrearRolesPredeterminadosEmpresa::class)->handle($empresa);
        $actor = User::factory()->superadministradorPlataforma()->create();
        $view = Permission::findByName('users.view', 'web');
        $edit = Permission::findByName('users.update', 'web');
        $logs = Permission::findByName('logs.view', 'web');
        $this->withEmpresaContext($empresa)
            ->actingAs($actor)->get(route('empresas.roles.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('permissions', fn (mixed $permissions): bool => collect($permissions)->pluck('name')->contains('users.view')
                && ! collect($permissions)->pluck('name')->contains('logs.view')
                && ! collect($permissions)->pluck('name')->contains('logs.delete')));
        $this->post(route('empresas.roles.store'), ['name' => 'Viewer', 'permissions' => [$view->id]])
            ->assertSessionHasNoErrors();
        $role = Role::where('empresa_id', $empresa->id)->where('name', 'Viewer')->firstOrFail();
        $this->put(route('empresas.roles.update', $role), ['name' => 'Editor', 'permissions' => [$view->id, $edit->id]])
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$view->id, $edit->id], $role->fresh()->permissions->modelKeys());
        $this->post(route('empresas.roles.store'), ['name' => 'Forbidden', 'permissions' => [$logs->id]])
            ->assertSessionHasErrors('permissions.0');
        $this->put(route('empresas.roles.update', $role), ['name' => 'Forbidden update', 'permissions' => [$logs->id]])
            ->assertSessionHasErrors('permissions.0');
        $this->put(route('empresas.roles.update', $administratorRole), ['name' => 'Renamed', 'permissions' => [$view->id]])
            ->assertSessionHasErrors('role');
        $this->delete(route('empresas.roles.destroy', $administratorRole))->assertSessionHasErrors('role');
        $this->assertSame('Administrador', $administratorRole->fresh()->name);
    }

    public function test_legacy_exports_only_contain_initial_company_records(): void
    {
        $initial = Empresa::where('slug', 'pixel-perfect')->firstOrFail();
        $actor = $this->administrator($initial);
        $foreign = Empresa::factory()->activa()->create();
        $localUser = User::factory()->create(['name' => 'Audit Local']);
        $foreignUser = User::factory()->create(['name' => 'Audit Foreign']);
        $suspended = User::factory()->create(['name' => 'Audit Suspended']);
        $this->membership($initial, $localUser);
        $this->membership($foreign, $foreignUser);
        $this->membership($initial, $suspended)->update(['estado' => EstadoMembresiaEmpresa::Suspendida]);
        Role::create(['empresa_id' => $initial->id, 'name' => 'Audit Local', 'guard_name' => 'web']);
        Role::create(['empresa_id' => $foreign->id, 'name' => 'Audit Foreign', 'guard_name' => 'web']);
        foreach (['usuarios', 'roles'] as $report) {
            $response = $this->withEmpresaContext($initial)
                ->actingAs($actor)->post(route('reportes.exportar', $report), [
                    'formato' => 'xlsx', 'filtros' => ['search' => 'Audit'],
                ])->assertOk()->assertDownload();
            $file = $response->baseResponse->getFile()->getPathname();
            $spreadsheet = IOFactory::load($file);
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $values = collect($rows)->flatten()->all();
            $this->assertContains('Audit Local', $values);
            $this->assertNotContains('Audit Foreign', $values);
            $this->assertNotContains('Audit Suspended', $values);
            $spreadsheet->disconnectWorksheets();
        }
        $foreignActor = $this->administrator($foreign);
        $this->withEmpresaContext($foreign)
            ->actingAs($foreignActor)
            ->post(route('reportes.exportar', 'usuarios'), ['formato' => 'xlsx'])
            ->assertOk();
    }

    #[DataProvider('reportClasses')]
    public function test_company_reports_fail_without_explicit_context(string $reportClass): void
    {
        app(EmpresaContext::class)->limpiar();
        $this->expectException(LogicException::class);
        app($reportClass)->query([]);
    }

    /** @return iterable<string, array{class-string<RolesReporte>|class-string<UsuariosReporte>}> */
    public static function reportClasses(): iterable
    {
        yield 'roles' => [RolesReporte::class];
        yield 'users' => [UsuariosReporte::class];
    }

    public function test_suspended_administrator_does_not_allow_last_active_administrator_demotion(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $active = $this->administrator($empresa);
        $suspended = $this->administrator($empresa);
        $suspended->membresiasEmpresa()->where('empresa_id', $empresa->id)->update(['estado' => 'SUSPENDIDA']);
        $viewer = Role::create(['empresa_id' => $empresa->id, 'name' => 'Viewer', 'guard_name' => 'web']);
        $this->withEmpresaContext($empresa)
            ->actingAs($active)->put(route('empresas.users.update', ['user' => $active]), ['roles' => [$viewer->id]])
            ->assertSessionHasErrors('roles');
        $this->assertTrue($active->fresh()->hasRole('Administrador'));
        $platform = User::factory()->superadministradorPlataforma()->create();
        $this->withEmpresaContext($empresa)
            ->actingAs($platform)->delete(route('empresas.users.destroy', ['user' => $active]))->assertSessionHasErrors('roles');
        $this->assertDatabaseHas('membresias_empresa', ['empresa_id' => $empresa->id, 'user_id' => $active->id]);
    }

    public function test_profile_deletion_checks_active_administrators_in_every_company(): void
    {
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        $target = $this->administrator($first);
        $this->administrator($first);
        $this->membership($second, $target);
        $secondRole = app(CrearRolesPredeterminadosEmpresa::class)->handle($second);
        setPermissionsTeamId($second->id);
        $target->unsetRelation('roles')->assignRole($secondRole);
        $backup = $this->administrator($second);
        $backup->membresiasEmpresa()->where('empresa_id', $second->id)->update(['estado' => 'SUSPENDIDA']);
        $this->actingAs($target)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('roles');
        $this->assertAuthenticatedAs($target);
        $this->assertModelExists($target);
        $backup->membresiasEmpresa()->where('empresa_id', $second->id)->update(['estado' => 'ACTIVA']);
        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertModelMissing($target);
    }

    public function test_active_backup_allows_company_administrator_demotion(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $active = $this->administrator($empresa);
        $this->administrator($empresa);
        $viewer = Role::create(['empresa_id' => $empresa->id, 'name' => 'Viewer', 'guard_name' => 'web']);
        $this->withEmpresaContext($empresa)
            ->actingAs($active)->put(route('empresas.users.update', ['user' => $active]), ['roles' => [$viewer->id]])
            ->assertSessionHasNoErrors();
        $this->assertTrue($active->fresh()->hasExactRoles([$viewer]));
    }

    public function test_selector_matches_platform_exception_and_normal_company_access(): void
    {
        $empresa = Empresa::factory()->vencida()->create();
        $actor = User::factory()->superadministradorPlataforma()->create();
        $this->actingAs($actor)
            ->post(route('empresa-contexto.store'), ['empresa_id' => $empresa->id])
            ->assertRedirect(route('empresas.inicio'));
        $this->get(route('empresas.inicio'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('empresas.disponibles',
                fn ($companies): bool => collect($companies)->firstWhere('id', $empresa->id)['puede_acceder'] === true));
        $member = User::factory()->create();
        $this->membership($empresa, $member);
        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertRedirect(route('empresa-contexto.create'));
        $this->post(route('empresa-contexto.store'), ['empresa_id' => $empresa->id])->assertForbidden();
    }

    private function administrator(Empresa $empresa): User
    {
        $role = app(CrearRolesPredeterminadosEmpresa::class)->handle($empresa);
        $user = User::factory()->create();
        $this->membership($empresa, $user);
        setPermissionsTeamId($empresa->id);
        $user->assignRole($role);

        return $user;
    }

    private function membership(Empresa $empresa, User $user): MembresiaEmpresa
    {
        return MembresiaEmpresa::factory()->for($empresa)->for($user)->create();
    }
}
