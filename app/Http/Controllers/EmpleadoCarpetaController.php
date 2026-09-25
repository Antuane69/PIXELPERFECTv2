<?php

namespace App\Http\Controllers;

use App\Actions\Empleados\SaveEmpleadoCarpeta;
use App\EstadoMembresiaEmpresa;
use App\Http\Requests\EmpleadoCarpetas\StoreEmpleadoCarpetaRequest;
use App\Http\Requests\EmpleadoCarpetas\UpdateEmpleadoCarpetaRequest;
use App\Models\EmpleadoCarpeta;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmpleadoCarpetaController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'archivados', 'per_page', 'page'];

    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        Gate::authorize('viewAny', EmpleadoCarpeta::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $archivados = $request->boolean('archivados');

        $carpetas = EmpleadoCarpeta::query()
            ->select(['id', 'empresa_id', 'creado_por_id', 'nombre', 'deleted_at'])
            ->whereBelongsTo($empresa)
            ->visiblesPara($usuario)
            ->with(['creadoPor:id,name', 'usuariosConAcceso:id,name'])
            ->when($archivados, fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== '', fn (Builder $query) => $query->where('nombre', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (EmpleadoCarpeta $carpeta): array => [
                'id' => $carpeta->id,
                'nombre' => $carpeta->nombre,
                'creado_por_id' => $carpeta->creado_por_id,
                'creado_por' => [
                    'id' => $carpeta->creadoPor->id,
                    'name' => $carpeta->creadoPor->name,
                ],
                'usuarios_con_acceso' => $carpeta->usuariosConAcceso
                    ->map(static fn (User $user): array => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ])
                    ->values()
                    ->all(),
                'deleted_at' => $carpeta->deleted_at?->toISOString(),
            ]);

        $usuarios = $usuario->can('empleados_carpetas.create') || $usuario->can('empleados_carpetas.update')
            ? $empresa->usuarios()
                ->wherePivot('estado', EstadoMembresiaEmpresa::Activa->value)
                ->where('users.id', '!=', $usuario->id)
                ->select(['users.id', 'users.name', 'users.email'])
                ->orderBy('users.name')
                ->get()
                ->map(static fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('empleados/carpetas/index', [
            'carpetas' => $carpetas,
            'usuarios' => $usuarios,
            'filters' => [
                'search' => $search,
                'archivados' => $archivados,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function store(StoreEmpleadoCarpetaRequest $request, SaveEmpleadoCarpeta $save): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        Gate::authorize('create', EmpleadoCarpeta::class);
        $save->create($empresa, $usuario, $request->carpetaData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Carpeta creada correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.empleados.carpetas.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    public function update(
        UpdateEmpleadoCarpetaRequest $request,
        EmpleadoCarpeta $empleadoCarpeta,
        SaveEmpleadoCarpeta $save,
    ): RedirectResponse {
        $empresa = $this->empresaContext->empresaRequerida();
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        $this->assertActiveCompany($empleadoCarpeta);
        Gate::authorize('update', $empleadoCarpeta);
        $this->assertCreator($request, $empleadoCarpeta);
        $save->update($empresa, $empleadoCarpeta, $usuario, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Carpeta actualizada correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.empleados.carpetas.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    public function destroy(Request $request, EmpleadoCarpeta $empleadoCarpeta): RedirectResponse
    {
        $this->assertActiveCompany($empleadoCarpeta);
        Gate::authorize('delete', $empleadoCarpeta);
        $this->assertCreator($request, $empleadoCarpeta);
        $empleadoCarpeta->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Carpeta eliminada correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.empleados.carpetas.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    public function restore(Request $request, EmpleadoCarpeta $empleadoCarpeta): RedirectResponse
    {
        $this->assertActiveCompany($empleadoCarpeta);
        Gate::authorize('restore', $empleadoCarpeta);
        $this->assertCreator($request, $empleadoCarpeta);
        $empleadoCarpeta->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Carpeta restaurada correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.empleados.carpetas.index',
            self::INDEX_QUERY_PARAMETERS,
            ['archivados' => true],
        );
    }

    private function assertActiveCompany(EmpleadoCarpeta $carpeta): void
    {
        abort_unless($carpeta->empresa_id === $this->empresaContext->empresaRequerida()->id, 404);
    }

    private function assertCreator(Request $request, EmpleadoCarpeta $carpeta): void
    {
        abort_unless((int) $request->user()?->getAuthIdentifier() === $carpeta->creado_por_id, 403);
    }
}
