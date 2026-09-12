<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Puesto;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaDashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(EmpresaContext $empresaContext): Response
    {
        $empresa = $empresaContext->empresaRequerida();

        return Inertia::render('empresas/inicio', [
            'empresa' => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
                'nombre_legal' => $empresa->nombre_legal,
                'slug' => $empresa->slug,
                'estado' => $empresa->estado->value,
            ],
            'modulos' => $empresa->modulos()
                ->select(['modulos.id', 'clave', 'nombre', 'descripcion'])
                ->where('activo', true)
                ->wherePivot('habilitado', true)
                ->orderBy('orden')
                ->get(),
            'stats' => [
                'puestosActivos' => $empresa->moduloHabilitado('puestos')
                    && Gate::allows('viewAny', Puesto::class)
                    ? Puesto::query()
                        ->whereBelongsTo($empresa)
                        ->where('activo', true)
                        ->count()
                    : null,
                'empleados' => $empresa->moduloHabilitado('empleados')
                    && Gate::allows('viewAny', Empleado::class)
                    ? Empleado::query()->whereBelongsTo($empresa)->count()
                    : null,
            ],
        ]);
    }
}
