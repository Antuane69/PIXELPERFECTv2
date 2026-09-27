<?php

namespace Tests\Feature\Incapacidades;

use App\Enums\Incapacidades\EstadoIncapacidad;
use App\Jobs\Incapacidades\EnviarNotificacionIncapacidad;
use App\Jobs\Incapacidades\VerificarIncapacidadesVencidas;
use App\Mail\Incapacidades\NotificacionIncapacidadMail;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Incapacidad;
use App\Models\MembresiaEmpresa;
use App\Models\Modulo;
use App\Models\User;
use App\Services\Empresas\ObtenerAdministradoresEmpresa;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IncapacidadWorkflowTest extends TestCase
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

    public function test_employee_can_submit_an_image_or_no_file_and_image_is_compressed_into_the_blob(): void
    {
        $solicitante = $this->createCompanyUser(['incapacidades.view', 'incapacidades.create']);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $solicitante->id,
        ]);
        $imagen = UploadedFile::fake()->image('justificante.png', 3000, 1500);
        Queue::fake();

        $this->actingAs($solicitante)
            ->post(route('empresas.incapacidades.store'), [
                'fecha_inicio' => now($this->empresa->zona_horaria)->subDays(4)->toDateString(),
                'fecha_fin' => now($this->empresa->zona_horaria)->subDays(2)->toDateString(),
                'motivo' => '  Reposo indicado por el médico.  ',
                'archivo' => $imagen,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.incapacidades.index'));

        $conArchivo = Incapacidad::query()->sole();

        $this->assertSame(EstadoIncapacidad::Pendiente, $conArchivo->estado);
        $this->assertSame($empleado->id, $conArchivo->empleado_id);
        $this->assertSame($solicitante->id, $conArchivo->solicitante_user_id);
        $this->assertSame('Reposo indicado por el médico.', $conArchivo->motivo);
        $this->assertSame('justificante.png', $conArchivo->nombre_original);
        $this->assertSame('image/png', $conArchivo->mime_type);
        $this->assertSame('png', $conArchivo->extension);
        $this->assertNotSame('', $conArchivo->archivo);
        $imagenProcesada = getimagesizefromstring($conArchivo->archivo);
        $this->assertIsArray($imagenProcesada);
        $this->assertSame(2400, $imagenProcesada[0]);
        $this->assertSame(1200, $imagenProcesada[1]);

        $svg = UploadedFile::fake()->createWithContent(
            'diagrama.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
                .'<!-- comentario que debe compactarse -->'
                .'<rect width="10" height="10"/>'
                .'</svg>',
        );

        $this->actingAs($solicitante)
            ->post(route('empresas.incapacidades.store'), [
                'fecha_inicio' => now($this->empresa->zona_horaria)->subDays(4)->toDateString(),
                'fecha_fin' => now($this->empresa->zona_horaria)->subDays(2)->toDateString(),
                'motivo' => 'Adjunto en formato vectorial.',
                'archivo' => $svg,
            ])
            ->assertSessionHasNoErrors();

        $incapacidadSvg = Incapacidad::query()->where('extension', 'svg')->sole();
        $this->assertSame('image/svg+xml', $incapacidadSvg->mime_type);
        $this->assertStringNotContainsString('comentario que debe compactarse', $incapacidadSvg->archivo);

        $this->actingAs($solicitante)
            ->post(route('empresas.incapacidades.store'), [
                'fecha_inicio' => now($this->empresa->zona_horaria)->subDay()->toDateString(),
                'fecha_fin' => now($this->empresa->zona_horaria)->toDateString(),
                'motivo' => 'Consulta de seguimiento.',
            ])
            ->assertSessionHasNoErrors();

        $sinArchivo = Incapacidad::query()->orderByDesc('id')->firstOrFail();
        $this->assertNull($sinArchivo->archivo);
        $this->assertNull($sinArchivo->nombre_original);
        Queue::assertPushed(EnviarNotificacionIncapacidad::class, 3);
    }

    public function test_pdf_and_word_attachments_are_accepted_but_other_file_types_are_rejected(): void
    {
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $pdf = UploadedFile::fake()->createWithContent(
            'constancia.pdf',
            "%PDF-1.4\ncontenido de prueba\n%%EOF",
        );
        $word = UploadedFile::fake()->createWithContent(
            'constancia.doc',
            "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 128),
        );
        $wordOpenXml = $this->createDocxUpload();
        Queue::fake();

        foreach ([$pdf, $word, $wordOpenXml] as $archivo) {
            $this->actingAs($this->administrator)
                ->post(route('empresas.incapacidades.store'), [
                    'empleado_id' => $empleado->id,
                    'fecha_inicio' => '2026-09-21',
                    'fecha_fin' => '2026-09-22',
                    'motivo' => 'Incapacidad documentada.',
                    'archivo' => $archivo,
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('empresas.incapacidades.index'));
        }

        $this->assertDatabaseCount('incapacidades', 3);
        $this->assertSame('application/pdf', Incapacidad::query()->where('extension', 'pdf')->sole()->mime_type);
        $this->assertSame('application/msword', Incapacidad::query()->where('extension', 'doc')->sole()->mime_type);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            Incapacidad::query()->where('extension', 'docx')->sole()->mime_type,
        );

        $ejecutable = UploadedFile::fake()->createWithContent('programa.exe', "MZ\0\0archivo no permitido");

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.store'), [
                'empleado_id' => $empleado->id,
                'fecha_inicio' => '2026-09-21',
                'fecha_fin' => '2026-09-22',
                'motivo' => 'Incapacidad documentada.',
                'archivo' => $ejecutable,
            ])
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('incapacidades', 3);
    }

    public function test_request_rejects_reversed_dates_missing_reason_and_an_employee_from_another_company(): void
    {
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $otraEmpresa = Empresa::factory()->activa()->create();
        $empleadoAjeno = Empleado::factory()->create(['empresa_id' => $otraEmpresa->id]);
        $datos = [
            'empleado_id' => $empleado->id,
            'fecha_inicio' => '2026-09-25',
            'fecha_fin' => '2026-09-24',
            'motivo' => 'Incapacidad documentada.',
        ];

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.store'), $datos)
            ->assertSessionHasErrors('fecha_fin');

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.store'), [
                ...$datos,
                'fecha_fin' => '2026-09-25',
                'motivo' => '  ',
            ])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.store'), [
                ...$datos,
                'empleado_id' => $empleadoAjeno->id,
                'fecha_fin' => '2026-09-25',
            ])
            ->assertSessionHasErrors('empleado_id');

        $this->assertDatabaseCount('incapacidades', 0);
    }

    public function test_reviewer_cannot_resolve_their_own_request_and_rejection_requires_a_reason(): void
    {
        Queue::fake();
        $empleadoPropio = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $solicitudPropia = Incapacidad::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleadoPropio->id,
            'solicitante_user_id' => $this->administrator->id,
            'solicitante_nombre' => $this->administrator->name,
            'solicitante_correo' => $this->administrator->email,
            'estado' => EstadoIncapacidad::Pendiente,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('empresas.incapacidades.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incapacidades.data.0.id', $solicitudPropia->id)
                ->where('incapacidades.data.0.puedeResolver', false)
                ->missing('incapacidades.data.0.archivo'),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.autorizar', $solicitudPropia))
            ->assertForbidden();

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.rechazar', $solicitudPropia), [
                'comentarios_rechazo' => 'No procede.',
            ])
            ->assertForbidden();

        $solicitante = $this->createCompanyUser(['incapacidades.view', 'incapacidades.create']);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $solicitante->id,
        ]);
        $solicitud = Incapacidad::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'solicitante_user_id' => $solicitante->id,
            'solicitante_nombre' => $solicitante->name,
            'solicitante_correo' => $solicitante->email,
            'estado' => EstadoIncapacidad::Pendiente,
        ]);

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.rechazar', $solicitud), [])
            ->assertSessionHasErrors('comentarios_rechazo');

        $this->assertSame(EstadoIncapacidad::Pendiente, $solicitud->fresh()->estado);

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.rechazar', $solicitud), [
                'comentarios_rechazo' => '  Falta información médica.  ',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.incapacidades.index'));

        $this->assertDatabaseHas('incapacidades', [
            'id' => $solicitud->id,
            'estado' => EstadoIncapacidad::Rechazada->value,
            'comentarios_rechazo' => 'Falta información médica.',
            'resuelto_por_user_id' => $this->administrator->id,
        ]);
        Queue::assertPushed(EnviarNotificacionIncapacidad::class, 1);
    }

    public function test_reviewer_can_authorize_a_pending_request_once(): void
    {
        Queue::fake();
        $solicitante = $this->createCompanyUser(['incapacidades.view', 'incapacidades.create']);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $solicitante->id,
        ]);
        $incapacidad = Incapacidad::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'solicitante_user_id' => $solicitante->id,
            'solicitante_nombre' => $solicitante->name,
            'solicitante_correo' => $solicitante->email,
            'estado' => EstadoIncapacidad::Pendiente,
        ]);

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.autorizar', $incapacidad))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.incapacidades.index'));

        $this->assertDatabaseHas('incapacidades', [
            'id' => $incapacidad->id,
            'estado' => EstadoIncapacidad::Autorizada->value,
            'resuelto_por_user_id' => $this->administrator->id,
        ]);
        $this->assertNotNull($incapacidad->fresh()->resuelto_at);

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.autorizar', $incapacidad))
            ->assertForbidden();

        Queue::assertPushed(EnviarNotificacionIncapacidad::class, 1);
    }

    public function test_attachment_download_is_authorized_for_the_employee_and_hidden_from_other_users_and_companies(): void
    {
        $propietario = $this->createCompanyUser(['incapacidades.view']);
        $otroUsuario = $this->createCompanyUser(['incapacidades.view']);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $propietario->id,
        ]);
        $contenido = "%PDF-1.4\njustificante privado\n%%EOF";
        $incapacidad = Incapacidad::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'solicitante_user_id' => $propietario->id,
            'solicitante_nombre' => $propietario->name,
            'solicitante_correo' => $propietario->email,
            'nombre_original' => 'justificante.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'archivo' => $contenido,
        ]);
        $downloadRoute = route('empresas.incapacidades.archivo', $incapacidad);

        $download = $this->actingAs($propietario)->get($downloadRoute);
        $download->assertOk()
            ->assertDownload('justificante.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($contenido, $download->streamedContent());

        $this->actingAs($this->administrator)
            ->get($downloadRoute)
            ->assertOk()
            ->assertDownload('justificante.pdf');

        $this->actingAs($otroUsuario)->get($downloadRoute)->assertForbidden();

        $otraEmpresa = Empresa::factory()->activa()->create();
        $empleadoAjeno = Empleado::factory()->create(['empresa_id' => $otraEmpresa->id]);
        $incapacidadAjena = Incapacidad::factory()->create([
            'empresa_id' => $otraEmpresa->id,
            'empleado_id' => $empleadoAjeno->id,
            'nombre_original' => 'otro.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'archivo' => $contenido,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('empresas.incapacidades.archivo', $incapacidadAjena))
            ->assertNotFound();
    }

    public function test_filters_and_pagination_keep_state_out_of_the_url_and_list_props_never_include_blob(): void
    {
        $empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);

        for ($index = 1; $index <= 16; $index++) {
            Incapacidad::factory()->create([
                'empresa_id' => $this->empresa->id,
                'empleado_id' => $empleado->id,
                'solicitante_user_id' => $this->administrator->id,
                'solicitante_nombre' => 'Solicitante compartido',
                'solicitante_correo' => $this->administrator->email,
                'motivo' => "Solicitud de incapacidad {$index}",
                'fecha_inicio' => now($this->empresa->zona_horaria)->addDays($index)->toDateString(),
                'fecha_fin' => now($this->empresa->zona_horaria)->addDays($index + 1)->toDateString(),
                'estado' => EstadoIncapacidad::Pendiente,
                'nombre_original' => 'privado.pdf',
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'archivo' => 'BLOB PRIVADO',
            ]);
        }

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.filtros'), [
                'search' => 'Solicitante',
                'estado' => EstadoIncapacidad::Pendiente->value,
            ])
            ->assertRedirect(route('empresas.incapacidades.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.incapacidades.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Solicitante')
                ->where('filters.estado', EstadoIncapacidad::Pendiente->value)
                ->where('incapacidades.current_page', 1)
                ->where('incapacidades.per_page', 15)
                ->where('incapacidades.total', 16)
                ->missing('incapacidades.data.0.archivo')
                ->where('incapacidades.data.0.puedeResolver', false)
                ->where('incapacidades.links.0.url', null),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.incapacidades.pagina'), ['page' => 2])
            ->assertRedirect(route('empresas.incapacidades.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.incapacidades.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Solicitante')
                ->where('filters.estado', EstadoIncapacidad::Pendiente->value)
                ->where('incapacidades.current_page', 2)
                ->where('incapacidades.total', 16)
                ->has('incapacidades.data', 1),
            );
    }

    public function test_notification_emails_go_to_company_admin_and_requester_and_expired_status_is_applied(): void
    {
        $solicitante = $this->createCompanyUser(['incapacidades.view', 'incapacidades.create']);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $solicitante->id,
        ]);
        $solicitud = Incapacidad::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'solicitante_user_id' => $solicitante->id,
            'solicitante_nombre' => $solicitante->name,
            'solicitante_correo' => $solicitante->email,
            'nombre_original' => 'constancia.pdf',
            'estado' => EstadoIncapacidad::Pendiente,
        ]);

        Mail::fake();

        (new EnviarNotificacionIncapacidad($solicitud->id, 'solicitud'))
            ->handle(app(ObtenerAdministradoresEmpresa::class));

        Mail::assertSent(NotificacionIncapacidadMail::class, fn (NotificacionIncapacidadMail $mail): bool => $mail->tipo === 'solicitud'
            && $mail->hasTo($this->administrator->email)
            && $mail->datos['nombre_archivo'] === 'constancia.pdf',
        );

        $solicitud->forceFill([
            'estado' => EstadoIncapacidad::Autorizada,
            'resuelto_por_user_id' => $this->administrator->id,
            'resuelto_at' => now(),
        ])->save();

        (new EnviarNotificacionIncapacidad($solicitud->id, 'resolucion'))
            ->handle(app(ObtenerAdministradoresEmpresa::class));

        Mail::assertSent(NotificacionIncapacidadMail::class, fn (NotificacionIncapacidadMail $mail): bool => $mail->tipo === 'resolucion'
            && $mail->hasTo($solicitante->email)
            && $mail->hasTo($empleado->correo)
            && $mail->hasCc($this->administrator->email),
        );

        $vencida = Incapacidad::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'fecha_inicio' => now($this->empresa->zona_horaria)->subDays(5)->toDateString(),
            'fecha_fin' => now($this->empresa->zona_horaria)->subDay()->toDateString(),
            'estado' => EstadoIncapacidad::Autorizada,
        ]);
        $vigenteHastaHoy = Incapacidad::factory()->create([
            'empresa_id' => $this->empresa->id,
            'empleado_id' => $empleado->id,
            'fecha_inicio' => now($this->empresa->zona_horaria)->subDay()->toDateString(),
            'fecha_fin' => now($this->empresa->zona_horaria)->toDateString(),
            'estado' => EstadoIncapacidad::Autorizada,
        ]);

        (new VerificarIncapacidadesVencidas)->handle();

        $this->assertSame(EstadoIncapacidad::Vencida, $vencida->fresh()->estado);
        $this->assertSame(EstadoIncapacidad::Autorizada, $vigenteHastaHoy->fresh()->estado);
    }

    public function test_module_routes_are_unavailable_when_the_company_entitlement_is_disabled(): void
    {
        $modulo = Modulo::query()->where('clave', 'incapacidades')->firstOrFail();
        $this->empresa->modulos()->updateExistingPivot($modulo->id, ['habilitado' => false]);

        $this->actingAs($this->administrator)
            ->get(route('empresas.incapacidades.index'))
            ->assertForbidden();
    }

    public function test_user_without_module_permissions_cannot_list_or_submit(): void
    {
        $usuario = $this->createCompanyUser([]);
        $empleado = Empleado::factory()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $usuario->id,
        ]);

        $this->actingAs($usuario)
            ->get(route('empresas.incapacidades.index'))
            ->assertForbidden();

        $this->actingAs($usuario)
            ->post(route('empresas.incapacidades.store'), [
                'fecha_inicio' => '2026-09-21',
                'fecha_fin' => '2026-09-22',
                'motivo' => 'Incapacidad documentada.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('incapacidades', 0);
        $this->assertModelExists($empleado);
    }

    /** @param array<int, string> $permissions */
    private function createCompanyUser(array $permissions): User
    {
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($user)->create();
        setPermissionsTeamId($this->empresa->id);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createDocxUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'incapacidad-docx-');

        if (! is_string($path)) {
            throw new \RuntimeException('No se pudo preparar el archivo Word de prueba.');
        }

        $archive = new \ZipArchive;

        if ($archive->open($path, \ZipArchive::OVERWRITE) !== true) {
            @unlink($path);

            throw new \RuntimeException('No se pudo crear el archivo Word de prueba.');
        }

        $archive->addFromString(
            '[Content_Types].xml',
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                .'</Types>',
        );
        $archive->addFromString(
            '_rels/.rels',
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                .'</Relationships>',
        );
        $archive->addFromString(
            'word/document.xml',
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                .'<w:body><w:p><w:r><w:t>Justificante</w:t></w:r></w:p></w:body>'
                .'</w:document>',
        );
        $archive->close();
        $contents = file_get_contents($path);
        @unlink($path);

        if (! is_string($contents)) {
            throw new \RuntimeException('No se pudo leer el archivo Word de prueba.');
        }

        return UploadedFile::fake()->createWithContent('constancia.docx', $contents);
    }
}
