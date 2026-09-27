<?php

namespace Tests\Feature\PermisosLaborales;

use App\Enums\PermisosLaborales\EstadoPermisoLaboral;
use App\Jobs\PermisosLaborales\EnviarNotificacionPermisoLaboral;
use App\Jobs\PermisosLaborales\VerificarPermisosLaboralesVencidos;
use App\Mail\PermisosLaborales\NotificacionPermisoLaboralMail;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\PermisosLaborales\PermisoLaboral;
use App\Models\PermisosLaborales\TipoPermiso;
use App\Models\User;
use App\Services\Empresas\ObtenerAdministradoresEmpresa;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PermisoLaboralWorkflowTest extends TestCase
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
        MembresiaEmpresa::factory()->for($this->empresa)->for($this->administrator)->create();
        setPermissionsTeamId($this->empresa->id);
        $this->administrator->assignRole('Administrador');
        $this->withEmpresaContext($this->empresa);
    }

    public function test_user_without_permission_to_request_for_others_can_select_optional_coverage(): void
    {
        $usuario = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($usuario)->create();
        setPermissionsTeamId($this->empresa->id);
        $usuario->givePermissionTo(['permisos_laborales.view', 'permisos_laborales.create']);

        $empleadoSolicitante = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $usuario->id,
        ]);
        $empleadosCobertura = collect(range(1, 21))
            ->map(fn (int $index): Empleado => Empleado::factory()->create([
                'empresa_id' => $this->empresa->id,
                'nombre' => "Persona de cobertura {$index}",
            ]));
        $empleadoCobertura = $empleadosCobertura->last();
        $tipoPermiso = TipoPermiso::factory()->for($this->empresa)->create();

        $this->actingAs($usuario)
            ->get(route('empresas.permisos-laborales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('empleados', [[
                    'id' => $empleadoSolicitante->id,
                    'nombre' => $empleadoSolicitante->nombre,
                ]])
                ->where('empleadosCobertura', fn (array $items): bool => count($items) === 22
                    && in_array($empleadoCobertura->id, array_column($items, 'id'), true),
                ),
            );

        $fecha = now($this->empresa->zona_horaria)->addDay()->toDateString();

        $this->actingAs($usuario)
            ->post(route('empresas.permisos-laborales.store'), [
                'tipo_permiso_id' => $tipoPermiso->id,
                'fecha_inicio' => $fecha,
                'fecha_fin' => $fecha,
                'empleados_cubre_ids' => $empleadosCobertura->pluck('id')->all(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.permisos-laborales.index'));

        $permiso = PermisoLaboral::query()->sole();
        $this->assertSame($empleadoSolicitante->id, $permiso->empleado_id);
        $this->assertDatabaseHas('permiso_laboral_empleado_cubre', [
            'empresa_id' => $this->empresa->id,
            'permiso_laboral_id' => $permiso->id,
            'empleado_id' => $empleadoCobertura->id,
        ]);
        $this->assertSame(21, $permiso->empleadosCobertura()->count());
    }

    public function test_reviewer_can_reject_a_pending_request_only_with_a_reason(): void
    {
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $permiso = PermisoLaboral::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'estado' => EstadoPermisoLaboral::Pendiente,
        ]);

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.rechazar', $permiso), [])
            ->assertSessionHasErrors('comentarios_rechazo');

        $this->assertSame(EstadoPermisoLaboral::Pendiente, $permiso->fresh()->estado);

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.rechazar', $permiso), [
                'comentarios_rechazo' => 'Falta cobertura para ese turno.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.permisos-laborales.index'));

        $this->assertDatabaseHas('permisos_laborales', [
            'id' => $permiso->id,
            'estado' => EstadoPermisoLaboral::Rechazado->value,
            'comentarios_rechazo' => 'Falta cobertura para ese turno.',
            'resuelto_por_user_id' => $this->administrator->id,
        ]);
    }

    public function test_reviewer_can_authorize_a_pending_request_and_other_users_cannot_resolve_it(): void
    {
        $usuario = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($usuario)->create();
        setPermissionsTeamId($this->empresa->id);
        $usuario->givePermissionTo(['permisos_laborales.view', 'permisos_laborales.create']);

        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $usuario->id,
        ]);
        $saldoVacacional = $empleado->dias_vacaciones;
        $permiso = PermisoLaboral::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'solicitante_user_id' => $usuario->id,
            'solicitante_nombre' => $usuario->name,
            'solicitante_correo' => $usuario->email,
            'estado' => EstadoPermisoLaboral::Pendiente,
        ]);

        $this->actingAs($usuario)
            ->post(route('empresas.permisos-laborales.autorizar', $permiso))
            ->assertForbidden();

        $this->assertSame(EstadoPermisoLaboral::Pendiente, $permiso->fresh()->estado);

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.autorizar', $permiso))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.permisos-laborales.index'));

        $this->assertDatabaseHas('permisos_laborales', [
            'id' => $permiso->id,
            'estado' => EstadoPermisoLaboral::Autorizado->value,
            'resuelto_por_user_id' => $this->administrator->id,
        ]);
        $this->assertNotNull($permiso->fresh()->resuelto_at);
        $this->assertSame($saldoVacacional, $empleado->fresh()->dias_vacaciones);
    }

    public function test_requester_with_review_permission_cannot_resolve_their_own_request(): void
    {
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $permiso = PermisoLaboral::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'solicitante_user_id' => $this->administrator->id,
            'solicitante_nombre' => $this->administrator->name,
            'solicitante_correo' => $this->administrator->email,
            'estado' => EstadoPermisoLaboral::Pendiente,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('empresas.permisos-laborales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permisos.data.0.id', $permiso->id)
                ->where('permisos.data.0.puedeResolver', false),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.autorizar', $permiso))
            ->assertForbidden();

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.rechazar', $permiso), [
                'comentarios_rechazo' => 'No procede.',
            ])
            ->assertForbidden();

        $this->assertSame(EstadoPermisoLaboral::Pendiente, $permiso->fresh()->estado);

        $otroAdministrador = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($otroAdministrador)->create();
        setPermissionsTeamId($this->empresa->id);
        $otroAdministrador->assignRole('Administrador');

        $this->actingAs($otroAdministrador)
            ->post(route('empresas.permisos-laborales.autorizar', $permiso))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.permisos-laborales.index'));

        $this->assertDatabaseHas('permisos_laborales', [
            'id' => $permiso->id,
            'estado' => EstadoPermisoLaboral::Autorizado->value,
            'resuelto_por_user_id' => $otroAdministrador->id,
        ]);
    }

    public function test_list_filters_and_pagination_keep_context_out_of_the_visible_url(): void
    {
        for ($index = 1; $index <= 16; $index++) {
            $empleado = Empleado::factory()->create([
                'empresa_id' => $this->empresa->id,
                'nombre' => "Empleado de solicitudes {$index}",
            ]);
            PermisoLaboral::factory()->create([
                'empresa_id' => $this->empresa->id,
                'empleado_id' => $empleado->id,
                'solicitante_nombre' => 'Solicitante compartido',
                'fecha_inicio' => now($this->empresa->zona_horaria)->addDays($index)->toDateString(),
                'fecha_fin' => now($this->empresa->zona_horaria)->addDays($index)->toDateString(),
                'estado' => EstadoPermisoLaboral::Pendiente,
            ]);
        }

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.filtros'), [
                'search' => '  Solicitante   compartido  ',
                'estado' => EstadoPermisoLaboral::Pendiente->value,
            ])
            ->assertRedirect(route('empresas.permisos-laborales.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.permisos-laborales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Solicitante compartido')
                ->where('filters.estado', EstadoPermisoLaboral::Pendiente->value)
                ->where('permisos.current_page', 1)
                ->where('permisos.per_page', 15)
                ->where('permisos.total', 16),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.pagina'), [
                'page' => 2,
                'listado' => 'solicitudes',
            ])
            ->assertRedirect(route('empresas.permisos-laborales.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.permisos-laborales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Solicitante compartido')
                ->where('filters.estado', EstadoPermisoLaboral::Pendiente->value)
                ->where('permisos.current_page', 2)
                ->where('permisos.total', 16)
                ->has('permisos.data', 1),
            );
    }

    public function test_reviewer_cannot_resolve_a_permission_from_another_company(): void
    {
        $otraEmpresa = Empresa::factory()->activa()->create();
        $empleado = Empleado::factory()->create(['empresa_id' => $otraEmpresa->id]);
        $permiso = PermisoLaboral::factory()->create([
            'empresa_id' => $otraEmpresa->id,
            'empleado_id' => $empleado->id,
            'estado' => EstadoPermisoLaboral::Pendiente,
        ]);

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.autorizar', $permiso))
            ->assertForbidden();

        $this->assertSame(EstadoPermisoLaboral::Pendiente, $permiso->fresh()->estado);
    }

    public function test_resolution_email_goes_to_applicant_and_employee_and_copies_company_administrators(): void
    {
        $usuario = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($usuario)->create();
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $usuario->id,
        ]);
        $permiso = PermisoLaboral::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'solicitante_user_id' => $usuario->id,
            'solicitante_nombre' => $usuario->name,
            'solicitante_correo' => $usuario->email,
            'estado' => EstadoPermisoLaboral::Autorizado,
        ]);

        Mail::fake();

        (new EnviarNotificacionPermisoLaboral($permiso->id, 'resolucion'))
            ->handle(app(ObtenerAdministradoresEmpresa::class));

        Mail::assertSent(NotificacionPermisoLaboralMail::class, fn (NotificacionPermisoLaboralMail $mail): bool => $mail->hasTo($usuario->email)
            && $mail->hasTo($empleado->correo)
            && $mail->hasCc($this->administrator->email),
        );
    }

    public function test_authorized_requests_ending_before_the_company_today_become_expired(): void
    {
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $fechaFin = now($this->empresa->zona_horaria)->subDay()->toDateString();
        $permiso = PermisoLaboral::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'fecha_inicio' => now($this->empresa->zona_horaria)->subDays(2)->toDateString(),
            'fecha_fin' => $fechaFin,
            'estado' => EstadoPermisoLaboral::Autorizado,
        ]);

        (new VerificarPermisosLaboralesVencidos)->handle();

        $this->assertDatabaseHas('permisos_laborales', [
            'id' => $permiso->id,
            'estado' => EstadoPermisoLaboral::Vencido->value,
        ]);
    }
}
