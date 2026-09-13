<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablecerEmpresaActiva
{
    public function __construct(private EmpresaContext $empresaContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $empresa = $this->empresaContext->empresa();

        if (! $empresa instanceof Empresa) {
            return $user->es_superadministrador_plataforma
                ? to_route('dashboard')
                : to_route('empresa-contexto.create');
        }

        return $next($request);
    }
}
