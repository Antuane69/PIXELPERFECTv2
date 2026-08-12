<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoDocumentoEmpleados\StoreTipoDocumentoEmpleadoRequest;
use App\Http\Requests\TipoDocumentoEmpleados\UpdateTipoDocumentoEmpleadoRequest;
use App\Models\TipoDocumentoEmpleado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TipoDocumentoEmpleadoController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = [
        'search',
        'activo',
        'es_renovable',
        'archivados',
        'per_page',
        'page',
    ];

    /**
     * Display a paginated employee document type listing.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TipoDocumentoEmpleado::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = $this->perPage($request);
        $archivados = $request->boolean('archivados');

        $tiposDocumento = TipoDocumentoEmpleado::query()
            ->select([
                'id',
                'nombre',
                'es_renovable',
                'frecuencia_cantidad',
                'frecuencia_tipo',
                'documentos_aceptados',
                'activo',
                'deleted_at',
            ])
            ->when($archivados, fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== '', fn (Builder $query) => $query->where('nombre', 'like', "%{$search}%"))
            ->when(
                $request->has('activo'),
                fn (Builder $query) => $query->where('activo', $request->boolean('activo')),
            )
            ->when(
                $request->has('es_renovable'),
                fn (Builder $query) => $query->where('es_renovable', $request->boolean('es_renovable')),
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (TipoDocumentoEmpleado $tipoDocumento): array => [
                'id' => $tipoDocumento->id,
                'nombre' => $tipoDocumento->nombre,
                'es_renovable' => $tipoDocumento->es_renovable,
                'frecuencia_cantidad' => $tipoDocumento->frecuencia_cantidad,
                'frecuencia_tipo' => $tipoDocumento->frecuencia_tipo,
                'documentos_aceptados' => $tipoDocumento->documentos_aceptados,
                'activo' => $tipoDocumento->activo,
                'deleted_at' => $tipoDocumento->deleted_at?->toISOString(),
            ]);

        return Inertia::render('tipos-documento-empleados/index', [
            'tiposDocumento' => $tiposDocumento,
            'filters' => [
                'search' => $search,
                'activo' => $request->has('activo') ? $request->boolean('activo') : null,
                'esRenovable' => $request->has('es_renovable') ? $request->boolean('es_renovable') : null,
                'archivados' => $archivados,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created employee document type.
     */
    public function store(StoreTipoDocumentoEmpleadoRequest $request): RedirectResponse
    {
        Gate::authorize('create', TipoDocumentoEmpleado::class);

        TipoDocumentoEmpleado::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo de documento creado correctamente.',
        ]);

        return $this->redirectToResourceIndex(
            $request,
            'tipos-documento-empleados.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    /**
     * Update the specified employee document type.
     */
    public function update(
        UpdateTipoDocumentoEmpleadoRequest $request,
        TipoDocumentoEmpleado $tipoDocumentoEmpleado,
    ): RedirectResponse {
        Gate::authorize('update', $tipoDocumentoEmpleado);

        $tipoDocumentoEmpleado->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo de documento actualizado correctamente.',
        ]);

        return $this->redirectToResourceIndex(
            $request,
            'tipos-documento-empleados.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    /**
     * Soft delete the specified employee document type when it is unused.
     */
    public function destroy(Request $request, TipoDocumentoEmpleado $tipoDocumentoEmpleado): RedirectResponse
    {
        Gate::authorize('delete', $tipoDocumentoEmpleado);

        if ($tipoDocumentoEmpleado->documentos()->exists()) {
            throw ValidationException::withMessages([
                'tipoDocumentoEmpleado' => 'No se puede eliminar un tipo de documento en uso.',
            ]);
        }

        $tipoDocumentoEmpleado->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo de documento eliminado correctamente.',
        ]);

        return $this->redirectToResourceIndex(
            $request,
            'tipos-documento-empleados.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    /**
     * Restore the specified archived employee document type.
     */
    public function restore(Request $request, TipoDocumentoEmpleado $tipoDocumentoEmpleado): RedirectResponse
    {
        Gate::authorize('restore', $tipoDocumentoEmpleado);

        $tipoDocumentoEmpleado->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo de documento restaurado correctamente.',
        ]);

        return $this->redirectToResourceIndex(
            $request,
            'tipos-documento-empleados.index',
            self::INDEX_QUERY_PARAMETERS,
            ['archivados' => true],
        );
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
