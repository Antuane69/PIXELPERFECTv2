<?php

namespace Tests\Feature\Multiempresa;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\MembresiaEmpresa;
use App\Models\Puesto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PuestoIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_companies_in_same_group_keep_separate_position_catalogs_and_salaries(): void
    {
        [$user, $first, $second] = $this->administratorWithTwoCompaniesInSameGroup();
        $firstPosition = Puesto::factory()->for($first)->create([
            'nombre' => 'Diseñador',
            'salario_dia' => 500,
        ]);
        $secondPosition = Puesto::factory()->for($second)->create([
            'nombre' => 'Diseñador',
            'salario_dia' => 850,
        ]);

        $this->actingAs($user)
            ->get(route('empresas.puestos.index', $first))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('puestos/index')
                ->has('puestos.data', 1)
                ->where('puestos.data.0.id', $firstPosition->id)
                ->where('puestos.data.0.salario_dia', '500.00'),
            );

        $this->actingAs($user)
            ->get(route('empresas.puestos.index', $second))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('puestos.data', 1)
                ->where('puestos.data.0.id', $secondPosition->id)
                ->where('puestos.data.0.salario_dia', '850.00'),
            );
    }

    public function test_company_route_ignores_manipulated_company_id_when_creating_position(): void
    {
        [$user, $first, $second] = $this->administratorWithTwoCompaniesInSameGroup();

        $this->actingAs($user)
            ->post(route('empresas.puestos.store', $first), [
                'empresa_id' => $second->id,
                'nombre' => 'Supervisor',
                'salario_dia' => 700,
                'salario_quincena' => 10500,
                'activo' => true,
            ])
            ->assertSessionHasNoErrors();

        $position = Puesto::query()->where('nombre', 'Supervisor')->sole();

        $this->assertSame($first->id, $position->empresa_id);
    }

    public function test_cross_company_position_bindings_hide_update_delete_and_restore_targets(): void
    {
        [$user, $first, $second] = $this->administratorWithTwoCompaniesInSameGroup();
        $foreignPosition = Puesto::factory()->for($second)->create(['nombre' => 'Ajeno']);

        $this->actingAs($user)
            ->put(route('empresas.puestos.update', [
                'empresa' => $first,
                'puesto' => $foreignPosition,
            ]), ['nombre' => 'Manipulado'])
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('empresas.puestos.destroy', [
                'empresa' => $first,
                'puesto' => $foreignPosition,
            ]))
            ->assertNotFound();

        $foreignPosition->delete();

        $this->actingAs($user)
            ->patch(route('empresas.puestos.restore', [
                'empresa' => $first,
                'puesto' => $foreignPosition,
            ]))
            ->assertNotFound();

        $this->assertSame('Ajeno', $foreignPosition->fresh()?->nombre);
        $this->assertSoftDeleted($foreignPosition);
    }

    public function test_database_rejects_duplicate_position_name_inside_same_company(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        Puesto::factory()->for($empresa)->create(['nombre' => 'Contador']);

        $this->expectException(QueryException::class);

        Puesto::factory()->for($empresa)->create(['nombre' => 'Contador']);
    }

    public function test_database_requires_an_existing_company_for_each_position(): void
    {
        $this->expectException(QueryException::class);

        Puesto::query()->create([
            'empresa_id' => 999999,
            'nombre' => 'Empresa inexistente',
            'activo' => true,
        ]);
    }

    public function test_company_dashboard_and_export_only_include_its_positions(): void
    {
        [$user, $first, $second] = $this->administratorWithTwoCompaniesInSameGroup();
        Puesto::factory()->for($first)->create(['nombre' => 'Local Activo', 'activo' => true]);
        Puesto::factory()->for($first)->inactive()->create(['nombre' => 'Local Inactivo']);
        Puesto::factory()->for($second)->create(['nombre' => 'Ajeno Activo', 'activo' => true]);

        $this->actingAs($user)
            ->get(route('empresas.inicio', $first))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.puestosActivos', 1),
            );

        $response = $this->actingAs($user)
            ->post(route('empresas.reportes.puestos.exportar', $first), [
                'formato' => 'xlsx',
            ])
            ->assertOk();

        $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $exportedNames = [
            (string) $sheet->getCell('B6')->getValue(),
            (string) $sheet->getCell('B7')->getValue(),
            (string) $sheet->getCell('B8')->getValue(),
        ];

        $this->assertContains('Local Activo', $exportedNames);
        $this->assertContains('Local Inactivo', $exportedNames);
        $this->assertNotContains('Ajeno Activo', $exportedNames);

        $spreadsheet->disconnectWorksheets();
    }

    public function test_member_without_position_permission_cannot_list_or_export_positions(): void
    {
        $empresa = Empresa::factory()->activa()->create();
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

        $this->actingAs($user)
            ->get(route('empresas.puestos.index', $empresa))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('empresas.reportes.puestos.exportar', $empresa), ['formato' => 'xlsx'])
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
}
