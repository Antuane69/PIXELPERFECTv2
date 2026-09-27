<?php

namespace App\Services\Vacaciones;

use App\Models\Empresa;
use App\Models\User;
use App\Services\Empresas\ObtenerAdministradoresEmpresa;
use Illuminate\Database\Eloquent\Collection;

class ObtenerAdministradoresVacaciones
{
    public function __construct(private readonly ObtenerAdministradoresEmpresa $obtenerAdministradores) {}

    /** @return Collection<int, User> */
    public function para(Empresa $empresa): Collection
    {
        return $this->obtenerAdministradores->para($empresa);
    }
}
