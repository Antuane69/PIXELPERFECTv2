<?php

namespace Database\Factories\FaltasReglamento;

use App\Models\Empresa;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TipoFaltaReglamento> */
class TipoFaltaReglamentoFactory extends Factory
{
    protected $model = TipoFaltaReglamento::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nombre' => fake()->unique()->words(2, true),
            'descripcion' => fake()->optional()->sentence(),
            'activo' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
