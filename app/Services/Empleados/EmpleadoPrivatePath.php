<?php

namespace App\Services\Empleados;

use App\Models\Empleado;
use App\Models\Empresa;

class EmpleadoPrivatePath
{
    public function belongsToEmployee(string $path, Empresa $empresa, Empleado $empleado): bool
    {
        if (str_starts_with($path, "empresas/{$empresa->id}/empleados/{$empleado->id}/")) {
            return true;
        }

        return $empresa->slug === 'pixel-perfect' && str_starts_with($path, 'empleados/');
    }
}
