<?php

namespace App\Http\Middleware;

use App\Services\Empresas\EmpresaContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private EmpresaContext $empresaContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        abort_unless(
            $this->empresaContext->empresaRequerida()->moduloHabilitado($moduleKey),
            403,
            'Este módulo no está habilitado para la empresa activa.',
        );

        return $next($request);
    }
}
