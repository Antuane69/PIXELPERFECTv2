<?php

namespace Database\Factories\FaltasReglamento;

use App\Models\Empresa;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\FaltasReglamento\FaltaReglamentoEvidencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FaltaReglamentoEvidencia> */
class FaltaReglamentoEvidenciaFactory extends Factory
{
    protected $model = FaltaReglamentoEvidencia::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'falta_reglamento_id' => fn (array $attributes): int => FaltaReglamento::factory()->create([
                'empresa_id' => $attributes['empresa_id'],
            ])->id,
            'nombre' => 'evidencia.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'archivo' => 'fake-pdf-contents',
        ];
    }
}
