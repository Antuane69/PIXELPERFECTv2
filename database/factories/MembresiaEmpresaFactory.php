<?php

namespace Database\Factories;

use App\EstadoMembresiaEmpresa;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembresiaEmpresa>
 */
class MembresiaEmpresaFactory extends Factory
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
            'user_id' => User::factory(),
            'estado' => EstadoMembresiaEmpresa::Activa,
            'fecha_incorporacion' => now(),
            'suspendida_at' => null,
            'invitado_por_user_id' => null,
        ];
    }

    public function suspendida(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => EstadoMembresiaEmpresa::Suspendida,
            'suspendida_at' => now(),
        ]);
    }
}
