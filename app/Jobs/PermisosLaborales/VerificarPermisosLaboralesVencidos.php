<?php

namespace App\Jobs\PermisosLaborales;

use App\Enums\PermisosLaborales\EstadoPermisoLaboral;
use App\Models\Empresa;
use App\Models\PermisosLaborales\PermisoLaboral;
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

class VerificarPermisosLaboralesVencidos implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public int $uniqueFor = 1800;

    public function uniqueId(): string
    {
        return 'permisos-laborales-vencidos';
    }

    public function handle(): void
    {
        $actualizados = 0;

        Empresa::query()
            ->select(['id', 'zona_horaria'])
            ->orderBy('id')
            ->chunkById(100, function ($empresas) use (&$actualizados): void {
                foreach ($empresas as $empresa) {
                    $fechaActual = CarbonImmutable::now($empresa->zona_horaria)->toDateString();
                    $actualizados += PermisoLaboral::query()
                        ->where('empresa_id', $empresa->id)
                        ->where('estado', EstadoPermisoLaboral::Autorizado->value)
                        ->where('fecha_fin', '<', $fechaActual)
                        ->update([
                            'estado' => EstadoPermisoLaboral::Vencido->value,
                            'updated_at' => now(),
                        ]);
                }
            });

        if ($actualizados > 0) {
            Log::info('Permisos laborales vencidos actualizados.', ['cantidad' => $actualizados]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('No se pudieron actualizar los permisos laborales vencidos.', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
