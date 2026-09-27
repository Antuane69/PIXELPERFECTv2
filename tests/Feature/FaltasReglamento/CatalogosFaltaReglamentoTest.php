<?php

namespace Tests\Feature\FaltasReglamento;

use App\Models\Empresa;
use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use App\Models\MembresiaEmpresa;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CatalogosFaltaReglamentoTest extends TestCase
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

    public function test_company_catalogs_can_be_created_and_catalog_falta_requires_a_type_from_the_same_company(): void
    {
        $this->actingAs($this->administrator)
            ->post(route('empresas.tipos-falta-reglamento.store'), [
                'nombre' => 'Categoría de prueba',
                'descripcion' => 'Descripción opcional.',
                'activo' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.tipos-falta-reglamento.index'));

        $tipo = TipoFaltaReglamento::query()->sole();

        $this->actingAs($this->administrator)
            ->post(route('empresas.catalogo-faltas-reglamento.store'), [
                'tipo_falta_reglamento_id' => $tipo->id,
                'nombre' => 'Registro de prueba',
                'descripcion' => null,
                'activo' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.catalogo-faltas-reglamento.index'));

        $this->assertDatabaseHas('catalogo_faltas_reglamento', [
            'empresa_id' => $this->empresa->id,
            'tipo_falta_reglamento_id' => $tipo->id,
            'nombre' => 'Registro de prueba',
        ]);

        $otraEmpresa = Empresa::factory()->activa()->create();
        $tipoAjeno = TipoFaltaReglamento::factory()->for($otraEmpresa)->create();

        $this->actingAs($this->administrator)
            ->post(route('empresas.catalogo-faltas-reglamento.store'), [
                'tipo_falta_reglamento_id' => $tipoAjeno->id,
                'nombre' => 'Relación ajena',
                'activo' => '1',
            ])
            ->assertSessionHasErrors('tipo_falta_reglamento_id');

        $this->assertSame(1, FaltaReglamentoCatalogo::query()->where('empresa_id', $this->empresa->id)->count());
    }

    public function test_catalog_pages_require_the_matching_company_permission(): void
    {
        $usuario = User::factory()->create();
        MembresiaEmpresa::factory()->for($this->empresa)->for($usuario)->create();

        $this->actingAs($usuario)
            ->get(route('empresas.tipos-falta-reglamento.index'))
            ->assertForbidden();

        $this->actingAs($usuario)
            ->get(route('empresas.catalogo-faltas-reglamento.index'))
            ->assertForbidden();
    }

    public function test_catalogs_are_scoped_to_the_selected_company(): void
    {
        $otraEmpresa = Empresa::factory()->activa()->create();
        $tipoAjeno = TipoFaltaReglamento::factory()->for($otraEmpresa)->create();
        $catalogoAjeno = FaltaReglamentoCatalogo::factory()->for($otraEmpresa)->create([
            'tipo_falta_reglamento_id' => $tipoAjeno->id,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('empresas.tipos-falta-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tiposFalta.total', 0),
            );

        $this->actingAs($this->administrator)
            ->get(route('empresas.catalogo-faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('faltasCatalogo.total', 0),
            );

        $this->assertNotSame($this->empresa->id, $catalogoAjeno->empresa_id);
    }

    public function test_catalog_records_can_be_updated_archived_and_restored(): void
    {
        $tipo = TipoFaltaReglamento::factory()->for($this->empresa)->create();
        $catalogo = FaltaReglamentoCatalogo::factory()->for($this->empresa)->create([
            'tipo_falta_reglamento_id' => $tipo->id,
        ]);

        $this->actingAs($this->administrator)
            ->put(route('empresas.tipos-falta-reglamento.update', $tipo), [
                'nombre' => 'Categoría actualizada',
                'descripcion' => 'Descripción actualizada.',
                'activo' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.tipos-falta-reglamento.index'));

        $this->actingAs($this->administrator)
            ->put(route('empresas.catalogo-faltas-reglamento.update', $catalogo), [
                'nombre' => 'Falta actualizada',
                'descripcion' => 'Detalle actualizado.',
                'activo' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresas.catalogo-faltas-reglamento.index'));

        $this->actingAs($this->administrator)
            ->delete(route('empresas.tipos-falta-reglamento.destroy', $tipo))
            ->assertRedirect(route('empresas.tipos-falta-reglamento.index'));
        $this->actingAs($this->administrator)
            ->patch(route('empresas.tipos-falta-reglamento.restore', $tipo))
            ->assertRedirect(route('empresas.tipos-falta-reglamento.index'));

        $this->actingAs($this->administrator)
            ->delete(route('empresas.catalogo-faltas-reglamento.destroy', $catalogo))
            ->assertRedirect(route('empresas.catalogo-faltas-reglamento.index'));
        $this->actingAs($this->administrator)
            ->patch(route('empresas.catalogo-faltas-reglamento.restore', $catalogo))
            ->assertRedirect(route('empresas.catalogo-faltas-reglamento.index'));

        $this->assertSame('Categoría actualizada', $tipo->fresh()->nombre);
        $this->assertSame('Falta actualizada', $catalogo->fresh()->nombre);
        $this->assertNull($tipo->fresh()->deleted_at);
        $this->assertNull($catalogo->fresh()->deleted_at);
    }

    public function test_catalog_search_filters_and_pagination_preserve_server_side_state(): void
    {
        $tipoSeleccionado = TipoFaltaReglamento::factory()->for($this->empresa)->create([
            'nombre' => 'Tipo filtrable',
        ]);

        foreach (range(1, 16) as $index) {
            TipoFaltaReglamento::factory()->for($this->empresa)->create([
                'nombre' => "Tipo de prueba {$index}",
            ]);
            FaltaReglamentoCatalogo::factory()->for($this->empresa)->create([
                'tipo_falta_reglamento_id' => $tipoSeleccionado->id,
                'nombre' => "Falta de prueba {$index}",
            ]);
        }

        $this->actingAs($this->administrator)
            ->post(route('empresas.tipos-falta-reglamento.filtros'), [
                'search' => 'Tipo de prueba',
                'activo' => true,
                'archivados' => false,
            ])
            ->assertRedirect(route('empresas.tipos-falta-reglamento.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.tipos-falta-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.search', 'Tipo de prueba')
                ->where('filters.activo', true)
                ->where('tiposFalta.total', 16)
                ->where('tiposFalta.current_page', 1),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.tipos-falta-reglamento.pagina'), ['page' => 2])
            ->assertRedirect(route('empresas.tipos-falta-reglamento.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.tipos-falta-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.search', 'Tipo de prueba')
                ->where('tiposFalta.current_page', 2)
                ->has('tiposFalta.data', 1),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.catalogo-faltas-reglamento.filtros'), [
                'search' => 'Falta de prueba',
                'activo' => true,
                'archivados' => false,
                'tipo_falta_reglamento_id' => $tipoSeleccionado->id,
            ])
            ->assertRedirect(route('empresas.catalogo-faltas-reglamento.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.catalogo-faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.search', 'Falta de prueba')
                ->where('filters.tipoFaltaReglamentoId', $tipoSeleccionado->id)
                ->where('faltasCatalogo.total', 16)
                ->where('faltasCatalogo.current_page', 1),
            );

        $this->actingAs($this->administrator)
            ->post(route('empresas.catalogo-faltas-reglamento.pagina'), ['page' => 2])
            ->assertRedirect(route('empresas.catalogo-faltas-reglamento.index'));

        $this->actingAs($this->administrator)
            ->get(route('empresas.catalogo-faltas-reglamento.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.tipoFaltaReglamentoId', $tipoSeleccionado->id)
                ->where('faltasCatalogo.current_page', 2)
                ->has('faltasCatalogo.data', 1),
            );
    }
}
