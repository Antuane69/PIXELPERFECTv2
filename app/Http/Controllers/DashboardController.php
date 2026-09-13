<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Modulo;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the management dashboard.
     */
    public function __invoke(Request $request, EmpresaContext $empresaContext): Response|RedirectResponse
    {
        if ($empresaContext->existe()) {
            return to_route('empresas.inicio');
        }

        if (! $request->user()?->es_superadministrador_plataforma) {
            return to_route('empresa-contexto.create');
        }

        return Inertia::render('dashboard', [
            'stats' => [
                'empresas' => Empresa::query()->count(),
                'users' => User::query()->count(),
                'planes' => Plan::query()->count(),
                'modules' => Modulo::query()->count(),
                'permissions' => Permission::query()->where('guard_name', 'web')->count(),
            ],
        ]);
    }
}
