<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Empresas\CrearEmpresa;
use App\EstadoEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmpresaRequest;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\Modulo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'estado', 'grupo_empresarial_id', 'per_page', 'page'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Empresa::class);

        $search = $request->string('search')->squish()->toString();
        $estado = EstadoEmpresa::tryFrom(Str::upper($request->string('estado')->toString()));
        $grupoEmpresarialId = $request->integer('grupo_empresarial_id');
        $grupoEmpresarialId = $grupoEmpresarialId > 0 ? $grupoEmpresarialId : null;
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $empresas = Empresa::query()
            ->select([
                'id',
                'grupo_empresarial_id',
                'nombre_legal',
                'nombre_comercial',
                'slug',
                'estado',
                'demo_ends_at',
                'created_at',
            ])
            ->with('grupoEmpresarial:id,nombre')
            ->with(['modulos' => fn ($query) => $query
                ->select(['modulos.id', 'clave', 'nombre'])
                ->where('activo', true)
                ->orderBy('orden')])
            ->withCount('membresias')
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $searchQuery) => $searchQuery
                    ->where('nombre_legal', 'like', "%{$search}%")
                    ->orWhere('nombre_comercial', 'like', "%{$search}%")
                    ->orWhere('rfc', 'like', "%{$search}%"),
            ))
            ->when($estado instanceof EstadoEmpresa, fn (Builder $query) => $query->where('estado', $estado))
            ->when(
                $grupoEmpresarialId !== null,
                fn (Builder $query) => $query->where('grupo_empresarial_id', $grupoEmpresarialId),
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (Empresa $empresa): array => [
                'id' => $empresa->id,
                'nombre_legal' => $empresa->nombre_legal,
                'nombre_comercial' => $empresa->nombre_comercial,
                'slug' => $empresa->slug,
                'estado' => $empresa->estado->value,
                'demo_ends_at' => $empresa->demo_ends_at?->toISOString(),
                'membresias_count' => $empresa->membresias_count,
                'grupo_empresarial' => [
                    'id' => $empresa->grupoEmpresarial->id,
                    'nombre' => $empresa->grupoEmpresarial->nombre,
                ],
                'modulos' => $empresa->modulos->map(static fn (Modulo $module): array => [
                    'id' => $module->id,
                    'clave' => $module->clave,
                    'nombre' => $module->nombre,
                    'habilitado' => (bool) $module->getRelation('pivot')->getAttribute('habilitado'),
                ])->values()->all(),
                'created_at' => $empresa->created_at?->toISOString(),
            ]);

        return Inertia::render('admin/empresas/index', [
            'empresasPaginadas' => $empresas,
            'grupos' => GrupoEmpresarial::query()
                ->select(['id', 'nombre'])
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'modulosDisponibles' => Modulo::query()
                ->select(['id', 'clave', 'nombre', 'descripcion'])
                ->where('activo', true)
                ->orderBy('orden')
                ->get(),
            'filters' => [
                'search' => $search,
                'estado' => $estado?->value,
                'grupoEmpresarialId' => $grupoEmpresarialId,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function store(StoreEmpresaRequest $request, CrearEmpresa $crearEmpresa): RedirectResponse
    {
        $empresa = $crearEmpresa->handle(
            nombreLegal: $request->nombreLegal(),
            nombreComercial: $request->nombreComercial(),
            grupoEmpresarialId: $request->grupoEmpresarialId(),
            rfc: $request->rfc(),
            correoContacto: $request->correoContacto(),
            telefonoContacto: $request->telefonoContacto(),
            zonaHoraria: $request->zonaHoraria(),
            moneda: $request->moneda(),
            estado: $request->estado(),
            demoEndsAt: $request->demoEndsAt(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Empresa {$empresa->nombre_legal} creada correctamente.",
        ]);

        return $this->redirectToResourceIndex($request, 'platform.empresas.index', self::INDEX_QUERY_PARAMETERS);
    }
}
