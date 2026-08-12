<?php

namespace Tests\Feature\Management;

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\Puesto;
use App\Models\TipoDocumentoEmpleado;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmpleadoManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->administrator = User::factory()->create();
        $this->administrator->assignRole('Administrador');
    }

    public function test_administrator_can_create_update_and_soft_delete_an_employee_with_a_document(): void
    {
        Storage::fake('local');

        $puesto = Puesto::factory()->create();
        $tipoDocumento = TipoDocumentoEmpleado::factory()->create([
            'nombre' => 'Contrato',
            'es_renovable' => false,
            'frecuencia_cantidad' => null,
            'frecuencia_tipo' => null,
            'documentos_aceptados' => ['PDF'],
            'activo' => true,
        ]);
        $payload = $this->validEmployeePayload($puesto);
        $payload['periodo_prueba_meses'] = 5;
        $payload['avatar'] = UploadedFile::fake()->image('avatar-original.jpg', 200, 200);
        $payload['documentos'] = [[
            'tipo_documento_empleado_id' => $tipoDocumento->id,
            'archivo' => UploadedFile::fake()->create(
                'contrato.pdf',
                120,
                'application/pdf',
            ),
            'vence_el' => null,
        ]];
        $filteredIndex = route('empleados.index', [
            'search' => 'Empleado',
            'puesto_id' => $puesto->id,
            'estado_civil' => 'soltero',
            'per_page' => 25,
            'page' => 2,
        ]);

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->post(route('empleados.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $empleado = Empleado::query()->where('correo', 'empleado@gmail.com')->firstOrFail();
        $documento = $empleado->documentos()->firstOrFail();
        $originalDocumentPath = $documento->ruta;
        $originalAvatarPath = $empleado->avatar;

        $this->assertSame('Empleado Inicial', $empleado->nombre);
        $this->assertSame(2, $empleado->dias_vacaciones);
        $this->assertSame(5, $empleado->periodo_prueba_meses);
        $this->assertNull($empleado->salario_vacaciones_finiquito);
        $this->assertNull($empleado->aguinaldo);
        $this->assertNull($empleado->prima_vacacional);
        $this->assertNull($empleado->dias_liquidacion);
        $this->assertSame('2024-02-01', $empleado->fecha_contrato_siguiente->toDateString());
        $this->assertSame('2024-06-01', $empleado->fecha_contrato_indefinido->toDateString());
        $this->assertSame($tipoDocumento->id, $documento->tipo_documento_empleado_id);
        Storage::disk($documento->disco)->assertExists($documento->ruta);
        Storage::disk('local')->assertExists($originalAvatarPath);

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->post(route('empleados.update', $empleado), [
                '_method' => 'PUT',
                'nombre' => 'Empleado Actualizado',
                'avatar' => UploadedFile::fake()->image('avatar-nuevo.png', 240, 240),
                'salario_vacaciones_finiquito' => '1000.00',
                'aguinaldo' => '2500.00',
                'prima_vacacional' => '500.00',
                'dias_vacaciones' => 12,
                'dias_liquidacion' => 20,
                'dias_descanso_present' => '1',
                'documentos' => [[
                    'tipo_documento_empleado_id' => $tipoDocumento->id,
                    'archivo' => UploadedFile::fake()->create(
                        'contrato-actualizado.pdf',
                        140,
                        'application/pdf',
                    ),
                    'vence_el' => null,
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $empleado->refresh();
        $documento->refresh();

        $this->assertSame('Empleado Actualizado', $empleado->nombre);
        $this->assertSame([], $empleado->dias_descanso);
        $this->assertSame('1000.00', (string) $empleado->salario_vacaciones_finiquito);
        $this->assertSame('2500.00', (string) $empleado->aguinaldo);
        $this->assertSame('500.00', (string) $empleado->prima_vacacional);
        $this->assertSame(12, $empleado->dias_vacaciones);
        $this->assertSame(20, $empleado->dias_liquidacion);
        $this->assertSame('contrato-actualizado.pdf', $documento->nombre_original);
        Storage::disk('local')->assertMissing($originalDocumentPath);
        Storage::disk('local')->assertMissing($originalAvatarPath);
        Storage::disk('local')->assertExists($documento->ruta);
        Storage::disk('local')->assertExists($empleado->avatar);

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->delete(route('empleados.destroy', $empleado))
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $this->assertSoftDeleted($empleado);
    }

    public function test_employee_validation_rejects_invalid_identity_data_and_missing_active_documents(): void
    {
        $puesto = Puesto::factory()->create();
        TipoDocumentoEmpleado::factory()->create([
            'nombre' => 'Identificación',
            'documentos_aceptados' => ['PDF'],
            'activo' => true,
        ]);

        $payload = $this->validEmployeePayload($puesto);
        $payload['correo'] = 'not-an-email';
        $payload['curp'] = 'invalid';
        $payload['rfc'] = 'invalid';
        $payload['nss'] = '12345ABC';
        $payload['telefono'] = '123ABC';
        $payload['fecha_nacimiento'] = now()->subYears(10)->toDateString();

        $this->actingAs($this->administrator)
            ->post(route('empleados.store'), $payload)
            ->assertSessionHasErrors([
                'correo',
                'curp',
                'rfc',
                'nss',
                'telefono',
                'fecha_nacimiento',
                'documentos',
            ]);

        $this->assertDatabaseCount('empleados', 0);
    }

    public function test_employee_validation_rejects_more_than_six_trial_months(): void
    {
        $puesto = Puesto::factory()->create();
        $payload = $this->validEmployeePayload($puesto);
        $payload['periodo_prueba_meses'] = 7;

        $this->actingAs($this->administrator)
            ->post(route('empleados.store'), $payload)
            ->assertSessionHasErrors(['periodo_prueba_meses']);

        $this->assertDatabaseCount('empleados', 0);
    }

    public function test_employee_validation_rejects_salary_values_with_more_than_two_decimals(): void
    {
        $puesto = Puesto::factory()->create();
        $payload = $this->validEmployeePayload($puesto);
        $payload['salario_dia'] = '10.123';
        $payload['salario_quincena'] = '100.999';

        $this->actingAs($this->administrator)
            ->post(route('empleados.store'), $payload)
            ->assertSessionHasErrors(['salario_dia', 'salario_quincena']);

        $this->assertDatabaseCount('empleados', 0);
    }

    public function test_renewable_employee_documents_require_an_expiration_date(): void
    {
        Storage::fake('local');

        $puesto = Puesto::factory()->create();
        $tipoDocumento = TipoDocumentoEmpleado::factory()->create([
            'es_renovable' => true,
            'frecuencia_cantidad' => 1,
            'frecuencia_tipo' => 'anios',
            'documentos_aceptados' => ['PDF'],
            'activo' => true,
        ]);
        $payload = $this->validEmployeePayload($puesto);
        $payload['documentos'] = [[
            'tipo_documento_empleado_id' => $tipoDocumento->id,
            'archivo' => UploadedFile::fake()->create('renovable.pdf', 20, 'application/pdf'),
            'vence_el' => null,
        ]];

        $this->actingAs($this->administrator)
            ->post(route('empleados.store'), $payload)
            ->assertSessionHasErrors('documentos.0.vence_el');

        $this->assertDatabaseCount('empleados', 0);
    }

    public function test_employee_listing_filters_records_caps_page_size_and_exposes_safe_download_links(): void
    {
        Storage::fake('local');

        $puesto = Puesto::factory()->create(['nombre' => 'Diseñador']);
        $otroPuesto = Puesto::factory()->create(['nombre' => 'Contador']);
        $tipoDocumento = TipoDocumentoEmpleado::factory()->create();
        $avatarPath = 'empleados/avatars/needle.png';
        $empleado = Empleado::factory()->create([
            'nombre' => 'Needle Employee',
            'puesto_id' => $puesto->id,
            'estado_civil' => 'soltero',
            'avatar' => $avatarPath,
        ]);
        $avatarContents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nkwAAAAASUVORK5CYII=',
            true,
        );
        $this->assertIsString($avatarContents);
        Storage::disk('local')->put($avatarPath, $avatarContents);
        Empleado::factory()->create([
            'nombre' => 'Needle Other Position',
            'puesto_id' => $otroPuesto->id,
            'estado_civil' => 'soltero',
        ]);
        Empleado::factory()->create([
            'nombre' => 'Unrelated Employee',
            'puesto_id' => $puesto->id,
            'estado_civil' => 'casado',
        ]);
        $documento = EmpleadoDocumento::factory()->create([
            'empleado_id' => $empleado->id,
            'tipo_documento_empleado_id' => $tipoDocumento->id,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('empleados.index', [
                'search' => 'Needle',
                'puesto_id' => $puesto->id,
                'estado_civil' => 'soltero',
                'per_page' => 500,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empleados/index')
                ->has('empleados.data', 1)
                ->where('empleados.data.0.id', $empleado->id)
                ->where(
                    'empleados.data.0.avatar_url',
                    route('empleados.avatar', $empleado, absolute: false),
                )
                ->missing('empleados.data.0.avatar')
                ->where(
                    'empleados.data.0.documentos.0.download_url',
                    route('empleados.documentos.download', [
                        'empleado' => $empleado,
                        'documento' => $documento,
                    ], absolute: false),
                )
                ->where('empleados.per_page', 100),
            );

        $this->actingAs($this->administrator)
            ->get(route('empleados.avatar', $empleado))
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_employee_document_download_is_authorized_and_scoped_to_its_employee(): void
    {
        Storage::fake('local');

        $empleado = Empleado::factory()->create();
        $otroEmpleado = Empleado::factory()->create();
        $documento = EmpleadoDocumento::factory()->create([
            'empleado_id' => $empleado->id,
            'nombre_original' => 'contrato.pdf',
            'ruta' => 'empleados/documentos/contrato.pdf',
            'disco' => 'local',
        ]);
        Storage::disk('local')->put($documento->ruta, 'private employee document');

        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo('empleados.view');

        $this->actingAs($authorizedUser)
            ->get(route('empleados.documentos.download', [$empleado, $documento]))
            ->assertOk()
            ->assertDownload('contrato.pdf');

        $this->actingAs($authorizedUser)
            ->get(route('empleados.documentos.download', [$otroEmpleado, $documento]))
            ->assertNotFound();

        $unauthorizedUser = User::factory()->create();

        $this->actingAs($unauthorizedUser)
            ->get(route('empleados.documentos.download', [$empleado, $documento]))
            ->assertForbidden();

        Storage::disk('local')->delete($documento->ruta);

        $this->actingAs($authorizedUser)
            ->get(route('empleados.documentos.download', [$empleado, $documento]))
            ->assertNotFound();
    }

    public function test_inactive_catalogs_cannot_be_newly_assigned_but_existing_position_is_preserved(): void
    {
        Storage::fake('local');

        $inactivePuesto = Puesto::factory()->inactive()->create();
        $payload = $this->validEmployeePayload($inactivePuesto);

        $this->actingAs($this->administrator)
            ->post(route('empleados.store'), $payload)
            ->assertSessionHasErrors('puesto_id');

        $existingEmployee = Empleado::factory()->create([
            'puesto_id' => $inactivePuesto->id,
        ]);

        $this->actingAs($this->administrator)
            ->put(route('empleados.update', $existingEmployee), [
                'nombre' => 'Empleado con puesto histórico',
            ])
            ->assertSessionHasNoErrors();

        $activePuesto = Puesto::factory()->create();
        $inactiveDocumentType = TipoDocumentoEmpleado::factory()->inactive()->create([
            'documentos_aceptados' => ['PDF'],
        ]);
        $payload = $this->validEmployeePayload($activePuesto);
        $payload['documentos'] = [[
            'tipo_documento_empleado_id' => $inactiveDocumentType->id,
            'archivo' => UploadedFile::fake()->create('inactivo.pdf', 20, 'application/pdf'),
            'vence_el' => null,
        ]];

        $this->actingAs($this->administrator)
            ->post(route('empleados.store'), $payload)
            ->assertSessionHasErrors('documentos.0.tipo_documento_empleado_id');
    }

    public function test_employee_restore_requires_an_active_position_and_preserves_the_record(): void
    {
        $puesto = Puesto::factory()->create(['nombre' => 'Puesto histórico']);
        $empleado = Empleado::factory()->create(['puesto_id' => $puesto->id]);
        $empleado->delete();
        $puesto->delete();
        $archivedIndex = route('empleados.index', [
            'archivados' => true,
            'search' => $empleado->nombre,
            'page' => 2,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('empleados.index', ['archivados' => true]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empleados/index')
                ->has('empleados.data', 1)
                ->where('empleados.data.0.id', $empleado->id)
                ->where('empleados.data.0.puesto.nombre', 'Puesto histórico')
                ->where('filters.archivados', true),
            );

        $this->actingAs($this->administrator)
            ->from(route('empleados.index', ['archivados' => true]))
            ->patch(route('empleados.restore', $empleado))
            ->assertSessionHasErrors('empleado');

        $this->assertSoftDeleted($empleado);

        $this->actingAs($this->administrator)
            ->patch(route('puestos.restore', $puesto))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->administrator)
            ->from($archivedIndex)
            ->patch(route('empleados.restore', $empleado))
            ->assertSessionHasNoErrors()
            ->assertRedirect($archivedIndex);

        $this->assertNotSoftDeleted($empleado);
    }

    /**
     * @return array<string, mixed>
     */
    private function validEmployeePayload(Puesto $puesto): array
    {
        return [
            'nombre' => 'Empleado Inicial',
            'nombre_usuario' => 'empleado.inicial',
            'correo' => 'empleado@gmail.com',
            'curp' => 'GODE561231HDFDBC09',
            'rfc' => 'GODE561231GR8',
            'nss' => '12345678901',
            'num_clinica_ss' => 'Clínica 10',
            'puesto_id' => $puesto->id,
            'estado_civil' => 'soltero',
            'sexo' => 'masculino',
            'domicilio' => 'Avenida Principal 123',
            'telefono' => '5512345678',
            'salario_dia' => '500.00',
            'salario_quincena' => '7500.00',
            'salario_vacaciones_finiquito' => '1000.00',
            'aguinaldo' => '2500.00',
            'prima_vacacional' => '500.00',
            'dias_vacaciones' => 12,
            'dias_liquidacion' => 20,
            'dias_descanso' => ['sabado', 'domingo'],
            'fecha_ingreso' => '2024-01-01',
            'fecha_nacimiento' => '1990-05-10',
            'periodo_prueba_meses' => 3,
        ];
    }
}
