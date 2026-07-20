<?php

namespace Database\Factories;

use App\Models\Puesto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Puesto>
 */
class PuestoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salarioDia = fake()->randomFloat(2, 250, 5000);

        return [
            'nombre' => Str::of(fake()->unique()->jobTitle())->squish()->limit(255, '')->toString(),
            'salario_dia' => $salarioDia,
            'salario_quincena' => round($salarioDia * 15, 2),
            'activo' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }
}
