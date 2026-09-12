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

    public function test_global_catalog_is_visible_from_every_company_employee_module(): void
    {
        $first = Empresa::factory()->activa()->create();
        $second = Empresa::factory()->activa()->create();
        $firstAdministrator = $this->companyAdministrator($first);
        $secondAdministrator = $this->companyAdministrator($second);
        $activeType = TipoDocumentoEmpleado::factory()->create([
            'nombre' => 'Identificación global',
            'activo' => true,
        ]);
        $inactiveType = TipoDocumentoEmpleado::factory()->inactive()->create([
            'nombre' => 'Documento histórico global',
        ]);

        foreach ([[$first, $firstAdministrator], [$second, $secondAdministrator]] as [$empresa, $administrator]) {
            $this->actingAs($administrator)
                ->get(route('empresas.empleados.index', $empresa))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('empleados/index')
                    ->has('tiposDocumento', 2)
                    ->where('tiposDocumento.0.id', $inactiveType->id)
                    ->where('tiposDocumento.0.activo', false)
                    ->where('tiposDocumento.1.id', $activeType->id)
                    ->where('tiposDocumento.1.activo', true),
                );
        }
    }

    public function test_company_administrator_cannot_modify_global_catalog_even_with_legacy_permissions(): void
    {
        $empresa = Empresa::query()->where('slug', 'pixel-perfect')->firstOrFail();
        $administrator = $this->companyAdministrator($empresa);
        $type = TipoDocumentoEmpleado::factory()->create();
        $archivedType = TipoDocumentoEmpleado::factory()->create();
        $archivedType->delete();

        setPermissionsTeamId($empresa->id);
        $administrator->givePermissionTo([
            'tipos_documento.view',
            'tipos_documento.create',
            'tipos_documento.update',
            'tipos_documento.delete',
        ]);

        $this->actingAs($administrator)
            ->get(route('platform.tipos-documento-empleados.index'))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->post(route('platform.tipos-documento-empleados.store'), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($administrator)
            ->put(route('platform.tipos-documento-empleados.update', $type), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($administrator)
            ->delete(route('platform.tipos-documento-empleados.destroy', $type))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->patch(route('platform.tipos-documento-empleados.restore', $archivedType))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->post(route('platform.reportes.tipos-documento-empleados.exportar'), ['formato' => 'xlsx'])
            ->assertForbidden();

        $this->actingAs($administrator)
            ->post(route('reportes.exportar', 'tipos-documento-empleados'), ['formato' => 'xlsx'])
            ->assertForbidden();
    }

    public function test_deactivation_preserves_historical_employee_document_references(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $administrator = $this->companyAdministrator($empresa);
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $type = TipoDocumentoEmpleado::factory()->create([
            'nombre' => 'Contrato histórico',
            'activo' => true,
        ]);
        $position = Puesto::factory()->for($empresa)->create();
        $employee = Empleado::factory()->for($empresa)->create(['puesto_id' => $position->id]);
        $document = EmpleadoDocumento::factory()->for($employee)->create([
            'empresa_id' => $empresa->id,
            'tipo_documento_empleado_id' => $type->id,
        ]);

        $this->actingAs($platformAdministrator)
            ->put(route('platform.tipos-documento-empleados.update', $type), [
                ...$this->validPayload(),
                'nombre' => $type->nombre,
                'activo' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($administrator)
            ->get(route('empresas.empleados.index', $empresa))
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

    public function test_platform_catalog_changes_are_logged_without_company_ownership(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();

        $this->actingAs($platformAdministrator)
            ->post(route('platform.tipos-documento-empleados.store'), [
                ...$this->validPayload(),
                'nombre' => 'Catálogo de plataforma',
            ])
            ->assertSessionHasNoErrors();

        $type = TipoDocumentoEmpleado::query()->where('nombre', 'Catálogo de plataforma')->firstOrFail();
        $activity = Activity::query()
            ->where('subject_type', TipoDocumentoEmpleado::class)
            ->where('subject_id', $type->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($activity->empresa_id);
        $this->assertSame($platformAdministrator->id, $activity->causer_id);
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
