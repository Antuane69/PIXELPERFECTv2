<?php

namespace Tests\Feature\Management;

use App\Models\Empleado;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->administrator = User::factory()->create();
        $this->administrator->assignRole('Administrador');
    }

    public function test_administrator_can_create_update_and_soft_delete_a_position(): void
    {
        $filteredIndex = route('puestos.index', [
            'search' => 'Desarrollador',
            'activo' => true,
            'per_page' => 25,
            'page' => 2,
        ]);
        $sourceIndex = route('puestos.index', [
            'search' => 'Desarrollador',
            'activo' => true,
            'per_page' => 25,
            'page' => 2,
            'return_to' => 'https://example.com',
        ]);

        $this->actingAs($this->administrator)
            ->from($sourceIndex)
            ->post(route('puestos.store'), [
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
            ->put(route('puestos.update', $puesto), [
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
            ->delete(route('puestos.destroy', $puesto))
            ->assertSessionHasNoErrors()
            ->assertRedirect($filteredIndex);

        $this->assertSoftDeleted($puesto);
    }

    public function test_position_validation_rejects_duplicate_name_and_negative_salary(): void
    {
        Puesto::factory()->create(['nombre' => 'Contador']);

        $this->actingAs($this->administrator)
            ->post(route('puestos.store'), [
                'nombre' => 'Contador',
                'salario_dia' => -1,
                'activo' => 'invalid',
            ])
            ->assertSessionHasErrors(['nombre', 'salario_dia', 'activo']);
    }

    public function test_position_listing_filters_and_caps_page_size(): void
    {
        Puesto::factory()->create(['nombre' => 'Needle Position', 'activo' => true]);
        Puesto::factory()->inactive()->create(['nombre' => 'Needle Inactive']);
        Puesto::factory()->create(['nombre' => 'Other Position', 'activo' => true]);

        $this->actingAs($this->administrator)
            ->get(route('puestos.index', [
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
            Puesto::factory()->create([
                'nombre' => sprintf('Puesto Paginado %02d', $index),
                'activo' => true,
            ]);
        }

        $this->actingAs($this->administrator)
            ->get(route('puestos.index', [
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
        $puesto = Puesto::factory()->create();
        Empleado::factory()->create(['puesto_id' => $puesto->id]);

        $this->actingAs($this->administrator)
            ->from(route('puestos.index'))
            ->delete(route('puestos.destroy', $puesto))
            ->assertSessionHasErrors('puesto')
            ->assertRedirect(route('puestos.index'));

        $this->assertNotSoftDeleted($puesto);
    }

    public function test_administrator_can_list_and_restore_archived_positions(): void
    {
        Puesto::factory()->create(['nombre' => 'Puesto vigente']);
        $archivedPuesto = Puesto::factory()->create(['nombre' => 'Puesto archivado']);
        $archivedPuesto->delete();
        $archivedIndex = route('puestos.index', [
            'archivados' => true,
            'search' => 'Puesto',
            'page' => 2,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('puestos.index', ['archivados' => true]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('puestos/index')
                ->has('puestos.data', 1)
                ->where('puestos.data.0.id', $archivedPuesto->id)
                ->where('filters.archivados', true),
            );

        $this->actingAs($this->administrator)
            ->from($archivedIndex)
            ->patch(route('puestos.restore', $archivedPuesto))
            ->assertSessionHasNoErrors()
            ->assertRedirect($archivedIndex);

        $this->assertNotSoftDeleted($archivedPuesto);
    }
}
