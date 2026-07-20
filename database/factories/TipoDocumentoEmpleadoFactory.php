<?php

namespace Database\Factories;

use App\Models\TipoDocumentoEmpleado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TipoDocumentoEmpleado>
 */
class TipoDocumentoEmpleadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $esRenovable = fake()->boolean(60);

        return [
            'nombre' => Str::upper(rtrim(fake()->unique()->sentence(3), '.')),
            'es_renovable' => $esRenovable,
            'frecuencia_cantidad' => $esRenovable ? fake()->numberBetween(1, 24) : null,
            'frecuencia_tipo' => $esRenovable
                ? fake()->randomElement(['dias', 'semanas', 'meses', 'anios'])
                : null,
            'documentos_aceptados' => fake()->randomElements(
                ['PDF', 'JPG', 'JPEG', 'PNG', 'WEBP', 'DOC', 'DOCX', 'XLS', 'XLSX'],
                fake()->numberBetween(1, 3),
            ),
            'activo' => true,
        ];
    }

    public function renewable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'es_renovable' => true,
            'frecuencia_cantidad' => 1,
            'frecuencia_tipo' => 'anios',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }
}
