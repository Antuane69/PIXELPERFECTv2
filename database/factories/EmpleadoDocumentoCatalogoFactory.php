<?php

namespace Database\Factories;

use App\Models\EmpleadoCarpeta;
use App\Models\EmpleadoDocumentoCatalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmpleadoDocumentoCatalogo>
 */
class EmpleadoDocumentoCatalogoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empleado_carpeta_id' => EmpleadoCarpeta::factory(),
            'empresa_id' => static fn (array $attributes): int => EmpleadoCarpeta::query()
                ->findOrFail((int) $attributes['empleado_carpeta_id'])
                ->empresa_id,
            'nombre' => fake()->words(3, true),
            'contenido_html' => '<p>Documento de {{nombre_empleado}}</p>',
        ];
    }
}
