<?php

namespace Database\Factories\FaltasReglamento;

use App\Models\Empresa;
use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FaltaReglamentoCatalogo> */
class FaltaReglamentoCatalogoFactory extends Factory
{
    protected $model = FaltaReglamentoCatalogo::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'tipo_falta_reglamento_id' => fn (array $attributes): int => TipoFaltaReglamento::factory()->create([
                'empresa_id' => $attributes['empresa_id'],
            ])->id,
            'nombre' => fake()->unique()->sentence(4),
            'descripcion' => fake()->optional()->sentence(),
            'activo' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
