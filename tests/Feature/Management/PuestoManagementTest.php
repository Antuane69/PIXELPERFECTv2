<?php

namespace Tests\Feature\Management;

use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Puesto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PuestoManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->administrator = User::factory()->create();
        $this->empresa = Empresa::query()->where('slug', 'pixel-perfect')->firstOrFail();
        MembresiaEmpresa::factory()
            ->for($this->empresa)
            ->for($this->administrator)
            ->create();
        $this->administrator->assignRole('Administrador');
        $this->withEmpresaContext($this->empresa);
    }

    public function test_administrator_can_create_update_and_soft_delete_a_position(): void
    {
        $filteredIndex = route('empresas.puestos.index', [
            'search' => 'Desarrollador',
            'activo' => true,
            'per_page' => 25,
            'page' => 2,
        ]);
        $sourceIndex = route('empresas.puestos.index', [
            'search' => 'Desarrollador',
            'activo' => true,
            'per_page' => 25,
            'page' => 2,
            'return_to' => 'https://example.com',
        ]);

        $this->actingAs($this->administrator)
            ->from($sourceIndex)
            ->post(route('empresas.puestos.store'), [
                'nombre' => 'Desarrollador',
                'salario_dia' => '750.50',
                'salario_quincena' => '11257.50',
                'activo' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $puesto = Puesto::query()->where('nombre', 'Desarrollador')->firstOrFail();

        $this->actingAs($this->administrator)
            ->from($sourceIndex)
            ->put(route('empresas.puestos.update', [
                'puesto' => $puesto,
            ]), [
                'nombre' => 'Desarrollador Senior',
                'salario_dia' => '900.00',
                'salario_quincena' => null,
                'activo' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $puesto->refresh();

        $this->assertSame('Desarrollador Senior', $puesto->nombre);
        $this->assertSame('900.00', $puesto->salario_dia);
        $this->assertNull($puesto->salario_quincena);
        $this->assertFalse($puesto->activo);

        $this->actingAs($this->administrator)
            ->from($sourceIndex)
            ->delete(route('empresas.puestos.destroy', [
                'puesto' => $puesto,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $this->assertSoftDeleted($puesto);
    }

    public function test_position_validation_rejects_duplicate_name_negative_salary_and_extra_decimals(): void
    {
        Puesto::factory()->for($this->empresa)->create(['nombre' => 'Contador']);

        $this->actingAs($this->administrator)
            ->post(route('empresas.puestos.store'), [
                'nombre' => 'Contador',
                'salario_dia' => -1,
                'salario_quincena' => '100.999',
                'activo' => 'invalid',
            ])
            ->assertSessionHasErrors(['nombre', 'salario_dia', 'salario_quincena', 'activo']);
    }

    public function test_position_validation_rejects_more_than_two_decimals_for_both_salary_fields(): void
    {
        $this->actingAs($this->administrator)
            ->post(route('empresas.puestos.store'), [
                'nombre' => 'Puesto con salario inválido',
                'salario_dia' => '10.123',
                'salario_quincena' => '100.999',
                'activo' => true,
            ])
            ->assertSessionHasErrors(['salario_dia', 'salario_quincena']);

        $this->assertDatabaseMissing('puestos', [
            'nombre' => 'Puesto con salario inválido',
        ]);
    }

    public function test_position_listing_filters_and_caps_page_size(): void
    {
        Puesto::factory()->for($this->empresa)->create(['nombre' => 'Needle Position', 'activo' => true]);
        Puesto::factory()->for($this->empresa)->inactive()->create(['nombre' => 'Needle Inactive']);
        Puesto::factory()->for($this->empresa)->create(['nombre' => 'Other Position', 'activo' => true]);

        $this->actingAs($this->administrator)
            ->get(route('empresas.puestos.index', [
                'search' => 'Needle',
                'activo' => true,
                'per_page' => 500,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('puestos/index')
                ->has('puestos.data', 1)
                ->where('puestos.data.0.nombre', 'Needle Position')
                ->where('puestos.per_page', 100),
            );
    }

    public function test_position_pagination_preserves_active_filters_on_the_second_page(): void
    {
        foreach (range(1, 7) as $index) {
            Puesto::factory()->for($this->empresa)->create([
                'nombre' => sprintf('Puesto Paginado %02d', $index),
                'activo' => true,
            ]);
        }

        $this->actingAs($this->administrator)
            ->get(route('empresas.puestos.index', [
                'search' => 'Puesto Paginado',
                'activo' => true,
                'per_page' => 5,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('puestos/index')
                ->where('puestos.current_page', 2)
                ->where('puestos.per_page', 5)
                ->has('puestos.data', 2)
                ->where('puestos.links.0.url', fn (mixed $url): bool => $this->urlContainsQuery($url, [
                    'search' => 'Puesto Paginado',
                    'activo' => 1,
                    'per_page' => 5,
                    'page' => 1,
                ])),
            );
    }

    public function test_position_assigned_to_an_employee_cannot_be_deleted(): void
    {
        $puesto = Puesto::factory()->for($this->empresa)->create();
        Empleado::factory()->create(['puesto_id' => $puesto->id]);

        $this->actingAs($this->administrator)
            ->from(route('empresas.puestos.index'))
            ->delete(route('empresas.puestos.destroy', [
                'puesto' => $puesto,
            ]))
            ->assertSessionHasErrors('puesto')
            ->assertRedirect(route('empresas.puestos.index'));

        $this->assertNotSoftDeleted($puesto);
    }

    public function test_administrator_can_list_and_restore_archived_positions(): void
    {
        Puesto::factory()->for($this->empresa)->create(['nombre' => 'Puesto vigente']);
        $archivedPuesto = Puesto::factory()->for($this->empresa)->create(['nombre' => 'Puesto archivado']);
        $archivedPuesto->delete();
        $archivedIndex = route('empresas.puestos.index', [
            'archivados' => true,
            'search' => 'Puesto',
            'page' => 2,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('empresas.puestos.index', [
                'archivados' => true,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('puestos/index')
                ->has('puestos.data', 1)
                ->where('puestos.data.0.id', $archivedPuesto->id)
                ->where('filters.archivados', true),
            );

        $this->actingAs($this->administrator)
            ->from($archivedIndex)
            ->patch(route('empresas.puestos.restore', [
                'puesto' => $archivedPuesto,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect($archivedIndex);

        $this->assertNotSoftDeleted($archivedPuesto);
    }
}
