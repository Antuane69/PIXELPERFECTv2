<?php

namespace Database\Factories;

use App\Enums\Incapacidades\EstadoIncapacidad;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Incapacidad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incapacidad>
 */
class IncapacidadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $empresa = Empresa::factory();
        $fechaInicio = fake()->dateTimeBetween('-2 years', 'now');
        $fechaFin = (clone $fechaInicio)->modify('+'.fake()->numberBetween(1, 14).' days');

        return [
            'empresa_id' => $empresa,
            'empleado_id' => static fn (array $attributes): int => (int) Empleado::factory()
                ->create(['empresa_id' => $attributes['empresa_id']])
                ->getKey(),
            'solicitante_user_id' => User::factory(),
            'resuelto_por_user_id' => null,
            'solicitante_nombre' => fake()->name(),
            'solicitante_correo' => fake()->safeEmail(),
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
            'estado' => EstadoIncapacidad::Pendiente,
            'motivo' => fake()->sentence(),
            'comentarios_rechazo' => null,
            'resuelto_at' => null,
        ];
    }
}
