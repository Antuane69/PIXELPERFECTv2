<?php

namespace App\Actions\Empresas;

use App\Models\Empresa;
use App\Models\Modulo;

class HabilitarModulosPredeterminadosEmpresa
{
    public function handle(Empresa $empresa): void
    {
        $moduleIds = Modulo::query()->where('activo', true)->pluck('id');

        $empresa->modulos()->syncWithPivotValues(
            $moduleIds,
            ['habilitado' => true],
            false,
        );
    }
}
