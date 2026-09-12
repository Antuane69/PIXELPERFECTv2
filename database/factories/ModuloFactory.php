<?php

namespace Database\Factories;

use App\Models\Modulo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Modulo>
 */
class ModuloFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fn (): string => 'modulo-'.Str::lower(Str::random(16)),
            'nombre' => fake()->unique()->words(2, true),
            'descripcion' => fake()->sentence(),
            'activo' => true,
            'orden' => fake()->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['activo' => false]);
    }
}
