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

            $puesto = Puesto::query()
                ->withTrashed()
                ->whereKey($empleadoArchivado->puesto_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($puesto->trashed()) {
                throw ValidationException::withMessages([
                    'empleado' => "Primero restaura el puesto {$puesto->nombre} para recuperar este empleado.",
                ]);
            }

            $empleadoArchivado->restore();
        });
    }
}
