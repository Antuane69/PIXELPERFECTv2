<?php

namespace App\Http\Requests\Empleados;

use App\Models\Empleado;

class UpdateEmpleadoRequest extends EmpleadoRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $empleado = $this->boundEmpleado();

        return $empleado instanceof Empleado
            && ($this->user()?->can('update', $empleado) ?? false);
    }

    protected function isCreating(): bool
    {
        return false;
    }
}
