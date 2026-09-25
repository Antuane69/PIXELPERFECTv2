<?php

namespace App\Services\Empleados;

use App\Models\Empresa;
use App\Models\Modulo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ModulosRelacionablesDocumentoCatalogo
{
    /**
     * @return Builder<Modulo>
     */
    public function query(User $user, Empresa $empresa): Builder
    {
        $query = Modulo::query()->where('activo', true);

        if (! $user->es_superadministrador_plataforma) {
            $enabledModuleIds = $empresa->modulos()
                ->where('activo', true)
                ->wherePivot('habilitado', true)
                ->pluck('modulos.id');

            $query->whereIn('id', $enabledModuleIds);
        }

        if (! $user->es_superadministrador_plataforma && ! $user->hasRole('Administrador', 'web')) {
            $permittedModuleIds = $user->getAllPermissions()
                ->pluck('modulo_id')
                ->filter()
                ->unique()
                ->values();

            $query->whereIn('id', $permittedModuleIds);
        }

        return $query;
    }
}
