<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablecerEmpresaInicial
{
    public function __construct(private EstablecerEmpresaActiva $establecerEmpresaActiva) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = Empresa::query()->where('slug', 'pixel-perfect')->firstOrFail();
        $request->route()?->setParameter('empresa', $empresa);

        return $this->establecerEmpresaActiva->handle($request, $next);
    }
}
