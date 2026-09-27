<?php

namespace Database\Factories;

use App\Models\DiaFestivo;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiaFestivo>
 */
class DiaFestivoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nombre' => fake()->words(3, true),
            'fecha' => fake()->date(),
        ];
    }
}
