<?php

namespace App\Jobs;

use App\EstadoVacacion;
use App\Models\Empresa;
use App\Models\Vacacion;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerificarVacacionesVencidas implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public int $uniqueFor = 1800;

    public function uniqueId(): string
    {
        return 'vacaciones-vencidas';
    }

    public function handle(): void
    {
        $actualizadas = 0;

        Empresa::query()
            ->select(['id', 'zona_horaria'])
            ->orderBy('id')
            ->chunkById(100, function ($empresas) use (&$actualizadas): void {
                foreach ($empresas as $empresa) {
                    $fechaActual = CarbonImmutable::now($empresa->zona_horaria)->toDateString();
                    $actualizadas += Vacacion::query()
                        ->where('empresa_id', $empresa->id)
                        ->where('estado', EstadoVacacion::Autorizada->value)
                        ->where('fecha_fin', '<', $fechaActual)
                        ->update([
                            'estado' => EstadoVacacion::Vencida->value,
                            'updated_at' => now(),
                        ]);
                }
            });

        if ($actualizadas > 0) {
            Log::info('Vacaciones vencidas actualizadas.', ['cantidad' => $actualizadas]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('No se pudieron actualizar las vacaciones vencidas.', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
