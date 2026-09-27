<?php

namespace Tests\Feature\FaltasReglamento;

use App\Enums\FaltasReglamento\EstadoFaltaReglamento;
use App\Jobs\FaltasReglamento\EnviarNotificacionFaltaReglamento;
use App\Mail\FaltasReglamento\NotificacionFaltaReglamentoMail;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\MembresiaEmpresa;
use App\Models\Modulo;
use App\Models\Puesto;
use App\Models\User;
use App\Services\Empresas\ObtenerAdministradoresEmpresa;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FaltaReglamentoWorkflowTest extends TestCase
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

    public function test_employee_can_submit_pending_report_with_multiple_evidence_and_reviewer_counts_authorization_once(): void
    {
        Queue::fake();

        $usuario = $this->createCompanyUser(['faltas_reglamento.view', 'faltas_reglamento.create']);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $usuario->id,
        ]);
        $catalogo = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create();
        $pdf = UploadedFile::fake()->createWithContent('evidencia.pdf', "%PDF-1.4\ncontenido de prueba\n%%EOF");
        $imagen = UploadedFile::fake()->image('fotografia.png', 40, 40);

        $this->actingAs($usuario)
            ->get(route('empresas.faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selfEmployeeId', $empleado->id)
                ->where('empleados.0.nombre', $empleado->nombre),
            );

        $this->actingAs($usuario)
            ->post(route('empresas.faltas-reglamento.store'), [
                'tipo_falta_reglamento_id' => $catalogo->tipo_falta_reglamento_id,
                'falta_reglamento_catalogo_id' => $catalogo->id,
                'fecha_ocurrencia' => now($this->empresa->zona_horaria)->subDay()->toDateString(),
                'comentarios' => '  Se registró una situación para revisión.  ',
                'archivos' => [$pdf, $imagen],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.faltas-reglamento.index'));

        $falta = FaltaReglamento::query()->sole();
        Queue::assertPushed(EnviarNotificacionFaltaReglamento::class, fn (EnviarNotificacionFaltaReglamento $job): bool => $job->faltaReglamentoId === $falta->id
            && $job->tipo === 'solicitud',
        );
        $this->assertSame(EstadoFaltaReglamento::Pendiente, $falta->estado);
        $this->assertSame($empleado->id, $falta->empleado_id);
        $this->assertSame($usuario->id, $falta->solicitante_user_id);
        $this->assertSame('Se registró una situación para revisión.', $falta->comentarios);
        $this->assertSame(2, $falta->evidencias()->count());
        $this->assertSame(0, DB::table('empleado_tipo_falta_reglamento')->count());

        $pdfEvidencia = $falta->evidencias()->where('file_extension', 'pdf')->firstOrFail();
        $this->assertSame('evidencia.pdf', $pdfEvidencia->nombre);
        $this->assertSame("%PDF-1.4\ncontenido de prueba\n%%EOF", $pdfEvidencia->archivo);
        $this->assertSame('image/png', $falta->evidencias()->where('mime_type', 'image/png')->sole()->mime_type);
        $this->assertIsArray(getimagesizefromstring($falta->evidencias()->where('mime_type', 'image/png')->sole()->archivo));

        $empleado->delete();

        $this->actingAs($this->administrator)
            ->post(route('empresas.faltas-reglamento.autorizar', $falta))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.faltas-reglamento.index'));

        Queue::assertPushed(EnviarNotificacionFaltaReglamento::class, fn (EnviarNotificacionFaltaReglamento $job): bool => $job->faltaReglamentoId === $falta->id
            && $job->tipo === 'resolucion',
        );

        $this->assertSame(EstadoFaltaReglamento::Autorizada, $falta->fresh()->estado);
        $this->assertDatabaseHas('empleado_tipo_falta_reglamento', [
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'tipo_falta_reglamento_id' => $catalogo->tipo_falta_reglamento_id,
            'cantidad' => 1,
        ]);

        $catalogo->tipoFalta()->delete();

        $this->actingAs($this->administrator)
            ->get(route('empresas.faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('faltas.data.0.conteoTipo', 1),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.faltas-reglamento.autorizar', $falta))
            ->assertForbidden();

        $this->assertDatabaseHas('empleado_tipo_falta_reglamento', [
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'tipo_falta_reglamento_id' => $catalogo->tipo_falta_reglamento_id,
            'cantidad' => 1,
        ]);
    }

    public function test_reporter_and_assigned_employee_can_see_history_and_download_evidence_but_other_users_cannot(): void
    {
        $reporter = $this->createCompanyUser([
            'faltas_reglamento.view',
            'faltas_reglamento.create',
            'faltas_reglamento.create_for_others',
        ]);
        $assignedUser = $this->createCompanyUser(['faltas_reglamento.view']);
        $outsider = $this->createCompanyUser(['faltas_reglamento.view']);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $assignedUser->id,
        ]);
        $catalogo = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create();
        $archivo = UploadedFile::fake()->createWithContent('reporte.pdf', "%PDF-1.4\ncontenido\n%%EOF");

        $this->actingAs($reporter)
            ->post(route('empresas.faltas-reglamento.store'), [
                'empleado_id' => $empleado->id,
                'tipo_falta_reglamento_id' => $catalogo->tipo_falta_reglamento_id,
                'falta_reglamento_catalogo_id' => $catalogo->id,
                'fecha_ocurrencia' => now($this->empresa->zona_horaria)->subDay()->toDateString(),
                'archivos' => [$archivo],
            ])
            ->assertSessionHasNoErrors();

        $falta = FaltaReglamento::query()->sole();
        $evidencia = $falta->evidencias()->sole();

        $this->actingAs($reporter)
            ->get(route('empresas.faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('faltas.total', 1)
                ->where('faltas.data.0.id', $falta->id),
            );

        $empleado->delete();

        $this->actingAs($assignedUser)
            ->get(route('empresas.faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('faltas.total', 1)
                ->where('faltas.data.0.empleado', $empleado->nombre),
            );

        $this->actingAs($outsider)
            ->get(route('empresas.faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('faltas.total', 0));

        $downloadRoute = route('empresas.faltas-reglamento.evidencias.download', [$falta, $evidencia]);
        $download = $this->actingAs($assignedUser)->get($downloadRoute);
        $download->assertOk()
            ->assertDownload('reporte.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('contenido', $download->streamedContent());

        $this->actingAs($outsider)->get($downloadRoute)->assertForbidden();
    }

    public function test_report_rejects_a_catalog_falta_that_does_not_belong_to_the_selected_type(): void
    {
        $usuario = $this->createCompanyUser(['faltas_reglamento.view', 'faltas_reglamento.create']);
        Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $usuario->id,
        ]);
        $tipoSeleccionado = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create();
        $faltaDeOtroTipo = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create();

        $this->actingAs($usuario)
            ->post(route('empresas.faltas-reglamento.store'), [
                'tipo_falta_reglamento_id' => $tipoSeleccionado->tipo_falta_reglamento_id,
                'falta_reglamento_catalogo_id' => $faltaDeOtroTipo->id,
                'fecha_ocurrencia' => now($this->empresa->zona_horaria)->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('falta_reglamento_catalogo_id');

        $this->assertSame(0, FaltaReglamento::query()->count());
    }

    public function test_report_rejects_unsupported_evidence_files(): void
    {
        $usuario = $this->createCompanyUser(['faltas_reglamento.view', 'faltas_reglamento.create']);
        Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $usuario->id,
        ]);
        $catalogo = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create();

        $this->actingAs($usuario)
            ->post(route('empresas.faltas-reglamento.store'), [
                'tipo_falta_reglamento_id' => $catalogo->tipo_falta_reglamento_id,
                'falta_reglamento_catalogo_id' => $catalogo->id,
                'fecha_ocurrencia' => now($this->empresa->zona_horaria)->subDay()->toDateString(),
                'archivos' => [UploadedFile::fake()->createWithContent('script.php', '<?php echo "no permitido";')],
            ])
            ->assertSessionHasErrors('archivos.0');

        $this->assertSame(0, FaltaReglamento::query()->count());
    }

    public function test_report_preserves_requester_name_and_email_with_their_supported_lengths(): void
    {
        $usuario = $this->createCompanyUser(['faltas_reglamento.view', 'faltas_reglamento.create']);
        $nombre = str_repeat('Nombre largo', 20);
        $correo = str_repeat('a', 60).'@'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.test';
        $usuario->forceFill(['name' => $nombre, 'email' => $correo])->save();
        Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $usuario->id,
        ]);
        $catalogo = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create();

        $this->actingAs($usuario)
            ->post(route('empresas.faltas-reglamento.store'), [
                'tipo_falta_reglamento_id' => $catalogo->tipo_falta_reglamento_id,
                'falta_reglamento_catalogo_id' => $catalogo->id,
                'fecha_ocurrencia' => now($this->empresa->zona_horaria)->subDay()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $falta = FaltaReglamento::query()->sole();
        $this->assertSame($nombre, $falta->solicitante_nombre);
        $this->assertSame($correo, $falta->solicitante_correo);
    }

    public function test_report_actions_and_evidence_cannot_cross_company_boundaries(): void
    {
        $otraEmpresa = Empresa::factory()->activa()->create();
        $catalogo = FaltaReglamentoCatalogo::factory()->for($otraEmpresa)->create();
        $falta = FaltaReglamento::factory()->create([
            'empresa_id' => $otraEmpresa->id,
            'falta_reglamento_catalogo_id' => $catalogo->id,
        ]);
        $evidencia = $falta->evidencias()->create([
            'empresa_id' => $otraEmpresa->id,
            'nombre' => 'evidencia.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'archivo' => 'contenido de otra empresa',
        ]);

        $this->actingAs($this->administrator)
            ->post(route('empresas.faltas-reglamento.autorizar', $falta))
            ->assertNotFound();

        $this->actingAs($this->administrator)
            ->get(route('empresas.faltas-reglamento.evidencias.download', [$falta, $evidencia]))
            ->assertNotFound();

        $this->assertSame(EstadoFaltaReglamento::Pendiente, $falta->fresh()->estado);
        $this->assertSame(0, DB::table('empleado_tipo_falta_reglamento')->count());
    }

    public function test_routes_require_the_faltas_module_entitlement_even_for_company_administrators(): void
    {
        $modulo = Modulo::query()->where('clave', 'faltas_reglamento')->firstOrFail();
        $this->empresa->modulos()->updateExistingPivot($modulo->id, ['habilitado' => false]);

        $this->actingAs($this->administrator)
            ->get(route('empresas.faltas-reglamento.index'))
            ->assertForbidden();
    }

    public function test_rejection_requires_a_reason_and_keeps_the_report_out_of_the_approved_count(): void
    {
        Queue::fake();

        $catalogo = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create();
        $falta = FaltaReglamento::factory()->create([
            'empresa_id' => $this->empresa->id,
            'falta_reglamento_catalogo_id' => $catalogo->id,
            'estado' => EstadoFaltaReglamento::Pendiente,
        ]);

        $this->actingAs($this->administrator)
            ->post(route('empresas.faltas-reglamento.rechazar', $falta), [])
            ->assertSessionHasErrors('comentarios_rechazo');

        $this->actingAs($this->administrator)
            ->post(route('empresas.faltas-reglamento.rechazar', $falta), [
                'comentarios_rechazo' => 'El reporte requiere información adicional.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.faltas-reglamento.index'));

        Queue::assertPushed(EnviarNotificacionFaltaReglamento::class, fn (EnviarNotificacionFaltaReglamento $job): bool => $job->faltaReglamentoId === $falta->id
            && $job->tipo === 'resolucion',
        );

        $this->assertDatabaseHas('faltas_reglamento', [
            'id' => $falta->id,
            'estado' => EstadoFaltaReglamento::Rechazada->value,
            'comentarios_rechazo' => 'El reporte requiere información adicional.',
            'resuelto_por_user_id' => $this->administrator->id,
        ]);
        $this->assertSame(0, DB::table('empleado_tipo_falta_reglamento')->count());
    }

    public function test_notifications_send_report_comments_and_affected_employee_details_to_company_recipients(): void
    {
        $empleadoUser = $this->createCompanyUser(['faltas_reglamento.view']);
        $solicitante = $this->createCompanyUser(['faltas_reglamento.view']);
        $puesto = Puesto::factory()->for($this->empresa)->create(['nombre' => 'Auxiliar de almacén']);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $empleadoUser->id,
            'nombre' => 'Persona Afectada',
            'nombre_usuario' => 'persona.afectada',
            'correo' => 'persona.afectada@empresa.test',
            'puesto_id' => $puesto->id,
        ]);
        $catalogo = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create([
            'nombre' => 'Incumplimiento de procedimiento',
            'descripcion' => 'Descripción de la falta para el correo.',
        ]);
        $falta = FaltaReglamento::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'falta_reglamento_catalogo_id' => $catalogo->id,
            'solicitante_user_id' => $solicitante->id,
            'solicitante_nombre' => $solicitante->name,
            'solicitante_correo' => $solicitante->email,
            'comentarios' => 'Se solicita revisar el incidente completo.',
            'estado' => EstadoFaltaReglamento::Pendiente,
        ]);
        $falta->evidencias()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'evidencia-incidente.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'archivo' => 'contenido PDF de prueba',
        ]);

        Mail::fake();

        (new EnviarNotificacionFaltaReglamento($falta->id, 'solicitud'))
            ->handle(app(ObtenerAdministradoresEmpresa::class));

        Mail::assertSent(NotificacionFaltaReglamentoMail::class, fn (NotificacionFaltaReglamentoMail $mail): bool => $mail->tipo === 'solicitud'
            && $mail->hasTo($this->administrator->email)
            && $mail->hasCc($empleadoUser->email)
            && $mail->hasCc($solicitante->email)
            && $mail->datos['registro']['comentarios'] === 'Se solicita revisar el incidente completo.'
            && $mail->datos['registro']['tipo_falta'] === $catalogo->tipoFalta->nombre
            && $mail->datos['registro']['descripcion_tipo_falta'] === $catalogo->tipoFalta->descripcion
            && $mail->datos['registro']['evidencias'][0]['nombre'] === 'evidencia-incidente.pdf'
            && $mail->datos['empleado']['nombre'] === 'Persona Afectada'
            && $mail->datos['empleado']['nombre_cuenta'] === $empleadoUser->name
            && $mail->datos['empleado']['correo_cuenta'] === $empleadoUser->email
            && $mail->datos['empleado']['puesto'] === 'Auxiliar de almacén'
            && str_contains($mail->render(), 'Se solicita revisar el incidente completo.'),
        );

        $falta->forceFill([
            'estado' => EstadoFaltaReglamento::Rechazada,
            'resuelto_por_user_id' => $this->administrator->id,
            'resuelto_at' => now(),
            'comentarios_rechazo' => 'Se rechaza porque falta documentar el hecho.',
        ])->save();

        (new EnviarNotificacionFaltaReglamento($falta->id, 'resolucion'))
            ->handle(app(ObtenerAdministradoresEmpresa::class));

        Mail::assertSent(NotificacionFaltaReglamentoMail::class, fn (NotificacionFaltaReglamentoMail $mail): bool => $mail->tipo === 'resolucion'
            && $mail->hasTo($solicitante->email)
            && $mail->hasTo($empleadoUser->email)
            && $mail->hasCc($this->administrator->email)
            && $mail->datos['registro']['estado'] === 'Rechazada'
            && $mail->datos['registro']['comentarios_rechazo'] === 'Se rechaza porque falta documentar el hecho.'
            && $mail->datos['registro']['resuelto_por'] === $this->administrator->name,
        );
    }

    public function test_company_reviewer_filters_and_pagination_keep_the_browser_url_clean(): void
    {
        $catalogo = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create();
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);

        foreach (range(1, 16) as $index) {
            FaltaReglamento::factory()->create([
                'empresa_id' => $this->empresa->id,
                'empleado_id' => $empleado->id,
                'falta_reglamento_catalogo_id' => $catalogo->id,
                'solicitante_nombre' => 'Solicitante de prueba',
                'estado' => EstadoFaltaReglamento::Pendiente,
                'fecha_ocurrencia' => now($this->empresa->zona_horaria)->subDays($index)->toDateString(),
            ]);
        }

        $this->actingAs($this->administrator)
            ->post(route('empresas.faltas-reglamento.filtros'), [
                'search' => '  Solicitante   de prueba ',
                'estado' => EstadoFaltaReglamento::Pendiente->value,
                'empleado_id' => $empleado->id,
                'tipo_falta_reglamento_id' => $catalogo->tipo_falta_reglamento_id,
                'fecha_desde' => now($this->empresa->zona_horaria)->subDays(30)->toDateString(),
                'fecha_hasta' => now($this->empresa->zona_horaria)->toDateString(),
            ])
            ->assertRedirect(route('empresas.faltas-reglamento.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Solicitante de prueba')
                ->where('filters.estado', EstadoFaltaReglamento::Pendiente->value)
                ->where('faltas.current_page', 1)
                ->where('faltas.per_page', 15)
                ->where('faltas.total', 16)
                ->where('faltas.links', fn (Collection $links): bool => $links->every(
                    static fn (array $link): bool => $link['url'] === null,
                )),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.faltas-reglamento.pagina'), ['page' => 2])
            ->assertRedirect(route('empresas.faltas-reglamento.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.estado', EstadoFaltaReglamento::Pendiente->value)
                ->where('filters.empleadoId', $empleado->id)
                ->where('faltas.current_page', 2)
                ->where('faltas.total', 16)
                ->has('faltas.data', 1),
            );
    }

    /** @param list<string> $permissions */
    private function createCompanyUser(array $permissions): User
    {
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($user)->create();
        setPermissionsTeamId($this->empresa->id);
        $user->givePermissionTo($permissions);

        return $user;
    }
}
