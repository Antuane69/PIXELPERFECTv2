<?php

namespace App\Http\Requests\Empleados;

use App\Models\Empleado;

class StoreEmpleadoRequest extends EmpleadoRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Empleado::class) ?? false;
    }

    protected function isCreating(): bool
    {
        return true;
    }
}
