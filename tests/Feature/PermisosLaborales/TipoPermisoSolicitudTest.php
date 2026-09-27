<?php

namespace Tests\Feature\PermisosLaborales;

use App\Jobs\PermisosLaborales\EnviarNotificacionPermisoLaboral;
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

class TipoPermisoSolicitudTest extends TestCase
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

    public function test_new_permission_request_requires_and_records_an_active_company_type(): void
    {
        $tipoPermiso = TipoPermiso::factory()->for($this->empresa)->create([
            'nombre' => 'Cita médica',
        ]);
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $saldoVacacional = $empleado->dias_vacaciones;
        $fechaSolicitud = now($this->empresa->zona_horaria)->addDay()->toDateString();
        $payload = [
            'tipo_permiso_id' => $tipoPermiso->id,
            'empleado_id' => $empleado->id,
            'fecha_inicio' => $fechaSolicitud,
            'fecha_fin' => $fechaSolicitud,
            'comentarios' => 'Consulta médica.',
        ];

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.permisos-laborales.index'));

        $this->assertDatabaseHas('permisos_laborales', [
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'tipo_permiso_id' => $tipoPermiso->id,
            'comentarios' => 'Consulta médica.',
        ]);
        $this->assertSame($saldoVacacional, $empleado->fresh()->dias_vacaciones);
    }

    public function test_inactive_or_foreign_company_type_is_rejected(): void
    {
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $inactivo = TipoPermiso::factory()->for($this->empresa)->inactive()->create();
        $otraEmpresa = Empresa::factory()->activa()->create();
        $tipoAjeno = TipoPermiso::factory()->for($otraEmpresa)->create();
        $fechaSolicitud = now($this->empresa->zona_horaria)->addDay()->toDateString();
        $payload = [
            'empleado_id' => $empleado->id,
            'fecha_inicio' => $fechaSolicitud,
            'fecha_fin' => $fechaSolicitud,
        ];

        foreach ([$inactivo->id, $tipoAjeno->id] as $tipoPermisoId) {
            $this->actingAs($this->administrator)
                ->post(route('empresas.permisos-laborales.store'), [
                    ...$payload,
                    'tipo_permiso_id' => $tipoPermisoId,
                ])
                ->assertSessionHasErrors('tipo_permiso_id');
        }

        $this->assertSame(0, PermisoLaboral::query()->count());
    }

    public function test_permission_type_is_required_for_new_requests(): void
    {
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $fechaSolicitud = now($this->empresa->zona_horaria)->addDay()->toDateString();

        $this->actingAs($this->administrator)
            ->post(route('empresas.permisos-laborales.store'), [
                'empleado_id' => $empleado->id,
                'fecha_inicio' => $fechaSolicitud,
                'fecha_fin' => $fechaSolicitud,
            ])
            ->assertSessionHasErrors('tipo_permiso_id');

        $this->assertSame(0, PermisoLaboral::query()->count());
    }

    public function test_request_email_includes_the_selected_type_and_overlapping_types(): void
    {
        $tipoPermiso = TipoPermiso::factory()->for($this->empresa)->create([
            'nombre' => 'Cita médica',
        ]);
        $tipoCoincidente = TipoPermiso::factory()->for($this->empresa)->create([
            'nombre' => 'Asunto personal',
        ]);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Persona solicitante',
        ]);
        $empleadoCoincidente = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Persona con cruce',
        ]);
        $fechaInicio = '2026-10-05';
        $fechaFin = '2026-10-06';
        $solicitud = PermisoLaboral::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'tipo_permiso_id' => $tipoPermiso->id,
            'solicitante_user_id' => $this->administrator->id,
            'solicitante_nombre' => $this->administrator->name,
            'solicitante_correo' => $this->administrator->email,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]);
        PermisoLaboral::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleadoCoincidente->id,
            'tipo_permiso_id' => $tipoCoincidente->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]);

        Mail::fake();

        (new EnviarNotificacionPermisoLaboral($solicitud->id, 'solicitud'))
            ->handle(app(ObtenerAdministradoresEmpresa::class));

        Mail::assertSent(NotificacionPermisoLaboralMail::class, function (NotificacionPermisoLaboralMail $mail) use ($tipoPermiso, $tipoCoincidente): bool {
            return $mail->tipo === 'solicitud'
                && $mail->hasTo($this->administrator->email)
                && $mail->datos['tipo_permiso'] === $tipoPermiso->nombre
                && ($mail->datos['coincidencias'][0]['tipo_permiso'] ?? null) === $tipoCoincidente->nombre;
        });
    }

    public function test_legacy_requests_without_a_type_remain_readable(): void
    {
        $permisoLaboral = PermisoLaboral::factory()->create([
            'empresa_id' => $this->empresa->id,
            'tipo_permiso_id' => null,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('empresas.permisos-laborales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('permisos-laborales/index')
                ->where('permisos.data.0.id', $permisoLaboral->id)
                ->where('permisos.data.0.tipoPermiso', null),
            );
    }
}
