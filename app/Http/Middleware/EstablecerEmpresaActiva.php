<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class EstablecerEmpresaActiva
{
    public function __construct(private EmpresaContext $empresaContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = $this->resolverEmpresa($request);
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $membresia = MembresiaEmpresa::query()
            ->whereBelongsTo($empresa)
            ->whereBelongsTo($user)
            ->first();

        if (! $user->es_superadministrador_plataforma) {
            abort_unless($membresia instanceof MembresiaEmpresa, 404);
            abort_unless($membresia->estaActiva(), 403, 'Tu membresía está suspendida para esta empresa.');
            abort_unless($empresa->permiteAcceso(), 403, 'La empresa no tiene acceso activo al sistema.');
        }

        $empresa->loadMissing('grupoEmpresarial:id,nombre,slug,tipo,activo');
        $this->empresaContext->establecer($empresa, $membresia);
        setPermissionsTeamId($empresa->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        Context::add([
            'empresa_id' => $empresa->id,
            'grupo_empresarial_id' => $empresa->grupo_empresarial_id,
            'membresia_empresa_id' => $membresia?->id,
        ]);

        return $next($request);
    }

    private function resolverEmpresa(Request $request): Empresa
    {
        $routeEmpresa = $request->route('empresa');

        if ($routeEmpresa instanceof Empresa) {
            return $routeEmpresa;
        }

        abort_unless(is_string($routeEmpresa), 404);

        $empresa = Empresa::query()->where('slug', $routeEmpresa)->firstOrFail();
        $request->route()?->setParameter('empresa', $empresa);

        return $empresa;
    }
}
