<?php

namespace Database\Factories;

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\TipoDocumentoEmpleado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmpleadoDocumento>
 */
class EmpleadoDocumentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empleado_id' => Empleado::factory(),
            'tipo_documento_empleado_id' => TipoDocumentoEmpleado::factory(),
            'nombre_original' => 'documento.pdf',
            'ruta' => 'empleados/documentos/'.Str::uuid().'.pdf',
            'disco' => 'local',
            'mime_type' => 'application/pdf',
            'tamano' => fake()->numberBetween(10_000, 2_000_000),
            'vence_el' => fake()->boolean(70)
                ? fake()->dateTimeBetween('+1 month', '+5 years')->format('Y-m-d')
                : null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'vence_el' => now()->subDay()->toDateString(),
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes): array => [
            'nombre_original' => 'documento.webp',
            'ruta' => 'empleados/documentos/'.Str::uuid().'.webp',
            'mime_type' => 'image/webp',
        ]);
    }
}
