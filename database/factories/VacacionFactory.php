<?php

namespace Database\Factories;

use App\EstadoVacacion;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacacion>
 */
class VacacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fechaInicio = CarbonImmutable::instance(fake()->dateTimeBetween('+1 week', '+3 months'))
            ->startOfDay();

        return [
            'empresa_id' => fn (): mixed => Empresa::query()
                ->where('slug', 'pixel-perfect')
                ->value('id'),
            'empleado_id' => fn (array $attributes): int => Empleado::factory()->create([
                'empresa_id' => $attributes['empresa_id'],
            ])->id,
            'solicitante_user_id' => User::factory(),
            'resuelto_por_user_id' => null,
            'solicitante_nombre' => fake()->name(),
            'solicitante_correo' => fake()->safeEmail(),
            'dias_solicitados' => 3,
            'saldo_dias_al_solicitar' => 12,
            'fecha_ultima_vacacion_al_solicitar' => null,
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaInicio->addDays(4)->toDateString(),
            'estado' => EstadoVacacion::Pendiente,
            'comentarios' => null,
            'comentarios_rechazo' => null,
            'resuelto_at' => null,
        ];
    }
}
