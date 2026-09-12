<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Empresas\ActualizarModulosEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEmpresaModulosRequest;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmpresaModuloController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdateEmpresaModulosRequest $request,
        Empresa $empresa,
        ActualizarModulosEmpresa $actualizarModulosEmpresa,
    ): RedirectResponse {
        /** @var list<int> $moduleIds */
        $moduleIds = $request->validated('modulos');
        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $actualizarModulosEmpresa->handle($empresa, $moduleIds, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Módulos de {$empresa->nombre_legal} actualizados.",
        ]);

        return $this->redirectToResourceIndex(
            $request,
            'platform.empresas.index',
            ['search', 'estado', 'grupo_empresarial_id', 'per_page', 'page'],
        );
    }
}
