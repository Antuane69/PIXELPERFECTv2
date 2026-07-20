<?php

namespace App\Actions\Empleados;

use App\Models\Empleado;
use Illuminate\Support\Facades\DB;

class DeleteEmpleado
{
    public function handle(Empleado $empleado): void
    {
        DB::transaction(function () use ($empleado): void {
            Empleado::query()
                ->whereKey($empleado->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        });
    }
}
