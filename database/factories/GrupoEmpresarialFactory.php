<?php

namespace Database\Factories;

use App\Models\GrupoEmpresarial;
use App\TipoGrupoEmpresarial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GrupoEmpresarial>
 */
class GrupoEmpresarialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->company();

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre).'-'.fake()->unique()->numberBetween(1000, 999999),
            'tipo' => TipoGrupoEmpresarial::Individual,
            'activo' => true,
        ];
    }

    public function corporativo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo' => TipoGrupoEmpresarial::Corporativo,
        ]);
    }
}
