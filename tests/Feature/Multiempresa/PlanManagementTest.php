<?php

namespace Tests\Feature\Multiempresa;

use App\IconoPlan;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_superadministrator_can_manage_plan_catalog(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        $regularUser = User::factory()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($regularUser)->get(route('platform.planes.index'))->assertForbidden();
        $this->actingAs($regularUser)->post(route('platform.planes.store'), $this->validPlanData())->assertForbidden();
        $this->actingAs($regularUser)->put(route('platform.planes.update', $plan), $this->validPlanData())->assertForbidden();
        $this->actingAs($regularUser)->delete(route('platform.planes.destroy', $plan))->assertForbidden();

        $plan->delete();
        $this->actingAs($regularUser)->patch(route('platform.planes.restore', $plan))->assertForbidden();

        $this->actingAs($administrator)
            ->get(route('platform.planes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/planes/index')
                ->has('planes.data', 0)
                ->has('iconos', 20),
            );
    }

    public function test_platform_superadministrator_can_create_normalized_plan(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();

        $this->actingAs($administrator)
            ->post(route('platform.planes.store'), $this->validPlanData([
                'nombre' => '  Plan   Crecimiento  ',
                'color' => '#a1b2c3',
                'modulos_incluidos' => "  Usuarios y roles\nPuestos y salarios  ",
                'limite_usuarios' => '',
            ]))
            ->assertRedirect(route('platform.planes.index'))
            ->assertSessionHasNoErrors();

        $plan = Plan::query()->where('nombre', 'Plan Crecimiento')->firstOrFail();

        $this->assertSame('1499.90', $plan->precio_mensual);
        $this->assertSame('#A1B2C3', $plan->color);
        $this->assertSame(IconoPlan::Rocket, $plan->icono);
        $this->assertSame("Usuarios y roles\nPuestos y salarios", $plan->modulos_incluidos);
        $this->assertNull($plan->limite_usuarios);
        $this->assertSame(15, $plan->periodo_gracia_dias);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'planes',
            'event' => 'created',
            'subject_id' => $plan->id,
        ]);
    }

    public function test_platform_superadministrator_can_update_archive_and_restore_plan(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        $plan = Plan::factory()->create(['nombre' => 'Inicial']);
        $indexWithContext = route('platform.planes.index', [
            'search' => 'Inicial',
            'activo' => 1,
            'per_page' => 25,
            'page' => 2,
        ]);

        $this->actingAs($administrator)
            ->from($indexWithContext)
            ->put(route('platform.planes.update', $plan), $this->validPlanData([
                'nombre' => 'Plan Empresa',
                'limite_usuarios' => 20,
                'periodo_gracia_dias' => 21,
                'activo' => false,
            ]))
            ->assertRedirect($indexWithContext)
            ->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('Plan Empresa', $plan->nombre);
        $this->assertSame(20, $plan->limite_usuarios);
        $this->assertSame(21, $plan->periodo_gracia_dias);
        $this->assertFalse($plan->activo);

        $this->actingAs($administrator)
            ->from(route('platform.planes.index'))
            ->delete(route('platform.planes.destroy', $plan))
            ->assertRedirect(route('platform.planes.index'));
        $this->assertSoftDeleted($plan);

        $this->actingAs($administrator)
            ->from(route('platform.planes.index', ['archivados' => true]))
            ->patch(route('platform.planes.restore', $plan))
            ->assertRedirect(route('platform.planes.index', ['archivados' => true]));
        $this->assertNotSoftDeleted($plan);
    }

    public function test_plan_validation_rejects_invalid_and_duplicate_values_including_archived_names(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        $archivedPlan = Plan::factory()->create(['nombre' => 'Reservado']);
        $archivedPlan->delete();

        $this->actingAs($administrator)
            ->post(route('platform.planes.store'), $this->validPlanData([
                'nombre' => 'Reservado',
                'precio_mensual' => -1,
                'color' => 'morado',
                'icono' => 'UnknownOutlined',
                'modulos_incluidos' => str_repeat('x', 4001),
                'limite_usuarios' => 0,
                'periodo_gracia_dias' => 91,
            ]))
            ->assertSessionHasErrors([
                'nombre',
                'precio_mensual',
                'color',
                'icono',
                'modulos_incluidos',
                'limite_usuarios',
                'periodo_gracia_dias',
            ]);
    }

    public function test_plan_listing_filters_archived_records_and_preserves_query_across_pages(): void
    {
        $administrator = User::factory()->superadministradorPlataforma()->create();
        Plan::factory()->count(12)->create([
            'modulos_incluidos' => 'Usuarios y roles',
            'activo' => true,
        ]);
        Plan::factory()->count(4)->create([
            'modulos_incluidos' => 'Solo otro módulo',
            'activo' => false,
        ]);
        Plan::factory()->create(['nombre' => 'Archivado'])->delete();

        $this->actingAs($administrator)
            ->get(route('platform.planes.index', [
                'search' => 'Usuarios',
                'activo' => 1,
                'per_page' => 5,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Usuarios')
                ->where('filters.activo', true)
                ->where('planes.current_page', 2)
                ->where('planes.per_page', 5)
                ->where('planes.total', 12)
                ->where('planes.links', fn (mixed $links): bool => collect($links)->contains(
                    fn (mixed $link): bool => is_array($link)
                        && $this->urlContainsQuery($link['url'] ?? null, [
                            'search' => 'Usuarios',
                            'activo' => '1',
                            'per_page' => '5',
                        ]),
                )),
            );

        $this->actingAs($administrator)
            ->get(route('platform.planes.index', ['archivados' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('planes.data', 1)
                ->where('planes.data.0.nombre', 'Archivado')
                ->where('filters.archivados', true),
            );
    }

    public function test_cashier_bills_companies_and_keeps_trial_disabled_by_configuration(): void
    {
        $empresa = Empresa::factory()->create([
            'nombre_legal' => 'Empresa Facturable SA de CV',
            'nombre_comercial' => 'Empresa Facturable',
            'correo_contacto' => 'cobranza@example.com',
            'telefono_contacto' => '5555555555',
        ]);

        $this->assertSame(Empresa::class, Cashier::$customerModel);
        $this->assertContains(Billable::class, class_uses_recursive(Empresa::class));
        $this->assertSame('Empresa Facturable', $empresa->stripeName());
        $this->assertSame('cobranza@example.com', $empresa->stripeEmail());
        $this->assertSame('+525555555555', $empresa->stripePhone());
        $this->assertNull($empresa->trial_ends_at);
        $this->assertTrue(Schema::hasColumns('empresas', ['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']));
        $this->assertTrue(Schema::hasColumn('subscriptions', 'empresa_id'));
        $this->assertFalse(Schema::hasColumn('subscriptions', 'user_id'));
        $this->assertFalse(Schema::hasColumn('plans', 'trial_ends_at'));
        $this->assertSame('mxn', config('cashier.currency'));
        $this->assertSame('es_MX', config('cashier.currency_locale'));
    }

    /** @param array<string, mixed> $overrides */
    private function validPlanData(array $overrides = []): array
    {
        return [
            'nombre' => 'Plan Crecimiento',
            'precio_mensual' => '1499.90',
            'color' => '#7C3AED',
            'icono' => IconoPlan::Rocket->value,
            'modulos_incluidos' => "Usuarios y roles\nPuestos y salarios\nEmpleados",
            'limite_usuarios' => null,
            'periodo_gracia_dias' => 15,
            'activo' => true,
            ...$overrides,
        ];
    }
}
