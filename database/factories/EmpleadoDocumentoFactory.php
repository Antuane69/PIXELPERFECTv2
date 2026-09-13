<?php

namespace Database\Factories;

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\TipoDocumentoEmpleado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmpleadoDocumento>
 */
class EmpleadoDocumentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empleado_id' => Empleado::factory(),
            'empresa_id' => fn (array $attributes): mixed => Empleado::query()
                ->whereKey($attributes['empleado_id'])
                ->value('empresa_id'),
            'tipo_documento_empleado_id' => fn (array $attributes): int => TipoDocumentoEmpleado::factory()->create([
                'empresa_id' => $attributes['empresa_id'],
            ])->id,
            'nombre_original' => 'documento.pdf',
            'ruta' => fn (array $attributes): string => "empresas/{$attributes['empresa_id']}/empleados/{$attributes['empleado_id']}/documentos/".Str::uuid().'.pdf',
            'disco' => 'local',
            'mime_type' => 'application/pdf',
            'tamano' => fake()->numberBetween(10_000, 2_000_000),
            'vence_el' => fake()->boolean(70)
                ? fake()->dateTimeBetween('+1 month', '+5 years')->format('Y-m-d')
                : null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'vence_el' => now()->subDay()->toDateString(),
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes): array => [
            'nombre_original' => 'documento.webp',
            'ruta' => fn (array $resolvedAttributes): string => "empresas/{$resolvedAttributes['empresa_id']}/empleados/{$resolvedAttributes['empleado_id']}/documentos/".Str::uuid().'.webp',
            'mime_type' => 'image/webp',
        ]);
    }
}
