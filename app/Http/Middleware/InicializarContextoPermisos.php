<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InicializarContextoPermisos
{
    public function __construct(private EmpresaContext $empresaContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        setPermissionsTeamId(null);
        $this->empresaContext->limpiar();

        $user = $request->user();

        if ($user instanceof User) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        return $next($request);
    }
}
