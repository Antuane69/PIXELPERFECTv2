<?php

namespace App\Actions\Empleados;

use App\Models\Empleado;
use App\Models\Puesto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreEmpleado
{
    public function handle(Empleado $empleado): void
    {
        DB::transaction(function () use ($empleado): void {
            $empleadoArchivado = Empleado::query()
                ->onlyTrashed()
                ->whereKey($empleado->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($empleadoArchivado->puesto_id !== null) {
                $puesto = Puesto::query()
                    ->withTrashed()
                    ->whereKey($empleadoArchivado->puesto_id)
                    ->lockForUpdate()
                    ->first();

                if ($puesto === null || $puesto->trashed()) {
                    $puestoNombre = $puesto?->nombre ?? 'asignado';

                    throw ValidationException::withMessages([
                        'empleado' => "Primero restaura el puesto {$puestoNombre} para recuperar este empleado.",
                    ]);
                }
            }

            $empleadoArchivado->restore();
        });
    }
}
