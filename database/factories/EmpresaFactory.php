<?php

namespace Database\Factories;

use App\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\Modulo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Empresa $empresa): void {
            $empresa->modulos()->syncWithPivotValues(
                Modulo::query()->where('activo', true)->pluck('id'),
                ['habilitado' => true],
                false,
            );
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->company();

        return [
            'grupo_empresarial_id' => GrupoEmpresarial::factory(),
            'nombre_legal' => $nombre,
            'nombre_comercial' => null,
            'slug' => Str::slug($nombre).'-'.fake()->unique()->numberBetween(1000, 999999),
            'rfc' => null,
            'correo_contacto' => fake()->companyEmail(),
            'telefono_contacto' => fake()->numerify('##########'),
            'zona_horaria' => 'America/Mexico_City',
            'moneda' => 'MXN',
            'estado' => EstadoEmpresa::Prospecto,
            'demo_ends_at' => null,
            'activada_at' => null,
            'vence_at' => null,
            'desactivada_at' => null,
            'retencion_hasta' => null,
        ];
    }

    public function activa(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => EstadoEmpresa::Activa,
            'activada_at' => now(),
        ]);
    }

    public function demo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => EstadoEmpresa::Demo,
            'demo_ends_at' => now()->addDays(14),
        ]);
    }

    public function vencida(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => EstadoEmpresa::Vencida,
            'vence_at' => now()->subDay(),
        ]);
    }

    public function desactivada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => EstadoEmpresa::Desactivada,
            'desactivada_at' => now(),
        ]);
    }
}
