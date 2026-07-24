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
        $this->actingAs($this->administrator)
            ->post(route('puestos.store'), [
                'nombre' => 'Desarrollador',
                'salario_dia' => '750.50',
                'salario_quincena' => '11257.50',
                'activo' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('puestos.index'));

        $puesto = Puesto::query()->where('nombre', 'Desarrollador')->firstOrFail();

        $this->actingAs($this->administrator)
            ->put(route('puestos.update', $puesto), [
                'nombre' => 'Desarrollador Senior',
                'salario_dia' => '900.00',
                'salario_quincena' => null,
                'activo' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('puestos.index'));

        $puesto->refresh();

        $this->assertSame('Desarrollador Senior', $puesto->nombre);
        $this->assertSame('900.00', $puesto->salario_dia);
        $this->assertNull($puesto->salario_quincena);
        $this->assertFalse($puesto->activo);

        $this->actingAs($this->administrator)
            ->delete(route('puestos.destroy', $puesto))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('puestos.index'));

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
            ->patch(route('puestos.restore', $archivedPuesto))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('puestos.index', ['archivados' => true]));

        $this->assertNotSoftDeleted($archivedPuesto);
    }
}
