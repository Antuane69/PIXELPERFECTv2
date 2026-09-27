<?php

namespace Database\Factories\FaltasReglamento;

use App\Enums\FaltasReglamento\EstadoFaltaReglamento;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FaltaReglamento> */
class FaltaReglamentoFactory extends Factory
{
    protected $model = FaltaReglamento::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'empleado_id' => fn (array $attributes): int => Empleado::factory()->create([
                'empresa_id' => $attributes['empresa_id'],
            ])->id,
            'falta_reglamento_catalogo_id' => fn (array $attributes): int => FaltaReglamentoCatalogo::factory()->create([
                'empresa_id' => $attributes['empresa_id'],
            ])->id,
            'solicitante_user_id' => User::factory(),
            'solicitante_nombre' => fake()->name(),
            'solicitante_correo' => fake()->safeEmail(),
            'fecha_ocurrencia' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'estado' => EstadoFaltaReglamento::Pendiente,
            'comentarios' => fake()->optional()->sentence(),
            'comentarios_rechazo' => null,
            'resuelto_por_user_id' => null,
            'resuelto_at' => null,
        ];
    }

    public function authorized(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoFaltaReglamento::Autorizada,
            'resuelto_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoFaltaReglamento::Rechazada,
            'comentarios_rechazo' => fake()->sentence(),
            'resuelto_at' => now(),
        ]);
    }
}
