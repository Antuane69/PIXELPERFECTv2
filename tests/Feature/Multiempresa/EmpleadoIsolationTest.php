<?php

namespace Tests\Feature\Multiempresa;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\MembresiaEmpresa;
use App\Models\Puesto;
use App\Models\TipoDocumentoEmpleado;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmpleadoIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_same_person_can_have_independent_records_in_different_companies(): void
    {
        [, $first, $second] = $this->administratorWithTwoCompaniesInSameGroup();
        $identifiers = [
            'nombre_usuario' => 'persona.compartida',
            'correo' => 'persona@example.com',
            'curp' => 'GODE561231HDFDBC09',
            'rfc' => 'GODE561231GR8',
            'nss' => '12345678901',
        ];
        $firstPosition = Puesto::factory()->for($first)->create();
        $secondPosition = Puesto::factory()->for($second)->create();

        Empleado::factory()->for($first)->create([
            ...$identifiers,
            'puesto_id' => $firstPosition->id,
            'salario_dia' => 500,
        ]);
        $secondEmployee = Empleado::factory()->for($second)->create([
            ...$identifiers,
            'puesto_id' => $secondPosition->id,
            'salario_dia' => 900,
        ]);

        $this->assertSame($second->id, $secondEmployee->empresa_id);
        $this->assertDatabaseCount('empleados', 2);
    }

    #[DataProvider('uniqueIdentifierColumns')]
    public function test_database_rejects_each_duplicate_identifier_inside_same_company(string $column): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $puesto = Puesto::factory()->for($empresa)->create();
        $employee = Empleado::factory()->for($empresa)->create(['puesto_id' => $puesto->id]);

        $this->expectException(QueryException::class);

        Empleado::factory()->for($empresa)->create([
            'puesto_id' => $puesto->id,
            $column => $employee->getAttribute($column),
        ]);
    }

    public function test_request_validation_rejects_all_duplicate_identifiers_inside_same_company(): void
    {
        [$user, $empresa] = $this->administratorWithTwoCompaniesInSameGroup();
        $puesto = Puesto::factory()->for($empresa)->create();
        $employee = Empleado::factory()->for($empresa)->create(['puesto_id' => $puesto->id]);

        $this->actingAs($user)
            ->post(route('empresas.empleados.store', $empresa), [
                ...$this->validEmployeePayload($puesto),
                'nombre_usuario' => $employee->nombre_usuario,
                'correo' => $employee->correo,
                'curp' => $employee->curp,
                'rfc' => $employee->rfc,
                'nss' => $employee->nss,
            ])
            ->assertSessionHasErrors(['nombre_usuario', 'correo', 'curp', 'rfc', 'nss']);
    }

    public function test_database_rejects_a_position_from_another_company(): void
    {
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        $foreignPosition = Puesto::factory()->for($second)->create();

        $this->expectException(QueryException::class);

        Empleado::factory()->for($first)->create(['puesto_id' => $foreignPosition->id]);
    }

    public function test_database_rejects_a_document_company_different_from_its_employee(): void
    {
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        $employee = Empleado::factory()->for($first)->create();

        $this->expectException(QueryException::class);

        EmpleadoDocumento::factory()->for($employee)->create(['empresa_id' => $second->id]);
    }

    public function test_listing_and_route_bindings_are_isolated_by_company(): void
    {
        [$user, $first, $second] = $this->administratorWithTwoCompaniesInSameGroup();
        $firstPosition = Puesto::factory()->for($first)->create();
        $secondPosition = Puesto::factory()->for($second)->create();
        $firstEmployee = Empleado::factory()->for($first)->create([
            'nombre' => 'Empleado Local',
            'puesto_id' => $firstPosition->id,
        ]);
        $foreignEmployee = Empleado::factory()->for($second)->create([
            'nombre' => 'Empleado Ajeno',
            'puesto_id' => $secondPosition->id,
        ]);

        $this->actingAs($user)
            ->get(route('empresas.empleados.index', $first))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empleados/index')
                ->has('empleados.data', 1)
                ->where('empleados.data.0.id', $firstEmployee->id),
            );

        $this->actingAs($user)
            ->put(route('empresas.empleados.update', ['empresa' => $first, 'empleado' => $foreignEmployee]), ['nombre' => 'Manipulado'])
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('empresas.empleados.destroy', ['empresa' => $first, 'empleado' => $foreignEmployee]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('empresas.empleados.avatar', ['empresa' => $first, 'empleado' => $foreignEmployee]))
            ->assertNotFound();

        $foreignEmployee->delete();

        $this->actingAs($user)
            ->patch(route('empresas.empleados.restore', ['empresa' => $first, 'empleado' => $foreignEmployee]))
            ->assertNotFound();
    }

    public function test_creation_ignores_company_input_and_rejects_a_foreign_position(): void
    {
        [$user, $first, $second] = $this->administratorWithTwoCompaniesInSameGroup();
        $firstPosition = Puesto::factory()->for($first)->create();
        $foreignPosition = Puesto::factory()->for($second)->create();

        $this->actingAs($user)
            ->post(route('empresas.empleados.store', $first), [
                ...$this->validEmployeePayload($foreignPosition),
                'empresa_id' => $second->id,
            ])
            ->assertSessionHasErrors('puesto_id');

        $this->actingAs($user)
            ->post(route('empresas.empleados.store', $first), [
                ...$this->validEmployeePayload($firstPosition),
                'empresa_id' => $second->id,
            ])
            ->assertSessionHasNoErrors();

        $employee = Empleado::query()->where('correo', 'empleado@gmail.com')->sole();

        $this->assertSame($first->id, $employee->empresa_id);
    }

    public function test_private_files_documents_and_activity_are_assigned_to_active_company(): void
    {
        Storage::fake('local');
        [$user, $first] = $this->administratorWithTwoCompaniesInSameGroup();
        $position = Puesto::factory()->for($first)->create();
        $type = TipoDocumentoEmpleado::factory()->create([
            'nombre' => 'Contrato',
            'documentos_aceptados' => ['PDF'],
            'activo' => true,
            'es_renovable' => false,
        ]);

        $this->actingAs($user)
            ->post(route('empresas.empleados.store', $first), [
                ...$this->validEmployeePayload($position),
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
                'documentos' => [[
                    'tipo_documento_empleado_id' => $type->id,
                    'archivo' => UploadedFile::fake()->create('contrato.pdf', 20, 'application/pdf'),
                    'vence_el' => null,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $employee = Empleado::query()->whereBelongsTo($first)->sole();
        $document = $employee->documentos()->sole();

        $this->assertSame($first->id, $document->empresa_id);
        $this->assertStringStartsWith("empresas/{$first->id}/empleados/{$employee->id}/", $employee->avatar);
        $this->assertStringStartsWith("empresas/{$first->id}/empleados/{$employee->id}/", $document->ruta);
        Storage::disk('local')->assertExists($employee->avatar);
        Storage::disk('local')->assertExists($document->ruta);
        $this->assertDatabaseHas('activity_log', [
            'empresa_id' => $first->id,
            'subject_type' => Empleado::class,
            'subject_id' => $employee->id,
            'event' => 'created',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'empresa_id' => $first->id,
            'subject_type' => EmpleadoDocumento::class,
            'subject_id' => $document->id,
            'event' => 'created',
        ]);
    }

    public function test_document_bindings_dashboard_and_export_never_cross_company(): void
    {
        Storage::fake('local');
        [$user, $first, $second] = $this->administratorWithTwoCompaniesInSameGroup();
        $firstPosition = Puesto::factory()->for($first)->create();
        $secondPosition = Puesto::factory()->for($second)->create();
        $localEmployee = Empleado::factory()->for($first)->create([
            'nombre' => 'Empleado Local Exportable',
            'puesto_id' => $firstPosition->id,
        ]);
        $foreignEmployee = Empleado::factory()->for($second)->create([
            'nombre' => 'Empleado Ajeno Oculto',
            'puesto_id' => $secondPosition->id,
        ]);
        $foreignDocument = EmpleadoDocumento::factory()->for($foreignEmployee)->image()->create();
        Storage::disk('local')->put($foreignDocument->ruta, 'image');

        $this->actingAs($user)
            ->get(route('empresas.empleados.documentos.preview', [
                'empresa' => $first,
                'empleado' => $foreignEmployee,
                'documento' => $foreignDocument,
            ]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('empresas.empleados.documentos.download', [
                'empresa' => $first,
                'empleado' => $foreignEmployee,
                'documento' => $foreignDocument,
            ]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('empresas.inicio', $first))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stats.empleados', 1));

        $response = $this->actingAs($user)
            ->post(route('empresas.reportes.empleados.exportar', $first), ['formato' => 'xlsx'])
            ->assertOk();
        $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $exportedNames = [
            (string) $sheet->getCell('B6')->getValue(),
            (string) $sheet->getCell('B7')->getValue(),
        ];

        $this->assertContains($localEmployee->nombre, $exportedNames);
        $this->assertNotContains($foreignEmployee->nombre, $exportedNames);
        $spreadsheet->disconnectWorksheets();
    }

    public function test_member_without_employee_permission_cannot_list_or_export(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

        $this->actingAs($user)
            ->get(route('empresas.empleados.index', $empresa))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('empresas.reportes.empleados.exportar', $empresa), ['formato' => 'xlsx'])
            ->assertForbidden();
    }

    /**
     * @return array{User, Empresa, Empresa}
     */
    private function administratorWithTwoCompaniesInSameGroup(): array
    {
        $group = GrupoEmpresarial::factory()->corporativo()->create();
        $first = Empresa::factory()->activa()->for($group, 'grupoEmpresarial')->create();
        $second = Empresa::factory()->activa()->for($group, 'grupoEmpresarial')->create();
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($first)->for($user)->create();
        MembresiaEmpresa::factory()->for($second)->for($user)->create();
        $firstRole = app(CrearRolesPredeterminadosEmpresa::class)->handle($first);
        $secondRole = app(CrearRolesPredeterminadosEmpresa::class)->handle($second);

        setPermissionsTeamId($first->id);
        $user->assignRole($firstRole);
        setPermissionsTeamId($second->id);
        $user->unsetRelation('roles')->unsetRelation('permissions')->assignRole($secondRole);

        return [$user, $first, $second];
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
            'dias_vacaciones' => 12,
            'dias_descanso' => ['sabado', 'domingo'],
            'fecha_ingreso' => '2024-01-01',
            'fecha_nacimiento' => '1990-05-10',
            'periodo_prueba_meses' => 3,
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function uniqueIdentifierColumns(): array
    {
        return [
            'nombre_usuario' => ['nombre_usuario'],
            'correo' => ['correo'],
            'CURP' => ['curp'],
            'RFC' => ['rfc'],
            'NSS' => ['nss'],
        ];
    }
}
