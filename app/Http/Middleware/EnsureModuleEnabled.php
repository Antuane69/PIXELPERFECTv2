<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
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
        $empresa = $this->empresaContext->empresaRequerida();

        foreach ($request->route()?->parameters() ?? [] as $name => $parameter) {
            if ($name === 'user' && $parameter instanceof User) {
                abort_unless(
                    $parameter->membresiasEmpresa()->where('empresa_id', $empresa->id)->exists(),
                    404,
                );

                continue;
            }

            if (! $parameter instanceof Model || $parameter->getAttribute('empresa_id') === null) {
                continue;
            }

            abort_unless((int) $parameter->getAttribute('empresa_id') === $empresa->id, 404);
        }

        if ($request->user()?->es_superadministrador_plataforma) {
            return $next($request);
        }

        abort_unless(
            $empresa->moduloHabilitado($moduleKey),
            403,
            'Este módulo no está habilitado para la empresa activa.',
        );

        return $next($request);
    }
}
