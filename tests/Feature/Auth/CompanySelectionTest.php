<?php

namespace Tests\Feature\Auth;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\EstadoMembresiaEmpresa;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Modulo;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanySelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_normal_user_selects_company_after_login(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->activa()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('empresa-contexto.create'));

        $this->get(route('empresa-contexto.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/seleccionar-empresa')
                ->has('empresas', 1)
                ->where('empresas.0.id', $empresa->id));
    }

    public function test_platform_administrator_skips_company_selection_after_login(): void
    {
        $user = User::factory()->superadministradorPlataforma()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse(session()->has(EmpresaContext::SESSION_KEY));
    }

    public function test_selector_only_lists_active_accessible_memberships(): void
    {
        $user = User::factory()->create();
        $allowed = Empresa::factory()->activa()->create();
        $suspended = Empresa::factory()->activa()->create();
        $expired = Empresa::factory()->vencida()->create();
        $unrelated = Empresa::factory()->activa()->create();
        MembresiaEmpresa::factory()->for($allowed)->for($user)->create();
        MembresiaEmpresa::factory()->suspendida()->for($suspended)->for($user)->create();
        MembresiaEmpresa::factory()->for($expired)->for($user)->create();

        $this->actingAs($user)
            ->get(route('empresa-contexto.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('empresas', 1)
                ->where('empresas.0.id', $allowed->id)
                ->where('empresas', fn (mixed $companies): bool => collect($companies)
                    ->pluck('id')
                    ->intersect([$suspended->id, $expired->id, $unrelated->id])
                    ->isEmpty()));
    }

    public function test_selected_company_persists_across_flat_company_routes(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $user = $this->companyAdministrator($empresa);

        $this->actingAs($user)
            ->post(route('empresa-contexto.store'), ['empresa_id' => $empresa->id])
            ->assertRedirect(route('empresas.inicio'))
            ->assertSessionHas(EmpresaContext::SESSION_KEY, $empresa->id);

        $this->get(route('empresas.puestos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('puestos/index')
                ->where('empresas.activa.id', $empresa->id));

        $this->assertSame('/puestos', route('empresas.puestos.index', absolute: false));
        $this->assertStringNotContainsString($empresa->slug, route('empresas.inicio', absolute: false));
    }

    public function test_user_cannot_select_unrelated_company_or_clear_context(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->activa()->create();

        $this->actingAs($user)
            ->post(route('empresa-contexto.store'), ['empresa_id' => $empresa->id])
            ->assertForbidden();
        $this->assertFalse(session()->has(EmpresaContext::SESSION_KEY));

        $this->delete(route('empresa-contexto.destroy'))->assertForbidden();
    }

    public function test_company_routes_require_session_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('empresas.puestos.index'))
            ->assertRedirect(route('empresa-contexto.create'));
    }

    public function test_platform_administrator_can_use_every_company_module_and_return_to_platform(): void
    {
        $empresa = Empresa::factory()->vencida()->create();
        $positionModule = Modulo::query()->where('clave', 'puestos')->firstOrFail();
        $empresa->modulos()->updateExistingPivot($positionModule->id, ['habilitado' => false]);
        $user = User::factory()->superadministradorPlataforma()->create();

        $this->actingAs($user)
            ->post(route('empresa-contexto.store'), ['empresa_id' => $empresa->id])
            ->assertRedirect(route('empresas.inicio'));

        $this->get(route('empresas.puestos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('empresas.activa.id', $empresa->id)
                ->where('empresas.activa.modulos', ['usuarios', 'roles', 'puestos', 'empleados']));

        $this->delete(route('empresa-contexto.destroy'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing(EmpresaContext::SESSION_KEY);
        $this->assertFalse(session()->has('inertia.flash_data'));

        $this->get(route('platform.permisos.index'))->assertOk();
        $this->get(route('platform.usuarios.index'))->assertOk();
    }

    private function companyAdministrator(Empresa $empresa): User
    {
        $role = app(CrearRolesPredeterminadosEmpresa::class)->handle($empresa);
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create([
            'estado' => EstadoMembresiaEmpresa::Activa,
        ]);
        setPermissionsTeamId($empresa->id);
        $user->assignRole($role);

        return $user;
    }
}
