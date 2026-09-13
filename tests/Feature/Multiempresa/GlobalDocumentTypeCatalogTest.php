<?php

namespace Tests\Feature\Multiempresa;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Puesto;
use App\Models\TipoDocumentoEmpleado;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class GlobalDocumentTypeCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_document_type_catalog_is_isolated_by_company(): void
    {
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        $firstAdministrator = $this->companyAdministrator($first);
        $secondAdministrator = $this->companyAdministrator($second);
        $firstType = TipoDocumentoEmpleado::factory()->for($first)->create([
            'nombre' => 'Identificación empresa uno',
            'activo' => true,
        ]);
        $secondType = TipoDocumentoEmpleado::factory()->for($second)->create([
            'nombre' => 'Identificación empresa dos',
        ]);

        $this->withEmpresaContext($first)
            ->actingAs($firstAdministrator)
            ->get(route('empresas.empleados.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empleados/index')
                ->has('tiposDocumento', 1)
                ->where('tiposDocumento.0.id', $firstType->id));

        $this->withEmpresaContext($second)
            ->actingAs($secondAdministrator)
            ->get(route('empresas.empleados.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tiposDocumento', 1)
                ->where('tiposDocumento.0.id', $secondType->id));
    }

    public function test_company_administrator_can_manage_only_its_document_types(): void
    {
        $empresa = Empresa::query()->where('slug', 'pixel-perfect')->firstOrFail();
        $administrator = $this->companyAdministrator($empresa);
        $foreignEmpresa = Empresa::factory()->activa()->create();
        $foreignType = TipoDocumentoEmpleado::factory()->for($foreignEmpresa)->create();

        $this->withEmpresaContext($empresa)
            ->actingAs($administrator)
            ->post(route('empresas.tipos-documento-empleados.store'), [
                ...$this->validPayload(),
                'nombre' => 'Documento empresarial',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tipo_documento_empleados', [
            'empresa_id' => $empresa->id,
            'nombre' => 'Documento empresarial',
        ]);

        $this->put(
            route('empresas.tipos-documento-empleados.update', $foreignType),
            $this->validPayload(),
        )->assertNotFound();
    }

    public function test_deactivation_preserves_historical_employee_document_references(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $administrator = $this->companyAdministrator($empresa);
        $type = TipoDocumentoEmpleado::factory()->for($empresa)->create([
            'nombre' => 'Contrato histórico',
            'activo' => true,
        ]);
        $position = Puesto::factory()->for($empresa)->create();
        $employee = Empleado::factory()->for($empresa)->create(['puesto_id' => $position->id]);
        $document = EmpleadoDocumento::factory()->for($employee)->create([
            'empresa_id' => $empresa->id,
            'tipo_documento_empleado_id' => $type->id,
        ]);

        $this->withEmpresaContext($empresa)
            ->actingAs($administrator)
            ->put(route('empresas.tipos-documento-empleados.update', $type), [
                ...$this->validPayload(),
                'nombre' => $type->nombre,
                'activo' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($administrator)
            ->get(route('empresas.empleados.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tiposDocumento.0.id', $type->id)
                ->where('tiposDocumento.0.activo', false)
                ->where('empleados.data.0.id', $employee->id)
                ->where('empleados.data.0.documentos.0.id', $document->id)
                ->where('empleados.data.0.documentos.0.tipo.id', $type->id)
                ->where('empleados.data.0.documentos.0.tipo.nombre', 'Contrato histórico'),
            );

        $this->assertDatabaseHas('empleado_documentos', [
            'id' => $document->id,
            'tipo_documento_empleado_id' => $type->id,
        ]);
    }

    public function test_document_type_changes_are_logged_with_company_ownership(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $administrator = $this->companyAdministrator($empresa);

        $this->withEmpresaContext($empresa)
            ->actingAs($administrator)
            ->post(route('empresas.tipos-documento-empleados.store'), [
                ...$this->validPayload(),
                'nombre' => 'Catálogo empresarial',
            ])
            ->assertSessionHasNoErrors();

        $type = TipoDocumentoEmpleado::query()->where('nombre', 'Catálogo empresarial')->firstOrFail();
        $activity = Activity::query()
            ->where('subject_type', TipoDocumentoEmpleado::class)
            ->where('subject_id', $type->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($empresa->id, $activity->empresa_id);
        $this->assertSame($administrator->id, $activity->causer_id);
    }

    private function companyAdministrator(Empresa $empresa): User
    {
        $role = app(CrearRolesPredeterminadosEmpresa::class)->handle($empresa);
        $administrator = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($administrator)->create();

        setPermissionsTeamId($empresa->id);
        $administrator->assignRole($role);

        return $administrator;
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'nombre' => 'Documento global',
            'es_renovable' => false,
            'documentos_aceptados' => ['PDF'],
            'activo' => true,
        ];
    }
}
