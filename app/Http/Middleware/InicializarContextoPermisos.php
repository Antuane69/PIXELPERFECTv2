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
            $this->restaurarContexto($request, $user);
        }

        return $next($request);
    }

    private function restaurarContexto(Request $request, User $user): void
    {
        $empresaId = $request->session()->get(EmpresaContext::SESSION_KEY);

        if (! is_int($empresaId) && ! (is_string($empresaId) && ctype_digit($empresaId))) {
            return;
        }

        $empresa = Empresa::query()->find((int) $empresaId);

        if (! $empresa instanceof Empresa) {
            $request->session()->forget(EmpresaContext::SESSION_KEY);

            return;
        }

        $membresia = MembresiaEmpresa::query()
            ->whereBelongsTo($empresa)
            ->whereBelongsTo($user)
            ->first();

        if (
            ! $user->es_superadministrador_plataforma
            && (! $membresia instanceof MembresiaEmpresa || ! $membresia->estaActiva() || ! $empresa->permiteAcceso())
        ) {
            $request->session()->forget(EmpresaContext::SESSION_KEY);

            return;
        }

        $empresa->loadMissing('grupoEmpresarial:id,nombre,slug,tipo,activo');
        $this->empresaContext->establecer($empresa, $membresia);
        setPermissionsTeamId($empresa->id);

        Context::add([
            'empresa_id' => $empresa->id,
            'grupo_empresarial_id' => $empresa->grupo_empresarial_id,
            'membresia_empresa_id' => $membresia?->id,
        ]);
    }
}
