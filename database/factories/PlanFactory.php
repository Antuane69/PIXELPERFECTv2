<?php

namespace Database\Factories;

use App\IconoPlan;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'precio_mensual' => fake()->randomFloat(2, 299, 9999),
            'color' => fake()->hexColor(),
            'icono' => fake()->randomElement(IconoPlan::values()),
            'modulos_incluidos' => fake()->sentence(8),
            'limite_usuarios' => null,
            'periodo_gracia_dias' => 15,
            'activo' => true,
        ];
    }
}
