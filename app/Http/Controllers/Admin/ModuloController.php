<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Modulos\StoreModuloRequest;
use App\Http\Requests\Admin\Modulos\UpdateModuloRequest;
use App\Models\Modulo;
use App\Services\Platform\ManageModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ModuloController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'activo', 'per_page', 'page'];

    public function __construct(private readonly ManageModules $manageModules) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Modulo::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $modules = Modulo::query()
            ->select(['id', 'clave', 'nombre', 'descripcion', 'activo', 'orden', 'created_at'])
            ->withCount(['empresas', 'permisos'])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $searchQuery) => $searchQuery
                    ->where('clave', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%"),
            ))
            ->when(
                $request->has('activo'),
                fn (Builder $query) => $query->where('activo', $request->boolean('activo')),
            )
            ->orderBy('orden')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (Modulo $module): array => [
                'id' => $module->id,
                'clave' => $module->clave,
                'nombre' => $module->nombre,
                'descripcion' => $module->descripcion,
                'activo' => $module->activo,
                'orden' => $module->orden,
                'empresas_count' => $module->empresas_count,
                'permisos_count' => $module->permisos_count,
                'created_at' => $module->created_at?->toISOString(),
            ]);

        return Inertia::render('admin/modulos/index', [
            'modules' => $modules,
            'filters' => [
                'search' => $search,
                'activo' => $request->has('activo') ? $request->boolean('activo') : null,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function store(StoreModuloRequest $request): RedirectResponse
    {
        $this->manageModules->save($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Módulo creado correctamente.']);

        return $this->redirectToResourceIndex($request, 'platform.modulos.index', self::INDEX_QUERY_PARAMETERS);
    }

    public function update(UpdateModuloRequest $request, Modulo $modulo): RedirectResponse
    {
        $this->manageModules->save($request->validated(), $modulo);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Módulo actualizado correctamente.']);

        return $this->redirectToResourceIndex($request, 'platform.modulos.index', self::INDEX_QUERY_PARAMETERS);
    }

    public function destroy(Request $request, Modulo $modulo): RedirectResponse
    {
        Gate::authorize('delete', $modulo);
        $this->manageModules->delete($modulo);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Módulo eliminado correctamente.']);

        return $this->redirectToResourceIndex($request, 'platform.modulos.index', self::INDEX_QUERY_PARAMETERS);
    }
}
