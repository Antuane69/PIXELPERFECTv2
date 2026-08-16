<?php

namespace Tests\Feature\Management;

use App\Models\EmpleadoDocumento;
use App\Models\TipoDocumentoEmpleado;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TipoDocumentoEmpleadoManagementTest extends TestCase
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

    public function test_administrator_can_create_update_and_soft_delete_a_document_type(): void
    {
        $filteredIndex = route('tipos-documento-empleados.index', [
            'search' => 'Identificación',
            'activo' => true,
            'es_renovable' => true,
            'per_page' => 25,
            'page' => 2,
        ]);

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->post(route('tipos-documento-empleados.store'), [
                'nombre' => 'Identificación oficial',
                'es_renovable' => true,
                'frecuencia_cantidad' => 4,
                'frecuencia_tipo' => 'anios',
                'documentos_aceptados' => ['pdf', 'jpg'],
                'activo' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $tipoDocumento = TipoDocumentoEmpleado::query()
            ->where('nombre', 'Identificación oficial')
            ->firstOrFail();

        $this->assertSame(['PDF', 'JPG'], $tipoDocumento->documentos_aceptados);

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->put(route('tipos-documento-empleados.update', $tipoDocumento), [
                'nombre' => 'Identificación vigente',
                'es_renovable' => false,
                'documentos_aceptados' => ['PDF'],
                'activo' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $tipoDocumento->refresh();

        $this->assertSame('Identificación vigente', $tipoDocumento->nombre);
        $this->assertFalse($tipoDocumento->es_renovable);
        $this->assertNull($tipoDocumento->frecuencia_cantidad);
        $this->assertNull($tipoDocumento->frecuencia_tipo);

        $this->actingAs($this->administrator)
            ->from($filteredIndex)
            ->delete(route('tipos-documento-empleados.destroy', $tipoDocumento))
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $this->assertSoftDeleted($tipoDocumento);
    }

    public function test_document_type_validation_requires_frequency_and_valid_extensions(): void
    {
        $this->actingAs($this->administrator)
            ->post(route('tipos-documento-empleados.store'), [
                'nombre' => 'Documento renovable',
                'es_renovable' => true,
                'documentos_aceptados' => ['exe'],
                'activo' => true,
            ])
            ->assertSessionHasErrors([
                'frecuencia_cantidad',
                'frecuencia_tipo',
                'documentos_aceptados.0',
            ]);
    }

    public function test_document_type_listing_filters_and_caps_page_size(): void
    {
        TipoDocumentoEmpleado::factory()->create([
            'nombre' => 'Needle Renewable',
            'es_renovable' => true,
            'activo' => true,
        ]);
        TipoDocumentoEmpleado::factory()->inactive()->create(['nombre' => 'Needle Inactive']);
        TipoDocumentoEmpleado::factory()->create(['nombre' => 'Other Type']);

        $this->actingAs($this->administrator)
            ->get(route('tipos-documento-empleados.index', [
                'search' => 'Needle',
                'activo' => true,
                'es_renovable' => true,
                'per_page' => 500,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tipos-documento-empleados/index')
                ->has('tiposDocumento.data', 1)
                ->where('tiposDocumento.data.0.nombre', 'Needle Renewable')
                ->where('tiposDocumento.per_page', 100),
            );
    }

    public function test_document_type_pagination_preserves_active_filters_on_the_second_page(): void
    {
        foreach (range(1, 7) as $index) {
            TipoDocumentoEmpleado::factory()->renewable()->create([
                'nombre' => sprintf('TIPO PAGINADO %02d', $index),
                'activo' => true,
            ]);
        }

        $this->actingAs($this->administrator)
            ->get(route('tipos-documento-empleados.index', [
                'search' => 'TIPO PAGINADO',
                'activo' => true,
                'es_renovable' => true,
                'per_page' => 5,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tipos-documento-empleados/index')
                ->where('tiposDocumento.current_page', 2)
                ->where('tiposDocumento.per_page', 5)
                ->has('tiposDocumento.data', 2)
                ->where('tiposDocumento.links.0.url', fn (mixed $url): bool => $this->urlContainsQuery($url, [
                    'search' => 'TIPO PAGINADO',
                    'activo' => 1,
                    'es_renovable' => 1,
                    'per_page' => 5,
                    'page' => 1,
                ])),
            );
    }

    public function test_document_type_in_use_cannot_be_deleted(): void
    {
        $tipoDocumento = TipoDocumentoEmpleado::factory()->create();
        EmpleadoDocumento::factory()->create([
            'tipo_documento_empleado_id' => $tipoDocumento->id,
        ]);

        $this->actingAs($this->administrator)
            ->from(route('tipos-documento-empleados.index'))
            ->delete(route('tipos-documento-empleados.destroy', $tipoDocumento))
            ->assertSessionHasErrors('tipoDocumentoEmpleado')
            ->assertRedirect(route('tipos-documento-empleados.index'));

        $this->assertNotSoftDeleted($tipoDocumento);
    }

    public function test_administrator_can_list_and_restore_archived_document_types(): void
    {
        TipoDocumentoEmpleado::factory()->create(['nombre' => 'Tipo vigente']);
        $archivedType = TipoDocumentoEmpleado::factory()->create(['nombre' => 'Tipo archivado']);
        $archivedType->delete();
        $archivedIndex = route('tipos-documento-empleados.index', [
            'archivados' => true,
            'search' => 'Tipo',
            'page' => 2,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('tipos-documento-empleados.index', ['archivados' => true]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tipos-documento-empleados/index')
                ->has('tiposDocumento.data', 1)
                ->where('tiposDocumento.data.0.id', $archivedType->id)
                ->where('filters.archivados', true),
            );

        $this->actingAs($this->administrator)
            ->from($archivedIndex)
            ->patch(route('tipos-documento-empleados.restore', $archivedType))
            ->assertSessionHasNoErrors()
            ->assertRedirect($archivedIndex);

        $this->assertNotSoftDeleted($archivedType);
    }
}
