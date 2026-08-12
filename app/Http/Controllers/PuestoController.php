<?php

namespace App\Http\Controllers;

use App\Http\Requests\Puestos\StorePuestoRequest;
use App\Http\Requests\Puestos\UpdatePuestoRequest;
use App\Models\Puesto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PuestoController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'activo', 'archivados', 'per_page', 'page'];

    /**
     * Display a paginated position listing.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Puesto::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = $this->perPage($request);
        $archivados = $request->boolean('archivados');

        $puestos = Puesto::query()
            ->select(['id', 'nombre', 'salario_dia', 'salario_quincena', 'activo', 'deleted_at'])
            ->withCount('empleados')
            ->when($archivados, fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== '', fn (Builder $query) => $query->where('nombre', 'like', "%{$search}%"))
            ->when(
                $request->has('activo'),
                fn (Builder $query) => $query->where('activo', $request->boolean('activo')),
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (Puesto $puesto): array => [
                'id' => $puesto->id,
                'nombre' => $puesto->nombre,
                'salario_dia' => $puesto->salario_dia !== null ? (string) $puesto->salario_dia : null,
                'salario_quincena' => $puesto->salario_quincena !== null ? (string) $puesto->salario_quincena : null,
                'activo' => $puesto->activo,
                'empleados_count' => $puesto->empleados_count,
                'deleted_at' => $puesto->deleted_at?->toISOString(),
            ]);

        return Inertia::render('puestos/index', [
            'puestos' => $puestos,
            'filters' => [
                'search' => $search,
                'activo' => $request->has('activo') ? $request->boolean('activo') : null,
                'archivados' => $archivados,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created position.
     */
    public function store(StorePuestoRequest $request): RedirectResponse
    {
        Gate::authorize('create', Puesto::class);

        Puesto::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Puesto creado correctamente.']);

        return $this->redirectToResourceIndex($request, 'puestos.index', self::INDEX_QUERY_PARAMETERS);
    }

    /**
     * Update the specified position.
     */
    public function update(UpdatePuestoRequest $request, Puesto $puesto): RedirectResponse
    {
        Gate::authorize('update', $puesto);

        $puesto->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Puesto actualizado correctamente.']);

        return $this->redirectToResourceIndex($request, 'puestos.index', self::INDEX_QUERY_PARAMETERS);
    }

    /**
     * Soft delete the specified position when it is unused.
     */
    public function destroy(Request $request, Puesto $puesto): RedirectResponse
    {
        Gate::authorize('delete', $puesto);

        if ($puesto->empleados()->exists()) {
            throw ValidationException::withMessages([
                'puesto' => 'No se puede eliminar un puesto asignado a empleados.',
            ]);
        }

        $puesto->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Puesto eliminado correctamente.']);

        return $this->redirectToResourceIndex($request, 'puestos.index', self::INDEX_QUERY_PARAMETERS);
    }

    /**
     * Restore the specified archived position.
     */
    public function restore(Request $request, Puesto $puesto): RedirectResponse
    {
        Gate::authorize('restore', $puesto);

        $puesto->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Puesto restaurado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'puestos.index',
            self::INDEX_QUERY_PARAMETERS,
            ['archivados' => true],
        );
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
