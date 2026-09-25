<?php

namespace Database\Factories;

use App\Models\EmpleadoCarpeta;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmpleadoCarpeta>
 */
class EmpleadoCarpetaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory()->activa(),
            'creado_por_id' => User::factory(),
            'nombre' => fake()->words(2, true),
        ];
    }
}
