<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Modulo;
use App\Models\Puesto;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaDashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, EmpresaContext $empresaContext): Response
    {
        $empresa = $empresaContext->empresaRequerida();
        $isPlatformAdministrator = (bool) $request->user()?->es_superadministrador_plataforma;

        return Inertia::render('empresas/inicio', [
            'empresa' => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
                'nombre_legal' => $empresa->nombre_legal,
                'estado' => $empresa->estado->value,
            ],
            'modulos' => $isPlatformAdministrator
                ? Modulo::query()
                    ->select(['id', 'clave', 'nombre', 'descripcion'])
                    ->where('activo', true)
                    ->orderBy('orden')
                    ->get()
                : $empresa->modulos()
                    ->select(['modulos.id', 'clave', 'nombre', 'descripcion'])
                    ->where('activo', true)
                    ->wherePivot('habilitado', true)
                    ->orderBy('orden')
                    ->get(),
            'stats' => [
                'puestosActivos' => ($isPlatformAdministrator || $empresa->moduloHabilitado('puestos'))
                    && Gate::allows('viewAny', Puesto::class)
                    ? Puesto::query()
                        ->whereBelongsTo($empresa)
                        ->where('activo', true)
                        ->count()
                    : null,
                'empleados' => ($isPlatformAdministrator || $empresa->moduloHabilitado('empleados'))
                    && Gate::allows('viewAny', Empleado::class)
                    ? Empleado::query()->whereBelongsTo($empresa)->count()
                    : null,
            ],
        ]);
    }
}
