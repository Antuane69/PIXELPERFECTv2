<?php

namespace Tests\Feature\Multiempresa;

use App\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\MembresiaEmpresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmpresaContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_access_company_context(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->activa()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

        $this->actingAs($user)
            ->get(route('empresas.inicio', $empresa))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empresas/inicio')
                ->where('empresa.id', $empresa->id)
                ->where('empresas.activa.id', $empresa->id)
                ->where('empresas.activa.grupo.id', $empresa->grupo_empresarial_id)
                ->has('empresas.disponibles', 1)
                ->where('empresas.disponibles.0.id', $empresa->id),
            );
    }

    public function test_user_with_multiple_companies_can_change_explicit_context(): void
    {
        $user = User::factory()->create();
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        MembresiaEmpresa::factory()->for($first)->for($user)->create();
        MembresiaEmpresa::factory()->for($second)->for($user)->create();

        $this->actingAs($user)
            ->get(route('empresas.inicio', $second))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('empresas.activa.id', $second->id)
                ->has('empresas.disponibles', 2),
            );
    }

    public function test_membership_in_company_does_not_grant_access_to_sibling_company_in_same_group(): void
    {
        $user = User::factory()->create();
        $group = GrupoEmpresarial::factory()->corporativo()->create();
        $allowed = Empresa::factory()->activa()->for($group, 'grupoEmpresarial')->create();
        $forbidden = Empresa::factory()->activa()->for($group, 'grupoEmpresarial')->create();
        MembresiaEmpresa::factory()->for($allowed)->for($user)->create();

        $this->actingAs($user)
            ->get(route('empresas.inicio', $forbidden))
            ->assertNotFound();
    }

    public function test_suspended_membership_is_forbidden_and_not_shared_as_available(): void
    {
        $user = User::factory()->create();
        $suspended = Empresa::factory()->activa()->create();
        $allowed = Empresa::factory()->activa()->create();
        MembresiaEmpresa::factory()->suspendida()->for($suspended)->for($user)->create();
        MembresiaEmpresa::factory()->for($allowed)->for($user)->create();

        $this->actingAs($user)
            ->get(route('empresas.inicio', $suspended))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('empresas.inicio', $allowed))
            ->assertInertia(fn (Assert $page) => $page
                ->has('empresas.disponibles', 1)
                ->where('empresas.disponibles.0.id', $allowed->id),
            );
    }

    public function test_inactive_or_expired_company_is_forbidden(): void
    {
        $user = User::factory()->create();
        $vencida = Empresa::factory()->vencida()->create();
        $demoExpirada = Empresa::factory()->create([
            'estado' => EstadoEmpresa::Demo,
            'demo_ends_at' => now()->subMinute(),
        ]);

        foreach ([$vencida, $demoExpirada] as $empresa) {
            MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

            $this->actingAs($user)
                ->get(route('empresas.inicio', $empresa))
                ->assertForbidden();
        }
    }

    public function test_props_never_expose_unrelated_company(): void
    {
        $user = User::factory()->create();
        $allowed = Empresa::factory()->activa()->create();
        $unrelated = Empresa::factory()->activa()->create();
        MembresiaEmpresa::factory()->for($allowed)->for($user)->create();

        $this->actingAs($user)
            ->get(route('empresas.inicio', $allowed))
            ->assertInertia(fn (Assert $page) => $page
                ->has('empresas.disponibles', 1)
                ->where('empresas.disponibles.0.id', $allowed->id)
                ->where('empresas.disponibles.0.id', fn (mixed $id): bool => $id !== $unrelated->id),
            );
    }

    public function test_unknown_company_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app/no-existe')
            ->assertNotFound();
    }
}
