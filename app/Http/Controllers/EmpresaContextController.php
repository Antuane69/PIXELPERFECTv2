<?php

namespace App\Http\Controllers;

use App\EstadoMembresiaEmpresa;
use App\Http\Requests\SelectEmpresaContextRequest;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaContextController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->es_superadministrador_plataforma) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/seleccionar-empresa', [
            'empresas' => $user->empresas()
                ->wherePivot('estado', EstadoMembresiaEmpresa::Activa->value)
                ->select([
                    'empresas.id',
                    'empresas.nombre_legal',
                    'empresas.nombre_comercial',
                    'empresas.estado',
                    'empresas.demo_ends_at',
                ])
                ->orderBy('empresas.nombre_legal')
                ->get()
                ->filter(fn (Empresa $empresa): bool => $empresa->permiteAcceso())
                ->map(fn (Empresa $empresa): array => $this->empresaData($empresa))
                ->values(),
        ]);
    }

    public function store(SelectEmpresaContextRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $empresa = Empresa::query()->findOrFail($request->integer('empresa_id'));

        if (! $user->es_superadministrador_plataforma) {
            $membresia = MembresiaEmpresa::query()
                ->whereBelongsTo($empresa)
                ->whereBelongsTo($user)
                ->first();

            abort_unless(
                $membresia instanceof MembresiaEmpresa
                    && $membresia->estaActiva()
                    && $empresa->permiteAcceso(),
                403,
            );
        }

        $request->session()->put(EmpresaContext::SESSION_KEY, $empresa->id);

        return to_route('empresas.inicio');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        abort_unless($user->es_superadministrador_plataforma, 403);

        $request->session()->forget(EmpresaContext::SESSION_KEY);

        return to_route('dashboard');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return array{id: int, nombre: string, nombre_legal: string, estado: string} */
    private function empresaData(Empresa $empresa): array
    {
        return [
            'id' => $empresa->id,
            'nombre' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
            'nombre_legal' => $empresa->nombre_legal,
            'estado' => $empresa->estado->value,
        ];
    }
}
